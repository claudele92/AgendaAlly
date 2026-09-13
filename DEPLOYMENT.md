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

- Copy `backend/.env.example` to `backend/.env` and fill in real values
  (DB credentials, `APP_URL`, `IMG_HOST`, mail, etc. - see the next
  section for the two that have bitten this codebase in production).
- `public/storage` (the symlink to `storage/app/public`, without which
  every uploaded file 404s even once `IMG_HOST` points at the right host)
  is now created automatically by `composer install`/`composer update` -
  see "Every deploy" below. It no longer needs a manual step on a fresh
  environment either; this line stays only as a record of what that
  automation does and why. If you ever run composer with `--no-scripts`,
  run `php artisan storage:link` by hand afterwards - it's a no-op
  (exits 0) if the symlink already exists, so it's always safe to re-run.

## Every deploy

`composer install` (step 1 above) now also runs `php artisan storage:link`
automatically via a `post-install-cmd`/`post-update-cmd` composer script -
this was previously a manual "first-time setup" step, found missing on a
real deployment for the second time in one day (once, its absence was the
direct cause of every uploaded image 404ing; a fix was applied by hand
and not made to survive future deploys the first time, which is exactly
why it recurred). It's cheap and idempotent, so it now runs on *every*
deploy rather than relying on someone remembering it was a one-time step
on a *new* environment specifically - the same reasoning `optimize:clear`
below is unconditional rather than conditional on "did this deploy touch
anything cached."

## Environment variables to re-check on every new environment

`backend/.env` is not committed, so these don't travel with the repo - they
must be set by hand on every new environment (a fresh VPS, a tunnel URL that
changed, a real domain replacing a tunnel). `backend/.env.example` documents
sane starting values for local development; both of the following need a
real, deliberate value in production:

- `IMG_HOST` - the base URL prepended to every uploaded file's path (see
  `config('app.img_host')`'s doc comment). Must match a host the *browser*
  can actually reach, not just the backend process itself, **and must
  include the port** the backend actually listens on (or the port a
  reverse proxy forwards to). Found shipping as the literal placeholder
  `https://api.example.com/`, which made every freshly-uploaded image
  (logos, favicons, category/service/shop images) silently fail to
  display while the upload itself "succeeded" - the file really did land
  on disk, just under a URL nobody could load. A later deploy fixed the
  placeholder but dropped the port (`http://localhost` instead of
  `http://localhost:8000`), reproducing the identical symptom - a bare
  host with no port is exactly as broken as no host at all, just less
  obviously so. Don't let the next value become the same kind of stale
  placeholder once a real production domain is in place - update it again
  at that point, port included if it's not the default for its scheme.
- `APP_URL` - same category of host-mismatch risk, and `FileHelper::
  uploadFile()` falls back to it when `IMG_HOST` is unset. Laravel's own
  default when `APP_URL` is unset is the literal string `http://localhost`
  - no port - so an unset `APP_URL` reproduces the exact same missing-port
  bug as an unset `IMG_HOST`. `FileHelper::uploadFile()` now falls back
  further still (to the current request's own host:port) if `APP_URL` is
  left at that literal default, so a forgotten `APP_URL` no longer
  produces a broken image URL by itself - but that fallback exists as a
  safety net, not a reason to skip setting `APP_URL` correctly. Set it
  explicitly, port included, the same as `IMG_HOST`.

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
