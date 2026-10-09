# Release Notes

## [Unreleased](https://github.com/taldres/laravel-immutable-attributes/commits/main)

- `#[Immutable]` with the `GuardsImmutableAttributes` trait: listed attributes are set on insert and throw `ImmutableAttributeException` on any later change
- `#[Immutable]` without arguments makes the whole model immutable once it exists
- Columns merge across parent models and the traits they use
- Subclasses of `Immutable` work as presets, such as `#[AppendOnly]`
- `getImmutableAttributes()` can be overridden to decide at runtime
- Guards saves without events and changes made by later `updating` listeners, as well as `increment()` and `decrement()` on a model
