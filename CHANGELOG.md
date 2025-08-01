# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- new application based on Seablast for PHP/0.2.9

### `Added` for new features

- local nav.latte for menu
- i18n module

### `Changed` for changes in existing functionality

- PHPUnit tests folder renamed according to a common convention
- Models moved to src folder for better compatibility

### `Deprecated` for soon-to-be removed features

### `Removed` for now removed features

### `Fixed` for any bugfixes

- only src folder in the root should be hidden from the web server access
- Scheduled GitHub Action runs do not commit-changes automatically to the same branch

### `Security` in case of vulnerabilities

- return 404 and thus hide all the files in any directory that have no filename but only an extension (like .prettierignore)

## [0.1.3] - 2025-03-09

chore: GitHub Actions chaining

### Changed

- GitHub Actions chained in polish-the-code.yml (replaced linter.yml, php-composer-dependencies.yml, prettier-fix.yml and phpcbf.yml)
- models folder to more universal src/Models
- blast.sh - Management script for deployment and development of a Seablast application (instead of assemble.sh)

### Fixed

- changed .htaccess directive for Apache 2.2 `Order Allow,Deny\nDeny from all` to Apache 2.4 `Require all denied` to return 403 (instead of 500)

## [0.1.2] - 2024-12-05

### Added

- prettier-fix

## [0.1.1] - 2023-06-17

### Changed

- Guzzle instead of seablast/brief-api-client
- GitHub workflows uses: WorkOfStan/seablast-actions instead of WorkOfStan/MyCMS

### Fixed

- fixed .htaccess , so that index.php redirect isn't necessary

### Security

- security: - Check: CKV2_GHA_1: "Ensure top-level permissions are not set to write-all" content read/write

## [0.1] - 2023-12-30

- new application based on Seablast for PHP/0.2.4
- demonstrate API
- demonstrate redirection

[Unreleased]: https://github.com/WorkOfStan/seablast-dist/compare/v0.1.3...HEAD?w=1
[0.1.3]: https://github.com/WorkOfStan/seablast-dist/compare/v0.1.2...v0.1.3?w=1
[0.1.2]: https://github.com/WorkOfStan/seablast-dist/compare/v0.1.1...v0.1.2?w=1
[0.1.1]: https://github.com/WorkOfStan/seablast-dist/compare/v0.1...v0.1.1?w=1
[0.1]: https://github.com/WorkOfStan/seablast-dist/releases/tag/v0.1
