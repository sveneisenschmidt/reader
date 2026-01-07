# Reader

A fast, minimal RSS reader.

![Screenshot](docs/screenshot.png)

## Features

- Three-column layout: sidebar, reading list, reading pane
- Unread/read tracking with mark-all-as-read
- Green dot indicator for new (unseen) articles
- Pull to refresh on mobile and desktop
- Organize feeds into folders
- Dark mode support

## Usage

### Reading Articles

Open an article by clicking it in the reading list. The article content appears in the reading pane.

Articles are marked **read** when you click "Mark as read", "Mark all as read", or open the original link.

The green dot disappears when you open an article in the reading pane, helping you track which articles you've looked at.

### Refreshing Feeds

- Pull down from the top of the page
- Click the "Refreshed..." timestamp in the footer

### Managing Subscriptions

Click "Subscriptions" to edit your feeds via YAML:

```yaml
- url: https://example.com/feed.xml
  title: Example Feed

- url: https://example.com/tech.xml
  title: Tech Feed
  folder: ["News", "Technology"]
```

Feeds with `folder` are grouped in the sidebar. Nested folders display as "News / Technology".

### Filters

- **Show unread**: Toggle to hide read articles
- **Limit**: Show 25, 50, or all articles

---

## Development

### Requirements

- PHP 8.4+
- Composer
- Symfony CLI

### Setup

```bash
composer install
make db-migrate
make dev
```

Open http://127.0.0.1:8000

### Stack

- Symfony 7
- SQLite (separate databases for users, subscriptions, content)
- Vanilla CSS and JavaScript
- Symfony Scheduler for background tasks

### Background Worker

Feeds refresh every 15 minutes. Old articles are cleaned up daily.

`make dev` starts the worker automatically. For production, use cron or Supervisor:

```cron
* * * * * cd /path/to/reader && make worker
```

### Available Commands

| Command | Description |
|---------|-------------|
| `make dev` | Start development server with worker |
| `make stop` | Stop development server |
| `make test` | Run tests |
| `make db-migrate` | Run database migrations |
| `make db-reset` | Reset all databases |
| `make cache-clear` | Clear application cache |
| `make worker` | Run background worker (55s timeout) |

## License

MIT
