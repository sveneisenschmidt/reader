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
- **Password:** zSV=FWOat_tCgW6xFR86
- **TOTP Secret:** `K5UO6CZNN3RAZTXPZT26XCULJWTBPKDEVIP3JQMLQ5NULXOFAQGA4R5VLCXLDNHI2YFRXWRB72JHJQWRQNBG7Q35MJKO5NZ4WNUOOFQ`

Add this secret to your authenticator app once. It's stable across database resets.

This command only works in dev environment.
