# shoptimised/ai-visibility

The **AI Item Group Visibility Tracker** for Shoptimised, built as a self-contained
Laravel package so it can be developed and run on its own, then dropped into the
production Shoptimised app with a one-line composer change — no file moves, no
namespace churn.

Everything lives under the `Shoptimised\AiVisibility\` namespace. The package never
ships a `User` model; it talks to the host app's user via `config('ai_visibility.user_model')`
and a trait you add to that user.

---

## Status (Phases 1-4 complete)

Implemented end-to-end:

- **Phase 1** — tenancy (global scope + middleware + `runAs`), models, migrations, policies, seeds.
- **Phase 2** — batch creation + run pipeline (`BatchService` → `CreateVisibilityBatchJob` → prompt generation → fan-out of `RunVisibilityPromptJob` per prompt × platform × run → completion), `PromptGenerator`, the provider layer (`ProviderRegistry`, `ManualEvidenceProvider` + API placeholders), DTOs, `BatchController`.
- **Phase 3** — the analysis brains: `VisibilityMatcher` + `ConfidenceScorer` (the confidence ladder), `CompetitorExtractor` (citation-backed only — never hallucinated), `ScoringService` (the weighted AI Visibility Score + supporting scores), `VisibilityResultParser`, and `RecommendationEngine`. The `ParseVisibilityResultJob`, `CalculateItemGroupScoresJob` and `GenerateFeedRecommendationsJob` jobs are now filled and call these services.
- **Phase 4** — the reporting UI: six full-page Livewire components (landing, new check, batch progress, batch results, item group detail, recommendations), shared Blade components, theme tokens, and the methodology note on every report. Plus an opt-in Filament v4 admin (`AiVisibilityPlugin`) for staff oversight of batches and recommendation triage.

Pipeline ordering: each `ParseVisibilityResultJob` is added to the same run batch
as its `RunVisibilityPromptJob`, so scoring and recommendations only start once
every run is both stored and parsed. Parsing is re-runnable against stored
responses without re-billing any provider.

Deferred: rate limits, cost tracking, audit logs, deploy (**Phase 5**);
recurring monthly monitoring, screenshots, PDF export (**Phase 6**).

**Behaviour with no API keys:** every platform resolves to manual/pending evidence
mode. A batch runs cleanly to completion; parsing marks those results "not
surfaced — awaiting manual evidence" rather than fabricating data. Wire real
provider keys to populate surfaced/score data automatically.

---

## Develop it independently

You need a throwaway Laravel 13 host app to run the package against.

1. **Create a host app and point composer at this package via a path repository:**

   ```bash
   laravel new aiv-host
   cd aiv-host
   ```

   In the host app's `composer.json`:

   ```json
   "repositories": [
       { "type": "path", "url": "../packages/ai-visibility", "options": { "symlink": true } }
   ],
   ```

   ```bash
   composer require shoptimised/ai-visibility:@dev
   composer require spatie/laravel-permission
   ```

2. **Adopt the package on your host `App\Models\User`:**

   ```php
   use Spatie\Permission\Traits\HasRoles;
   use Shoptimised\AiVisibility\Concerns\InteractsWithAiVisibility;

   class User extends Authenticatable
   {
       use HasRoles, InteractsWithAiVisibility;
       // ...
   }
   ```

3. **Register the tenant context + middleware** (host `AppServiceProvider::register()`):

   ```php
   $this->app->singleton(\Shoptimised\AiVisibility\Support\TenantContext::class);
   ```

   The package already aliases the `aiv.tenant` middleware and mounts its routes
   under `config('ai_visibility.routing.prefix')`.

4. **Environment** (`.env`) — Postgres in production; sqlite is fine locally:

   ```dotenv
   DB_CONNECTION=pgsql
   QUEUE_CONNECTION=database          # or redis / sqs; Laravel Cloud managed queues in prod
   AI_VISIBILITY_DEFAULT_COUNTRY=GB
   # leave provider keys unset to run in manual/pending mode:
   # OPENAI_API_KEY=...  GEMINI_API_KEY=...  PERPLEXITY_API_KEY=...  BING_SEARCH_API_KEY=...
   ```

5. **Migrate, seed and run a worker.** The pipeline uses job batching, so you need
   the `job_batches` and `failed_jobs` tables:

   ```bash
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   php artisan queue:batches-table
   php artisan queue:failed-table
   php artisan migrate --seed   # runs package migrations + seeds "Garden Living Co"
   php artisan db:seed --class="Shoptimised\\AiVisibility\\Database\\Seeders\\DatabaseSeeder"
   php artisan queue:work --queue=ai-visibility,parsing,default
   ```

6. **Kick off a batch** (e.g. from `php artisan tinker`):

   ```php
   $feed = Shoptimised\AiVisibility\Models\Feed::first();
   $user = App\Models\User::where('email','analyst@shoptimised.example')->first();
   $ids  = $feed->products()->whereNotNull('item_group_id')->distinct()->pluck('item_group_id')->all();

   app(Shoptimised\AiVisibility\Services\BatchService::class)->create([
       'feed_id' => $feed->id,
       'platforms' => ['manual','openai'],
       'item_group_ids' => $ids,
       'runs_per_prompt' => 1,
   ], $user);
   ```

   Watch the worker process it; the batch moves Queued → Running → Completed and you
   get item-group rows, prompts, results and raw-response evidence.

   HTTP endpoints (under the configured prefix):
   `GET feeds/{feed}/item-groups`, `POST batches`, `GET batches/{batch}`,
   `POST batches/{batch}/cancel`.

---

## Reporting UI (Phase 4)

The customer module is six full-page Livewire components, mounted under the
configured route prefix:

| Route name | Path | Page |
|---|---|---|
| `aiv.landing` | `/` | Recent checks + key stats + "New visibility check" |
| `aiv.new` | `/new` | Feed filter, item-group multi-select, platforms, runs, live estimate |
| `aiv.batches.progress` | `/batches/{batch}/progress` | Live progress bar (polls counters), cancel |
| `aiv.batches.results` | `/batches/{batch}/results` | Exec cards, leaderboard, platform breakdown, competitors, recommendations |
| `aiv.groups.show` | `/groups/{itemGroup}` | Prompt-level results, competitors, recommended actions |
| `aiv.recommendations` | `/recommendations` | All actions with status controls (staff only for changes) |

The full prefix is `config('ai_visibility.routing.prefix')` (default
`reports/ai-shopping-readiness`), so the landing page is at
`/reports/ai-shopping-readiness`.

**Layout.** Pages render inside `config('ai_visibility.layout')`. It defaults to
the package's own minimal layout so it works standalone; point it at your host
layout (e.g. `components.layouts.app`) to inherit Shoptimised chrome:

```dotenv
AI_VISIBILITY_LAYOUT=components.layouts.app
```

**Theme.** Styling is self-contained (no Tailwind build needed) and driven by CSS
variables in the `<x-aiv::theme />` partial. Override `--aiv-primary` etc. with
the real Shoptimised blue — it's a find-and-replace, not a redesign. The
methodology note (`<x-aiv::methodology-note />`) renders on every report page and
the language stays "monitored AI visibility / observed position / surfaced rate",
never "ranking".

**Internal admin (opt-in, Filament v4).** Staff oversight is a Filament plugin —
the package never loads Filament unless you add it to a panel:

```php
use Shoptimised\AiVisibility\Filament\AiVisibilityPlugin;

$panel->plugin(AiVisibilityPlugin::make());
```

It registers a read-only batch resource and a recommendation-triage resource. If
you're on a different Filament major, regenerate with
`php artisan make:filament-resource` keeping the same models/columns.

## Run the tests

```bash
composer install
./vendor/bin/pest
```

Unit tests (`tests/Unit`) are pure and need no app. Feature tests boot Testbench
with an in-memory SQLite database (`tests/TestCase.php`) and cover tenant isolation
and batch creation.

---

## Migrate to production Shoptimised

When you're happy with the results, in the **real** Shoptimised app:

1. Add the same path (or a VCS) repository for `shoptimised/ai-visibility` to its
   `composer.json` and `composer require shoptimised/ai-visibility`.
2. Add `HasRoles` + `InteractsWithAiVisibility` to the real `App\Models\User`.
3. Bind `TenantContext` as a singleton (as above).
4. `php artisan migrate` (package migrations run automatically) and seed roles.

No namespaces change between dev and prod, so nothing else moves. Optionally
`php artisan vendor:publish --tag=ai-visibility-config` / `--tag=ai-visibility-migrations`
if you want local copies to customise.
