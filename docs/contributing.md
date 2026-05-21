# Contributing Guidelines

Contributions modifying token parameters, discovering undocumented behaviors via providers, adding new model defaults, or resolving framework bugs are severely encouraged.

## Preparing the Development Branch

1. Fork this `good-one` related repository structurally.
2. Ensure you initialize via `composer update` connecting to any underlying linked system modules.
3. Configure your branch clearly explicitly explaining structural modifications (`feature/cache-override-fix`, `feat/gemini-new-prices`).

## Adding New Price Models

Because `DefaultPriceCatalog` must strictly correlate with actual market data, you MUST include clear verification metrics when submitting updates.

1. Locate `src/Catalog/DefaultPriceCatalog.php`.
2. Append the new `ModelPrice` value object explicitly.
3. Your update PR **MUST** include a `note` property containing the link to the official pricing release or documentation. Prices without evidence will automatically face rejection delays preventing misinformation inside core tables.

## PR Validation Constraints

Your submissions will safely trigger GitHub Actions automatically running the `composer check` boundary.

Before committing:

- Execute `composer lint-fix` maintaining internal spacing logic.
- Execute `composer analyse` resolving unexpected typings implicitly impacting `isUnknownModel` fallbacks.
- Write a short `unit test` asserting your logic properly parses values accurately inside `/tests/`.

Upon satisfying the metrics, notify the system orchestrators for final ecosystem validation merging.

---

> **← Back:** [Testing](testing.md)
