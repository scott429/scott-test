# Deploying the Shoptimised AI Visibility app

A runbook for taking this app live (written for Laravel Cloud; the steps map to
Forge/any host). Reference env: [`.env.production.example`](.env.production.example).

## 1. Provision resources
- **App** — connect the `scott429/scott-test` Git repo. Size for fast web requests; let the queue autoscale the AI work.
- **PostgreSQL** (serverless) — the schema uses `jsonb` + a GIN index guarded to pgsql.
- **Valkey** — cache, sessions, locks, and the per-provider rate limiters.
- **Managed queues** — create three named queues: `default`, `ai-visibility`, `parsing`.
- **Object storage** (S3-compatible) — raw responses / future screenshots.

## 2. Environment
Set the variables from `.env.production.example` in the platform's env settings.
- Generate `APP_KEY` once (`php artisan key:generate --show`) and keep it stable.
- `DB_*`, Valkey, queue and storage credentials are injected when you attach each resource.
- Set the provider keys you use (`GEMINI_API_KEY`, `PERPLEXITY_API_KEY`, `OPENAI_API_KEY`). Each one enables that platform; none set = manual/pending mode.
- Keep `APP_DEBUG=false`.

## 3. Build & deploy commands
```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force          # package + spatie-permission migrations
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Seeding (do NOT run the full seeder in production)
The default `db:seed` creates demo data ("Garden Living Co" + a test user). In
production seed **only** the roles/permissions:
```bash
php artisan db:seed --force --class="Shoptimised\AiVisibility\Database\Seeders\RolesAndPermissionsSeeder"
```
Then create real users and assign roles (`shoptimised_admin`, `shoptimised_analyst`, `retailer_admin`, `retailer_viewer`).

## 4. Queue worker (required)
Checks only run while a worker consumes the queues. On Laravel Cloud add a worker process:
```bash
php artisan queue:work --queue=ai-visibility,parsing,default --tries=3 --timeout=120
```
Without this, batches sit at "queued" (the progress page shows a hint).

## 5. First-run checklist
1. Log in, open **Feeds**, import a product feed by URL (with the `Question_And_Answer` field for Q&A).
2. Open the feed's **Reliability** report — confirm completeness / grouping / Q&A coverage look right.
3. Start a small check (1 item group, runs = 1), confirm the worker processes it and results land.
4. Review **AI shopping readiness** and **Q&A insights**.

## 6. Notes
- **Rate limits:** provider free tiers are small (per-minute *and* daily). The app throttles per-minute and retries transient 429/5xx with backoff; tune `AI_VISIBILITY_*_RPM` on paid tiers.
- **Cost:** per-run cost is recorded; watch "Estimated spend" on results and start conservative.
- **Scheduler / monthly monitoring:** `MonthlyScheduledVisibilityBatchJob` is a Phase-6 stub (no-op) — no scheduler entry needed yet. When Phase 6 lands, enable `AI_VISIBILITY_MONTHLY_BATCH_ENABLED=true` and add it to the platform scheduler.
- **Sentry (optional):** install `sentry/sentry-laravel` and set `SENTRY_LARAVEL_DSN`.
- **Staging:** run a separate staging app with its own DB/cache/queue before promoting to production.
