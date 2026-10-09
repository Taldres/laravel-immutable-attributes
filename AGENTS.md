# Laravel Immutable Attributes

This repository is a Laravel package. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, documentation, and examples aligned with `taldres/laravel-immutable-attributes`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs and documentation promises.

## Project Rules

- Code, comments, commit messages and docs are in English. Comments only explain a non-obvious why.
- The package is a trait, two attributes and two exceptions. It has no service provider, config, migrations or routes; keep it that way unless a feature cannot work without one.
- The guard covers the model's save path only. Query builder writes stay unguarded on purpose, as the escape hatch for deliberate corrections.
- Every write path the README lists as guarded or not guarded has a test that pins it.
- Write the implementation from Laravel's own APIs. Do not copy code from other immutability packages or from closed framework pull requests.

## Attribution

- Do not add `Co-authored-by:` trailers to commits.
- Do not add any other AI attribution, such as "Generated with ..." lines, to commit messages or pull request descriptions.
- Commits are authored by the configured git user only.

## Quick Commands

- Full validation: `composer test`
- Formatting check: `composer lint:check`
- Static analysis: `composer analyse`
- Pest tests: `composer test:unit`
- Workbench build: `composer build`
- Workbench server: `composer serve`

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4/5 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
- `package-generate-skill`: use when updating the bundled Boost skill from the package implementation, README, and examples.
