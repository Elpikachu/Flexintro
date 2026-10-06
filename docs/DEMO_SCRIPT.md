# Workshop script: BUI Platform and Espace Pro LMS on Flex (45 min)

Goal: the customer agrees that Flex fits both apps and commits to a first concrete
step (LMS dev environment migration). Cost is a bonus, mentioned briefly at the end.
Never show CPU, RAM or IO "pressure" graphs: they are internal only.

## Before you start (2 minutes before)

- Laptop on hotspot if the venue Wi-Fi is weak. Screen brightness and zoom at 150%.
- Tabs open, logged in: demo app `/files` (main), `/files` (preview), `/import` (main),
  Upsun console resources page of the demo project.
- Terminal open, font large, `UPSUN_PROJECT` exported, `upsun resources:get` already run
  once. Resolve the queue worker name once and keep it in a variable, so the live
  command is short and cannot be mistyped:
  ```bash
  export QUEUE_WORKER=$(upsun resources:get --worker queue \
    --columns service --format plain --no-header | head -1)
  ```
  `scripts/scale-for-import.sh` and `ci/gitlab-ci-import-schedule.yml` open in the
  editor.
- `/import` reset, queue worker at 1 instance.
- Fallback video and screenshots on the desktop.

## 0:00 to 0:04, framing

Say: "We looked at 30 days of production metrics and the configs of both apps. They
have different problems, with one common cause: both pay for Fixed's shape, not for
what they actually need. LMS has CPU where it isn't used and is short of memory and
queue capacity where it matters. BUI pays for a terabyte of disk per environment to
hold 450 GB of files."

Ask: "What would make these 45 minutes worth it for you?"

## 0:04 to 0:18, BUI Platform

Talking points:
- 452 GB used, ~1 TB provisioned, billed for every available environment. Usage has
  been flat for a month: the jump comes from provisioning, not growth.
- Two paths on Flex:
  1. Network storage, right-sized: no code change, but each preview environment still
     carries ~450 GB of disk. Cost stays high.
  2. Object storage: about six times cheaper per GB, needs the Laravel S3 driver for
     reads and writes. This is the path that fixes the budget.

**Proof moment 1 (about 2 minutes), browser only:**
1. `/files` on main: "These files live in the app's object storage bucket." Click one
   small file: it opens through a Laravel route.
2. Point at "Where these files live": "The endpoint is `object-storage.internal`. It is
   only reachable inside the project. There is no public bucket, by design. Files reach
   the browser through your app, with your access rules."
3. Switch to the preview tab: "Same files, different environment, its own cloned
   bucket. Preview environments start with minimum resources."

Fallback: screenshots of the three screens.

Ask (pick what's missing):
- "Around September 9 to 10, about 240 GB disappeared. What happened, and is 450 GB the
  steady state, or will launch add much more?"
- "What are the files: types, typical sizes? Who downloads them, and are they public or
  behind login?" (Key point: on object storage every download goes through PHP. Large
  files need the app sized for it, and public assets benefit from CDN caching.)
- "Is Elasticsearch in use today? Its data disk looks empty."
- "Do all preview environments need the full file set?"

## 0:18 to 0:32, Espace Pro LMS

Talking points (show the RAM allocated-vs-used chart):
- The High Mem plan gives about half its memory to network storage and Redis, which
  use about 1% of it. The app gets 0.25 GB and goes over its limit on about a third of
  the days.
- On days that look like imports (around Sep 9, 15, 17), the app, the database and the
  queue worker peak together. The queue worker, the one doing the import work, is the
  smallest container in the plan and runs at its ceiling.
- On Flex, each container gets its own size, and the queue worker can scale out just
  for the import.

**Proof moment 2 (about 3 minutes), terminal and browser:**
1. Browser `/import`: click "Start import". One worker tile appears, progress starts.
2. Terminal:
   ```bash
   upsun resources:get
   upsun resources:set --count $QUEUE_WORKER:3
   ```
   Say while it redeploys: "Changing the instance count redeploys without downtime.
   No ticket, no plan change, no console login required."
3. Refresh nothing, keep talking: the app stays responsive. Within 1 to 2 minutes the
   worker strip shows three tiles and throughput goes up.
4. Show `scripts/scale-for-import.sh` and the CI snippet: "Same commands, run from your
   CI on a schedule with an API token: scale out before the import, back after. Two
   extra worker instances for a three-hour import cost well under a euro at list
   price."
5. Mention, don't demo: "Or let the platform add instances on CPU thresholds with
   worker autoscaling."
6. `upsun resources:set --count $QUEUE_WORKER:1` (can finish in the background).

Fallback: the recording of the scale-up, then the script on screen.

Ask:
- "How is the import triggered today: a file uploaded in the app, then processed by
  queue jobs?"
- "Did it run around September 9, 15 and 17? How often does it really run?"
- "How long does it take, and would faster be useful?"

## 0:32 to 0:37, migration path and custom apps

- Moving to Flex means a new project plus a data migration. They lead it; Upsun
  services can assist or run it.
- LMS first: config translation only, plus the MySQL 8.0 to 8.4 upgrade (8.0 isn't
  available on Flex). Still in dev: the right moment.
- BUI second: S3 integration and file serving, then the move.
- The same model fits the custom apps they plan: per-container sizing, small preview
  environments, workers scaled when needed.

## 0:37 to 0:45, close

Costs, one sentence each, orders of magnitude, list prices:
- "BUI with object storage lands around your original budget."
- "LMS at roughly a third of today, with more memory for the app and more capacity for
  the import."

Next steps (What / Who / When):
1. LMS: migrate a dev environment to Flex, with 2 or 3 success criteria agreed now
   (for example: import completes, app memory no longer saturated). Customer, with
   Upsun services if wanted. Date to agree.
2. BUI: short technical session with their developers on S3 integration and file
   serving, once they have file sizes and download volumes. Date to agree.
3. Commercials: AE follows up separately.

## After the meeting

- Delete the preview environment and the demo project (object storage bills ~€43/month
  per environment, prorated hourly).
