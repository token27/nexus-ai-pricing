# Flow Diagrams & Communication Architecture

Visual reference for understanding how `nexus-ai-pricing` processes requests, resolves prices, calculates costs, and integrates into production systems. All diagrams reflect the actual runtime behaviour of the library.

---

## 1. System Architecture Overview

High-level map of every component and how they relate to each other.

```mermaid
graph TB
    subgraph Entry["Entry Points"]
        SFA["PricingEngine::for('model')<br/>Static Facade"]
        WTB["PricingEngine::withTable()<br/>Custom Table Factory"]
        WRG["PricingEngine::withRegistry()<br/>Registry Factory"]
        WTK["PricingEngine::withTokenizer()<br/>Tokenizer Factory"]
        DIC["new PricingEngine(...)<br/>DI Constructor"]
    end

    subgraph Engine["Core Engine"]
        PE["PricingEngine<br/>(implements PricingEngineInterface)"]
        PB["PricingBuilder<br/>Fluent Binder (for / make)"]
    end

    subgraph Tables["Price Resolution Layer"]
        APT["ArrayPriceTable<br/>In-Memory + Glob"]
        JPT["JsonFilePriceTable<br/>Lazy-loaded JSON"]
        CPT["ChainedPriceTable<br/>Priority Decorator"]
        NPT["NullPriceTable<br/>Zero-cost Sentinel"]
        PRG["PricingRegistry<br/>Lazy Factory Container"]
        DPC["DefaultPriceCatalog<br/>50+ Built-in Models"]
    end

    subgraph Tokenizer["Text Estimation Layer"]
        TBA["TokenizerBridgeAdapter<br/>(wraps nexus-ai-tokenizer)"]
        TEI["TextEstimatorInterface<br/>Custom Estimator"]
    end

    subgraph ImageEst["Image Estimation Layer"]
        OAI["OpenAIImageEstimator<br/>512px Tile Formula"]
        ANT["AnthropicImageEstimator<br/>w×h / 750"]
        GEM["GeminiImageEstimator<br/>768px Tile Formula"]
        CUS["Custom Estimator<br/>ImageTokenEstimatorInterface"]
    end

    subgraph Results["Result Layer"]
        MP["ModelPrice DTO<br/>Immutable Price Descriptor"]
        PR["PricingResult<br/>Immutable Cost Result"]
        MM["MultimodalPricingResult<br/>Extends PricingResult + Images"]
    end

    SFA --> PB
    WTB --> PE
    WRG --> PE
    WTK --> PE
    DIC --> PE
    PE --> PB

    PE -->|"getPrice(model)"| CPT
    PE -->|"getPrice(model)"| APT
    PE -->|"getPrice(model)"| PRG
    CPT --> APT
    CPT --> JPT
    PRG -->|"loads"| DPC
    APT -->|"returns"| MP
    JPT -->|"returns"| MP
    PRG -->|"returns"| MP

    PE -->|"estimateTokenCount()"| TBA
    PE -->|"estimateTokenCount()"| TEI

    PE -->|"estimateImageTokens()"| OAI
    PE -->|"estimateImageTokens()"| ANT
    PE -->|"estimateImageTokens()"| GEM
    PE -->|"estimateImageTokens()"| CUS

    PE -->|"produces"| PR
    PE -->|"produces"| MM
```

---

## 2. Post-Request Billing Lifecycle

The most common usage pattern: you receive token counts from the LLM API and calculate exact costs.

```mermaid
sequenceDiagram
    participant App as Application
    participant Builder as PricingBuilder
    participant Engine as PricingEngine
    participant Table as PriceTable
    participant Math as PricingResult::compute()
    participant Result as PricingResult

    App->>Builder: PricingEngine::for('gpt-4o')
    Builder->>Engine: create default engine (DefaultPriceCatalog)
    App->>Builder: ->calculate(inputTokens: 1200, outputTokens: 350)
    Builder->>Engine: calculate('gpt-4o', 1200, 350)
    Engine->>Table: getPrice('gpt-4o')

    alt Model found (exact or glob match)
        Table-->>Engine: ModelPrice{input: $2.50/M, output: $10.00/M}
        Engine->>Math: compute(price, 1200, 350, 0, 0)
        Note over Math: inputCost  = (1200 / 1_000_000) × 2.50 = $0.003000<br/>outputCost = (350  / 1_000_000) × 10.00 = $0.003500<br/>total      = $0.006500
        Math-->>Result: PricingResult{total: $0.006500, isUnknown: false}
    else Model not found
        Table-->>Engine: ModelPrice::zero('gpt-4o-unknown')
        Engine->>Math: compute(zero_price, ..., unknownModel: true)
        Math-->>Result: PricingResult{total: $0.000000, isUnknown: true}
    end

    Result-->>App: PricingResult
    App->>Result: ->format()
    Result-->>App: "$0.006500 USD (1,200 input + 350 output tokens)"
```

