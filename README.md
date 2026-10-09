![Laravel Immutable Attributes — protect individual Eloquent columns from changes](.github/assets/laravel-immutable-attributes-social-preview.jpg)

# Laravel Immutable Attributes

> **Columns that are set once and never change: declare them on the Eloquent model, and every save that would alter them throws.**

![Tests](https://img.shields.io/github/actions/workflow/status/taldres/laravel-immutable-attributes/tests.yml?label=tests)
![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen)
![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue)
![License](https://img.shields.io/badge/license-MIT-lightgrey)

```php
#[Immutable('number', 'customer_id', 'issued_at')]
class Invoice extends Model
{
    use GuardsImmutableAttributes;
}

$invoice->number = 'INV-999';
$invoice->save(); // ImmutableAttributeException, nothing is written
```

## Why this package?

Some values must stay what they were when the row was written: an invoice
number that is already on a sent PDF, the owner of an order, the text a person
consented to. When code changes one of them by mistake, nothing fails. The row
looks normal, and the damage shows up much later.

- **`$guarded` does not help.** It only stops mass assignment; `$invoice->number = ...` still goes through
- **One declaration instead of a listener per model.** The usual fix, an `updating` listener that checks `isDirty()`, ends up copied into every model that needs it
- **Covers the paths a listener misses.** Saves without events (`saveQuietly()`, `withoutEvents()`) and changes made by a later `updating` listener or observer are caught too
- **Inserts are untouched.** The attributes are set freely until the row exists
- **Same value is not a change.** The check uses Eloquent's own dirty tracking, casts included

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

```bash
composer require taldres/laravel-immutable-attributes
```

There is nothing to publish or configure.

## Usage

Add the trait and list the immutable attributes with `#[Immutable]`:

```php
use Illuminate\Database\Eloquent\Model;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;

#[Immutable('number', 'customer_id', 'issued_at')]
class Invoice extends Model
{
    use GuardsImmutableAttributes;
}
```

`#[Immutable(['number', 'customer_id'])]` works as well. It needs at least one
column and does not take `'*'`; a model declared that way fails when it boots
with an `InvalidImmutableColumnsException` that names the class carrying the
attribute.

### The whole model

`#[ImmutableModel]` guards every attribute: once a row exists it is never
updated again, not even by `touch()` or `restore()`. Deleting stays possible.
That suits tables whose rows are only ever added, such as logs or ledgers:

```php
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

#[ImmutableModel]
class LedgerEntry extends Model
{
    use GuardsImmutableAttributes;
}
```

### Inheritance and presets

Declarations stack: `#[Immutable]` and `#[ImmutableModel]` on a parent model, on
the model itself, and on any trait they use are merged, so a child model can add
attributes but never release one.

Both attributes can be extended for a named preset. The subclass needs its own
`#[Attribute]` marker:

```php
use Attribute;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;

#[Attribute(Attribute::TARGET_CLASS)]
class Ledger extends ImmutableModel
{
    //
}
```

### Deciding at runtime

Override `getImmutableAttributes()` and return the guarded attributes, or `['*']`
for all of them, as Laravel's `getGuarded()` does. Base the decision on the
stored state with `getOriginal()`, not on unsaved attributes; otherwise the save
that finalizes the row is already checked against the stricter list and throws:

```php
public function getImmutableAttributes(): array
{
    return $this->getOriginal('finalized_at') !== null ? ['*'] : ['number'];
}
```

### The exception

A violation throws `Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException`,
a `RuntimeException`, before the update query runs:

```php
try {
    $invoice->update(['number' => 'INV-999', 'paid' => true]);
} catch (ImmutableAttributeException $e) {
    $e->model;      // App\Models\Invoice
    $e->key;        // 42
    $e->attributes; // ['number']
}
```

The model keeps its unsaved changes; call `$invoice->refresh()` to discard them.
Until then every save of that model throws, including an `increment()` of
another column.

### What is guarded

| Guarded | Not guarded |
| --- | --- |
| `save()`, `update()`, `push()` | Query builder writes: `Invoice::whereKey($id)->update([...])`, `upsert()`, raw queries |
| `fill()` and `forceFill()` followed by a save | `incrementQuietly()` and `decrementQuietly()`, which fire no events and write their own query |
| `saveQuietly()`, `updateQuietly()` and saves inside `Model::withoutEvents()` | `increment()` and `decrement()` inside `Model::withoutEvents()`, for the same reason |
| Changes made by `updating` listeners and observers | `incrementEachQuietly()` and `decrementEachQuietly()` |
| `increment()` and `decrement()`, including their extra columns | Deleting and soft deleting |
| `incrementEach()` and `decrementEach()` on Laravel 13.3 and later | Parent timestamps touched through `$touches` |
| `touch()` when the touched column is immutable, such as `updated_at` under `#[ImmutableModel]` | |
| `restore()` when `deleted_at` is immutable, such as under `#[ImmutableModel]` | |

The query builder stays open on purpose: it is the escape hatch for deliberate
corrections, data migrations and erasure.

An attribute that was not selected, as with `select('id', 'paid')`, counts as
changed once you set it, even to the stored value.

The check for saves without events lives in `getDirtyForUpdate()`. If the model
overrides that method, PHP uses the model's method instead of the trait's, and
`parent::getDirtyForUpdate()` skips the trait, so the check is gone. Alias the
trait method and call it instead:

```php
use GuardsImmutableAttributes {
    getDirtyForUpdate as protected guardedDirtyForUpdate;
}

protected function getDirtyForUpdate(): array
{
    $dirty = $this->guardedDirtyForUpdate();

    // Your own adjustments.

    return $dirty;
}
```

## AI agents

The package ships a [Laravel Boost](https://github.com/laravel/boost) skill,
`laravel-immutable-attributes-development`. Run `php artisan boost:install` and
select `taldres/laravel-immutable-attributes` among the third-party packages. A
non-interactive install only picks up packages listed under `packages` in
`boost.json`.

## Development

Built on the [Laravel package skeleton](https://github.com/laravel/package-skeleton):

```bash
composer test       # PHPStan level 10, Pint, type coverage, Pest
composer lint       # Pint
```

See [CONTRIBUTING](.github/CONTRIBUTING.md) and the [changelog](CHANGELOG.md).

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## License

MIT. See [LICENSE.md](LICENSE.md).
