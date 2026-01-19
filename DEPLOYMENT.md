# Deployment

See `.github/workflows/deploy.yml` for an example deployment setup.

## Background Worker

Reader uses a background worker to refresh feeds and clean up old content. Built on Symfony Messenger with the Scheduler component.

### Scheduled Tasks

| Task | Description |
|------|-------------|
| Heartbeat | Logs a heartbeat entry to indicate the worker is alive |
| Refresh Feeds | Fetches new articles from all subscribed feeds |
| Cleanup Content | Trims articles to 50 per subscription |

### Running the Worker

```bash
php bin/console messenger:consume scheduler_default
```

For production, use `--time-limit` to ensure periodic restarts:

```cron
0 * * * * cd /path/to/reader && php bin/console messenger:consume scheduler_default --time-limit=3540 >> var/log/worker.log 2>&1
```

This runs the worker hourly with a 59-minute time limit.

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `WORKER_REFRESH_INTERVAL` | 5 minutes | How often feeds are refreshed |
| `WORKER_CLEANUP_INTERVAL` | 1 day | How often old content is cleaned up |

## Webhooks

As an alternative to the background worker, you can trigger tasks via HTTP webhooks.

### Configuration

Webhooks use Basic Auth. Set credentials in your `.env`:

```env
WEBHOOK_USER=webhook
WEBHOOK_PASSWORD=<encrypted-password>
```

To encrypt your webhook password:

```bash
php bin/console reader:encrypt your-secret-password
```

Use the output as `WEBHOOK_PASSWORD`. The encryption uses `APP_SECRET`, so run this command in the same environment where it will be used.

### Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/webhook/refresh-feeds` | GET | Refreshes all subscribed feeds |
| `/webhook/cleanup-content` | GET | Trims articles to 50 per subscription |

### Example

```bash
curl -u "webhook:your-secret-password" https://your-reader.com/webhook/refresh-feeds
```

Cron setup (refresh feeds every 5 minutes):

```cron
*/5 * * * * curl -s -u "webhook:your-secret-password" https://your-reader.com/webhook/refresh-feeds
```

## Error Email Notifications

Reader can send error notifications via email in production.

### Configuration

```env
MAILER_DSN=smtp://user:password@smtp.example.com:587
ERROR_LOG_SENDER=noreply@example.com
ERROR_LOG_RECIPIENT=admin@example.com
```

By default, `MAILER_DSN=null://null` which discards all emails.
