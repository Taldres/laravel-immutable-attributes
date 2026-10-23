# Release Notes

## [Unreleased](https://github.com/taldres/laravel-immutable-attributes/compare/v1.8.0...main)

## [v1.8.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.7.0...v1.8.0) - 2026-10-23

#### Upgrade notes

- **A JSON path key with a dot now throws when a column it reaches is immutable.** PostgreSQL and SQLite cut a key such as `note->meta.options` at a dot and write `options` instead of `note`. A write like `$invoice->increment('total', 1, ['note->meta.options' => [...]])`, or `Invoice::whereKey($id)->update(['note->meta.options' => [...]])` on a model that uses `GuardsImmutableQueries`, could therefore overwrite an immutable column. The guard now checks every column such a key can write, on every database, MySQL, MariaDB and SQL Server included. Write the JSON path without a dot after `->`, or update the JSON column as a whole.
- Everything else stays as it is: keys without `->`, JSON paths such as `options->currency`, model saves, and `DB::table()`, `toBase()` and raw SQL.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Bug Fixes

* Guard the columns a JSON path with a dot writes by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/46

#### Documentation

* Document where the guard ends by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/47

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.7.0...v1.8.0

## [v1.7.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.6.0...v1.7.0) - 2026-10-23

#### Upgrade notes

- **Nothing changes unless a model opts in.** Models that only use `GuardsImmutableAttributes` behave as before, and their query writes such as `Invoice::whereKey($id)->update([...])` stay unguarded.
- **With `GuardsImmutableQueries`, touching a guarded parent can throw.** When `updated_at` is immutable, `$touches` and `belongsToMany()->touch()` reach the parent through its query and throw after the child is saved. Wrap the save in a transaction, leave `updated_at` out of the guard, or use `Model::withoutTouchingOn()`.
- **A custom builder of an opted-in model must guard its writes.** Extend `ImmutableBuilder` or use `GuardsImmutableWrites` in it, otherwise the model throws a `LogicException`. A model that uses Laravel's `HasBuilder` resolves `newEloquentBuilder()` with `insteadof`, as the README shows.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Enhancements

* Guard Eloquent query writes on models that opt in by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/43

#### Maintenance

* Run the weekly tests of every branch from one workflow by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/35
* Open a pull request for the changelog by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/40

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.6.0...v1.7.0

## [v1.6.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.5.0...v1.6.0) - 2026-10-15

#### Upgrade notes

- **A named argument other than `columns:` in `#[Immutable]` now throws.** `#[Immutable('number', except: ['note'])]`, `#[Immutable(column: 'number')]` or `#[Immutable('number', colums: ['total'])]` used to boot and guard the values of those arguments as columns. They now raise `InvalidImmutableColumnsException` when the model boots. Pass the columns positionally or as `columns: [...]`.
- `getImmutableAttributes()` returns the same columns as before, but their order can differ for models that use traits.
- Everything else stays as it is: `#[Immutable('number')]`, `#[Immutable(['number'])]` and `#[Immutable(columns: ['number'])]` work as before.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Bug Fixes

* Reject unknown named arguments in #[Immutable] by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/33

#### Maintenance

* Use Laravel's class helpers and clean up the tests by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/32

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.5.0...v1.6.0

## [v1.5.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.4.1...v1.5.0) - 2026-10-13

#### Upgrade notes

- **An increment by `0` of an immutable column throws.** `increment()`, `decrement()`, their `Each` and `Quietly` variants and the same calls inside `Model::withoutEvents()` or `Model::withoutTimestamps()` now throw `ImmutableAttributeException` when they name an immutable column, whatever the amount. An increment by `0` still writes its column, and the database turns the value into a number, so `001` in a text column became `1` without an exception. To change such a column on purpose, write through the query builder or allow it with `except` on `#[ImmutableModel]`.
- **On SQLite, a column left out matches another column only by ASCII case.** SQLite treats `café` and `CAFÉ` as two columns, so `#[ImmutableModel(except: ['café'])]` no longer leaves out `CAFÉ` as well. The same applies to entries returned by `getImmutableAttributes()` and `getMutableAttributes()`.
- **Unchanged:** increments of mutable columns, the update timestamp an increment writes, and how column names are matched on MySQL, MariaDB and PostgreSQL.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Bug Fixes

* Guard increments by zero and SQLite columns beyond ASCII case by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/27

#### Dependencies

* build(deps): bump shivammathur/setup-php from 2.37.2 to 2.40.0 by @dependabot[bot] in https://github.com/Taldres/laravel-immutable-attributes/pull/25

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.4.1...v1.5.0

## [v1.4.1](https://github.com/taldres/laravel-immutable-attributes/compare/v1.3.0...v1.4.1) - 2026-10-12

#### Upgrade notes

- **A column name with whitespace around it now throws.** `#[Immutable(' number')]`, a name with a trailing space, a tab or a non-breaking space, in `#[Immutable]`, in the `except` of `#[ImmutableModel]` or in the list of a `getImmutableAttributes()` or `getMutableAttributes()` override used to boot and guard nothing for that name, because it never matched the column. It now raises `InvalidImmutableColumnsException` when the model boots, or when the list of an override is used. The message names the class and shows the name with its escapes, so that a space that cannot be seen is visible. Remove the whitespace.
- Names without whitespace around them behave as before. A space inside a name, as in `num ber`, is still a name.
- **There is no 1.4.0.** It was cut from the wrong branch and withdrawn, and Packagist does not take a version number again after it was removed. 1.4.1 carries the changes that 1.4.0 was meant to have.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Bug Fixes

