# Pre-staging checklist

## Day before (D-1), on good connectivity

Project and app
- [ ] Flex project created in a non-trial organization, region chosen.
- [ ] Sensitive variables set: `env:APP_KEY`, `env:DEMO_USER`, `env:DEMO_PASSWORD`.
- [ ] Deployed on `main`; resources set; object storage enabled (512 GB).
- [ ] Queue worker name resolved and exported:
      `export QUEUE_WORKER=$(upsun resources:get --worker queue --columns service --format plain --no-header | head -1)`.
      `scripts/scale-for-import.sh` resolves it on its own, so nothing to edit.
- [ ] `php artisan demo:seed-files` run; `/files` lists ~12 files; each opens.
- [ ] "Where these files live" shows an `.internal` endpoint.
- [ ] Preview environment created from `main`; its pill is visibly different; it lists
      the same files.

Import rehearsal
- [ ] One full import at 1 instance: duration noted (target 10 to 12 minutes).
- [ ] One import with a scale-up to 3 instances mid-way: three worker tiles appear
      within 2 minutes; app stays responsive; final counts exact.
- [ ] Scale back to 1 confirmed.
- [ ] `scripts/scale-for-import.sh` run end to end with `UPSUN_CLI_TOKEN`.
- [ ] `demo:reset` leaves a clean dashboard.

Fallbacks
- [ ] Screen recording: files page, preview environment, start import, scale-up,
      three tiles, scale-down (3 to 4 minutes, no sound needed).
- [ ] Screenshots of each key screen.
- [ ] LMS chart (RAM allocated vs used) ready as an image.
- [ ] Phone hotspot tested with the laptop.

## Day of (D0), 30 minutes before

- [ ] `/up` returns 200 from the phone.
- [ ] `/import` reset; queue worker at 1 instance (`upsun resources:get`).
- [ ] No deployment or activity running on the project.
- [ ] Tabs and terminal arranged as in DEMO_SCRIPT.md; fonts large.
- [ ] Notifications off; other apps closed.

## After

- [ ] Delete the preview environment, then the project, to stop object storage billing.
