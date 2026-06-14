# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Notira is a WordPress plugin that turns draft notes into clean HTML using WordPress's AI client (`wp_ai_client_prompt`). Two modes: **email** (polish + wrap with greeting/signoff from settings) and **proofread** (minimal corrections). Requires WordPress 7.0+ (for AI support) and PHP 7.4+ at runtime; Composer pins PHP 8.0 for dev.

## Commands

PHP — use Composer scripts, not raw tool invocations:

- `composer lint` — runs parallel-lint then phpcs (WordPress + PHPCompatibility + Slevomat rules per `.phpcs.xml.dist`)
- `composer format` — phpcbf auto-fix
- `composer test` — PHPUnit; needs the WP test suite installed via `bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 <wp-version> false`
- `composer pot` / `composer po` / `composer mo` — i18n: regenerate POT, update PO files, compile MO files. Always use these scripts — never edit `.pot`, `.po`, or `.mo` files by hand.

Single test: `./vendor/bin/phpunit --filter <method_or_class>` (suite is defined as `tests/REST_API_Test.php` in `phpunit.xml.dist`).

Front-end — **pnpm only**, never npm:

- `pnpm install`
- `pnpm run dev` — `vite build --watch`
- `pnpm run build` — production bundle into `build/` (consumed by `wp_enqueue_*`)
- `pnpm run format` — wp-prettier across `*.{cjs,css,js,json,mjs,svelte}`

After editing PHP or Svelte that ships to admin, you must `pnpm run build` — `Bootstrap::enqueue_admin_assets` loads from `build/main.{js,css}` with `NOTIRA_VERSION` as the cache buster.

## Architecture

PSR-4 root: `Nilambar\Notira\` → `app/`. Entry point `notira.php` defines `NOTIRA_*` constants, requires `vendor/autoload.php` plus `vendor/ernilambar/optiz/init.php`, then calls `Bootstrap::init()`.

Request flow:

1. `app/Core/Bootstrap.php` registers admin menu, enqueues the Svelte bundle, and emits a `window.notiraAdmin` settings blob via `print_admin_settings()` on `admin_footer` priority 0 (script modules can't use `wp_localize_script`, so this inline tag must run before the module loads). It also prints i18n strings, the REST URL, the `wp_rest` nonce, and current defaults.
2. `templates/admin-page.php` renders the `<div id="notira-root">` mount point.
3. `src/main.js` mounts `App.svelte`, which POSTs to `/wp-json/notira/v1/generate`.
4. `app/API/REST_API.php` validates (`INPUT_MIN_LENGTH=20`, `INPUT_MAX_LENGTH=2000`, mode + tone enum), checks the 5-minute transient cache (`notira_<mode>_<tone>_md5(input)`), and on miss calls `call_ai()`.
5. `call_ai()` builds prompts via `Prompt_Utils::get_assembled_system_prompt()` + `get_mode_user_prompt()`, runs `wp_ai_client_prompt()->using_model_preference(...)`, runs the result through `wp_kses` with a tight allow-list (`p, br, ul, ol, li, strong, em, a[href]`), and for email mode wraps with greeting/signoff from options.

Permissions: every REST call requires `current_user_can('manage_options')`. Credential gating goes through `Credential_Utils::supports_ai()` (checks `wp_supports_ai()`) and `has_ai_credentials()` (reads Connectors per-provider option keys). UI shows different admin notices for unsupported vs missing key.

Settings storage: handled by the **Optiz** library (`ernilambar/optiz`, registered in `app/Options/Options.php`). All option access goes through `Nilambar\Notira\Core\Option::get($key)`, which proxies `Manager::instance('notira_options')->get($key)`. Don't call `get_option('notira_options')` directly. Adding a new option means adding a field in `Options::register_plugin_options()`; if it's user-supplied at runtime, also surface it through `Bootstrap::print_admin_settings()` for the JS bundle.

Prompts: `prompts/<mode>-system.md` and `prompts/<mode>-user.md`. `Prompt_Utils::get_template()` does `{{key}}` placeholder substitution. To add a new mode, add a slug to `Mode_Utils::get_valid_slugs()` AND drop the matching two `.md` files — the filename is computed as `<slug>-<system|user>.md`.

Tones: `Tone_Utils::get_tone_options()` is the single source of truth (slug → translated label). `Prompt_Utils::get_tone_instruction_block()` has a special case for `match_original` and softens phrasing for proofread mode.

## Conventions

- Follow WordPress PHP coding standards; `composer phpcs` is authoritative. Yoda conditions in conditionals.
- Use `WP_Error` and `is_wp_error()` for error flow, not throw/catch. The one place a `Throwable` is caught is around the third-party AI client's `toText()` call in `REST_API::call_ai()`.
- Always import classes with `use` (e.g. `use WP_Error;`) — no leading-backslash FQNs in code.
- Snake_case for PHP function and variable names. Class files are PSR-4 with StudlyCase + underscores allowed (`REST_API`, `Mode_Utils`, `Credential_Utils`).
- Inline comments (PHP, JS, CSS): capital first letter, period at the end, concise and generic — never reference a task, ticket, or recent change.
- PHPDoc: WordPress style. Use `@since 1.0.0` when unknown; do not bump an existing `@since` version unless explicitly asked. Add missing `@since` tags when you spot them.
- CSS is PostCSS with nesting capped at 2 levels. Do not use the `&--modifier` selector-expansion shorthand — write the full selector.
- All user-visible strings go through `__()` / `esc_html__()` with the `notira` textdomain. Sanitize on input with WordPress functions; escape on output.
- REST endpoints follow the `{ success, data }` shape used by `REST_API::generate()`; errors return `WP_Error` with a numeric `status` so the REST server formats them consistently.
- Package manager is **pnpm**. Do not run `npm install` / `npm run …`; the lockfile is `pnpm-lock.yaml`.
- When asked architecture, naming, or design questions, recommend and wait for the user's choice before writing code.

## Quality Gate

Every task must end with:
1. `composer lint` — 0 errors, 0 warnings (run `composer format` to auto-fix first)
2. `pnpm build` — assets compiled cleanly
3. `pnpm format` — JS/CSS formatted with Prettier
