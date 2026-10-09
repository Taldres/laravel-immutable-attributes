---
name: laravel-immutable-attributes-development
description: >
  Protect Eloquent columns that must not change after insert with
  taldres/laravel-immutable-attributes: declare them with #[Immutable], handle
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

- `#[Immutable]` without arguments, or `#[Immutable('*')]`, guards every
  attribute of an existing row; an empty list, `#[Immutable([])]`, guards nothing
- both the attribute and the trait are required; the attribute alone does nothing
- `#[Immutable]` on a parent model or on a trait the model uses is merged in
- override `public function getImmutableAttributes(): array` only when the list
  depends on runtime state; return `['*']` for the whole model

### 3. Handle violations

- a change throws `Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException`
  (a `RuntimeException`) before the update query runs, with `model`, `key` and
  `attributes` properties
- the model keeps its unsaved changes; `refresh()` discards them

### 4. Deliberate corrections

- write through the query builder, which the guard does not cover:
  `Invoice::query()->whereKey($id)->update(['number' => $corrected])`
- keep such writes in one explicit place (an action, command or migration)

## Examples

- an order whose `user_id` and `placed_at` must never move to another customer or day
- an activity log where rows are only ever inserted: `#[Immutable]`

## Anti-patterns

- do not add an `updating` listener that re-implements the check
- do not catch `ImmutableAttributeException` to retry the same save
- do not route normal application writes through the query builder to get around the guard
- do not rely on the guard for `incrementQuietly()`, `decrementQuietly()`, mass updates or deletes; it does not cover them
