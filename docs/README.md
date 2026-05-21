# nexus-ai-pricing — Documentation

Welcome to the full documentation for `token27/nexus-ai-pricing`.

## Table of Contents

### Getting Started

| Guide | Description |
|-------|-------------|
| [Getting Started](getting-started.md) | Cost calculations, quick one-liners, and estimating API usage |
| [Installation](installation.md) | Composer, system requirements, and advanced DI container setup |
| [Architecture Schematic](architecture.md) | Core concepts, mathematical flows and value-object mappings |

### Core Mechanics

| Guide | Description |
|-------|-------------|
| [Pricing Engine](pricing-engine.md) | System usage, estimation methods, and calculation variations |
| [Pricing Result DTOs](pricing-result.md) | Result interfaces, multimodal overrides, and formatters |
| [Caching Strategies](caching-strategies.md) | Additive Anthropic Prompt Caching versus OpenAI's Subset Cached Input |
| [Vision Multimodal](multimodal-vision.md) | Calculating image dimensions based on differing provider constraints |

### Tables and Registries

| Guide | Description |
|-------|-------------|
| [Custom Tables](custom-tables.md) | Array tables, Chained prioritization, and remote JSON file overlays |
| [Pricing Registry](pricing-registry.md) | Registering lazy closures, factory mapping, and glob wildcard resolution |
| [Default Catalog Data](default-catalog.md) | The fully integrated and inherently tracked AI metric list |

### Advanced Integration & Tooling

| Guide | Description |
|-------|-------------|
| [Integration Workflows](advanced-integration.md) | Accumulating metrics per-session and handling model failures gracefully |
| [System Internals](internals.md) | Safe floating point mathematics and absolute exception limits |
| [Flow Diagrams](flow-diagrams.md) | Mermaid diagrams of the full request lifecycle, price resolution, caching math, image pipeline, and DI architecture |
| [Examples Guide](examples.md) | Complete walkthrough of all 13 example scripts |
| [Troubleshooting](troubleshooting.md) | How to solve Unknown model bounds and file permission faults |

### Maintainer Operations

| Guide | Description |
|-------|-------------|
| [Testing](testing.md) | Validating mathematical constraints via PHPUnit and PHPStan analysis |
| [Contributing](contributing.md) | Updating the catalog matrices correctly with proper verifications |

## Ecosystem Position

`nexus-ai-pricing` is the financial computation layer backing the enterprise AI ecosystem. By operating in conjunction strictly via `nexus-ai-tokenizer`, it enables accurate calculations offline keeping system speeds incredibly resilient securely.