---

## 3. Pre-Request Estimation Lifecycle

Estimate the cost of a prompt **before** sending it to the API.

```mermaid
sequenceDiagram
    participant App as Application
    participant Builder as PricingBuilder
    participant Engine as PricingEngine
    participant Est as TextEstimatorInterface
    participant Bridge as TokenizerBridgeAdapter
    participant Tok as TokenizerInterface
    participant Table as PriceTable
    participant Result as PricingResult

    App->>Engine: PricingEngine::withTokenizer(TokenizerRegistry::createDefault())
    Note over Engine: Wraps tokenizer in TokenizerBridgeAdapter
    App->>Builder: ->make('claude-sonnet-4-6')
    App->>Builder: ->estimate('Write a PHP tutorial on closures.')
    Builder->>Engine: estimate('Write a PHP tutorial...', 'claude-sonnet-4-6')

    Engine->>Est: estimateTokenCount(text, model)
    Note over Engine,Est: textEstimator is required for estimate()<br/>Throws EstimationNotAvailableException if null

    Est->>Bridge: estimateTokenCount(text, model)
    Bridge->>Tok: count(text, model)
    Tok-->>Bridge: TokenCount{count: 92, approximate: false}
    Bridge-->>Est: 92

    Engine->>Table: getPrice('claude-sonnet-4-6')
    Table-->>Engine: ModelPrice{input: $3.00/M, output: $15.00/M}

    Engine->>Result: compute(price, inputTokens: 92, outputTokens: 0)
    Note over Result: inputCost = (92 / 1_000_000) × 3.00 = $0.000276<br/>outputCost = $0.000000 (not yet known)<br/>total = $0.000276
    Result-->>App: PricingResult{input: 92 tokens, cost: $0.000276}
```

---

## 4. Price Resolution: ChainedPriceTable

How a `ChainedPriceTable` walks through its priority queue to find a model price.

```mermaid
flowchart TD
    START(["getPrice('my-enterprise-gpt-v3')"])
    
    START --> C1{"Table 1:<br/>JsonFilePriceTable<br/>Has exact match?"}
    C1 -- Yes --> R1(["Return ModelPrice<br/>from JSON file"])
    C1 -- No --> C2{"Table 1:<br/>Glob pattern match?<br/>'my-enterprise-*'?"}
    C2 -- Yes --> R2(["Return ModelPrice<br/>from JSON glob"])
    C2 -- No --> C3{"Table 2:<br/>ArrayPriceTable (custom)<br/>Has exact match?"}
    C3 -- Yes --> R3(["Return ModelPrice<br/>from custom overrides"])
    C3 -- No --> C4{"Table 2:<br/>Glob pattern match?"}
    C4 -- Yes --> R4(["Return ModelPrice<br/>from custom glob"])
    C4 -- No --> C5{"Table 3:<br/>DefaultPriceCatalog<br/>Has exact match?"}
    C5 -- Yes --> R5(["Return ModelPrice<br/>from built-in catalog"])
    C5 -- No --> C6{"Table 3:<br/>Glob pattern match?<br/>'gpt-*'?"}
    C6 -- Yes --> R6(["Return ModelPrice<br/>from catalog glob"])
    C6 -- No --> ZERO(["Return ModelPrice::zero()<br/>isUnknownModel = true"])
    
    style R1 fill:#22c55e,color:#fff
    style R2 fill:#22c55e,color:#fff
    style R3 fill:#86efac,color:#333
    style R4 fill:#86efac,color:#333
    style R5 fill:#bfdbfe,color:#333
    style R6 fill:#bfdbfe,color:#333
    style ZERO fill:#fca5a5,color:#333
```

