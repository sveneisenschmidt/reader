# Development

## Requirements

- PHP 8.4+
- SQLite3

## Setup

```bash
# Install dependencies
make install

# Run database migrations
make db-migrate

# Create dev test user (dev environment only)
make db-create-dev-user

# Start development server (without worker)
make dev

# Start development server (with worker)
make dev-with-worker

# Run tests
make test

# Run linter (PHPStan, PHP-CS-Fixer, ESLint, Stylelint)
make lint

# Auto-fix lint issues
make lint-fix
```

## Dev Test User

The `make db-create-dev-user` command creates a test user for development:

- **Email:** dev@localhost.arpa
- **Password:** devdevdev
- **TOTP Secret:** `3DDQUI6BMJAJMWV3U5YGHSZYKVCHZIUAQCTI6ZWWEHYNI5JSLCYZ75ADRJQQC3BECC73O2GWOSWGO6MLRD56MONJXPOF23NIA47TLLQ`

Add this secret to your authenticator app once. It's stable across database resets.

This command only works in dev environment.
