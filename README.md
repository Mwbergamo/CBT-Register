# CodeBlue Retail Register / Checkout

A Laravel + Livewire app for CodeBlue Technology's retail store: associates
find or enter a customer (synced with ConnectWise Manage), build a
"Solutions List" from the Retail product catalog, and check out — creating
a closed Miscellaneous invoice in ConnectWise automatically. Touch-friendly,
responsive (tablet/phone/desktop), Microsoft 365 SSO.

**Start here:**

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — how it fits together and
  why, including the hybrid real-time/retry checkout workflow
- [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md) — run it locally
- [docs/CONNECTWISE_SETUP.md](docs/CONNECTWISE_SETUP.md) — API member,
  keys, auto-approval status, and the Retail warehouse
- [docs/AZURE_AD_SETUP.md](docs/AZURE_AD_SETUP.md) — Microsoft 365 SSO
  app registration
- [docs/BLUEHOST_DEPLOYMENT.md](docs/BLUEHOST_DEPLOYMENT.md) — deploying
  to register.codebluetechnology.com
- [docs/DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) — every table and
  why it exists

## Stack

PHP 8.3+ / Laravel 13 / Livewire 3 / MySQL. Tailwind CSS via CDN and
Livewire's own bundled JS — **no Node/npm build step**, so this runs on
plain Bluehost shared hosting with nothing beyond PHP + MySQL.

## Status

This is a complete, working scaffold: every screen, model, ConnectWise
integration point, background job, and feature test described in
docs/ARCHITECTURE.md is written. It has not yet been run against a live
ConnectWise instance or real Azure AD tenant — see docs/DEVELOPMENT.md for
exactly what to confirm first, and docs/CONNECTWISE_SETUP.md for the small
number of integration details (an approval status ID, a catalog filter,
the invoice line item shape) that only your live ConnectWise instance can
finalize.
