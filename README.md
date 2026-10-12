# KRE Solar CRM

A lead-to-installation CRM for a solar business: leads, a stage workflow with
per-role tasks, quotes, documentation handoff, payments, push notifications and
sales-rep location tracking.

Hand-rolled PHP 8.1 MVC — a route table in `index.php` maps each route to
`[file, Class, method]`. PDO with server-side prepares, Bootstrap 5, no build
step and no framework.

## Two front ends, one database

| Path | What it is |
|---|---|
| `/` (repo root) | **v1** — the original UI, still in daily use |
| `v2/` | **v2** — a mobile-first rebuild, running in parallel on the same database |

They share the schema, so a change to `leads` has to work for both. v2 is the
one being actively developed.

## Setup

```bash
# 1. Database
mysql -u <user> -p <database> < sql/schema.sql

# 2. Credentials — one per environment, never committed
cp config/db.example.php config/db.php    # then fill in the real values
cp v2/config/db.example.php v2/config/db.php

# 3. First admin account (CLI only; the password comes from the environment)
KRE_ADMIN_PASSWORD='…' php setup.php you@example.com
```

`config/db.php` is git-ignored in both trees. Nothing in this repository
contains a live credential, and nothing should.

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