---

## 5. Glob Pattern Resolution Order

When multiple glob patterns could match the same model ID, the library picks the **most specific** (longest) match.

```mermaid
flowchart LR
    REQ(["Request: 'claude-sonnet-4-6'"])
    
    REQ --> E1{"Exact match?<br/>'claude-sonnet-4-6'"}
    E1 -- Hit --> EXACT(["Resolve exact entry"])
    
    E1 -- Miss --> G1{"Longest glob first<br/>'claude-sonnet-4-6*'"}
    G1 -- Hit --> LONG(["Resolve longest glob"])
    
    G1 -- Miss --> G2{"Next pattern<br/>'claude-sonnet-*'"}
    G2 -- Hit --> MED(["Resolve medium glob"])
    
    G2 -- Miss --> G3{"Shortest pattern<br/>'claude-*'"}
    G3 -- Hit --> SHORT(["Resolve shortest glob"])
    
    G3 -- Miss --> NULL(["ModelPrice::zero()"])

    style EXACT fill:#16a34a,color:#fff
    style LONG fill:#22c55e,color:#fff
    style MED fill:#86efac,color:#333
    style SHORT fill:#bbf7d0,color:#333
    style NULL fill:#fca5a5,color:#333
```

---

## 6. Anthropic vs OpenAI Caching Math

The fundamental billing difference between the two providers.

```mermaid
graph TB
    subgraph ANTHROPIC["Anthropic — ADDITIVE Model (cacheReadIsSubsetOfInput = false)"]
        direction LR
        AI["input_tokens: 200<br/>→ billed at $3.00/M"]
        AW["cache_creation_input_tokens: 5,000<br/>→ billed at $3.75/M (1.25× surcharge)"]
        AR["cache_read_input_tokens: 5,000<br/>→ billed at $0.30/M (0.10× discount)"]
        AT["Total = inputCost + cacheWriteCost + cacheReadCost<br/>Three INDEPENDENT token pools"]
        AI --> AT
        AW --> AT
        AR --> AT
    end

    subgraph OPENAI["OpenAI — SUBSET Model (cacheReadIsSubsetOfInput = true)"]
        direction LR
        OP["prompt_tokens: 5,200<br/>→ TOTAL includes cached tokens"]
        OC["prompt_tokens_details.cached_tokens: 5,000<br/>→ SUBSET of prompt_tokens"]
        ON["Non-cached portion: 5,200 − 5,000 = 200<br/>→ billed at $2.50/M (standard)"]
        OD["Cached portion: 5,000<br/>→ billed at $1.25/M (50% discount)"]
        OT["Total = nonCachedInputCost + cachedInputCost + outputCost<br/>prompt_tokens OVERLAPS cached_tokens"]
        OP --> ON
        OC --> OD
        ON --> OT
        OD --> OT
    end

    subgraph KEY["Key Difference"]
        K1["Anthropic: inputTokens + cacheWriteTokens + cacheReadTokens are ALL DIFFERENT token pools"]
        K2["OpenAI: cacheReadTokens ⊂ inputTokens — the cached tokens are ALREADY inside inputTokens"]
    end
```

---

## 7. Caching Strategy Decision Flow

How the engine decides which math to apply based on `ModelPrice.cacheReadIsSubsetOfInput`.

```mermaid
flowchart TD
    INPUT(["calculate(model, inputTokens=5200,<br/>outputTokens=400, cacheReadTokens=5000)"])
    
    INPUT --> L1["getPrice(model)"]
    L1 --> L2{"cacheReadIsSubsetOfInput?"}
    
    L2 -- "true (OpenAI)" --> OA["Non-cached = inputTokens − cacheReadTokens<br/>= 5200 − 5000 = 200 tokens"]
    OA --> OB["inputCost = 200 × (inputPerMillion / 1M)"]
    OB --> OC["cacheReadCost = 5000 × (cacheReadPerMillion / 1M)"]
    OC --> OD["cacheSavings = 5000 × (inputPerMillion − cacheReadPerMillion) / 1M"]

    L2 -- "false (Anthropic)" --> AA["inputCost = inputTokens × (inputPerMillion / 1M)<br/>= 5200 × 3.00/M (full standard rate)"]
    AA --> AB["cacheReadCost = cacheReadTokens × (cacheReadPerMillion / 1M)<br/>= 5000 × 0.30/M (separately metered)"]
    AB --> AC["cacheSavings = cacheReadTokens × (inputPerMillion − cacheReadPerMillion) / 1M"]

    OD --> TOTAL["totalCostUsd() = inputCost + outputCost + cacheWriteCost + cacheReadCost"]
    AC --> TOTAL
```

