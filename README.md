# Laravel eCommerce Dashboard

This repo has two parts, kept deliberately separate:

```
Laravel-eCommerce-Dashboard/
├── storehub-prototype.html     ← the clickable UI/UX prototype (client-facing)
├── .github/workflows/          ← auto-publishes the prototype to GitHub Pages
└── api/                        ← the real Laravel backend (not yet deployed)
```

## `storehub-prototype.html` — the live demo

This is a self-contained HTML/CSS/JS file — no build step, no backend, runs
entirely in the browser on mock data. It's what's published automatically to
GitHub Pages on every push (see `.github/workflows/deploy-pages.yml`).

**Live at:** `https://<your-username>.github.io/Laravel-eCommerce-Dashboard/`

This is for design/flow sign-off only. Nothing clicked in it is actually
saved anywhere — it resets every time the page reloads.

**To update it:** replace this file (keep the exact name
`storehub-prototype.html`) and push to `main`. The GitHub Action re-publishes
it automatically within about a minute — no manual steps.

## `api/` — the Laravel backend

The real application: database migrations, models, controllers, and the
Stripe/PayPal/Bank Transfer payment integrations. This is **not** what's
running at the GitHub Pages URL — GitHub Pages can only serve static files,
it can't run PHP or MySQL.

See `api/README.md` for full setup instructions. Short version:

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

This needs to be deployed to real PHP/MySQL hosting (a VPS, or a PaaS like
Railway/Render/Laravel Cloud) before it's "live" in the sense the client
will eventually use — GitHub Pages is only ever hosting the demo above.

## Status

- [x] Prototype: complete, all screens, full Arabic/RTL support
- [x] Backend: written, migrations/models/controllers/payment services complete
- [ ] Backend: not yet run against a real PHP/Composer environment — see
      `api/README.md` section 8 for the pre-production checklist
- [ ] Frontend not yet wired to the real API (currently reads mock data)
- [ ] Not yet deployed to real hosting
