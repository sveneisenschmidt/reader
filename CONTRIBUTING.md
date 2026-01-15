# Contributing

Thanks for helping improve Reader. This guide covers local setup, workflow, and
style expectations.

## Quick Start

1. Install dependencies:
   ```bash
   make install
   ```
2. Run migrations:
   ```bash
   make db-migrate
   ```
3. Start the app:
   ```bash
   make dev
   ```

## Development Notes

- Reader is a single-user, self-hosted app.
- For local development with the background worker, use:
  ```bash
  make dev-with-worker
  ```

## Linting and Formatting

Run all linters:
```bash
make lint
```

Auto-fix where possible:
```bash
make lint-fix
```

If PHP-CS-Fixer fails due to parallel runner restrictions, retry sequentially:
```bash
vendor/bin/php-cs-fixer fix --sequential
```

## Tests

Run the test suite:
```bash
make test
```

## Style Guides

- Backend: `STYLEGUIDE_BACKEND.md`
- Frontend: `STYLEGUIDE_FRONTEND.md`

## Screenshots

To update UI screenshots:
```bash
make screenshots
```

## Pull Requests

- Keep changes focused and scoped.
- Run `make lint` and `make test` before pushing changes.
- Describe behavior changes and UI updates clearly.

## Coverage Expectations

Code coverage for classes, methods, and lines must stay above 95%.
