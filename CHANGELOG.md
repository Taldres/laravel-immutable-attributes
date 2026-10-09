# Release Notes

## [Unreleased](https://github.com/taldres/laravel-immutable-attributes/commits/main)

- `#[Immutable]` with the `GuardsImmutableAttributes` trait: listed attributes are set on insert and throw `ImmutableAttributeException` on any later change
- `#[ImmutableModel]` makes the whole model immutable once it exists; `#[Immutable]` needs at least one column, and a model declared without one fails when it boots with `InvalidImmutableColumnsException`
- Columns merge across parent models and the traits they use
- Subclasses of both attributes work as named presets
- `getImmutableAttributes()` can be overridden to decide at runtime
- Guards saves without events and changes made by later `updating` listeners, as well as `increment()` and `decrement()` on a model
