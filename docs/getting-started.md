# Getting Started

The **nexus-ai-pricing** library offers a streamlined experience for calculating LLM costs without fuss. You can use it as a simple utility or integrate its advanced DI patterns into your enterprise framework.

## 1. Zero-Config One-Liners (Post-Request Billing)

If you just need to calculate the cost from the usage totals returned by an API (e.g. OpenAI's `usage` object):

```php
use Token27\NexusAI\Pricing\Engine\PricingEngine;

$result = PricingEngine::for('gpt-4o')
    ->calculate(inputTokens: 1200, outputTokens: 350);

echo $result->totalCostUsd(); // 0.008850
echo $result->format();       // "$0.008850 USD (1,200 input + 350 output tokens)"
```

## 2. Pre-Request Cost Estimation

You can estimate costs **before** calling the API. The engine relies on [`token27/nexus-ai-tokenizer`](https://github.com/token27/nexus-ai-tokenizer) to count tokens locally, keeping latency low and protecting privacy.

```php
$estimated = PricingEngine::for('claude-sonnet-4-6')
    ->estimate('Write a comprehensive tutorial on abstract syntax trees.');

echo "Estimated cost: " . $estimated->format();
```

## 3. Tool Calling and Multi-turn Estimation

For chat endpoints, `estimateChat()` handles overhead such as ChatML markers, message primes, and tool schemas.

```php
$chatResult = PricingEngine::for('gpt-4o-mini')->estimateChat([
    ['role' => 'system', 'content' => 'Extract data.'],
    ['role' => 'user', 'content' => 'Data: ...']
]);

echo "Includes provider overhead: yes: " . $chatResult->format();
```

## 4. Vision & Multimodal

Images drastically change token limits and pricing. The engine correctly computes image tile overhead out of the box based on the provider format.

```php
use Token27\NexusAI\Pricing\ValueObject\ImageAttachment;

$visionPrice = PricingEngine::for('gemini-2.5-pro')->estimateWithImages(
    text: 'Analyze the defect in this circuit.',
    images: [
        ImageAttachment::highDetail(1920, 1080)
    ]
);

echo $visionPrice->imageCostUsd();
```

## Next Steps

Now that you've seen the basics, follow the [Installation Guide](installation.md) to set it up locally, or dive straight into the [Architecture Overview](architecture.md).

---

> **Next:** [Installation & Setup →](installation.md)
