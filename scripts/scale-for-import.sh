#!/usr/bin/env bash
# Scale the queue worker out for an import, run it, scale back in.
# Horizontal scaling (--count) redeploys WITHOUT downtime on Upsun Flex:
# "If you've only made changes to the number of instances for your apps and
# workers, this redeployment causes no downtime."
#
# Works locally or in CI. Requires the Upsun CLI and an API token:
#   export UPSUN_CLI_TOKEN=...        (masked CI variable in CI)
#   export UPSUN_PROJECT=<project-id>
#   ./scripts/scale-for-import.sh
#
# The CLI name of the queue worker is resolved from the project rather than
# guessed, because it may be reported as "queue" or as "app--queue". Override
# it with WORKER=... if you ever need to.
set -euo pipefail

: "${UPSUN_CLI_TOKEN:?Set UPSUN_CLI_TOKEN (API token)}"
: "${UPSUN_PROJECT:?Set UPSUN_PROJECT (project ID)}"
ENVIRONMENT="${UPSUN_ENVIRONMENT:-main}"
WORKER_NAME="${WORKER_NAME:-queue}"
PEAK_INSTANCES="${PEAK_INSTANCES:-3}"
BASE_INSTANCES="${BASE_INSTANCES:-1}"
IMPORT_CMD="${IMPORT_CMD:-php artisan demo:import}"
POLL_SECONDS="${POLL_SECONDS:-30}"

upsun_env() { upsun "$@" -p "$UPSUN_PROJECT" -e "$ENVIRONMENT"; }

resolve_worker() {
  upsun_env resources:get --worker "$WORKER_NAME" \
    --columns service --format plain --no-header 2>/dev/null | head -n 1 | tr -d '[:space:]'
}

WORKER="${WORKER:-$(resolve_worker)}"

if [ -z "$WORKER" ]; then
  echo "Could not resolve the CLI name of the '${WORKER_NAME}' worker." >&2
  echo "Run: upsun resources:get -p ${UPSUN_PROJECT} -e ${ENVIRONMENT}" >&2
  echo "then re-run with WORKER=<name-from-the-service-column>" >&2
  exit 1
fi

echo ">> Queue worker resolves to '${WORKER}' on ${ENVIRONMENT}"

scale() {
  echo ">> Scaling ${WORKER} to $1 instance(s) on ${ENVIRONMENT}"
  upsun_env resources:set --count "${WORKER}:$1" --yes
}

# Always come back to the baseline, even if the import fails.
trap 'scale "$BASE_INSTANCES"' EXIT

scale "$PEAK_INSTANCES"

echo ">> Starting import"
upsun_env ssh -A app -- "$IMPORT_CMD"

echo ">> Waiting for the queue to drain"
until upsun_env ssh -A app -- "php artisan demo:import-status --quiet-if-done"; do
  sleep "$POLL_SECONDS"
done

echo ">> Import done"
# The EXIT trap scales the worker back to $BASE_INSTANCES.