* Reject column names with whitespace around them by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/20

#### Maintenance

* Let main call the test workflows of 1.x by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/18

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.3.0...v1.4.1

## [v1.3.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.2.0...v1.3.0) - 2026-10-12

### Upgrade notes

- **Increments without events are guarded.** `incrementQuietly()`, `decrementQuietly()` and `increment()` or `decrement()` inside `Model::withoutEvents()` used to be a way around the guard, and so were the `*Each` variants and their `Quietly` variants where Laravel has them. They now throw `ImmutableAttributeException` for an immutable column, like the same call with events does. To change such a column on purpose, write through the query builder, `Invoice::query()->whereKey($id)->update([...])`, or allow the column explicitly with `except` on `#[ImmutableModel]`.
- **A rejected increment leaves the model as it was.** The check runs before the increment, so the model no longer holds the incremented value after the exception.
- **Removed:** the protected property `$pendingIncrementColumns`, which 1.1.0 added to guarded models. Nothing in the package reads it any more.
- **Unchanged and now documented:** `increment()` on a model that does not exist yet stays a query on the whole table, as in Laravel itself, and is not guarded.

<!-- Release notes generated using configuration in .github/release.yml at 1.x -->
### What's Changed

#### Bug Fixes

* Guard increments and decrements that fire no events by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/10

#### Documentation

* Document increments on a model that does not exist yet by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/9
* Document releases from a maintenance branch by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/14

#### Maintenance

* Update the changelog of the branch that was released by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/13

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.2.0...v1.3.0

## [v1.2.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.1.0...v1.2.0) - 2026-10-12

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Enhancements

* Leave columns out of #[ImmutableModel] with except by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/7

#### Documentation

* Add a pull request skill and document the release steps by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/6

#### Maintenance

* Run the tests and databases workflows weekly by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/8

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.1.0...v1.2.0

## [v1.1.0](https://github.com/taldres/laravel-immutable-attributes/compare/v1.0.0...v1.1.0) - 2026-10-12

### Changed

- `#[Immutable]` rejects empty names, non-strings, nested lists and JSON paths such as `options->currency`: a model declared that way now fails when it boots with `InvalidImmutableColumnsException` instead of guarding nothing for them. A value of the wrong type, such as `null` or an integer, raises the same exception instead of a `TypeError`
- `#[ImmutableModel]` rejects columns, which it used to drop silently, and a `getImmutableAttributes()` override that returns anything but column names throws `InvalidImmutableColumnsException`, naming the model, instead of a `TypeError`
- `increment()`, `decrement()` and, on Laravel 13.3 and later, `incrementEach()` and `decrementEach()` check every column their query writes: the incremented columns, the extra columns and the update timestamp. An immutable extra column throws even with its current value, and under `#[ImmutableModel]` an increment by `0` throws on a model with timestamps, because it still writes `updated_at`

### Fixed

- A cast or setter can no longer hide a change that an increment writes to an immutable column
- A key counts as the column it writes, whatever its qualifier, so `invoices.number` or `options->currency` no longer bypass the guard, and neither does `NUMBER` outside PostgreSQL, where case keeps columns apart; the exception names the attribute as declared

### Documentation

- Documents the limits of deciding at runtime with `getOriginal()`, and that `incrementEach()` and `decrementEach()` before Laravel 13.3 run as a query on the whole table

<!-- Release notes generated using configuration in .github/release.yml at main -->
### What's Changed

#### Bug Fixes

* Guard the columns an increment writes and match keys as columns by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/5

#### Other Changes

* docs: replace the README banner with the new GitHub banner by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/1
* docs: use the new banner in the README by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/2
* build: exclude paratest 7.26.1 until Pest supports it by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/4
* docs: add the Packagist downloads badge to the README by @Taldres in https://github.com/Taldres/laravel-immutable-attributes/pull/3

### New Contributors

* @Taldres made their first contribution in https://github.com/Taldres/laravel-immutable-attributes/pull/1

**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/compare/v1.0.0...v1.1.0

## [v1.0.0](https://github.com/taldres/laravel-immutable-attributes/releases/tag/v1.0.0) - 2026-10-09

- `#[Immutable]` with the `GuardsImmutableAttributes` trait: listed attributes are set on insert and throw `ImmutableAttributeException` on any later change
- `#[ImmutableModel]` makes the whole model immutable once it exists; `#[Immutable]` needs at least one column, and a model declared without one fails when it boots with `InvalidImmutableColumnsException`
- Columns merge across parent models and the traits they use
- Subclasses of both attributes work as named presets
- `getImmutableAttributes()` can be overridden to decide at runtime
- Guards saves without events and changes made by later `updating` listeners, as well as `increment()` and `decrement()` on a model

<!-- Release notes generated using configuration in .github/release.yml at main -->
**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/commits/v1.0.0
