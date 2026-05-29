# Contributing

Thanks for your interest in Arc. This document covers how to contribute.

## Bug Reports

Open an issue with:
- PHP version
- Arc version
- Steps to reproduce
- Expected vs actual behavior

## Pull Requests

1. Fork the repository
2. Create a feature branch from `main`
3. Add tests for any new functionality
4. Ensure all existing tests pass: `./vendor/bin/phpunit`
5. Follow the existing code style: `declare(strict_types=1)`, PHP 8.4+ features
6. Keep PRs focused on a single concern
7. Write clear commit messages

## Code Style

- `declare(strict_types=1)` in every file
- Constructor property promotion
- Named arguments where they improve readability
- Minimal comments (only where intent isn't obvious)
- Methods should be small and composable

## Running Tests

```bash
./vendor/bin/phpunit
```

Tests requiring a database use SQLite in-memory. Make sure `pdo_sqlite` is available.

## Adding Features

New features should include:
- Implementation
- Unit tests
- Documentation in the README if it's a user-facing feature

## Questions

Open an issue with the `question` label.