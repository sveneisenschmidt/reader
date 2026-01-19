# Reader

A fast, minimal RSS reader for a single user.

![Feed View](docs/screenshots/feed.png)

## Features

- Three-column layout: feeds, articles, reading pane
- Track read/unread articles
- Bookmark articles
- Green dot for new articles
- Pull down to refresh
- Light, dark, and auto theme
- Keyboard navigation
- OPML import/export

## Getting Started

First-time setup walks you through account creation, two-factor authentication, and adding your first feed.

Every login requires email, password, and a 6-digit code from your authenticator app.

## Using Reader

**Reading:** Click any article to read it. Use "Mark as read" or "Read original" to mark articles.

**Refreshing:** Pull down to refresh feeds. Click "Refreshed..." in the footer to open the Status page. For automatic refresh, configure the background worker.

**Subscriptions:** Click "Subscriptions" in the sidebar to add, edit, or remove feeds. Reader auto-detects feeds from YouTube channels and Reddit subreddits.

**Settings:** Click "Settings" in the sidebar. Theme, pull to refresh, auto mark as read, keyboard shortcuts, bookmarks, filter words.

**Status:** Click "Refreshed..." in the footer or "View detailed status" in Settings. Shows worker/webhook status, subscription stats, and processed messages.

### Keyboard Shortcuts

When enabled in Settings:

**Article list:** Tab/Shift+Tab to navigate subscriptions, Enter to open first article.

**Reading:** Arrow Up/Down for previous/next, Space to toggle read, Enter to open original, Escape to close.

## Screenshots

![Login](docs/screenshots/login.png)
![Setup](docs/screenshots/setup.png)
![Onboarding](docs/screenshots/onboarding.png)
![Subscriptions](docs/screenshots/subscriptions.png)
![Preferences](docs/screenshots/preferences.png)
![Mobile](docs/screenshots/feed-mobile-list.png)

## Requirements

- PHP 8.4+
- SQLite3

## Documentation

- [Development](DEVELOPMENT.md) - Local setup, test user
- [Deployment](DEPLOYMENT.md) - Worker, webhooks, error emails

## License

MIT
