# Reader

A fast, lightweight RSS reader.

![Screenshot](docs/screenshot.png)

## Features

- Three-column layout: subscriptions, articles, reading pane
- Unread/read tracking with mark-all-as-read
- Pull to refresh on mobile
- YAML-based subscription management with folder support
- Background worker for automatic feed updates and cleanup
- Dark mode

## User Guide

### Read Status

Articles are marked as **read** when you:
- Click "Mark as read" button
- Click "Mark all as read" in the sidebar
- Click on the article title (opens original in new tab)
- Click "Read original" (opens original in new tab)

### Seen Status

Articles are marked as **seen** (removes the green dot indicator) when you:
- Open an article in the reading pane

The green dot helps you identify new articles you haven't looked at yet, even if they're still unread.

### Folders

Organize subscriptions into folders via the YAML editor in "Subscriptions":

```yaml
- url: https://example.com/feed.xml
  title: Example Feed
  folder: ["News"]

- url: https://example.com/tech.xml
  title: Tech Feed
  folder: ["News", "Technology"]
```

- `folder` is an array of strings for nested folders
- Feeds without a folder appear at the bottom of the sidebar
- Nested folders display as "News / Technology" in the sidebar

### Filters

- **Show unread**: Toggle to show only unread articles
- **Limit**: Show 25, 50, or all (99+) articles

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
