# Release Notes

## [Unreleased](https://github.com/taldres/laravel-immutable-attributes/compare/v1.1.0...HEAD)

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
