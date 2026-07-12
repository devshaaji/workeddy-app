# Repository Guidelines

## Project Structure & Module Organization
This repository is a modular PHP monolith for tenant-aware commerce operations. Core runtime code lives in `platform/`, shared utilities in `shared/`, domain modules in `modules/<ModuleName>/`, and database migrations in `migrations/`. Public entry points are under `public/`, while operational scripts and CLI helpers live in `bin/` and `cronjobs/`. Keep module-local docs beside the module, for example `modules/Analytics/README.md`.

## Build, Test, and Development Commands
- `composer install`: install PHP dependencies.
- `composer test` or `php tests/run.php`: run the full foundation test suite.
- `composer analyse`: run PHPStan static analysis with the repo config.
- `composer validate:iam`: run the IAM architecture guard test directly.
- `php bin/WorkEddy analytics:backfill --from=YYYY-MM-DD --to=YYYY-MM-DD --refresh-rollups`: run a module CLI task when working on analytics.

## Coding Style & Naming Conventions
Use PHP 8.1 with `declare(strict_types=1);` in new PHP files. Follow PSR-4 namespaces defined in `composer.json`: `WorkEddy\Platform\`, `WorkEddy\Modules\`, `WorkEddy\Shared\`, and `WorkEddy\Migrations\`. Prefer `StudlyCaps` for classes, `camelCase` for methods and variables, and `UPPER_SNAKE_CASE` only for constants and environment variables. Mirror existing folder naming such as `Presentation/`, `Infrastructure/`, `Contracts/`, `Settings/`, and `Authorization/`.

## Testing Guidelines
Tests are lightweight PHP files in `tests/` with names ending in `*FoundationTest.php`. The runner `tests/run.php` loads the suite in a fixed order, so keep new tests deterministic and side-effect free. Put module-specific checks under `modules/<Module>/Tests/` when a rule belongs to one module only. Run `composer test` before opening a PR, and use `composer analyse` for changes that touch shared abstractions or boundaries.

## Commit & Pull Request Guidelines
This checkout does not expose usable Git history, so no repository-specific commit convention could be verified. Use short, imperative commit messages such as `Add inventory projection test`. PRs should include a clear summary, the commands you ran, and screenshots or sample requests only when the change affects API behavior or operator output.

## Security & Configuration Tips
Do not commit `.env`; use `.env.example` as the reference for local configuration. Treat module boundary tests and production-readiness notes as guardrails when changing cross-module behavior.
