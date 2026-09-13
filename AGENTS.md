# AGENTS.md

## Overview

Notira is a WordPress plugin that turns draft notes into clean HTML with AI, polishing email bodies or proofreading text from the wp-admin UI. Stack: PHP 7.4+ (Composer, PSR-4 `Nilambar\Notira\` mapped to `app/`), Svelte 5 + Vite for the admin bundle (pnpm), settings handled by the `ernilambar/optiz` library, and WordPress's AI client for generation.

## Setup

- `composer install` — install PHP dependencies (dev tooling pins PHP 8.2).
- `pnpm install` — install front-end dependencies.
- `pnpm run build` — compile admin assets into `build/`; the plugin UI needs this to run.
- `bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 7.0 false` — install the WordPress test suite, required before `composer test`. Honors `WP_TESTS_DIR` if you want a non-default location.

## Commands

- Build (production): `pnpm run build`
- Build (watch): `pnpm run dev`
- Test (full suite): `composer test`
- Test (single case): `./vendor/bin/phpunit --filter <ClassOrMethod>`
- Lint (PHP: parallel-lint + PHPCS): `composer lint`
- Lint (front-end: Prettier check): `pnpm exec prettier "**/*.{cjs,css,js,json,mjs,svelte}" --check`
- Format (PHP: phpcbf autofix): `composer format`
- Format (front-end: Prettier write): `pnpm run format`
- Typecheck: none configured — this project has no TypeScript; `composer lint` and `pnpm run build` are the static checks.
- i18n (only when explicitly asked): `composer pot`, `composer po`, `composer mo`

## Conventions

- Access plugin settings only through `Nilambar\Notira\Core\Option::get()` (Optiz-backed); never call `get_option( 'notira_options' )` directly. New options are fields declared in `Options::register_plugin_options()` inside a `pages[].tabs[].fields[]` schema.
- Use **pnpm** only — never run `npm install` or `npm run …`; `pnpm-lock.yaml` is the lockfile.
- Never hand-edit generated i18n artifacts (`.pot`, `.po`, `.mo`); regenerate them with the Composer scripts.
- After changing any PHP or Svelte/JS/CSS that ships to the admin, run `pnpm run build` — `Bootstrap::enqueue_admin_assets()` loads `build/main.{js,css}`.
- Go beyond the linter: Yoda conditions, `WP_Error`/`is_wp_error()` for error flow, `use` imports instead of fully-qualified names, snake_case PHP names, the `notira` textdomain for all user-visible strings, and sanitize input / escape output.
- A new generation mode needs both a slug in `Mode_Utils::get_valid_slugs()` and matching `prompts/<slug>-system.md` and `prompts/<slug>-user.md` files.

## Quality Gate

Every task must end with:
1. `composer lint` — 0 errors, 0 warnings (run `composer format` to auto-fix first)
2. `pnpm build` — assets compiled cleanly
3. `pnpm format` — JS/CSS formatted with Prettier