---

## 8. Image Token Estimation Pipeline

How `estimateWithImages()` converts image dimensions into token counts and costs.

```mermaid
sequenceDiagram
    participant App as Application
    participant Engine as PricingEngine
    participant Est as estimate() [text path]
    participant Chain as ImageEstimator Chain
    participant OAI as OpenAIImageEstimator
    participant ANT as AnthropicImageEstimator
    participant GEM as GeminiImageEstimator
    participant Result as MultimodalPricingResult

    App->>Engine: estimateWithImages('Describe image', 'gpt-4o', [ImageAttachment::highDetail(1920, 1080)])

    Engine->>Est: estimate(text, model)
    Est-->>Engine: textResult (PricingResult, text tokens only)

    loop for each ImageAttachment
        Engine->>Chain: countImageTokens(image, 'gpt-4o')
        Chain->>OAI: supports('gpt-4o')
        OAI-->>Chain: true
        Chain->>OAI: estimateImageTokens(1920, 1080, 'high', 'gpt-4o')
        Note over OAI: Scale to 2048px → tile at 512px<br/>→ 4 tiles × 170 + 85 base = 765 tokens
        OAI-->>Chain: TokenCount{count: 765}
        Chain-->>Engine: 765 image tokens
    end

    Engine->>Engine: imageCost = (765 / 1_000_000) × price.effectiveImagePrice()
    Note over Engine: effectiveImagePrice() = imageInputPerMillion ?? inputPerMillion
    Engine->>Result: new MultimodalPricingResult(textResult, imageCost, imageTokens=765)
    Result-->>App: MultimodalPricingResult
    App->>Result: ->imageTokens()   // 765
    App->>Result: ->imageCostUsd()  // $0.001913
    App->>Result: ->totalCostUsd()  // textCost + imageCost
```

---

## 9. Image Estimator Fallback Chain

Resolution order when multiple estimators are registered.

```mermaid
flowchart TD
    REQ(["estimateImageTokens(model='gemini-2.5-flash', ...)"])
    
    REQ --> E1{"Estimator 1:<br/>CustomVisionEstimator<br/>supports('gemini-2.5-flash')?"}
    E1 -- "false (prefix 'my-vision-')" --> E2{"Estimator 2:<br/>OpenAIImageEstimator<br/>supports('gemini-2.5-flash')?"}
    E2 -- "false (prefix 'gpt-', 'o1', 'o3')" --> E3{"Estimator 3:<br/>AnthropicImageEstimator<br/>supports('gemini-2.5-flash')?"}
    E3 -- "false (prefix 'claude-')" --> E4{"Estimator 4:<br/>GeminiImageEstimator<br/>supports('gemini-2.5-flash')?"}
    E4 -- "true (prefix 'gemini-')" --> R4["Use GeminiImageEstimator<br/>768×768 tile formula<br/>258 tokens/tile"]
    E4 -- "false (no match at all)" --> FB["Fallback: return 0 tokens<br/>(no estimator matched)"]

    E1 -- "true" --> R1["Use CustomVisionEstimator<br/>(first match wins)"]
    
    style R1 fill:#22c55e,color:#fff
    style R4 fill:#3b82f6,color:#fff
    style FB fill:#fca5a5,color:#333
```

---

## 10. PricingRegistry Lazy Factory Resolution

How the `PricingRegistry` loads model prices on demand without pre-instantiating everything.

