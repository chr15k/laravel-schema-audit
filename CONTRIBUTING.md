# Contributing

Contributions are welcome via pull requests. Please review these guidelines before submitting a pull request.

## Process

1. Fork the repository.
2. Create a new branch.
3. Make your changes and add or update tests.
4. Run the test and quality checks.
5. Commit and push your changes.
6. Open a pull request and follow the [pull request template](.github/PULL_REQUEST_TEMPLATE.md).

## Guidelines

* Follow the project's coding standards and run `composer lint` before submitting.
* Add tests for new functionality and bug fixes.
* Keep commits focused and meaningful.
* Keep the commit history coherent. Rebase if necessary to resolve conflicts.
* Follow [Semantic Versioning](https://semver.org/).

## Setup

Clone your fork and install the development dependencies:

```bash
composer install
```

The test application skeleton is built automatically via `post-autoload-dump`, so no additional setup is required.

## Code Quality

Fix formatting and apply automated refactors:

```bash
composer fix
```

Check formatting without modifying files:

```bash
composer test:lint
```

Check for available refactors without applying them:

```bash
composer test:refactor
```

Run static analysis:

```bash
composer test:types
```

## Tests

Run the unit test suite:

```bash
composer test:coverage
```

## CI

Run the full quality and test suite:

```bash
composer test
```

This runs the lint, refactor, type, and test checks used by the project.
