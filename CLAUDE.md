# Flex demo app: build brief for Claude Code

You are building a small Laravel demo app deployed on **Upsun Flex (Upsun Cloud)**.
It supports a 45-minute customer workshop with two short proof moments (about 5 minutes
of demo in total). The meeting happens at an event with weak connectivity, so the app
must be **boring, robust and pre-staged**: no live uploads, no live deploys, no build
steps that can fail on the day.

Read this whole file before writing code. Then read, in this order:
- `.upsun/config.yaml` and `.environment` (provided, adjust only if something doesn't work)
- `scripts/scale-for-import.sh` and `ci/gitlab-ci-import-schedule.yml` (provided)
- `docs/DEMO_SCRIPT.md` (what the presenter will show; the app exists to serve it)
- `docs/PRESTAGING_CHECKLIST.md` (what must be true the day before)

## Context: what the demo must prove

The customer runs two Laravel apps on Upsun Fixed and is considering Flex.

1. **BUI Platform (storage story).** ~450 GB of files on network storage, billed per
   environment on Fixed. On Flex, object storage is cheaper per GB. The customer fears
   the bucket "could be public". Proof: files are stored in the app's object storage
   bucket, reached through an **internal-only** endpoint, and served **through a
   Laravel route**. A preview environment has its **own cloned bucket**.
2. **Espace Pro LMS (scaling story).** An import is processed by a queue worker that is
   CPU-capped on Fixed. On Flex, the queue worker can be scaled **horizontally from the
   CLI or API, without downtime**, before an import and back after. Proof: start a
   synthetic import, run `upsun resources:set --count <worker>:3`, and watch jobs being
   processed by 3 distinct worker instances on a live dashboard.

## Stack and constraints

- Laravel, current stable release, on **PHP 8.3** (`php:8.3` image).
- Oracle MySQL 8.4 (`oracle-mysql:8.4`), Redis 7.2 for cache, sessions and queue.
- Object storage via the app's `object-storage` relationship (S3-compatible, path-style).
  Package: `league/flysystem-aws-s3-v3` ^3.
- **No frontend build step.** No npm, no Vite. One Blade layout with inline CSS.
  Fonts from Google Fonts with Arial fallback.
- Logs to stderr (`LOG_CHANNEL=stderr`). No file sessions, no file cache.
- Do **not** run `php artisan optimize` or `config:cache` in the build hook (env vars
  aren't available at build time). The demo doesn't need them at all.
- Whole app behind HTTP basic auth (`DEMO_USER` / `DEMO_PASSWORD` environment
  variables), because the URL is public.
- Every job must be **idempotent**. Scaling redeploys the environment, which can
  restart a worker mid-job; retries must not corrupt counts.

## Features

### 1. Layout (shared)

A header visible on every page showing:
- the **environment name** (`PLATFORM_ENVIRONMENT` or `PLATFORM_BRANCH`) as a clear
  pill. Main and preview must look visibly different (for example violet for main,
  neutral outline for anything else). This is how the presenter proves "this is the
  preview environment" in one glance.
- the serving instance hostname (`gethostname()`), in Space Grotesk, small.
- navigation: "Files" and "Import".

### 2. Files page (`/files`), BUI proof moment

- Lists objects under the `demo/` prefix of the S3 disk: name, size, last modified.
- Each name links to `/files/{path}`, which **streams** the object through Laravel
  (`Storage::disk('s3')->response($path)`). Never redirect to the bucket, never build a
  public URL: there isn't one.
- A small, factual panel titled "Where these files live" showing:
  - storage endpoint host and port (from `OBJECT_STORAGE_HOST` / `OBJECT_STORAGE_PORT`,
    expected to look like `object-storage.internal:8080`)
  - bucket name
  - one line of copy: "This endpoint is only reachable inside the project. Files reach
    the browser through the app."
- Empty state: "No files yet. Run `php artisan demo:seed-files`."

Artisan command `demo:seed-files`:
- Writes about 12 small, lightweight files under `demo/` (generated SVG "course
  thumbnails" and a few small text or JSON "lesson" files). Total well under 5 MB, so
  clicking any of them works on a hotspot.
- Idempotent: skips files that already exist.

### 3. Import page (`/import`), LMS proof moment

Data model:
- `imports`: id, status (pending, running, done), total_chunks, processed_chunks,
  rows_per_chunk, started_at, finished_at.
- `import_chunk_runs`: id, import_id, chunk_index (unique with import_id), worker_host,
  rows, duration_ms, finished_at.
- `learners`: synthetic records (external_id unique, name, email, course_code,
  score, updated_at). Upserts only.

Artisan command `demo:import {--rows=200000} {--chunk=500}` and a "Start import" button
on the page (POST, CSRF-protected) that do the same thing:
- Creates an `imports` row and dispatches one `ImportChunk` job per chunk to the
  `redis` queue.
- No file upload. Rows are generated deterministically from the chunk index.

Artisan command `demo:import-status {--quiet-if-done}`: prints the latest import's
progress; with `--quiet-if-done`, exits 0 silently when no import is pending or running
and exits 1 otherwise. `scripts/scale-for-import.sh` polls it to know when to scale back.

Job `ImportChunk`:
- Generates its rows, burns a configurable amount of CPU per chunk
  (`IMPORT_CPU_MS`, default 400, for example repeated `hash('sha256', ...)` until the
  time budget is spent), then upserts the rows in one statement.
- Records its run in `import_chunk_runs` with `gethostname()`, using an upsert keyed
  on (import_id, chunk_index) so a retried chunk is counted once.
- Increments `processed_chunks` from the count of distinct finished chunks, not with a
  blind `+1`.

Dashboard (polls `/import/status` JSON every 2 seconds, plain JavaScript, no library):
- Progress bar and "x of y chunks", rows imported, chunks per minute over the last
  60 seconds.
- Pending jobs in the queue (`Queue::size()`).
- **The memorable element:** a strip of worker tiles, one per distinct `worker_host`
  that finished a chunk in the last 60 seconds. When the presenter scales from 1 to 3
  instances, the strip goes from one tile to three. Each tile shows the hostname and
  its chunks per minute. Give this strip the visual weight; keep the rest quiet.
- A "Reset demo data" button (POST) that truncates `imports`, `import_chunk_runs`,
  `learners` and clears the queue. Same as `php artisan demo:reset`.

Tuning target (adjust `IMPORT_CPU_MS` and default `--rows` until true on the real
project with the queue worker at 0.5 CPU):
- 1 instance: the import takes about 10 to 12 minutes.
- 3 instances: the remaining work clearly speeds up within 1 to 2 minutes of the
  scale-up finishing.

### 4. Health route

`/up` returns 200 without basic auth (Laravel's default health route is fine), so the
presenter can check the app quickly from a phone.

## Design

Apply the Upsun brand, light mode:
- Background `#ECEDEE`, text `#0C0F10`, secondary text `#646F75`, borders `#D8DBDC`,
  accent Violet 600 `#6046FF`. Lime `#D5F800` only for one deliberate highlight
  (for example the newest worker tile), always with `#0C0F10` text.
- Instrument Sans for everything; Space Grotesk only for hostnames, endpoints and
  CLI snippets. Fallback Arial.
- Sentence case everywhere. No all-caps labels, no em dashes in copy.
- Large type: the screen will be shared or shown on a laptop across a table. Minimum
  18px body, readable at 2 meters.
- Responsive enough to look fine on a phone, visible keyboard focus.

## Upsun specifics you must respect

- Config lives in `.upsun/config.yaml`. Resources (CPU, RAM, disk, object storage,
  instance counts) are **not** in config: they are set with `upsun resources:set`.
- Service credentials come from service environment variables, mapped to Laravel's
  names in `.environment` (provided).
- Object storage: one bucket per app, named after the app; endpoint internal-only;
  path-style addressing; credentials from the relationship. Minimum allocation 512 GB.
  Docs: https://developer.upsun.com/docs/manage-resources/object-storage
- Horizontal scaling: `upsun resources:set --count <name>:<n>` redeploys **without
  downtime** if nothing else changes. Changing `--size` causes a short downtime: never
  use it during the demo.
- When unsure about any Upsun behavior or CLI flag, check
  https://developer.upsun.com/llms.txt or `upsun <command> --help`. Do not guess.
  In particular, confirm with `upsun resources:get` the exact name the CLI uses for
  the queue worker (it may be `queue` or `app--queue`) and update
  `scripts/scale-for-import.sh` and `docs/DEMO_SCRIPT.md` accordingly.

## Deployment (ask the user before each step that creates billable resources)

1. Create the Flex project in the user's organization (**not a trial**: object
   storage isn't available on trials). Ask the user for the organization and region.
2. Set sensitive variables: `env:APP_KEY` (generate with
   `php artisan key:generate --show`), `env:DEMO_USER`, `env:DEMO_PASSWORD`.
3. Push, then set resources on `main`:
   ```bash
   upsun resources:set \
     --size app:0.5,<queue-name>:0.5,<scheduler-name>:0.1,db:0.5,cache:0.1 \
     --object-storage app:524288
   ```
   Object storage at 512 GB costs about €43/month per environment, prorated hourly.
   Remind the user to delete the project after the meeting.
4. Run `php artisan migrate --force` (deploy hook does it) and `demo:seed-files` via
   `upsun ssh`.
5. Create the preview environment from `main` (development type, minimum resources),
   then check that `/files` on the preview lists the same files.
6. Run one full import at 1 instance, then one with a scale-up to 3, and tune.

## Acceptance checklist

- [ ] `/files` on main lists ~12 files; each opens instantly on a phone hotspot.
- [ ] The "Where these files live" panel shows an `.internal` host.
- [ ] The preview environment shows its own pill and the same files.
- [ ] `/import`: start, progress, reset all work; a full import completes.
- [ ] Scaling the queue worker to 3 instances during an import shows 3 worker tiles
      within 2 minutes, the app keeps responding, and final counts are exact.
- [ ] Scaling back to 1 works; no chunk counted twice.
- [ ] `scripts/scale-for-import.sh` runs end to end with `UPSUN_CLI_TOKEN` set.
- [ ] Nothing in the demo path depends on npm, uploads or a fresh deploy.

## Out of scope

Authentication beyond basic auth, tests beyond a few feature tests for idempotency,
real file uploads, any UI not described here, Elasticsearch/OpenSearch, CDN setup.
