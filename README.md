# Flex demo app

A small Laravel app that backs two proof moments in a 45 minute customer workshop:

1. **Files** (`/files`) — objects live in the app's object storage bucket, behind an
   internal only endpoint, and are streamed to the browser through a Laravel route.
   There is no public bucket URL.
2. **Import** (`/import`) — a synthetic import is processed by queued chunk jobs. Scale
   the queue worker out from the CLI and the worker strip goes from one tile to three,
   without downtime.

`CLAUDE.md` holds the full brief, `docs/DEMO_SCRIPT.md` the running order, and
`docs/PRESTAGING_CHECKLIST.md` what must be true the day before.

## Commands

| Command | What it does |
| --- | --- |
| `php artisan demo:seed-files` | Writes 12 small course files under `demo/` on the object storage disk. Idempotent. |
| `php artisan demo:import --rows=200000 --chunk=500` | Creates an import and queues one `ImportChunk` job per chunk. |
| `php artisan demo:import-status` | Prints the latest import's progress. `--quiet-if-done` exits 0 when nothing is in flight, 1 otherwise. |
| `php artisan demo:reset` | Truncates imports, chunk runs and learners, and clears the queue. |

## Scaling during the demo

```bash
export QUEUE_WORKER=$(upsun resources:get --worker queue \
  --columns service --format plain --no-header | head -1)

upsun resources:set --count $QUEUE_WORKER:3   # no downtime
upsun resources:set --count $QUEUE_WORKER:1   # back to baseline
```

`scripts/scale-for-import.sh` does the same around a full import and resolves the
worker name itself. `ci/gitlab-ci-import-schedule.yml` shows it on a schedule.

## Idempotency

Scaling redeploys the environment, which can kill a worker mid chunk. Every job is
safe to replay:

- learners are written with `upsert` keyed on `external_id`;
- each chunk run is upserted on `(import_id, chunk_index)`, so a retry overwrites its
  own row instead of adding one;
- `processed_chunks` is recomputed from the count of distinct finished chunks, never
  incremented blindly;
- a chunk whose import was reset mid flight writes nothing and exits quietly.

`php artisan test` covers these.

## Running it locally

No bucket and no redis needed:

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan demo:seed-files        # DEMO_DISK=local writes to storage/app
php artisan serve
```

Set `DEMO_DISK=local`, `QUEUE_CONNECTION=sync` and `IMPORT_CPU_MS=0` in `.env` to click
through both pages on a laptop. On Upsun, `.environment` provides the real values and
the disk stays `s3`.

No npm, no Vite, no build step.
