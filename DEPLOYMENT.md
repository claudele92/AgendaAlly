# Deployment checklist

Steps to run after pulling backend changes into an environment where
Laravel's config/route/event caches are enabled (staging, production).

## Every deploy

1. `composer install --no-dev --optimize-autoloader` (or your usual install step)
2. `php artisan migrate --force`
3. `php artisan optimize:clear`

Run `optimize:clear` **every time**, not only when you know a change
touched routes/config/migrations — it's cheap, and skipping it on the
assumption "this deploy didn't touch anything cached" is exactly how a
stale-cache symptom gets mistaken for a new bug (or a real bug gets
mistaken for a stale cache) after the fact. `optimize:clear` clears the
cached config, routes, views, and events in one call; follow it with
`php artisan optimize` (or the more targeted `config:cache`/`route:cache`)
if the environment normally runs with those caches warm.

If you run persistent queue workers, restart them after deploying so they
pick up the new code (`php artisan queue:restart`).

## First-time setup on a new environment

- `php artisan storage:link` - creates the `public/storage` symlink to
  `storage/app/public`. Without it, every uploaded file 404s even once
  `IMG_HOST` (below) points at the right host: the upload itself succeeds
  and lands on disk, but nothing served under `/storage/...` actually
  resolves. Found missing here, compounding the `IMG_HOST` placeholder bug
  below into the same "upload succeeds, image never displays" symptom.

## Environment variables to re-check on every new environment

`backend/.env` is not committed, so these don't travel with the repo - they
must be set by hand on every new environment (a fresh VPS, a tunnel URL that
changed, a real domain replacing a tunnel):

- `IMG_HOST` - the base URL prepended to every uploaded file's path (see
  `config('app.img_host')`'s doc comment). Must match a host the *browser*
  can actually reach, not just the backend process itself. Found shipping
  as the literal placeholder `https://api.example.com/`, which made every
  freshly-uploaded image (logos, favicons, category/service/shop images)
  silently fail to display while the upload itself "succeeded" - the file
  really did land on disk, just under a URL nobody could load. Don't let
  the next value (a tunnel URL, a `localhost` port) become the same kind
  of stale placeholder once a real production domain is in place - update
  it again at that point.
- `APP_URL` - same category of host-mismatch risk; currently also a
  placeholder (`https://api.example.org/`). Not yet confirmed to cause a
  user-visible symptom the way `IMG_HOST` did, but worth setting correctly
  alongside it rather than leaving a second placeholder domain in place.

## Why this matters here

Two bugs found in this codebase were only reachable because a stale or
never-rebuilt cache and a genuine code bug produce the identical symptom
from the outside (an "unexplained 404" or "endpoint doesn't work"):

- A route file change (registration order) that only takes effect once
  the cached route table is rebuilt.
- A migration whose effects depend on when it's run relative to seeding.

`optimize:clear` doesn't fix a genuine code bug, but running it as a
routine, unconditional step after every deploy is what lets you tell the
two apart quickly instead of re-investigating from scratch each time.
