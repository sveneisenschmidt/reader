# Reader

A fast, minimal RSS reader for a single user.

![Screenshot](docs/screenshot.png)

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
- Green dot shows new articles you haven't seen yet
- Pull down to refresh on any device
- Organize feeds into folders
- Automatic dark mode

## Using Reader

### Reading Articles

Click any article in the list to read it. The content appears in the reading pane on the right.

**Marking articles as read:**
- Click "Mark as read" on an article
- Click "Mark all as read" in the sidebar
- Click "Read original" to open the source and mark as read

The green dot next to new articles disappears once you've viewed them.

### Refreshing Feeds

Two ways to check for new articles:
- Pull down from the top of the page
- Click the "Refreshed..." text in the footer

Feeds also refresh automatically in the background.

### Managing Your Feeds

Click **Subscriptions** in the header to add, edit, or remove feeds.

Feeds are configured in YAML format:

```yaml
- url: https://example.com/feed.xml
  title: Example Feed

- url: https://news.site/rss
  title: Daily News
  folder: ["News"]

- url: https://techblog.com/feed
  title: Tech Blog
  folder: ["News", "Technology"]
```

**Adding a feed:** Add a new entry with `url` and `title`.

**Organizing into folders:** Add a `folder` property with a list of folder names. Nested folders appear as "News / Technology" in the sidebar.

**Removing a feed:** Delete its entry from the list.

Click **Save** to apply your changes.

### Filtering Articles

Use the filters in the sidebar:

- **Show unread** - Hide articles you've already read
- **25 / 50 / 99+** - Limit how many articles are shown

### Keyboard Shortcuts

Navigate efficiently with your keyboard:
- Pull-to-refresh works on desktop too (scroll up past the top)

## Security

Reader is designed for single-user, self-hosted use:

- All pages require authentication
- Passwords are securely hashed
- Two-factor authentication (TOTP) is mandatory
- Sessions expire automatically

## License

MIT
