# KRE Solar CRM

A lead-to-installation CRM for a solar business: leads, a stage workflow with
per-role tasks, quotes, a documentation handoff and portal, payments, push
notifications and sales-rep location tracking.

Hand-rolled PHP 8.1 MVC — a route table in `index.php` maps each route to
`[file, Class, method]`. PDO with server-side prepares, Bootstrap 5 over a
custom design system, no build step and no framework.

Mobile-first: the people using it are reps standing in a customer's garden, so
every screen is designed for a phone and asks for one decision at a time.

## Setup

```bash
# 1. Database
mysql -u <user> -p <database> < sql/schema.sql

# 2. Credentials — one per environment, never committed
cp config/db.example.php config/db.php    # then fill in the real values

# 3. First admin account (CLI only; the password comes from the environment)
KRE_ADMIN_PASSWORD='…' php setup.php you@example.com
```

`config/db.php` is git-ignored. Nothing in this repository contains a live
credential, and nothing should.

Migrations are plain `.sql` files in `sql/`, applied in filename order; a few
one-off backfills are PHP scripts in the same directory and are safe to re-run.

## Layout

```
index.php          route table and front controller
controllers/       one class per area (Leads, Workflow, DocPortal, Payments…)
includes/          functions.php, auth.php, audit.php, workflow.php, push.php
views/             PHP templates, grouped by area
assets/            css, js — no bundler; assetUrl() does mtime cache-busting
sql/               schema, migrations, backfills
docs/              process documentation and lifecycle diagrams
```

## Things that will bite you

- `jsonError()` returns `{error}`. JavaScript must read `res.error`.
- `leads.updated_at` is `ON UPDATE CURRENT_TIMESTAMP`, and the "won this month"
  figure counts rows by it. Any bulk `UPDATE leads` must carry
  `` `updated_at` = `updated_at` `` or it will silently inflate that number.
- `assetUrl()` exists because browsers cache aggressively; use it for every
  JS/CSS include.
- MariaDB cannot type a placeholder inside `CASE WHEN ? IN (…)` under
  server-side prepares. Branch in PHP instead.
- A workflow task's `stage` is the stage it was *raised* at, not the lead's
  current stage. `checkAndTriggerInstallation()` counts pending tasks with
  `stage='Won'`, which is why documentation tasks deliberately carry
  `stage='Documentation'`.

## History

An earlier UI lived at the repository root with this one nested under `v2/`.
That version was retired; its code is still reachable in the history at commit
`fc76459` if anything needs recovering.