```mermaid
sequenceDiagram
    participant App as Application
    participant Reg as PricingRegistry
    participant Cache as Resolution Cache (array)
    participant Factory as Closure(): ModelPrice

    Note over Reg: At boot: only Closures are stored in memory.<br/>No ModelPrice objects exist yet.

    App->>Reg: getPrice('my-enterprise-llm')

    Reg->>Cache: lookup('my-enterprise-llm')
    Cache-->>Reg: null (not cached yet)

    Reg->>Reg: fnmatch glob patterns...
    Note over Reg: Checks 'my-enterprise-*' glob → match!

    Reg->>Factory: invoke closure for 'my-enterprise-*'
    Note over Factory: return new ModelPrice(<br/>  model: 'my-enterprise-*',<br/>  inputPerMillion: 1.00,<br/>  outputPerMillion: 4.00<br/>)
    Factory-->>Reg: ModelPrice

    Reg->>Cache: store('my-enterprise-llm', ModelPrice)
    Reg-->>App: ModelPrice

    App->>Reg: getPrice('my-enterprise-llm') [second call]
    Reg->>Cache: lookup('my-enterprise-llm')
    Cache-->>Reg: ModelPrice (already cached)
    Reg-->>App: ModelPrice (no factory call needed)
```

---

## 11. Tool-Calling Loop: Immutable Cost Accumulation

How `PricingResult::add()` accumulates costs across multiple LLM calls without mutating state.

```mermaid
sequenceDiagram
    participant Agent as Agentic Loop
    participant Engine as PricingEngine
    participant T1 as Turn 1 Result
    participant T2 as Turn 2 Result
    participant T3 as Turn 3 Result
    participant Session as Session Total

    Agent->>Engine: calculate(model, input=350, output=280, cacheWrite=4800)
    Engine-->>T1: PricingResult{total: $0.023700, cache_write: $0.018000}

    Agent->>Engine: calculate(model, input=850, output=420, cacheRead=4800)
    Engine-->>T2: PricingResult{total: $0.009390, cache_read: $0.001440}

    Agent->>Engine: calculate(model, input=1200, output=380, cacheRead=4800)
    Engine-->>T3: PricingResult{total: $0.011940, cache_read: $0.001440}

    Note over T1,Session: add() is IMMUTABLE — always creates a new PricingResult
    Agent->>T1: ->add(T2)
    T1-->>Agent: new PricingResult{total: $0.033090} [T1+T2]
    Agent->>Agent: ->add(T3)
    Agent-->>Session: new PricingResult{total: $0.045030} [T1+T2+T3]

    Session-->>Agent: totalCostUsd() = $0.045030
    Session-->>Agent: cacheSavingsUsd() = (savings from cache reads)
    Session-->>Agent: inputTokens() = 2400 (accumulated)
    Session-->>Agent: outputTokens() = 1080 (accumulated)
```

---

## 12. Pre-Request Budget Guard Pattern

A production pattern that checks cost before making the API call.

```mermaid
sequenceDiagram
    participant Client as Client Code
    participant Dispatcher as RequestDispatcher
    participant Engine as PricingEngine
    participant API as LLM API

    Client->>Dispatcher: dispatch('Write a 5,000 word report', usage_estimate)

    Dispatcher->>Engine: estimate(prompt, model)
    Engine-->>Dispatcher: estimatedResult{total: $0.025000}

    Dispatcher->>Dispatcher: projectedTotal = sessionCost.add(estimate).totalCostUsd()
    Note over Dispatcher: sessionCost = $0.018000 (accumulated so far)<br/>projected   = $0.018000 + $0.025000 = $0.043000<br/>budget      = $0.020000

    alt projected > budget
        Dispatcher-->>Client: throw RuntimeException("Budget exceeded: $0.043 > $0.020")
    else within budget
        Dispatcher->>API: send actual request
        API-->>Dispatcher: response + usage{input: 1200, output: 3800}
        Dispatcher->>Engine: calculate(model, inputTokens: 1200, outputTokens: 3800)
        Engine-->>Dispatcher: actualResult{total: $0.022800}
        Dispatcher->>Dispatcher: sessionCost = sessionCost.add(actualResult)
        Dispatcher-->>Client: actualResult
    end
```

---

## 13. Dependency Injection: Three Injectable Axes

The three independent customisation points and how they compose.

