# seablast-dist

Distribution and seed project for `Seablast for PHP`.

This repository is meant to be copied and reshaped into a real app. It intentionally combines reusable
Seablast boilerplate with a few removable demo routes.

## What this repository gives you

- route mapping in `conf/app.conf.php`
- model-to-view, API, and redirect wiring through Seablast
- bootstrap scripts and local config templates
- Phinx migrations for development and testing databases
- Apache rewrite rules and protected folders
- GitHub Actions and linting setup
- optional `seablast/i18n` integration already present in Composer and Phinx

## Quick start

1. Create a development database and a separate testing database.
   Use collation `utf8_general_ci` or preferably `utf8mb3_general_ci`.
2. Rename the distribution namespace and package metadata to your own app.
3. Run [./blast.sh](./blast.sh) or
   [./vendor/seablast/seablast/blast.sh](https://github.com/WorkOfStan/seablast/blob/v0.2.10.1/blast.sh).
4. On first run, the script creates:
   - `conf/phinx.local.php` from [conf/phinx.dist.php](conf/phinx.dist.php)
   - `conf/app.conf.local.php` from [conf/app.conf.dist.php](conf/app.conf.dist.php)
5. Edit those local files with your database names, credentials, selected Phinx environment, and any app-specific
   overrides.
6. Run `./blast.sh` again to install or update dependencies, run Phinx migrations for development and testing,
   and execute PHPUnit.

Local configuration lives in `conf/*.local.php` and is intentionally ignored by Git.

If PHPStan reports `Constant APP_DIR not found.`, uncomment the relevant lines in
[conf/phpstan.webmozart-assert.neon](conf/phpstan.webmozart-assert.neon).

```sh
# Show all deployment and development options
./vendor/seablast/seablast/blast.sh -?
```

<!-- SCAFFOLDIFY:REMOVE-START dist-demo -->

## Scaffold cleanup

To strip the distribution demos and rename the project identity, use `scaffoldify.php`:

```sh
php scaffoldify.php --dry-run
php scaffoldify.php
```

The script will:

- delete the dedicated demo models and views: `src/Models/ArithmeticModel.php`, `src/Models/BlogModel.php`, `src/Models/ApiMirrorModel.php`, `src/Models/UseMirrorModel.php`, `src/Models/RedirModel.php`, `views/arithmetic.latte`, `views/blog-editable.latte`, `views/blog-readonly.latte`, and `views/mirror.latte`
- delete the dedicated demo migrations: `conf/db/migrations/20250803081249_first_blog_posts.php` and `conf/db/migrations/20260222090000_create_arithmetic_attempts.php`
- strip blocks marked with `SCAFFOLDIFY:REMOVE-START` / `SCAFFOLDIFY:REMOVE-END` - both with suffix ` dist-demo` - from shared files such as `conf/app.conf.php`, `views/nav.latte`, `README.md`, `TODO.md`, `CHANGELOG.md`, and `views/home.latte`
- prompt for replacements of the current distribution identity, including repository URLs, Composer package name, PHP namespace, short project slug, author name, author email, and `HOME DIST`
- keep `scaffoldify.php` itself so you can review the changes and delete the tool manually later

After scaffoldify, rewrite the starter home scaffold in `src/Models/HomeModel.php` and `views/home.latte`, and decide whether `views/item.latte` plus the `/item` route still belong in your app.

## Included demos

This seed project ships with a few examples that show Seablast patterns in practice and can be removed in a real app:

- `/blog-e` and `/blog-r`: localised blog demo built on top of `seablast/i18n`
- `/api/mirror` and `/use-mirror`: simple JSON API plus a server-side Guzzle caller
- `/redir`: redirect example
- `/arithmetic`: form + Latte + DB persistence example
- `/`: placeholder homepage scaffold

<!-- SCAFFOLDIFY:REMOVE-END dist-demo -->

## Writable directories

- `cache/` and `log/` must be writable by the web server
- app-specific upload directories should follow the same pattern
- permissions are expected to be `2775`
- owner should be the server user, group should be the web user
- run [permissions.sh](permissions.sh) to apply the documented permissions
- keep `upload_max_filesize` in `php.ini` aligned with your app needs

## Security and web server notes

- HTTPS enforcement should happen at the web server or reverse proxy level.
- `.htaccess` rewrites app requests to the Seablast front controller and blocks direct web access to folders such
  as `cache/`, `conf/`, `log/`, `src/`, `tests/`, and `views/`.
- `.htaccess` also hides repository metadata and text/config files such as `*.md`, `*.sh`, and `composer.*`.

Example Apache HTTP-to-HTTPS redirect:

```htaccess
<VirtualHost *:80>
        RewriteEngine On
        RewriteCond %{REQUEST_URI} !^/server-status.*
        RewriteRule ^/?(.*) https://%{SERVER_NAME}/$1 [R,L]
</VirtualHost>
```

## Project layout

| Directory  | Description                                                             |
| ---------- | ----------------------------------------------------------------------- |
| `.github/` | Automations and linting configuration                                   |
| `assets/`  | Frontend assets placeholder; split into subdirectories as the app grows |
| `cache/`   | Latte cache and other runtime cache                                     |
| `conf/`    | Seablast app config, local config templates, Phinx, PHPStan, migrations |
| `log/`     | Runtime logs                                                            |
| `src/`     | Application classes, especially `src/Models/`                           |
| `tests/`   | PHPUnit scaffold; currently almost empty                                |
| `views/`   | App Latte templates                                                     |
