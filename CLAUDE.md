# seablast-dist maintainer notes

<!-- SCAFFOLDIFY:REMOVE-START dist-demo -->

This repository is the public Seablast seed application. It is intentionally part boilerplate, part demo.
Treat it as a starter project to clone and reshape, not as a finished domain app.

## What this repository is

- Purpose: show the minimal structure of a Seablast web app plus a few removable demos.
- Core layering: `conf/` -> `src/Models/` -> `views/*.latte` -> `assets/` -> public web root.
- Main package namespace is currently `Seablast\Distribution`; a real app is expected to rename that.
- The repository already includes framework plumbing, Phinx setup, writable runtime directories, and CI automation.

<!-- SCAFFOLDIFY:REMOVE-END dist-demo -->

## Request and runtime flow

1. Apache `.htaccess` rewrites app requests to `vendor/seablast/seablast/index.php` and blocks direct web
   access to sensitive folders and text/config files.
2. Seablast loads framework defaults plus app configuration from `conf/app.conf.php` and optional local
   overrides from `conf/app.conf.local.php`.
3. Routing is declared in `SeablastConstant::APP_MAPPING` inside `conf/app.conf.php`.
4. A route maps to a model, a template, or both.
5. Models commonly accept `SeablastConfiguration` and `Superglobals` in the constructor and expose
   `knowledge(): stdClass`.
6. Response conventions are:
   - `rest` => JSON API response
   - `redirectionUrl` with optional `httpCode` => redirect response
   - otherwise render the mapped Latte template
7. App templates usually extend `../vendor/seablast/seablast/views/BlueprintWeb.latte`.
8. Local `views/nav.latte` and `views/footer.latte` can populate the default layout navigation.

<!-- SCAFFOLDIFY:REMOVE-START dist-demo -->

## Keep as boilerplate

- `blast.sh` is the main bootstrap and maintenance entry point. It creates local config files on first run,
  installs or updates dependencies, runs Phinx migrations for development and testing, and executes PHPUnit.
- `conf/app.conf.dist.php` and `conf/phinx.dist.php` are templates for local uncommitted configuration.
- `conf/app.conf.php` is the main app-level routing and configuration entry point.
- `conf/db/migrations/` and the referenced vendor `seablast/i18n` migration path are part of the starter
  database layout.
- `.htaccess` is important boilerplate, not demo content. It handles friendly URLs, language-prefixed asset
  routing, and blocks direct access to folders such as `conf/`, `src/`, `tests/`, and `views/`.
- `cache/` and `log/` are writable runtime directories. `permissions.sh` documents the expected permissions.
- `.github/workflows/polish-the-code.yml` and `.github/linters/` are part of the seed project's CI and linting.
- `src/AppConstant.php` is intentionally empty and meant to hold app-specific keys and flags later.
- `assets/` is currently just a placeholder directory with a `README.md`; real apps are expected to add their own
  scripts, styles, and images there.
- `seablast/i18n` is included and already wired into Phinx; your app may use it heavily, lightly, or not at all.

## Current demos

- Blog demo:
  - routes: `/blog-e`, `/blog-r`
  - files: `src/Models/BlogModel.php`, `views/blog-editable.latte`, `views/blog-readonly.latte`
  - data: `conf/db/migrations/20250803081249_first_blog_posts.php`
  - purpose: demonstrate localised content retrieval via `seablast/i18n`
  - note: the editable variant assumes auth/admin APIs exist in the wider Seablast ecosystem
- Mirror demo:
  - routes: `/api/mirror`, `/use-mirror`
  - files: `src/Models/ApiMirrorModel.php`, `src/Models/UseMirrorModel.php`, `views/mirror.latte`
  - purpose: demonstrate JSON API output and a server-side Guzzle call back into the same app
- Redirect demo:
  - route: `/redir`
  - file: `src/Models/RedirModel.php`
  - purpose: demonstrate redirect output via `redirectionUrl` and `httpCode`
- Arithmetic demo:
  - route: `/arithmetic`
  - files: `src/Models/ArithmeticModel.php`, `views/arithmetic.latte`
  - data: `conf/db/migrations/20260222090000_create_arithmetic_attempts.php`
  - purpose: demonstrate form handling, POST validation, Latte rendering, and DB persistence
- Starter home page:
  - route: `/`
  - files: `src/Models/HomeModel.php`, `views/home.latte`
  - purpose: only starter scaffolding, not a finished homepage

## Placeholders and sharp edges

- `src/Models/HomeModel.php` and `views/home.latte` are deliberately minimal and should usually be rewritten first.
- `views/item.latte` is only a stub placeholder.
- The `/article` route in `conf/app.conf.php` is illustrative but incomplete in this repository:
  it points to template `article`, but there is no local or inherited `article.latte`, so the route should be
  implemented or removed before use.
- `views/nav.latte` still presents the project as `DIST` and links to demos. Rewrite it early in a real app.
- `tests/` is currently scaffold-only. The repository has PHPUnit and CI wiring, but almost no custom application
  tests yet.

<!-- SCAFFOLDIFY:REMOVE-END dist-demo -->

## Repository structure

- `.github/` automation and linting
- `assets/` frontend assets placeholder
- `cache/` Latte cache and runtime cache
- `conf/` app config, local config templates, Phinx config, PHPStan config, DB migrations
- `log/` runtime logs
- `src/` app classes, especially `src/Models/`
- `tests/` scaffold only at the moment
- `views/` app Latte templates

## Setup and runtime notes

- First run `./blast.sh` to generate `conf/app.conf.local.php` and `conf/phinx.local.php` if they do not exist.
  Edit those files, then rerun the script.
- `conf/*.local.php` files are Git-ignored by design.
- Phinx is configured for both development and testing databases. Keep a separate testing DB so migration history
  and test data do not collide with development data.
- `phpunit.xml` is present and CI runs PHPUnit, but meaningful coverage only appears once the app adds real tests.
- `./blast.sh phpstan` installs extra PHPStan packages on demand, and `./blast.sh phpstan-remove` removes them.
- If PHPStan reports `Constant APP_DIR not found.`, uncomment the relevant lines in
  `conf/phpstan.webmozart-assert.neon`.
- `permissions.sh` documents the expected write permissions for `cache/` and `log/`.

<!-- SCAFFOLDIFY:REMOVE-START dist-demo -->

## Guidance when turning this into a real app

- Rename namespace and package metadata away from `Seablast\Distribution`.
- Replace the starter home page and navigation early so the project stops presenting itself as a distribution demo.
- Run `php scaffoldify.php` to remove demo-only files and marked `dist-demo` blocks, then rewrite the remaining starter placeholders.
- Keep the bootstrap and deployment plumbing unless you have a deliberate replacement for it.
- Put app-specific flags and keys into `src/AppConstant.php`.
- Add real PHPUnit coverage; do not rely on the current scaffold-only `tests/` directory.

<!-- SCAFFOLDIFY:REMOVE-END dist-demo -->
