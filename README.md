# Reader

A fast, lightweight RSS reader.

![Screenshot](docs/screenshot.png)

## Features

- Three-column layout: subscriptions, articles, reading pane
- Unread/read tracking with mark-all-as-read
- Pull to refresh on mobile
- YAML-based subscription management
- Background worker for automatic feed updates and cleanup
- Dark mode

## Requirements

- PHP 8.4+
- Composer

## Installation

```bash
composer install
make db-create
make db-migrate
```

## Development

```bash
make serve
```

Open http://127.0.0.1:8000 in your browser.

## Background Worker

The app uses Symfony Scheduler for periodic tasks:

- **Refresh feeds**: Every 15 minutes
- **Cleanup old content**: Daily (removes articles older than 30 days)

### Running the Worker

**Development** (runs once and exits):
```bash
php bin/console messenger:consume scheduler_default --limit=1
```

**Production** (via cron, runs for 55 seconds then exits cleanly):
```cron
* * * * * cd /path/to/reader && php bin/console messenger:consume scheduler_default --time-limit=55
```

**Production** (via Supervisor for continuous running):
```ini
[program:reader-worker]
command=php bin/console messenger:consume scheduler_default --time-limit=3600
directory=/path/to/reader
autostart=true
autorestart=true
```

## Production

TBD

## License

MIT
