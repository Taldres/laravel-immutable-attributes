---
name: laravel-immutable-attributes-development
description: >
  Protect Eloquent columns that must not change after insert with
  taldres/laravel-immutable-attributes: declare them with #[Immutable] or
  #[ImmutableModel], handle
  ImmutableAttributeException, and make deliberate corrections through the
  query builder.
license: MIT
metadata:
  author: Dennis Petersmann
---

# Laravel Immutable Attributes

Use this skill when a Laravel application has model attributes that are written
once and must never change afterwards, such as invoice numbers, owners, consent
texts or append-only log rows, and uses `taldres/laravel-immutable-attributes`.

## Primary Goal

- declare immutable attributes on the model in the smallest correct way and keep
  every application write path compatible with them

## Workflow

### 1. Find the attributes

- identify the columns that must keep their insert value, and whether the whole
  table is append-only
- search the codebase for writes to those columns on existing models (`update`,
  assignments followed by `save`, `increment`, observers) before adding the guard

### 2. Declare them on the model

```php
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

#[Immutable('number', 'customer_id', 'issued_at')]
class Invoice extends Model
{
    use GuardsImmutableAttributes;
}
```

- `#[ImmutableModel]` guards every attribute: the row is never updated again,
  not even by `touch()` or `restore()`; deleting stays possible
- `#[Immutable]` needs at least one column and rejects `'*'`; a model declared
  that way fails when it boots with `InvalidImmutableColumnsException`, so use
  `#[ImmutableModel]` for the whole model
- the trait is required; an attribute alone does nothing
- `#[Immutable]` and `#[ImmutableModel]` on a parent model or on a trait the
  model uses are merged in
- override `public function getImmutableAttributes(): array` only when the list
  depends on runtime state; decide on the stored state with `getOriginal()`, not
  on unsaved attributes, and return `['*']` for the whole model

### 3. Handle violations

- a change throws `Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException`
  (a `RuntimeException`) before the update query runs, with `model`, `key` and
  `attributes` properties
- the model keeps its unsaved changes; `refresh()` discards them, and until then
  every save of that model throws, including an `increment()` of another column

### 4. Deliberate corrections

- write through the query builder, which the guard does not cover:
  `Invoice::query()->whereKey($id)->update(['number' => $corrected])`
- keep such writes in one explicit place (an action, command or migration)

## Examples

- an order whose `user_id` and `placed_at` must never move to another customer or day
- an activity log where rows are only ever inserted: `#[ImmutableModel]`

## Anti-patterns

- do not add an `updating` listener that re-implements the check
- do not catch `ImmutableAttributeException` to retry the same save
- do not route normal application writes through the query builder to get around the guard
- do not rely on the guard for `incrementQuietly()`, `decrementQuietly()`, increments inside `Model::withoutEvents()`, mass updates, `$touches` or deletes; it does not cover them
- do not override `getDirtyForUpdate()` on a guarded model without aliasing the trait method and calling it; `parent::getDirtyForUpdate()` skips the guard
