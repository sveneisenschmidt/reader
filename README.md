# Reader

A fast, minimal RSS reader for a single user.

![Feed View (Dark)](docs/screenshots/feed.png)

![Feed View (Light)](docs/screenshots/feed-light.png)

## Getting Started

### First-Time Setup

When you open Reader for the first time, you'll be guided through a setup wizard:

1. **Create your account** - Enter your email and choose a password
2. **Set up two-factor authentication** - Scan the QR code with your authenticator app (Google Authenticator, Authy, 1Password, etc.)
3. **Verify** - Enter the 6-digit code from your authenticator to confirm it's working
4. **Add your first feed** - Paste an RSS feed URL to get started

### Logging In

Every login requires:
- Your email
- Your password
- A 6-digit code from your authenticator app

## Features

- Clean three-column layout: feeds, articles, reading pane
- Track read/unread articles
- Bookmark articles for later reading
- Green dot shows new articles you haven't seen yet
- Pull down to refresh on any device
- Light, dark, and auto theme

## Using Reader

### Reading Articles

Click any article in the list to read it. The content appears in the reading pane on the right.

**Marking articles as read:**
- Click "Mark as read" on an article
- Click "Mark all as read" in the sidebar
- Click "Read original" to open the source and mark as read

**Bookmarking articles:**
- Click "Bookmark" to save an article for later
- Access your bookmarks via the "Bookmarks" link in the sidebar

The green dot next to new articles disappears once you've viewed them.

### Refreshing Feeds

Two ways to check for new articles:
- Pull down from the top of the page
- Click the "Refreshed..." text in the footer

Feeds also refresh automatically when the background worker is running (check Profile for status).

### Managing Your Feeds

Click **Subscriptions** in the header to add, edit, or remove feeds.

**Adding a feed:** Enter a feed URL and click "Subscribe".

Reader automatically detects and resolves feeds from various sources:

- **Standard RSS/Atom feeds** - Direct feed URLs
- **YouTube channels** - Paste any YouTube channel URL
- **Reddit subreddits** - Paste any subreddit URL, resolves to top weekly posts

**Editing a feed:** Change the name, folder, or settings and click "Update".

**Archive.is integration:** Enable "Open links via archive.is" for any subscription to open article links through archive.is. This helps bypass paywalls and preserves article content.

**Removing a feed:** Click "Remove" next to the feed you want to remove.

**Import/Export (OPML):** Use the Import/Export section to migrate your subscriptions:
- **Import:** Upload an OPML file to import feeds from other RSS readers. Duplicate feeds are automatically skipped.
- **Export:** Download all your subscriptions as an OPML file for backup or migration.

### Filtering Articles

Use the filters in the sidebar:

- **Show unread** - Hide articles you've already read
- **25 / 50 / 99+** - Limit how many articles are shown

### Preferences

Click the gear icon in the header to access your preferences:

- **Theme** - Choose between Auto, Light, or Dark mode
- **Pull to refresh** - Enable or disable the pull-down gesture to refresh feeds
- **Auto mark as read** - Automatically mark articles as read after 5 seconds of viewing
- **Keyboard shortcuts** - Enable keyboard navigation (arrow keys, space, enter, escape)
- **Bookmarks** - Enable or disable the bookmarks feature
- **Filter words** - Hide articles containing specific words (one word per line, case-insensitive)
- **Worker Status** - Shows if background refresh is running

### Keyboard Shortcuts

When enabled in preferences, you can navigate Reader with your keyboard:

**In the article list:**
- **Tab / Shift+Tab** - Navigate between subscriptions
- **Enter** - Open first article

**When reading an article:**
- **Arrow Up / Down** - Previous / next article
- **Space** - Toggle read/unread
- **Enter** - Open original article in new tab
- **Escape** - Close article

## Security

Reader is designed for single-user, self-hosted use:

- All pages require authentication
- Passwords are securely hashed
- Two-factor authentication (TOTP) is mandatory
- Sessions expire automatically

## Screenshots

### Login
![Login](docs/screenshots/login.png)

### Setup
![Setup](docs/screenshots/setup.png)

### Onboarding
![Onboarding](docs/screenshots/onboarding.png)

### Subscriptions
![Subscriptions](docs/screenshots/subscriptions.png)

### Profile
![Preferences](docs/screenshots/preferences.png)

