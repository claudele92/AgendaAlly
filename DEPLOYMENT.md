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
