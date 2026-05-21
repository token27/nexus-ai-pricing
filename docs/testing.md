# Testing Guide

For enterprise readiness, every mathematical calculation must strictly pass deep verification. This library features completely comprehensive automated boundaries mitigating regressions.

## Running the Unit Test Environment

We utilize standard `PHPUnit`. Ensure dependencies have successfully downloaded:

```bash
composer install
```

To run exclusively unit-tests measuring cost constraints, token overrides, and internal logic flows:

```bash
composer test:unit
```

To visualize complete mapping checks validating code paths:

```bash
composer test:coverage
```

*(Coverage mandates `xdebug` enabled inherently upon the local device limits).*

## Static Analysis Checks

Every single interface constraint maps aggressively to `PHPStan Level 8`.

```bash
composer analyse
```

Failing Level 8 signifies unsafe closure handling, invalid mixed implementations or typecasting flaws overriding safe values. Zero faults are accepted.

## Code Style Enforcement

We utilize the custom local ecosystem definitions applied natively via `.php-cs-fixer.php` config bounds.

To strictly visualize failures safely preventing pipeline rejects:

```bash
composer lint
```

To automatically format the code aggressively correctly patching violations:

```bash
composer lint-fix
```

## Running the Complete Security Pipeline

Execute the exact action the CI handles natively:

```bash
composer check
```

---

> **← Back:** [Troubleshooting](troubleshooting.md) · **Next:** [Contributing →](contributing.md)
