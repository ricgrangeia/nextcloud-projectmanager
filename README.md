# Project Manager

A multi-project hour and progress tracker for Nextcloud. It replaces a
spreadsheet-based workflow with a real app: break work into estimated
**Points** grouped under **Modules**, log day-to-day work as dated **Leaves**,
and set the actual **hours worked per day** — the app automatically computes
each Point's done/remaining hours using a largest-remainder percentage split,
plus optional cost tracking, a Features (proposal vs. status) view, a Tests
(validation log) view, and .xlsx export.

## Features

- **Hours grid** — Modules → Points → Leaves, with live estimate/done/remaining
  hours and a per-day percentage breakdown (largest-remainder split, so every
  day always adds up to exactly 100%).
- **Modules vs. OTHERS** — mark a module as outside the original estimate to
  track extra work (bug fixes, ad-hoc requests) separately from what was planned.
- **Cost tracking (optional)** — set an hourly rate and currency symbol per
  project to see a computed cost column in the summary.
- **Features tab** — what was sold/proposed to the client vs. its current status.
- **Tests tab** — a validation log with pass/fail/to-test tracking.
- **.xlsx export** — reproduces the tracker as a formatted spreadsheet.
- **Example project** — one click seeds a fully worked example so a new user
  can see how everything fits together without any setup.
- **In-app help** — a "?" in the sidebar explains the whole model (Points,
  Leaves, the calculation, Modules vs. OTHERS, cost) without leaving the app.
- **Translations** — English, Portuguese (pt_PT), French, Spanish, German,
  Italian and Dutch out of the box.
- **Token-friendly API** — every action is also available over Nextcloud's OCS
  API with app-password auth, so a script or an AI assistant can pair with you
  on a project (see below).

## Requirements

- Nextcloud 34 (Hub 26 "Spring")
- PHP 8.2 – 8.5
- Node.js ^24 / npm ^11.3 (build only)

## Install into a Nextcloud instance

```bash
# from this directory
composer install --no-dev
npm install
npm run build

# then either symlink or copy this whole directory into your Nextcloud's
# apps/ (or custom_apps/) folder as "projectmanager", then:
php occ app:enable projectmanager
```

Database tables are created automatically on `app:enable` via the migrations
in `lib/Migration/`. No manual SQL required.

## How it works, in short

Each day you log a few **Leaves** (short "what I did" entries) against one or
more **Points**. The app splits that day's actual hours across those Points,
proportional to how many Leaves each one got, and adds it up into "Done" hours
per Point — so the estimate/remaining numbers stay accurate without any manual
bookkeeping. The in-app help (the "?" icon in the sidebar) walks through this
with the rest of the app's concepts.

## Development

```bash
composer install
npm install
npm run watch     # rebuilds src/ on change

# run PHP unit tests (must be run from apps/projectmanager inside a real
# Nextcloud server checkout — the app framework's tests/bootstrap.php needs it)
composer test:unit
```

See `lib/Service/CalculationService.php` for the largest-remainder percentage
algorithm, and `lib/Service/TrackerService.php::buildGrid()` for how it's
assembled into the grid the UI renders. Nothing derived (percentages,
done/remaining hours, summary) is persisted — it's all computed at request
time from Project/Module/Point/Leaf/DayHours.

## Translations

UI strings are wrapped in `t('projectmanager', '...')` (JS) and ship with
`en` (source), `pt_PT`, `fr`, `es`, `de`, `it` and `nl` translations in `l10n/`.
To add another language, add a matching `l10n/<lang>.json` + `l10n/<lang>.js`
pair — the server discovers them automatically, no registration needed.
Nextcloud's `l10n/<lang>.json` format:

```json
{ "translations": { "Source string": "Translated string" }, "pluralForm": "nplurals=2; plural=(n != 1);" }
```

The matching `.js` file wraps the same data in
`OC.L10N.register("projectmanager", {...}, "pluralForm...")` for the browser
side.

## Giving an AI assistant (or any external tool) token access

The app exposes every operation twice:

- **`/apps/projectmanager/api/...`** — used by the bundled web UI, authenticated
  via your normal Nextcloud session + CSRF token.
- **`/ocs/v2.php/apps/projectmanager/api/v1/...`** — the same operations,
  meant for external/automated clients (scripts, an AI agent pairing with you,
  a mobile shortcut, etc.), authenticated with a Nextcloud **app password**
  instead of a browser session.

To let something else act on your projects "in pair" with you:

1. In Nextcloud, go to **Settings → Security → Devices & sessions → Create new
   app password**. Give it a name (e.g. "AI pairing") and copy the generated
   username + password — this is the token; it's shown only once.
2. Give that username/password pair to your AI tool/script. It authenticates
   with plain **HTTP Basic Auth** and must send the `OCS-APIRequest: true`
   header on every request (standard Nextcloud OCS API requirement).

Example — list projects and log a leaf, from the command line:

```bash
NC_USER="ricardo"
NC_TOKEN="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"   # the app password
BASE="https://your-nextcloud.example.com/ocs/v2.php/apps/projectmanager/api/v1"

curl -s -u "$NC_USER:$NC_TOKEN" -H "OCS-APIRequest: true" -H "Accept: application/json" \
  "$BASE/projects"

curl -s -u "$NC_USER:$NC_TOKEN" -H "OCS-APIRequest: true" -H "Accept: application/json" \
  -X POST "$BASE/points/42/leaves" \
  -d description="Implemented CalculationService" -d workDate="2026-08-24"
```

You can revoke access at any time from the same Security settings page by
deleting that app password — no code changes needed.

### Helping an AI discover the API instead of guessing endpoints

Point it at these two, in order, before it starts making requests:

1. **`GET /ocs/v2.php/apps/projectmanager/api/v1/help`** — human/AI-readable
   quick reference (no authentication required). Lists every endpoint with
   its method and a one-line summary, and explains how to authenticate.
2. **`https://your-nextcloud.example.com/custom_apps/projectmanager/openapi.json`**
   — the full OpenAPI 3.0 spec (parameters, request/response shapes). Served
   from the app's own folder, **not** the Nextcloud root.

See `lib/Controller/ApiController.php` for the full list of endpoints
(projects, modules, points, leaves, day-hours, features, tests).

## License

AGPL-3.0-or-later
