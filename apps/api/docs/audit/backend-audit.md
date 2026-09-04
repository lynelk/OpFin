# OpFin Backend Audit

Audit date: 2026-05-21

Scope: Laravel backend repository only. This audit documents the current repository state and does not add new business features.

Important repository state: the working tree already contains uncommitted security hardening changes from the previous pass, including password-reset OTP enforcement, logout token revocation, callback secret checks, authorization guards, CI workflows, and security regression tests. This audit describes the current working tree, not only the last committed `main` snapshot.

## Executive Summary

OpFin-BE is a Laravel 11 application that combines a web admin portal, Sanctum-backed mobile API, loan lifecycle logic, mobile money integrations, CRB/NIN validation, SMS, accounting-like ledger records, and an AI/RAG chat surface. It is a usable foundation for OpFin, but it is not yet fintech-production-ready.

The strongest existing areas are the domain model coverage for loans, applications, schedules, transactions, institutions, users, float topups, journal entries, and credit scores. The riskiest areas are access control consistency, payment callback trust, auditability, migration hygiene, regulatory controls, and automated test/build maturity.

## Runtime and Framework

- PHP requirement: `^8.2` in `composer.json`.
- Laravel framework requirement: `^11.31` in `composer.json`.
- Locked Laravel framework version: `v11.34.2` in `composer.lock`.
- Application bootstrap: Laravel 11-style `bootstrap/app.php`.
- Health route: `/up` configured in `bootstrap/app.php`.
- Queue: database queue tables exist; `routes/console.php` schedules `CheckTransactionStatus` every minute.

## Authentication Setup

- Web authentication uses `laravel/ui` and `Auth::routes(['register' => false])`.
- API authentication uses Laravel Sanctum via `auth:sanctum` route middleware.
- Mobile login/register endpoints issue Sanctum personal access tokens.
- User passwords are hashed with Laravel hashing through model casts and explicit `Hash::make`.
- Password reset flow currently uses phone plus OTP in the working tree.
- API logout route exists in the working tree and deletes the current Sanctum token.

## Installed Packages

Production Composer requirements:

- `php`: `^8.2`
- `laravel/framework`: `^11.31`, locked `v11.34.2`
- `laravel/sanctum`: `^4.2`, locked `v4.2.0`
- `laravel/tinker`: `^2.9`
- `laravel/ui`: `^4.6`, locked `v4.6.1`
- `openai-php/client`: `^0.18.0`, locked `v0.18.0`

Development Composer requirements:

- `fakerphp/faker`
- `laravel/pail`
- `laravel/pint`
- `laravel/sail`
- `mockery/mockery`
- `nunomaduro/collision`
- `phpunit/phpunit`, locked `11.5.55`

Node/Vite development requirements:

- Vite 5
- Laravel Vite plugin
- Bootstrap
- Tailwind CSS
- Sass
- Axios
- Concurrently
- PostCSS/Autoprefixer

## Tests and Verification

Test files currently present:

- `tests/Feature/ExampleTest.php`
- `tests/Feature/ApiSecurityTest.php`
- `tests/Unit/ExampleTest.php`
- `tests/TestCase.php`

Commands attempted locally:

```text
php artisan test
composer install --no-interaction --prefer-dist --no-progress
npm run build
```

Results:

- `php artisan test`: blocked because `php` is not installed or not on PATH.
- `composer install`: blocked because `composer` is not installed or not on PATH.
- `npm run build`: blocked because `npm` is not installed or not on PATH.

No backend tests or asset builds were successfully executed in this local environment. Verification should be run on a machine or CI runner with PHP 8.2+, Composer, Node/npm, and database extensions available.

## High-Level Assessment

Build on this repository, but harden before release:

- Keep Laravel/Sanctum as the backend foundation.
- Treat the API and admin web portal as separate security surfaces.
- Standardize authorization with policies, gates, and role permissions instead of scattered inline checks.
- Formalize payment callbacks, reconciliation, audit logs, and ledger integrity before scaling financial flows.
- Replace boilerplate docs and example tests with OpFin-specific operational and domain documentation.

