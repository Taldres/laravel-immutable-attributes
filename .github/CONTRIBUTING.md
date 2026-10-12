# Contribution Guide

Thank you for considering contributing to Laravel Immutable Attributes! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Scope

This package deliberately stays small: it guards attributes on the Eloquent save
path and nothing else. Auditing, versioning, approval workflows or guards on
query builder writes are out of scope; build them as separate packages.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Add tests for behavior changes.
- Title the pull request with one clear sentence of 72 characters or fewer that says what changes for users; it becomes a line of the release notes. Leave out prefixes such as `fix:`.
- Do not edit `CHANGELOG.md`. It is written when a release is published, from the titles of the pull requests, so a pull request needs no version.
- Label the pull request with one of `breaking`, `enhancement`, `bug`, `documentation`, `dependencies` or `maintenance`.
- Keep code, comments, and docs in English.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

```bash
composer lint
```

## Tests

Run everything CI runs, static analysis, lint, type coverage and Pest:

```bash
composer test
```

The suite runs on SQLite. The `databases` workflow runs it on MySQL, MariaDB and
PostgreSQL as well, because the drivers read column names differently. To try
one locally, start a server and point Laravel at it:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=laravel DB_USERNAME=forge DB_PASSWORD=password vendor/bin/pest
```

## Releasing

Releases follow the [Laravel package skeleton](https://github.com/laravel/package-skeleton). `.github/release.yml`, which groups the generated release notes by pull request label, and `.github/workflows/update-changelog.yml`, which copies them into `CHANGELOG.md`, are unchanged from the skeleton. Compare them with it when the skeleton changes.

1. Make sure `main` is green.
2. Choose the version with [SemVer](http://semver.org/): major for a breaking change, minor for a new feature, patch for a fix.
3. On GitHub, draft a new release, create the tag `vX.Y.Z` on `main` and generate the release notes. Read the generated list before publishing: every line should make sense to a user, and a weak line is fixed by retitling its pull request and generating the notes again. If a pull request changed behavior, add a short "Upgrade notes" section above the generated list, taken from its "Behavior changes". Publish. GitHub creates the tag; do not tag by hand.
4. The `update changelog` workflow adds the release to `CHANGELOG.md` in a commit on `main`. Unreleased stays empty, so there is nothing to move.