```mermaid
graph TB
    subgraph Constructor["new PricingEngine(textEstimator, priceTable, imageEstimators)"]
        AX1["Axis 1: TextEstimatorInterface<br/>(text token counting)"]
        AX2["Axis 2: PriceTableInterface<br/>(price lookup)"]
        AX3["Axis 3: list&lt;ImageTokenEstimatorInterface&gt;<br/>(image token counting)"]
    end

    subgraph AX1Impl["Axis 1 Implementations"]
        TBA["TokenizerBridgeAdapter<br/>(wraps nexus-ai-tokenizer)"]
        CTE["Custom TextEstimatorInterface<br/>(your own implementation)"]
        NONE1["null<br/>(estimate() not available)"]
    end

    subgraph AX2Impl["Axis 2 Implementations"]
        APT["ArrayPriceTable<br/>(in-memory, glob patterns)"]
        JPT["JsonFilePriceTable<br/>(external JSON, no redeploy)"]
        CPT["ChainedPriceTable<br/>(priority delegation)"]
        PRG["PricingRegistry<br/>(lazy factories)"]
        NPT["NullPriceTable<br/>(test isolation)"]
    end

    subgraph AX3Impl["Axis 3 Implementations"]
        OAI2["OpenAIImageEstimator (built-in)"]
        ANT2["AnthropicImageEstimator (built-in)"]
        GEM2["GeminiImageEstimator (built-in)"]
        CUSTOM["Custom estimator(s)"]
        EMPTY["empty list → auto-load built-ins"]
    end

    AX1 --> TBA
    AX1 --> CTE
    AX1 --> NONE1
    AX2 --> APT
    AX2 --> JPT
    AX2 --> CPT
    AX2 --> PRG
    AX2 --> NPT
    AX3 --> OAI2
    AX3 --> ANT2
    AX3 --> GEM2
    AX3 --> CUSTOM
    AX3 --> EMPTY
```

---

## 14. PricingResult Value Object Immutability

How results are created, accessed, and combined without mutation.

```mermaid
stateDiagram-v2
    [*] --> Created: PricingResult::compute(price, tokens...)

    Created --> Accessed: read-only accessors\ntotalCostUsd(), inputTokens(),\ncacheSavingsUsd(), format(), toArray()

    Created --> Combined: ->add(otherResult)

    Combined --> NewResult: new PricingResult\n(tokens & costs summed)

    NewResult --> Accessed
    NewResult --> Combined

    Accessed --> [*]: no state mutation ever occurs

    note right of Created
        Immutable via PHP `readonly`
        Cannot be modified after construction
    end note

    note right of Combined
        Returns a NEW instance
        Original results unchanged
        Safe in async workers (Octane/Horizon)
    end note
```

---

## 15. Exception & Graceful Degradation Map

Which operations throw exceptions and which degrade gracefully.

```mermaid
flowchart TD
    subgraph SAFE["Never throws — graceful degradation"]
        S1["calculate() with unknown model<br/>→ returns PricingResult{cost: $0, isUnknown: true}"]
        S2["estimate() with unknown model<br/>→ returns PricingResult{cost: $0, isUnknown: true}"]
        S3["estimateWithImages() with no matching estimator<br/>→ image tokens counted as 0"]
    end

    subgraph THROW["Throws exceptions"]
        T1["estimate() / estimateChat() / estimateWithImages()<br/>with no TextEstimatorInterface configured<br/>→ EstimationNotAvailableException"]
        T2["JsonFilePriceTable with missing / unreadable file<br/>→ PriceTableException"]
        T3["JsonFilePriceTable with invalid JSON schema<br/>→ PriceTableException"]
        T4["PricingRegistry::getPrice() called directly<br/>on unregistered model (bypasses engine)<br/>→ UnknownModelPriceException"]
    end

    subgraph AVOID["How to avoid exceptions"]
        A1["Always inject TextEstimatorInterface if calling estimate()"]
        A2["Ensure JSON file exists and is readable by the web process"]
        A3["Use PricingEngine::calculate() (goes through engine, never throws)"]
    end

    T1 --> A1
    T2 --> A2
    T3 --> A2
    T4 --> A3
```

---

> **← Back:** [System Internals](internals.md) · **Next:** [Examples Guide →](examples.md)
