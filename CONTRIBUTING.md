# Contributing to drand-client-php

Thank you for your interest in contributing to this project! Please follow the guidelines below to help us maintain high quality and consistency.

---

## Testing

This project uses PHPUnit for all tests. Tests are grouped as **unit** or **integration** using PHPUnit's @group annotation.

### Run all tests

```
vendor/bin/phpunit
```

### Run only unit tests

```
vendor/bin/phpunit --group unit
```

### Run only integration tests

```
vendor/bin/phpunit --group integration
```

You can also run a specific test file directly:

```
vendor/bin/phpunit tests/DrandClientIntegrationTest.php
```

See the `tests/` directory for all available test files.

---

## Contributor Quick Start: Testing & Code Quality

To ensure high code quality and consistency, please run the following tools before submitting a pull request:

### 1. Run all tests
```bash
vendor/bin/phpunit
```

### 2. Run static analysis with Psalm
```bash
vendor/bin/psalm
```

### 3. Run static analysis with PHPStan
```bash
vendor/bin/phpstan analyse src --level=max
```

### 4. Check code style with PHP_CodeSniffer (PSR-12)
If not installed, add it as a dev dependency:
```bash
composer require --dev squizlabs/php_codesniffer
```
Then run:
```bash
vendor/bin/phpcs --standard=PSR12 src/ tests/
```

Alternatively, you can use a globally installed `phpcs`:
```bash
phpcs --standard=PSR12 src/ tests/
```

> **Tip:** You can also use `php-cs-fixer` for auto-fixing style issues:
> ```bash
> composer run cs
> ```

Please ensure your code passes all tests and checks before opening a PR. 