# AI Item Group Visibility Tracker — Build Guide

**Platform:** Shoptimised
**Stack:** Laravel 13 (current stable) on Laravel Cloud
**Document purpose:** the architectural plan and decisions a Laravel developer follows before writing code. Full implementations come in the phase-by-phase responses (Phase 1 = migrations/models/policies/seeds, Phase 2 = prompt generation + provider layer + jobs, etc.).

---

## 0. Decisions made for you (and why)

Your prompt left two forks open and asked for a recommendation. Here they are, locked, because they shape everything downstream.

**Database: PostgreSQL (Laravel Cloud serverless Postgres, Neon-powered).**
The schema is JSON-heavy — `custom_labels`, `platforms`, `selected_filters`, `competitors_surfaced`, every `*_gaps` column, `recommended_actions`. Postgres `jsonb` with GIN indexes lets you filter and query inside those structures cheaply; MySQL's JSON support is workable but weaker for this. Laravel Cloud's Postgres is serverless and autoscaling, which suits bursty AI-batch workloads that sit idle then spike. Use `jsonb` columns throughout (Laravel's `$casts = ['col' => 'array']` works transparently).

**Frontend: Livewire 3 for the customer reporting module + Filament 4 for internal Shoptimised tooling.**
- The reporting UI is server-driven, data-table and filter heavy, with live batch progress. Livewire gives you reactive filters and `wire:poll` / Laravel Echo progress without building and versioning a separate SPA + API. One codebase, one auth, one set of policies.
- Filament (which is Livewire under the hood) handles the internal admin/analyst surfaces — batch oversight, recommendation triage, retailer management — for near-zero build cost.
- Inertia + Vue/React would be the call only if you already have a Vue/React design system you must reuse. You don't mention one, so don't take on the SPA tax.

Everything below assumes Postgres + Livewire/Filament.

---

## 1. Product scope

A premium reporting add-on inside Shoptimised that lets a retailer filter their feed, pick item group titles, and run **monitored AI visibility checks** across multiple AI/search platforms via controlled prompt testing. It reports where the retailer's product families surface, how they compare to competitors, and which feed optimisation actions to take next.

**In scope (MVP):** filtering, item-group selection, batch creation, prompt generation, multi-provider prompt running via queues, response parsing/matching, scoring, competitor extraction, feed-action recommendations, and the six reporting pages.

**Out of scope (MVP):** PDF export, recurring scheduled monitoring, screenshot capture, trend reporting, Google Ads/Merchant Center live overlays — all Phase 6.

**Positioning guardrail (enforced in copy and labels):** "monitored AI visibility based on controlled prompt testing", never "LLM rankings". The methodology note appears on every report.

---

## 2. User stories

- *As a retailer admin* I filter my feed and select item group titles so I can test only the families I care about.
- *As a retailer admin* I run an AI Visibility Check and see live progress without my page hanging.
- *As a retailer viewer* I read completed reports but cannot start batches or change recommendations.
- *As a Shoptimised analyst* I create and manage batches for the retailers assigned to me, and I approve/complete feed-action recommendations.
- *As a Shoptimised admin* I see all retailers, manage everything, and configure provider keys and limits.
- *As any report reader* I see the methodology note so I never mistake the result for a fixed organic ranking.
- *As a retailer admin* I drill into an item group to see prompt-level evidence and the specific feed actions recommended.

---

## 3. System architecture

```
                 ┌────────────────────────────────────────────┐
  Browser ─────► │ Laravel Cloud App Cluster (web, fast only)   │
                 │  Livewire reporting + Filament admin         │
                 │  Controllers create batch → dispatch job     │
                 └───────────────┬──────────────────────────────┘
                                 │ dispatch (never call LLM in request)
                                 ▼
                 ┌────────────────────────────────────────────┐
                 │ Laravel Cloud Managed Queues (autoscaling)   │
                 │  queues: default | ai-visibility | parsing   │
                 │  CreateBatch → GeneratePrompts → DispatchRuns │
                 │  → RunPrompt (provider call) → ParseResult    │
                 │  → CalculateScores → GenerateRecs → Complete  │
                 └───────┬───────────────────────┬──────────────┘
                         │                        │
                         ▼                        ▼
        ┌────────────────────────┐   ┌──────────────────────────┐
        │ Provider layer (swap)  │   │ Postgres (Neon) + Valkey  │
        │ OpenAI / Gemini /      │   │ cache + rate-limit store  │
        │ Perplexity / Bing /    │   └──────────────────────────┘
        │ ManualEvidence         │            │
        └────────────────────────┘            ▼
                                   ┌──────────────────────────┐
                                   │ Object storage (S3-compat)│
                                   │ raw responses, screenshots │
                                   └──────────────────────────┘
```

**Golden rule:** the web request only validates input, creates the batch row, and dispatches `CreateVisibilityBatchJob`. Every provider call, parse, and score happens on the queue. The browser polls/subscribes for progress.

---

## 4. Recommended Laravel stack

| Concern | Choice |
|---|---|
| Framework | Laravel 13 |
| DB | PostgreSQL (Neon serverless on Laravel Cloud), `jsonb` columns |
| Cache / rate-limit / locks | Valkey (managed) |
| Queue | Laravel Cloud **managed queues**, named queues |
| Customer UI | Livewire 3 (+ Flux or your existing Blade components) |
| Internal admin | Filament 4 |
| Auth/permissions | Laravel policies + `spatie/laravel-permission` for roles |
| Object storage | S3-compatible bucket (Laravel Cloud storage resource) |
| HTTP to providers | `Http::` client with per-provider retry/timeout/pool |
| Audit logging | `owen-it/laravel-auditing` or a custom `audits` table |
| Error monitoring | Sentry (`SENTRY_DSN`) |
| Tests | Pest |
| Tenancy | Single-DB row-level scoping (`retailer_id`) + global scope + policies |

**Tenancy note:** do *not* reach for separate databases per retailer. A `retailer_id` on every tenant-owned table, a `BelongsToTenant` trait applying a global scope, and a middleware that resolves the current retailer is sufficient, simpler, and plays nicely with Laravel Cloud's managed DB.

---

## 5. Database schema (migration plan)

Order matters (FKs). Create in this sequence. All tenant-owned tables get `retailer_id` + index. JSON columns are `jsonb`.

1. `tenants` (retailers) — `name, domain, status`
2. `users` (extend default) — add `retailer_id` nullable (null = Shoptimised staff), role via permission package
3. `feeds` — `retailer_id, merchant_center_id, name, country, currency, source_url, last_imported_at`
4. `products` — feed products; index `(retailer_id, feed_id, item_group_id)` and `(retailer_id, item_group_title)`
5. `product_performance_daily` — index `(retailer_id, feed_id, product_id_external, date)`; consider a partial/covering index for performance-segment filters
6. `product_conversational_attributes` — `attribute_type` enum-as-string, `attribute_value jsonb`, `live_in_feed bool`
7. `ai_visibility_batches` — `status, platforms jsonb, selected_filters jsonb`, counters, timestamps
8. `ai_visibility_item_groups` — per-batch selected groups + computed scores
9. `ai_visibility_prompts` — generated prompts, `prompt_type`, `platform`, `status`, `run_count`
10. `ai_visibility_results` — one row per run; `raw_response jsonb`, `match_type`, confidence, positions, `*_gaps jsonb`
11. `ai_visibility_competitors` — normalised competitor mentions per result
12. `ai_visibility_evidence` — `evidence_type, storage_path, external_url, metadata jsonb`
13. `feed_action_recommendations` — `action_type, priority, status, assigned_to_user_id`
14. `audits` (if not using the package's own table)

**Index priorities** (these are the queries that will hurt without them):
- Filtering item groups in the "New Check" page → `products (retailer_id, feed_id, item_group_title)` + GIN on `custom_labels`.
- Performance segments (zero-click, no-conversion, ROAS range) → composite index on `product_performance_daily` covering `clicks, conversions, roas`.
- Results aggregation per item group → `ai_visibility_results (batch_id, item_group_visibility_id)`.
- Competitor rollups → `ai_visibility_competitors (retailer_id, competitor_domain)`.

> Full migration code (every column, type, FK, index) is delivered in the Phase 1 response. The list above is the build order and the indexing intent.

---

## 6. Models and relationships

```
Retailer (tenants)
 ├─ hasMany Feed
 ├─ hasMany Product
 ├─ hasMany ProductPerformanceDaily
 ├─ hasMany AiVisibilityBatch
 ├─ hasMany FeedActionRecommendation
 └─ hasMany User

Feed
 ├─ belongsTo Retailer
 ├─ hasMany Product
 └─ hasMany AiVisibilityBatch

Product
 ├─ belongsTo Retailer
 ├─ belongsTo Feed
 ├─ hasMany ProductConversationalAttribute
 └─ hasMany ProductPerformanceDaily (via product_id_external)

AiVisibilityBatch
 ├─ belongsTo Retailer, Feed, User(createdBy)
 ├─ hasMany AiVisibilityItemGroup
 ├─ hasMany AiVisibilityPrompt
 ├─ hasMany AiVisibilityResult
 └─ hasMany FeedActionRecommendation

AiVisibilityItemGroup
 ├─ belongsTo AiVisibilityBatch, Retailer, Feed
 ├─ belongsTo Product (representative)
 ├─ hasMany AiVisibilityPrompt
 └─ hasMany AiVisibilityResult

AiVisibilityPrompt
 ├─ belongsTo AiVisibilityBatch, AiVisibilityItemGroup
 └─ hasMany AiVisibilityResult

AiVisibilityResult
 ├─ belongsTo AiVisibilityBatch, AiVisibilityPrompt, Retailer
 ├─ hasMany AiVisibilityCompetitor
 └─ hasMany AiVisibilityEvidence

FeedActionRecommendation
 └─ belongsTo Retailer, Feed, AiVisibilityBatch, AiVisibilityItemGroup, Product(nullable), User(assignedTo, nullable)
```

Add a `BelongsToTenant` trait (global scope on `retailer_id`) used by every tenant-owned model, plus casts for all `jsonb`/datetime columns and string-backed enums (`status`, `match_type`, `action_type`, etc.) as PHP enums.

---

## 7. API / service integrations

| Provider | Mode | Notes |
|---|---|---|
| OpenAI | browsing/web-search capable responses | citations available; no screenshots |
| Gemini | Search-grounding | citations via grounding metadata |
| Perplexity (Sonar) | search API | strong citation surface |
| Bing/Copilot | search API *if accessible* | otherwise downgrade to ManualEvidence |
| Manual | semi-automated evidence | analyst pastes/uploads response + screenshot |

The integration is abstracted behind `AiVisibilityProviderInterface` (Section 0 of Phase 2). Business logic never names a concrete provider — it resolves them through a `ProviderRegistry` keyed by the `platforms` array on the batch. Any provider without live API access is registered as `ManualEvidenceProvider` and flagged `manual/semi-automated evidence mode` in the UI.

---

## 8. Queue / job workflow

Three named queues so slow LLM calls never starve fast bookkeeping:

- `default` — batch lifecycle, scoring, recommendations
- `ai-visibility` — the actual provider calls (`RunVisibilityPromptJob`), heavily rate-limited
- `parsing` — `ParseVisibilityResultJob`

Pipeline:

```
CreateVisibilityBatchJob (default)
  └─ for each selected item group: GeneratePromptsForItemGroupJob (default)
        └─ DispatchPromptRunsJob (default)  // fans out runs = prompts × platforms × runs
              └─ RunVisibilityPromptJob (ai-visibility)  // ONE provider call each
                    └─ ParseVisibilityResultJob (parsing)
CalculateItemGroupScoresJob (default)   // after all results for a group land
GenerateFeedRecommendationsJob (default)
CompleteVisibilityBatchJob (default)    // flips status, fires notification
CancelVisibilityBatchJob (default)
MonthlyScheduledVisibilityBatchJob (scheduler) // Phase 6
```

Use **Job Batching** (`Bus::batch()`) so the run jobs report progress and `then()`/`finally()` trigger scoring + completion automatically. Update `completed_prompts`/`failed_prompts` counters via batch callbacks — that's what the progress bar polls.

Reliability per job: `$tries`, `backoff()` (exponential), `WithoutOverlapping`, `RateLimited` middleware per provider, `$timeout`, and `failed()` to record the failure on the result row and increment `failed_prompts` without killing the batch. Raw responses are stored *before* parsing, so a parser bug never loses data.

---

## 9. Prompt generation workflow

`PromptGenerator` service. For each item group it builds up to 10 prompts spanning the 8 `prompt_type`s, hydrating templates from: `item_group_title`, `brand`, `category`, `product_type`, variant options, price range, descriptions, existing Q&A, performance signals, and known attribute gaps.

```php
$prompts = app(PromptGenerator::class)->generate($itemGroup, [
    'country'  => 'GB',
    'language' => 'en',
    'limit'    => 10,
]);
// returns array of ['prompt_text','prompt_type'] ready to persist
```

Prompts are **persisted and reused** across months so trend lines compare like-for-like. Variant- and attribute-led prompts are skipped when the source data is missing rather than fabricated.

---

## 10. LLM visibility testing workflow

Each persisted prompt is run `run_count` times per selected platform (MVP default: 3 runs × 3 platforms). `RunVisibilityPromptJob` resolves the provider from the registry, calls `runPrompt($text, $context)`, stores the `AiProviderResponse` raw, then dispatches parsing. Runs are independent and isolated — one provider 500 fails its run only.

Defaults (env-overridable): 10 prompts/group, 3 platforms, 3 runs → 90 runs per item group. Guardrails: `AI_VISIBILITY_MAX_ITEM_GROUPS_PER_BATCH`, `…_MAX_PROMPTS_PER_BATCH`, `…_MAX_RUNS_PER_PROMPT`, enforced at batch creation with a clear cost/run estimate shown to the user before they hit Start.

---

## 11. Result parsing and matching logic

`VisibilityResultParser` normalises the raw response, then delegates to `VisibilityMatcher` (retailer/URL/item-group/semantic) and `CompetitorExtractor` (competitor domains, names, positions), producing a `ParsedVisibilityResult` DTO. Confidence map (highest wins):

```php
final class ConfidenceScorer
{
    public function score(MatchSignals $s): int
    {
        return match (true) {
            $s->exactItemGroupTitle && $s->retailerUrlCited => 100,
            $s->productUrlCited                              => 95,
            $s->exactItemGroupTitle                          => 85,
            $s->retailerDomainOnly                           => 70,
            $s->semanticProductFamily   => $this->lerp($s->semanticSimilarity, 50, 75),
            $s->categoryMentionOnly                          => 30,
            default                                          => 0,
        };
    }

    // map a 0..1 similarity into the 50..75 band
    private function lerp(float $t, int $lo, int $hi): int
    {
        return (int) round($lo + max(0, min(1, $t)) * ($hi - $lo));
    }
}
```

`match_type` is set from the same signals (`exact_item_group_and_url`, `product_url`, `retailer_domain`, `exact_item_group`, `semantic_product_family`, `category_only`, `none`). Mention position = order of first retailer reference in prose; citation position = order in the sources list when the provider `supportsCitations()`.

---

## 12. Scoring methodology

**AI Visibility Score (per item group, 0–100):**

```
visibility_score =
      0.35 * surfaced_rate_pct           // surfaced runs / total runs
    + 0.20 * position_component          // 100 - normalised(avg_observed_position)
    + 0.15 * citation_presence_pct       // runs where retailer was cited
    + 0.15 * cross_platform_consistency  // share of platforms where surfaced ≥1
    + 0.10 * competitor_gap_component    // 100 - normalised(competitor_count vs retailer)
    + 0.05 * avg_match_confidence        // mean confidence of surfaced runs
```

Where `position_component` maps position 1 → ~100 and decays (e.g. `100 / position`, capped). Computed in `CalculateItemGroupScoresJob` once all results for the group exist; written to `ai_visibility_item_groups.ai_visibility_score`, `surfaced_rate`, `average_position`.

**Supporting scores:**
- *Platform Visibility Score* = per-platform surfaced_rate weighted by citation presence — drives "best/weakest platform".
- *Competitor Pressure Score* = competitor surfaced rate − retailer surfaced rate, normalised; high = under threat.
- *Prompt Theme Gap Score* = share of `prompt_type`s where retailer never surfaced — directly seeds recommendations.

Keep all formulas in a single `ScoringService` so weights live in one place (and can later become tenant-configurable).

---

## 13. Dashboard / reporting UI

Built as Livewire components. Six pages (Section detail in your spec):

1. **AI Visibility Landing** — recent batches, key stats, "New Visibility Check" CTA.
2. **New Visibility Check** — reusable filter bar, item-group title table with multi-select, platform + run settings, live run/cost estimate, Start.
3. **Batch Progress** — status, progress bar (polls batch counters), current platform, cancel.
4. **Batch Results** — executive cards, item-group leaderboard, platform breakdown, competitor surface map, theme gaps, recommendations.
5. **Item Group Detail** — score, surfaced rate, avg position, prompt-level results, competitors, raw evidence, recommended actions, performance overlay.
6. **Recommendations** — all actions with priority/status/owner/evidence and accept/in-progress/complete/reject controls (staff only for state changes).

**Dashboard cards, leaderboard columns, prompt-results columns, recommendations columns, and filter set** — all exactly as enumerated in your spec; build them as shared Blade/Livewire components (`<x-card.metric>`, `<x-table.leaderboard>`, `<x-filters.feed-bar>`) so they're reused across pages.

**Theme tokens** (drop your exact values in later):
```css
:root {
  --color-primary: #1d4ed8;       /* Shoptimised blue (placeholder) */
  --color-primary-dark: #0f172a;  /* navy */
  --color-accent: #60a5fa;        /* lighter blue */
  --color-bg: #f8fafc;            /* white / light grey */
  --color-text: #0f172a;          /* dark navy */
  --font-sans: 'InterVariable', system-ui, sans-serif;
}
```

**Methodology note** is a single `<x-methodology-note>` component rendered on every report page, with the approved wording and the approved/avoided label vocabulary baked in.

---

## 14. Routes / controllers

Thin controllers; logic in services and Livewire components.

```php
Route::middleware(['auth','tenant'])->prefix('reports/ai-shopping-readiness')->group(function () {
    Route::get('item-group-visibility', LandingPage::class)->name('aiv.landing');
    Route::get('item-group-visibility/new', NewCheckPage::class)->name('aiv.new');
    Route::post('item-group-visibility/batches', [BatchController::class, 'store'])
        ->name('aiv.batches.store'); // validates, creates batch, dispatches CreateVisibilityBatchJob, returns redirect to progress
    Route::get('item-group-visibility/batches/{batch}', BatchProgressPage::class)->name('aiv.batches.progress');
    Route::get('item-group-visibility/batches/{batch}/results', BatchResultsPage::class)->name('aiv.batches.results');
    Route::get('item-group-visibility/groups/{itemGroup}', ItemGroupDetailPage::class)->name('aiv.groups.show');
    Route::get('item-group-visibility/recommendations', RecommendationsPage::class)->name('aiv.recommendations');
});
```

`BatchController@store` does only: authorize → validate filters/selection against limits → `BatchService::create()` → `CreateVisibilityBatchJob::dispatch()` → redirect to progress. No provider calls here, ever.

---

## 15. Policies / permissions

Roles via `spatie/laravel-permission`: `shoptimised_admin`, `shoptimised_analyst`, `retailer_admin`, `retailer_viewer`.

| Ability | s.admin | s.analyst | r.admin | r.viewer |
|---|---|---|---|---|
| View any retailer | ✓ | assigned only | own only | own only |
| Create/manage batch | ✓ | assigned | own | – |
| View reports | ✓ | assigned | own | own |
| Approve/complete recommendations | ✓ | ✓ | – | – |

Enforce with: `tenant` middleware (sets current retailer, blocks cross-tenant access), `BelongsToTenant` global scope (defence in depth), and `AiVisibilityBatchPolicy` / `FeedActionRecommendationPolicy`. Analyst→retailer assignment is a pivot (`retailer_user` with a `role` column or a dedicated `analyst_assignments` table).

---

## 16. Laravel Cloud deployment setup

- **App cluster:** connect the Git repo; zero-config deploy. Keep it sized for fast web requests only.
- **Database:** provision managed **Postgres** (serverless); Laravel Cloud injects `DB_*`. Same region as compute.
- **Cache:** provision **Valkey**; used for cache, rate limiting, locks, Livewire/session if desired.
- **Queues:** use **managed queues** (the recommended default) — autoscaling on queue depth, scale-to-zero, built-in failed-job dashboard with retry. Create/assign the named queues (`default`, `ai-visibility`, `parsing`). The slow `ai-visibility` queue scales independently of web traffic.
- **Scheduler:** enable Laravel Cloud's scheduler for `MonthlyScheduledVisibilityBatchJob` (Phase 6).
- **Object storage:** attach an S3-compatible bucket for raw responses and screenshots; `OBJECT_STORAGE_*` injected.
- **Environments:** separate `staging` and `production` apps, each with own DB/cache/queue resources and env vars.
- **Sizing:** start small on the app cluster; let managed queues autoscale the AI workload. Monitor the queue dashboard and provider cost before scaling up.
- **Observability:** Laravel Cloud logs + metrics; add Sentry for exceptions.

---

## 17. Environment variables

```dotenv
APP_ENV=production
APP_URL=https://app.shoptimised.example
# DB_* injected by Laravel Cloud (Postgres)
QUEUE_CONNECTION=          # set per managed-queue guidance
CACHE_STORE=valkey

OPENAI_API_KEY=
GEMINI_API_KEY=
PERPLEXITY_API_KEY=
BING_SEARCH_API_KEY=

AI_VISIBILITY_DEFAULT_COUNTRY=GB
AI_VISIBILITY_DEFAULT_LANGUAGE=en
AI_VISIBILITY_MAX_ITEM_GROUPS_PER_BATCH=25
AI_VISIBILITY_MAX_PROMPTS_PER_BATCH=250
AI_VISIBILITY_MAX_RUNS_PER_PROMPT=3
AI_VISIBILITY_MONTHLY_BATCH_ENABLED=false

# OBJECT_STORAGE_* injected by Laravel Cloud storage resource
SENTRY_DSN=
```

Keep provider keys and limits in `config/ai_visibility.php` (read from env) so the registry and limit checks reference config, never `env()` directly outside config files.

---

## 18. Testing plan (Pest)

- **Unit:** `ConfidenceScorer`, `ScoringService` (each weight + edge cases: 0 runs, all surfaced, all failed), `VisibilityMatcher` (each `match_type`), `CompetitorExtractor`, `PromptGenerator` (skips missing-data prompt types).
- **Feature:** batch creation authorisation per role; limit enforcement; tenant isolation (retailer A cannot load retailer B's batch); recommendation state-change permissions.
- **Job:** pipeline with a **fake provider** returning canned responses → assert results, scores, recommendations land; assert a failing run increments `failed_prompts` without failing the batch.
- **Contract:** every provider satisfies `AiVisibilityProviderInterface`; `ManualEvidenceProvider` returns a `pending_manual` response.
- Use `Http::fake()` for real providers, `Bus::fake()`/`Queue::fake()` for dispatch assertions, and factories for all entities.

---

## 19. MVP build order with acceptance criteria

| Phase | Deliverable | Acceptance criteria |
|---|---|---|
| 1 | Migrations, models, relationships, policies, seeds, `BelongsToTenant`, tenant middleware | `migrate:fresh --seed` works; tenant isolation test passes; a seeded retailer has feeds/products/performance/attributes |
| 2 | `PromptGenerator`, `AiVisibilityProviderInterface` + DTOs, `ManualEvidenceProvider`, all jobs, `BatchController@store` | Creating a batch dispatches the pipeline; prompts persist; runs store raw responses; no LLM call happens in the request |
| 3 | Parser, matcher, competitor extractor, confidence + visibility scores, recommendation engine | Given canned responses, scores and `match_type` are correct; recommendations map per the gap rules |
| 4 | The six Livewire pages + shared components + theme tokens + methodology note | Full journey works end-to-end on dummy/manual provider; progress bar updates; methodology note present on every report |
| 5 | Rate limits, retries, cost tracking, audit logs, Sentry, Laravel Cloud deploy, monthly schedule | Provider failures isolated; audit trail on batch + recommendation changes; deployed to staging then production |
| 6 | Recurring monitoring, screenshots, PDF export, competitor trends, Ads/Merchant overlays, client approval workflow | Each as a discrete, separately-acceptance-tested feature |

---

## 20. Future roadmap

Recurring monthly monitoring with trend lines (reusing persisted prompts), screenshot/evidence capture to object storage, branded PDF export, competitor trend reporting, Google Ads performance overlay on item-group detail, Merchant Center attribute-gap overlay, and a client-facing approval workflow for feed changes (recommendation → accepted → applied to feed).

---

## Suggested folder structure

```
app/
├─ Models/                      # Retailer, Feed, Product, AiVisibility* , FeedActionRecommendation
├─ Models/Concerns/BelongsToTenant.php
├─ Enums/                       # BatchStatus, MatchType, PromptType, ActionType, RecommendationStatus
├─ Policies/
├─ Http/
│  ├─ Controllers/AiVisibility/BatchController.php
│  ├─ Middleware/SetTenant.php
│  └─ Livewire/AiVisibility/   # LandingPage, NewCheckPage, BatchProgressPage, ...
├─ Jobs/AiVisibility/          # the 10 jobs
├─ Services/AiVisibility/
│  ├─ BatchService.php
│  ├─ PromptGenerator.php
│  ├─ VisibilityResultParser.php
│  ├─ VisibilityMatcher.php
│  ├─ CompetitorExtractor.php
│  ├─ ConfidenceScorer.php
│  ├─ ScoringService.php
│  └─ RecommendationEngine.php
├─ Providers/AiVisibility/
│  ├─ Contracts/AiVisibilityProviderInterface.php
│  ├─ ProviderRegistry.php
│  ├─ OpenAiSearchProvider.php
│  ├─ GeminiGroundedSearchProvider.php
│  ├─ PerplexitySearchProvider.php
│  ├─ BingSearchProvider.php
│  └─ ManualEvidenceProvider.php
└─ DataObjects/AiVisibility/   # AiProviderResponse, ParsedVisibilityResult, CompetitorMention, Citation, RecommendedAction
config/ai_visibility.php
database/migrations/ , database/factories/ , database/seeders/
resources/views/livewire/ai-visibility/ , resources/css/theme.css
tests/Unit/ , tests/Feature/
```

---

## Risks and safeguards

- **Provider drift / no fixed rankings** → enforced "monitored visibility" language and methodology note everywhere; multiple runs per prompt to surface variance, not hide it.
- **Cost blow-out** → batch-creation limit checks, per-provider cost tracking, the run/cost estimate shown before Start, and managed-queue scale-to-zero so idle costs nothing.
- **Provider outage** → isolated runs, retries with backoff, `ManualEvidenceProvider` fallback, raw responses stored before parsing.
- **Cross-tenant leakage** → global scope + middleware + policies (three layers) and an explicit isolation test.
- **Rate limits** → `RateLimited` middleware per provider keyed in Valkey.
- **Parser fragility** → store raw first; parsing is re-runnable against stored responses without re-billing providers.
- **Hallucinated competitors** → competitor extraction validated against real domains/URLs; unverifiable mentions flagged low-confidence, not asserted as fact.
