# Between Sujood & Strategy — Book Website

Handoff/technical notes for the developers maintaining this site.

## Overview

Marketing site + admin panel for Rahmah Aderinoye's *Between Sujood & Strategy*.

- **Public site** (`index.php`): hero, about the book, about the author, where to buy, reader reviews, "Gift It Forward" request form, upcoming events, newsletter signup, and social links.
- **Admin panel** (`/admin/`): login-gated dashboard to manage all content and review incoming submissions.
- **Storage**: everything is plain CSV files in `data/` — no database, no framework. Built for shared cPanel hosting.

## Requirements

- **PHP 8.0 minimum, recommended 8.3 / 8.4** (current on cPanel's MultiPHP). Do **not** run on PHP 7.x — the code uses `match`, typed parameters, and arrow functions.
- Apache 2.4 (or LiteSpeed) with `mod_rewrite` not required, but `.htaccess` support is expected.
- No extensions beyond the PHP core (sessions, CSV, `password_hash`, `mail`) are required.

---

## Project structure

```
/                           → document root (upload the CONTENTS here on cPanel)
├── index.php               Public site (renders CSVs)
├── review.php              AJAX endpoint — saves reviews.csv
├── subscribe.php           AJAX endpoint — saves subscribers.csv + emails
├── giftRequest.php         AJAX endpoint — saves requests.csv + emails
├── config.php              Shared config: mail settings, email templates, rate limiter, CSV append helper
├── .htaccess               Force UTF-8, disable directory listing
├── assets/
│   ├── css/style.css       Public styles
│   ├── css/admin.css       Admin styles
│   ├── js/main.js          Public JS (forms, toasts, review carousel)
│   ├── js/admin.js         Admin JS (custom modal, edit dialogs)
│   └── images/             cover.jpeg, author.jpeg
├── admin/
│   ├── index.php           Dashboard (login + all views + actions)
│   ├── download.php        CSV export per view
│   └── logout.php          Session destroy
└── data/                   All content (WEB-BLOCKED by .htaccess)
    ├── reviews.csv         rating,name,review,submitted_at
    ├── events.csv          name,day,month,location,time,tag
    ├── socials.csv         platform,url
    ├── subscribers.csv     email,submitted_at
    └── requests.csv        sender_name,sender_email,recipient_name,recipient_email,gift_message,submitted_at
```

## How the data flows

1. **Public forms** (`subscribe.php`, `review.php`, `giftRequest.php`) accept JSON POSTs from the frontend JS, validate, **append** one row to the matching CSV, and return clean JSON.
2. **Admin** (`admin/index.php`) reads the same CSVs, lists/searches/filters/paginates them, and writes back through add/edit/delete/clear actions.
3. CSVs are the single source of truth for BOTH the public site and the admin — nothing is hard-coded anymore.

### CSV safety (important)

- `data/.htaccess` returns `403` for any direct URL like `/data/subscribers.csv` (Apache 2.4 `Require all denied` with 2.2 fallback).
- All `fputcsv()`/`fgetcsv()` calls pass the 5th `escape` argument (`, ',', '"', '"'`) — required to avoid **PHP 8.4 deprecation warnings** that would pollute JSON responses.
- Appends use `csv_append_row()` (`config.php`) which guarantees a trailing newline before writing — this prevents rows from being glued onto the previous line (a real bug that once made subscribe rows unreadable in the admin).

---

## Admin panel guide

- Login: `admin/index.php`. Credentials configured via constants in `admin/index.php`:
  - `ADMIN_USER` (username) and `ADMIN_PASS_HASH` (a `password_hash()` bcrypt hash).
  - To change the password: run `php -r "echo password_hash('YOUR-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"` and paste the result into `ADMIN_PASS_HASH`.
- **Views**: Overview (6 stat cards, 3 per row), Subscribers, Reviews, Gift requests, Events, Social links.
- **Per-view capabilities**:

  | View | Add | Edit | Delete | Export | Filters |
  |---|---|---|---|---|---|
  | Subscribers | — | — | ✅ | ✅ | search |
  | Reviews | ✅ | — | ✅ | ✅ | search + rating |
  | Gift requests | — | — | ✅ | ✅ | search (from/to) |
  | Events | ✅ | ✅ | ✅ | ✅ | search |
  | Social links | — | ✅ | ✖ (permanent) | ✅ | search |

- **Social links are intentionally edit-only** — they render on the public site, so rows can't be deleted or cleared (delete + "Clear all" are hidden for that view).
- **Gift requests** show a **Type** column distinguishing **"Gift someone I know"** (recipient details shown) from **"Surprise a stranger"** (badge in both Type and To columns).
- Destructive actions (delete, clear-all, logout) use a **custom modal** instead of the browser's `confirm()`; the edit UI is also a modal pre-filled with the row's current values.
- All POST actions require the session **CSRF token** and use **Post-Redirect-Get** (no double-submit, no "confirm resend" prompts).
- The sidebar has a **"View public site"** link.

---

## What was improved vs. the original build

> For the developer who originally built this: here's everything that changed from the first version.

### Structure & deployment
1. **Flattened the project out of `public_html/`** into the web root so it deploys on cPanel by uploading the directory contents. Removed the old static `index.html` / `page.html` / `page.css` and everything hard-coded with them.
2. **Converted the frontend from static HTML to PHP**, driven entirely by CSVs (`reviews.csv`, `events.csv`, `socials.csv`). Editors change the admin → the public site updates, no HTML editing.
3. **All links are relative** (`assets/…`, `../index.php`, `index.php?view=…`) so the site works in a subfolder or the root without path rewrites.
4. **UTF-8 enforced via `.htaccess`** (`AddDefaultCharset UTF-8`), fixing mojibake that appeared with em-dashes, stars and checkmarks.
5. **Base64-embedded images extracted** to real files (`assets/images/cover.jpeg`, `author.jpeg`).
6. **Data files protected**: `data/.htaccess` returns 403 for direct CSV access.

### Data integrity
7. **Fixed a CSV corruption bug**: appends used to write directly onto the file; if the last line had no trailing newline, a new row was glued onto the header/previous row (which is why subscribers once disappeared from the admin). Now every append goes through `csv_append_row()` which guarantees a newline first.
8. **Added a CSV-injection guard** so values like `=cmd` can't be read as spreadsheet formulas.

### PHP 8.4 / 8.5 compatibility
9. **Audited + fixed every `fgetcsv`/`fputcsv` call** to pass the `escape` argument (this is the main silent-breaker on PHP 8.4). All files pass `php -l` and emit zero deprecations under `E_ALL`.
10. Used `?string` (explicit nullable) everywhere — no PHP 8.4 "implicit nullable" deprecations.

### Admin panel
11. **Fixed the sidebar**: the nav links were missing the `nav-link` class, so none of their styling/active state applied. Now styled with inline **SVG icons** (no emoji), active states, and counts.
12. **Dashboard stat cards** — 6 cards in a strict **3-per-row × 2 rows** grid (responsive to 2-col on tablet), each card links to its view, no link underlines.
13. **Login hardened** — `password_hash`/`password_verify` with a constant hash, session regeneration on login, and the default password is no longer exposed.
14. **CSRF token on every action** + **Post-Redirect-Get** for all mutations.
15. **Custom confirmation/validation modal** replaces native `confirm()` for log out, delete row, and clear-all — consistent with the site's design.
16. **Inline edit for Events and Social links** through the modal (pre-filled fields, required-field validation with a toast).
17. **Add forms** for Reviews and Events; **Social links are edit-only** (they're public, so they must stay permanent).
18. **Search, rating filter (reviews), and pagination** (12 per page) on every list.
19. **CSV export** for every view via `admin/download.php?type=…` (previously only three types were hard-wired; events/socials were silently exporting the wrong file).
20. **Flash messages + custom admin toast** for action feedback; **"View public site"** link in the sidebar.
21. **Gift requests show their type** ("Gift someone I know" vs "Surprise a stranger") so fulfillment is clear at a glance.

### Public site
22. **Styled toast notifications** (coral→wine gradient, bottom-center) for all form submissions.
23. **Star picker and stars are encoding-proof** (`&#9733;` in HTML, `\u2605` in JS) — no broken glyphs.
24. **Reviews now rotate 2 at a time** with Prev/Next (prev/next hidden when there are 2 or fewer, stays in sync when a new review posts). Also fixed a layout bug where the Prev/Next buttons pushed the review form down — they're now wrapped so the list + form stay side-by-side.
25. **Honest error messages**: the frontend now shows the server's real error (e.g. "Please wait a moment…") instead of a misleading generic "fields are empty".
26. **Events auto-sort** by month/day (including a `TBD` day), tags rendered as pills, and a nice empty state wired to the newsletter CTA.

### Email
27. **Branded HTML emails** via `config.php` (`email_brand()` + `email_plain_build()` + `send_email()`):
    - **Subscription**: confirmation to the subscriber + a "new subscriber" alert to the site owner.
    - **Gift request**: confirmation to the sender + a full-detail alert (from/recipient/message) to the owner.
    - Multipart `text/plain + text/html` so plain-text clients still read nicely.
    - `mail()` failures are suppressed so JSON responses never get polluted.

### Anti-spam
28. **Honeypot fields** on all three public forms (visually hidden, JS never filled by humans); bots are silently dropped with a fake success.
29. **Session-based rate limiting** (`rate_limited()`): 60s between subscribe submits, 120s between gift/review submits; returns HTTP 429 with a clear message.

---

## Deploy checklist (cPanel)

1. **Select PHP 8.3 or 8.4** for the domain (MultiPHP).
2. Upload the directory contents into your web root (`public_html` or subfolder).
3. **Make `data/` writable** — set the folder (and its CSVs) to **775** in File Manager if submissions ever fail to save.
4. Edit `config.php`:
   - `SITE_MAIL_FROM` → `no-reply@<your-domain>` (must be on your domain for cPanel to send).
   - `SITE_ADMIN_EMAIL` → your real inbox (receives new-subscriber/gift alerts).
5. Your admin credentials are trivial to change via `ADMIN_USER` / `ADMIN_PASS_HASH` in `admin/index.php` (current default: `admin` / `admin123` — change it before launch).
6. Verify: `yourdomain.com/` renders, `yourdomain.com/admin/` logs in, submitting each form works, and `yourdomain.com/data/reviews.csv` returns **403**.

## Local testing (Laragon)

- Vhost DocumentRoot: the project root (not a nested `public_html`).
- PHP 8.4 CLI ships with Laragon for `php -l` checks.
- `mail()` can't deliver on Windows dev — it fails silently; emails only actually send on the cPanel host.