### Mobile
![Mobile Feed List](docs/screenshots/feed-mobile-list.png)
![Mobile Reading Pane](docs/screenshots/feed-mobile-reading.png)

## Background Worker

Reader uses a background worker to automatically refresh feeds and clean up old content. The worker is built on Symfony Messenger with the Scheduler component.

### Scheduled Tasks

| Task | Description |
|------|-------------|
| Heartbeat | Logs a heartbeat entry to the database to indicate the worker is alive |
| Refresh Feeds | Fetches new articles from all subscribed feeds |
| Cleanup Content | Deletes articles older than 30 days |

Intervals are configured via environment variables (see below).

### Running the Worker

```bash
php bin/console messenger:consume scheduler_default
```

For production, use `--time-limit` (in seconds) to ensure periodic restarts:

```cron
0 * * * * cd /path/to/reader && php bin/console messenger:consume scheduler_default --time-limit=3540 >> var/log/worker.log 2>&1
```

This runs the worker hourly, with a 59-minute time limit (3540 seconds) to ensure it exits before the next cron run.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `WORKER_REFRESH_INTERVAL` | 5 minutes | How often feeds are refreshed |
| `WORKER_CLEANUP_INTERVAL` | 1 day | How often old content is cleaned up |

## Error Email Notifications

Reader can send error notifications via email in production. Errors are logged to both file and email when configured.

### Configuration

Set these environment variables in your `.env.local` or production environment:

```env
# SMTP transport (required for actual email delivery)
MAILER_DSN=smtp://user:password@smtp.example.com:587

# Error notification emails
ERROR_LOG_SENDER=system@eisenschmidt.email
ERROR_LOG_RECIPIENT=sven@eisenschmidt.email
```

By default, `MAILER_DSN=null://null` which discards all emails. Configure a real SMTP transport to enable email notifications.

### Worker Status

The Profile page shows whether the worker is running. This is determined by checking if the last heartbeat log entry was created within the last 30 seconds.

### Webhooks

As an alternative to the background worker, you can trigger tasks via HTTP webhooks. Webhooks use Basic Auth with dedicated credentials configured in your `.env`:

```env
WEBHOOK_USER=webhook
WEBHOOK_PASSWORD=<encrypted-password>
```

To encrypt your webhook password, run on your production server:

```bash
php bin/console reader:encrypt your-secret-password
```

Then set the output as `WEBHOOK_PASSWORD` in your `.env` file. The encryption uses `APP_SECRET`, so you must run this command in the same environment where the password will be used.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/webhook/refresh-feeds` | POST | Refreshes all subscribed feeds |
| `/webhook/cleanup-content` | POST | Deletes articles older than 30 days |

Example using curl:

```bash
curl -X POST -u "webhook:your-secret-password" https://your-reader.com/webhook/refresh-feeds
```

Example cron setup (refresh feeds every 5 minutes):

```cron
*/5 * * * * curl -s -X POST -u "webhook:your-secret-password" https://your-reader.com/webhook/refresh-feeds
```

### Viewing Messages

Processed messages are logged to a database. Use the `reader:messages` command to view recent entries:

```bash
# Show last 20 messages
php bin/console reader:messages

# Filter by message type
php bin/console reader:messages --type=HeartbeatMessage
php bin/console reader:messages --type=RefreshFeedsMessage

# Filter by status
php bin/console reader:messages --status=success
php bin/console reader:messages --status=failed

# Combine filters and set limit
php bin/console reader:messages -t HeartbeatMessage -s failed -l 50

# Tail mode (continuously display last 10 messages)
php bin/console reader:messages --tail
```

## Requirements

- PHP 8.4+
- SQLite3

## Development

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
```

### Dev Test User

The `make db-create-dev-user` command creates a test user for development:

- **Email:** dev@localhost.arpa
- **Password:** devdevdev
- **TOTP Secret:** `3DDQUI6BMJAJMWV3U5YGHSZYKVCHZIUAQCTI6ZWWEHYNI5JSLCYZ75ADRJQQC3BECC73O2GWOSWGO6MLRD56MONJXPOF23NIA47TLLQ`

Add this secret to your authenticator app (Google Authenticator, Authy, 1Password, etc.) once. It's stable across database resets.

This command only works in dev environment and will abort on test/prod.

## Deployment

See `.github/workflows/deploy.yml` for an example deployment setup.

## License

MIT
