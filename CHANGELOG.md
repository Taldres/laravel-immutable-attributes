# Release Notes

## [Unreleased](https://github.com/taldres/laravel-immutable-attributes/commits/main/compare/v1.0.0...HEAD)

- `#[Immutable]` with the `GuardsImmutableAttributes` trait: listed attributes are set on insert and throw `ImmutableAttributeException` on any later change
- `#[ImmutableModel]` makes the whole model immutable once it exists; `#[Immutable]` needs at least one column, and a model declared without one fails when it boots with `InvalidImmutableColumnsException`
- Columns merge across parent models and the traits they use
- Subclasses of both attributes work as named presets
- `getImmutableAttributes()` can be overridden to decide at runtime
- Guards saves without events and changes made by later `updating` listeners, as well as `increment()` and `decrement()` on a model

## [v1.0.0](https://github.com/taldres/laravel-immutable-attributes/commits/main/compare/main...v1.0.0) - 2026-10-09

<!-- Release notes generated using configuration in .github/release.yml at main -->
**Full Changelog**: https://github.com/Taldres/laravel-immutable-attributes/commits/v1.0.0
