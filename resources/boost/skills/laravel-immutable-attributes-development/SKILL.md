---
name: laravel-immutable-attributes-development
description: >
  Protect Eloquent columns that must not change after insert with
  taldres/laravel-immutable-attributes: declare them with #[Immutable] or
  #[ImmutableModel], optionally guard Eloquent builder writes with
  GuardsImmutableQueries, handle ImmutableAttributeException, and make
  deliberate corrections through the query builder.
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
  not even by `touch()`, `restore()` or an increment by `0`, which still
  writes its column; deleting stays possible
- `#[Immutable]` needs at least one column and rejects `'*'`; a model declared
  that way fails when it boots with `InvalidImmutableColumnsException`, so use
  `#[ImmutableModel]` for the whole model; `#[ImmutableModel]` takes no columns
  and fails the same way if given one
- `#[ImmutableModel(except: ['views', 'updated_at'])]` guards the whole model
  except the columns named, including columns added later; `except` is named on
  purpose, a positional column is rejected; leave `updated_at` out for counters,
  because every save and increment writes it; `#[Immutable]` wins over `except`
- the trait is required; an attribute alone does nothing
- `#[Immutable]` and `#[ImmutableModel]` on a parent model or on a trait the
  model uses are merged in
- override `public function getImmutableAttributes(): array` only when the list
  depends on runtime state; decide on the stored state with `getOriginal()`, not
  on unsaved attributes, and return `['*']` for the whole model, with `getMutableAttributes()`
  listing what it leaves out; the original is
  the state this instance loaded, so select the deciding column and use a
  transaction with `lockForUpdate()` where another process may finalize the row;
  return column names as non-empty strings without whitespace around them,
  anything else throws
  `InvalidImmutableColumnsException`

### 3. Handle violations

- a change throws `Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException`
  (a `RuntimeException`) before the update query runs, with `model`, `key` and
  `attributes` properties
- the model keeps its unsaved changes; `refresh()` discards them, and until then
  every save of that model throws, including an `increment()` of another column

### 4. Guard Eloquent queries (optional)

- add `use GuardsImmutableQueries;` next to `GuardsImmutableAttributes` when
  mass updates, `upsert()` or relation updates must not write the columns
  either; it needs `GuardsImmutableAttributes` on the model or a parent
- the builder rejects any write of an immutable column, even with the stored
  value, before the query runs and without loading rows; the exception's `key`
  is `null`
- checked: `update()`, `increment()`, `decrement()`, `incrementEach()`,
  `decrementEach()` with extra columns and the update timestamp the Eloquent
  builder adds, `touch()`, `updateFrom()`, `updateOrInsert()` on an existing
  row, and the columns `upsert()` updates on a conflict; inserts, deletes and
  soft deletes pass, a soft delete never rewrites an immutable deletion
  timestamp that is set, and a restore through the builder is checked
- pass the update columns to `upsert()`; without them it updates every column
  given and throws
- with it, an `increment()` on a model that does not exist yet and a relation
  that touches this model when `updated_at` is immutable throw as well; the
  child is saved first, so use a transaction or `Model::withoutTouchingOn()`
- a runtime `getImmutableAttributes()` is asked on a new, empty model, even for
  `toQuery()`, so a rule based on `getOriginal()` sees a row that is not finalized
- `DB::table()`, `toBase()`, `getQuery()` and raw SQL are never checked
- a custom builder must extend `Taldres\ImmutableAttributes\Builders\ImmutableBuilder`
  or use `Taldres\ImmutableAttributes\Concerns\GuardsImmutableWrites`; name it
  with `protected static string $builder`, `#[UseEloquentBuilder]` or
  `HasBuilder` (with `GuardsImmutableQueries::newEloquentBuilder insteadof HasBuilder`);
  a builder without the guard throws a `LogicException` on the first query

### 5. Deliberate corrections

- write through `DB::table('invoices')`, which no guard covers, or through
  `Invoice::query()` when the model does not use `GuardsImmutableQueries`:
  `DB::table('invoices')->where('id', $id)->update(['number' => $corrected])`
- keep such writes in one explicit place (an action, command or migration)

## Examples

- an order whose `user_id` and `placed_at` must never move to another customer or day
- an activity log where rows are only ever inserted: `#[ImmutableModel]`

## Anti-patterns

- do not add an `updating` listener that re-implements the check
- do not catch `ImmutableAttributeException` to retry the same save
- do not route normal application writes through the query builder to get around the guard
- without `GuardsImmutableQueries`, do not rely on the guard for mass updates, `$touches` or an `increment()` on a model that does not exist yet, which Laravel runs as a query on the whole table; it does not cover them; deletes are never covered
- without `GuardsImmutableQueries`, do not call `incrementEach()` or `decrementEach()` on a model before Laravel 13.3; Laravel runs them as an unguarded query on the whole table
- do not define `newEloquentBuilder()` on a model that uses `GuardsImmutableQueries`; it throws a `LogicException` when the model boots, so name the builder with `$builder` instead
- do not override a write method such as `update()` in a builder that uses `GuardsImmutableWrites` without aliasing the trait method and calling it; keep its return type
- do not override `getDirtyForUpdate()` on a guarded model without aliasing the trait method and calling it; `parent::getDirtyForUpdate()` skips the guard
- the same applies to `incrementOrDecrement()` and, on Laravel 13.3 and later, `incrementOrDecrementEach()`: the trait overrides them to check the columns an increment writes, even without events, so keep their `int|false` return type
- a key counts as the column it writes, whatever its qualifier or case (`invoices.number` and `NUMBER` count as `number`, `options->currency` as `options`, and a JSON path with a dot such as `note->meta.options` as both `note` and `options`, because PostgreSQL and SQLite write `options`); case counts on PostgreSQL, where `NUMBER` is a different column, and SQLite folds ASCII case only, so `CAFÉ` and `café` stay apart there; `#[Immutable]` itself rejects JSON paths, so declare the whole column
