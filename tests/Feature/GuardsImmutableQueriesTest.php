<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Taldres\ImmutableAttributes\Builders\ImmutableBuilder;
use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\CountingBuilder;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\ForeignBuilder;
use Taldres\ImmutableAttributes\Tests\Fixtures\Builders\InvoiceBuilder;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\AttributedBuilderInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\BuiltInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\CountingBuiltInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\ForeignBuiltInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\HasBuilderInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\Invoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\PlainBuiltInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryCountedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryFinalizedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryGuardedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryLockedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryTallyInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\QueryTouchingLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\SelfBuildingInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\SharedBuilderInvoice;

use function Pest\Laravel\assertSoftDeleted;

/**
 * Inserts the row through the query builder, so that no fixture's guard is
 * involved in setting it up.
 *
 * @param  array<string, mixed>  $attributes
 */
function invoiceRow(array $attributes = []): int
{
    return DB::table('invoices')->insertGetId([
        'number' => 'INV-001',
        'customer_id' => 7,
        'issued_at' => '2026-10-01 09:00:00',
        'total' => 1000,
        'options' => json_encode(['currency' => 'EUR']),
        'created_at' => '2026-10-01 09:00:00',
        'updated_at' => '2026-10-01 09:00:00',
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function storedRow(int $id): array
{
    return (array) DB::table('invoices')->where('id', $id)->first();
}

/**
 * Runs the write and expects it to throw before any query reaches the database.
 *
 * @param  list<string>  $attributes
 */
function expectRejectedWrite(Closure $write, string $model, array $attributes): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect($write)->toThrow(function (ImmutableAttributeException $e) use ($model, $attributes): void {
            expect($e->model)->toBe($model)
                ->and($e->key)->toBeNull()
                ->and($e->attributes)->toBe($attributes);
        });

        expect(DB::getQueryLog())->toBe([]);
    } finally {
        DB::disableQueryLog();
    }
}

describe('without the opt-in', function () {
    it('leaves every builder write alone', function (Closure $write) {
        $id = invoiceRow();

        $write(Invoice::query()->whereKey($id), $id);

        expect(storedRow($id)['number'])->toBe('INV-999');
    })->with([
        'update()' => fn (Builder $query) => $query->update(['number' => 'INV-999']),
        'increment() with an extra column' => fn (Builder $query) => $query->increment('total', 1, ['number' => 'INV-999']),
        'decrement() with an extra column' => fn (Builder $query) => $query->decrement('total', 1, ['number' => 'INV-999']),
        'incrementEach() with an extra column' => fn (Builder $query) => $query->incrementEach(['total' => 1], ['number' => 'INV-999']),
        'upsert()' => fn (Builder $query, int $id) => $query->upsert([[
            'id' => $id,
            'number' => 'INV-999',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ]], ['id'], ['number']),
        'updateOrInsert()' => fn (Builder $query, int $id) => $query->updateOrInsert(['id' => $id], ['number' => 'INV-999']),
    ]);

    it('leaves a guarding builder alone on a model that does not opt in', function () {
        $id = invoiceRow();

        expect(SharedBuilderInvoice::query())->toBeInstanceOf(ForeignBuilder::class);

        SharedBuilderInvoice::query()->whereKey($id)->update(['number' => 'INV-999']);

        expect(storedRow($id)['number'])->toBe('INV-999');
    });

    it('keeps Laravel\'s own builder', function () {
        expect(Invoice::query()::class)->toBe(Builder::class);
    });
});

describe('builder writes', function () {
    it('gives the model the guarded builder', function () {
        expect(QueryGuardedInvoice::query())->toBeInstanceOf(ImmutableBuilder::class);
    });

    it('rejects a write of an immutable column before it runs', function (Closure $write, array $attributes) {
        $id = invoiceRow();
        $stored = storedRow($id);

        expectRejectedWrite(fn () => $write(QueryGuardedInvoice::query()->whereKey($id), $id), QueryGuardedInvoice::class, $attributes);

        expect(storedRow($id))->toBe($stored);
    })->with([
        'update()' => [fn (Builder $query) => $query->update(['number' => 'INV-999', 'paid' => true]), ['number']],
        'update() with the stored value' => [fn (Builder $query) => $query->update(['number' => 'INV-001']), ['number']],
        'update() of a qualified column' => [fn (Builder $query) => $query->update(['invoices.number' => 'INV-999']), ['number']],
        'update() of a column qualified with a table alias' => [fn (Builder $query) => $query->from('invoices as i')->update(['i.number' => 'INV-999']), ['number']],
        'update() of a JSON path' => [fn (Builder $query) => $query->update(['options->currency' => 'USD']), ['options']],
        'update() of a JSON path with a dot, which PostgreSQL and SQLite write to the column after the dot' => [fn (Builder $query) => $query->update(['note->meta.options' => ['currency' => 'USD']]), ['options']],
        'update() of a qualified JSON path, which SQLite writes to the column after the first dot' => [fn (Builder $query) => $query->update(['main.options.note->currency' => 'USD']), ['options']],
        'increment()' => [fn (Builder $query) => $query->increment('customer_id'), ['customer_id']],
        'increment() by zero' => [fn (Builder $query) => $query->increment('customer_id', 0), ['customer_id']],
        'decrement()' => [fn (Builder $query) => $query->decrement('customer_id'), ['customer_id']],
        'increment() with an immutable extra column' => [fn (Builder $query) => $query->increment('total', 1, ['number' => 'INV-001']), ['number']],
        'decrement() with an immutable extra column' => [fn (Builder $query) => $query->decrement('total', 1, ['number' => 'INV-001']), ['number']],
        'incrementEach()' => [fn (Builder $query) => $query->incrementEach(['customer_id' => 1, 'total' => 1]), ['customer_id']],
        'decrementEach() with an immutable extra column' => [fn (Builder $query) => $query->decrementEach(['total' => 1], ['number' => 'INV-001']), ['number']],
        'touch() of an immutable column' => [fn (Builder $query) => $query->touch('issued_at'), ['issued_at']],
        'upsert() that updates an immutable column' => [fn (Builder $query, int $id) => $query->upsert([[
            'id' => $id,
            'number' => 'INV-999',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ]], ['id'], ['number', 'total']), ['number']],
        'upsert() that updates a column with a value of its own' => [fn (Builder $query, int $id) => $query->upsert([[
            'id' => $id,
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ]], ['id'], ['customer_id' => 8]), ['customer_id']],
        'upsert() without update columns, which updates every column given' => [fn (Builder $query, int $id) => $query->upsert([
            'id' => $id,
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ], ['id']), ['number', 'customer_id', 'issued_at']],
        'updateFrom()' => [fn (Builder $query) => $query->updateFrom(['number' => 'INV-999']), ['number']],
    ]);

    it('names the attribute as declared when the key differs in case', function () {
        $id = invoiceRow();

        expectRejectedWrite(fn () => QueryGuardedInvoice::query()->whereKey($id)->update(['NUMBER' => 'INV-999']), QueryGuardedInvoice::class, ['number']);
    })->skip(fn () => DB::connection()->getDriverName() === 'pgsql', 'PostgreSQL tells "NUMBER" and "number" apart.');

    it('rejects a write through a relation', function () {
        $id = invoiceRow();
        QueryTouchingLedgerEntry::$parent = QueryGuardedInvoice::class;
        $entry = QueryTouchingLedgerEntry::query()->create(['amount' => 100, 'invoice_id' => $id]);

        expectRejectedWrite(fn () => $entry->invoice()->update(['number' => 'INV-999']), QueryGuardedInvoice::class, ['number']);
        expect(storedRow($id)['number'])->toBe('INV-001');
    });

    it('rejects a write through a collection\'s query', function () {
        invoiceRow();
        invoiceRow(['number' => 'INV-002']);

        $query = QueryGuardedInvoice::query()->get()->toQuery();

        expectRejectedWrite(fn () => $query->update(['number' => 'INV-999']), QueryGuardedInvoice::class, ['number']);
        expect(DB::table('invoices')->orderBy('id')->pluck('number')->all())->toBe(['INV-001', 'INV-002']);
    });

    it('rejects updateOrInsert() of an immutable column on an existing row after Laravel\'s own existence query', function (array|Closure $values) {
        $id = invoiceRow();

        DB::flushQueryLog();
        DB::enableQueryLog();

        expect(fn () => QueryGuardedInvoice::query()->updateOrInsert(['id' => $id], $values))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['number']));
        expect(DB::getQueryLog())->toHaveCount(1)
            ->and(DB::getQueryLog()[0]['query'])->toStartWith('select')
            ->and(storedRow($id)['number'])->toBe('INV-001');

        DB::disableQueryLog();
    })->with([
        'values' => [['number' => 'INV-999']],
        'a closure' => [fn () => fn (bool $exists): array => $exists ? ['number' => 'INV-999'] : ['note' => 'New']],
    ]);

    it('lets mutable columns change', function () {
        $id = invoiceRow();

        QueryGuardedInvoice::query()->whereKey($id)->update(['paid' => true, 'note' => 'Paid by card']);
        QueryGuardedInvoice::query()->whereKey($id)->increment('total', 5, ['note' => 'Adjusted']);
        QueryGuardedInvoice::query()->whereKey($id)->incrementEach(['total' => 5]);
        QueryGuardedInvoice::query()->whereKey($id)->decrement('total', 20);
        QueryGuardedInvoice::query()->whereKey($id)->updateOrInsert(['id' => $id], ['paid' => false]);

        expect(storedRow($id))
            ->total->toBe(990)
            ->note->toBe('Adjusted')
            ->paid->toBeFalsy()
            ->number->toBe('INV-001');
    });

    it('lets touch() write a mutable update timestamp', function () {
        $id = invoiceRow();

        QueryGuardedInvoice::query()->whereKey($id)->touch();

        expect(Carbon::parse(storedRow($id)['updated_at'])->isAfter('2026-10-01 09:00:00'))->toBeTrue();
    });

    it('lets upsert() set immutable columns on new rows and update only the mutable ones on a conflict', function () {
        $id = invoiceRow();

        QueryGuardedInvoice::query()->upsert([
            ['id' => $id, 'number' => 'INV-999', 'customer_id' => 8, 'issued_at' => '2026-10-02 09:00:00', 'total' => 5],
            ['id' => $id + 1, 'number' => 'INV-002', 'customer_id' => 8, 'issued_at' => '2026-10-02 09:00:00', 'total' => 7],
        ], ['id'], ['total']);

        expect(DB::table('invoices')->orderBy('id')->get(['number', 'customer_id', 'total'])->map(fn (object $row): array => (array) $row)->all())->toBe([
            ['number' => 'INV-001', 'customer_id' => 7, 'total' => 5],
            ['number' => 'INV-002', 'customer_id' => 8, 'total' => 7],
        ]);
    });

    it('lets updateOrInsert() set immutable columns on a new row', function (array|Closure $values) {
        QueryGuardedInvoice::query()->updateOrInsert(['number' => 'INV-002'], $values);

        expect(DB::table('invoices')->where('number', 'INV-002')->value('customer_id'))->toBe(8);
    })->with([
        'values' => [['customer_id' => 8, 'issued_at' => '2026-10-02 09:00:00']],
        'a closure' => [fn () => fn (bool $exists): array => $exists ? ['customer_id' => 9] : ['customer_id' => 8, 'issued_at' => '2026-10-02 09:00:00']],
    ]);

    it('lets inserts and deletes through', function () {
        QueryGuardedInvoice::query()->insert(['number' => 'INV-002', 'customer_id' => 8, 'issued_at' => '2026-10-02 09:00:00']);

        expect(QueryGuardedInvoice::query()->where('number', 'INV-002')->delete())->toBe(1)
            ->and(DB::table('invoices')->count())->toBe(0);
    });

    it('checks the columns that the whole model leaves out', function () {
        $id = invoiceRow();

        QueryCountedInvoice::query()->whereKey($id)->update(['total' => 5, 'note' => 'Counted']);
        QueryCountedInvoice::query()->whereKey($id)->increment('total');

        expectRejectedWrite(fn () => QueryCountedInvoice::query()->whereKey($id)->update(['note' => 'Changed', 'paid' => true]), QueryCountedInvoice::class, ['paid']);
        expect(storedRow($id))->total->toBe(6)->note->toBe('Counted');
    });

    it('checks a runtime list on a new model, since the rows are not loaded', function () {
        $id = invoiceRow(['note' => 'final']);

        QueryFinalizedInvoice::query()->whereKey($id)->update(['note' => 'reopened']);

        expectRejectedWrite(fn () => QueryFinalizedInvoice::query()->whereKey($id)->update(['number' => 'INV-999']), QueryFinalizedInvoice::class, ['number']);
        expect(storedRow($id)['note'])->toBe('reopened');
    });

    it('asks a runtime list on a new model whichever loaded model the query starts from', function (array $notes) {
        foreach ($notes as $number => $note) {
            invoiceRow(['number' => $number, 'note' => $note]);
        }

        QueryFinalizedInvoice::query()->orderBy('id')->get()->toQuery()->update(['paid' => true]);

        expect(DB::table('invoices')->where('paid', true)->count())->toBe(2);
    })->with([
        'a draft first' => [['INV-001' => 'draft', 'INV-002' => 'final']],
        'a final row first' => [['INV-001' => 'final', 'INV-002' => 'draft']],
    ]);
});

describe('the update timestamp', function () {
    it('counts the update timestamp that the Eloquent builder adds', function (Closure $write) {
        $id = invoiceRow();

        expectRejectedWrite(fn () => $write(QueryTallyInvoice::query()->whereKey($id), $id), QueryTallyInvoice::class, ['updated_at']);
    })->with([
        'update()' => fn (Builder $query) => $query->update(['total' => 5]),
        'increment()' => fn (Builder $query) => $query->increment('total'),
        'decrement()' => fn (Builder $query) => $query->decrement('total'),
        'touch()' => fn (Builder $query) => $query->touch(),
        'upsert()' => fn (Builder $query, int $id) => $query->upsert([['id' => $id, 'number' => 'INV-001', 'customer_id' => 7, 'issued_at' => '2026-10-01 09:00:00', 'total' => 5]], ['id'], ['total']),
        'upsert() with no update columns, which still writes the timestamp' => fn (Builder $query, int $id) => $query->upsert([['id' => $id, 'number' => 'INV-001', 'customer_id' => 7, 'issued_at' => '2026-10-01 09:00:00']], ['id'], []),
    ]);

    it('lets the write through without timestamps', function () {
        $id = invoiceRow();

        Model::withoutTimestamps(fn () => QueryTallyInvoice::query()->whereKey($id)->increment('total'));

        expect(storedRow($id))->total->toBe(1001)->updated_at->toBe('2026-10-01 09:00:00');
    });

    // @todo Laravel 13.3: Drop the second case once composer.json requires ^13.3.
    it('counts the update timestamp of incrementEach() once the Eloquent builder writes it', function () {
        $id = invoiceRow();

        expectRejectedWrite(fn () => QueryTallyInvoice::query()->whereKey($id)->incrementEach(['total' => 1]), QueryTallyInvoice::class, ['updated_at']);
    })->skip(! method_exists(Builder::class, 'incrementEach'), 'The Eloquent builder writes the timestamp in incrementEach() since Laravel 13.3.');

    it('lets incrementEach() through before the Eloquent builder wrote the update timestamp', function () {
        $id = invoiceRow();

        QueryTallyInvoice::query()->whereKey($id)->incrementEach(['total' => 1]);

        expect(storedRow($id)['total'])->toBe(1001);
    })->skip(method_exists(Builder::class, 'incrementEach'), 'The Eloquent builder writes the timestamp in incrementEach() since Laravel 13.3.');
});

describe('soft deleting', function () {
    it('lets the model and the builder soft delete and force delete', function () {
        $first = QueryLockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);
        $second = QueryLockedInvoice::query()->create(['number' => 'INV-002', 'customer_id' => 7, 'issued_at' => now()]);

        $first->delete();
        QueryLockedInvoice::query()->whereKey($second->id)->delete();

        assertSoftDeleted($first);
        assertSoftDeleted($second);

        $first->forceDelete();
        QueryLockedInvoice::withTrashed()->whereKey($second->id)->forceDelete();

        expect(DB::table('invoices')->count())->toBe(0);
    });

    it('rejects restoring through the builder when the deletion timestamp is immutable', function () {
        $invoice = QueryLockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);
        $invoice->delete();

        expectRejectedWrite(fn () => QueryLockedInvoice::withTrashed()->whereKey($invoice->id)->restore(), QueryLockedInvoice::class, ['deleted_at', 'updated_at']);
        assertSoftDeleted($invoice);
    });

    it('counts a deletion timestamp written with another column as a write', function () {
        $id = invoiceRow();

        expectRejectedWrite(fn () => QueryLockedInvoice::query()->whereKey($id)->update(['deleted_at' => now(), 'note' => 'Gone']), QueryLockedInvoice::class, ['deleted_at', 'note', 'updated_at']);
    });

    it('keeps an immutable deletion timestamp that is set already, whatever the query selects', function (Closure $select, Closure $softDelete) {
        $deleted = invoiceRow(['deleted_at' => '2026-10-02 09:00:00']);
        $live = invoiceRow(['number' => 'INV-002']);
        DB::table('ledger_entries')->insert([['invoice_id' => $deleted, 'amount' => 1], ['invoice_id' => $live, 'amount' => 1]]);

        $affected = $softDelete($select(QueryLockedInvoice::withTrashed(), $deleted, $live));

        expect($affected)->toBe(1)
            ->and(storedRow($deleted)['deleted_at'])->toBe('2026-10-02 09:00:00')
            ->and(storedRow($live)['deleted_at'])->not->toBeNull();
    })->with([
        'a list of keys' => fn (Builder $query, int $deleted, int $live) => $query->whereKey([$deleted, $live]),
        'an orWhere()' => fn (Builder $query, int $deleted, int $live) => $query->whereKey($deleted)->orWhere('id', $live),
        'a nested orWhere()' => fn (Builder $query, int $deleted, int $live) => $query->where(fn (Builder $query) => $query->whereKey($deleted)->orWhere('id', $live))->orWhere('id', $deleted),
        'a whereNot()' => fn (Builder $query, int $deleted) => $query->whereNot('id', 0)->orWhere('id', $deleted),
        'a join' => fn (Builder $query) => $query->join('ledger_entries', 'ledger_entries.invoice_id', '=', 'invoices.id'),
        'a limit' => fn (Builder $query, int $deleted, int $live) => $query->whereKey([$deleted, $live])->limit(5),
    ])->with([
        'delete()' => fn (Builder $query) => $query->delete(),
        'update() of the qualified column, as a join needs it' => fn (Builder $query) => $query->update(['invoices.deleted_at' => '2000-01-01 00:00:00']),
    ]);

    it('soft deletes through a builder with a table alias', function (bool $withTrashed) {
        $deleted = invoiceRow(['deleted_at' => '2026-10-02 09:00:00']);
        $live = invoiceRow(['number' => 'INV-002']);

        $query = $withTrashed ? QueryLockedInvoice::withTrashed() : QueryLockedInvoice::query();
        $query->from('invoices as i')->delete();

        expect(storedRow($deleted)['deleted_at'])->toBe('2026-10-02 09:00:00')
            ->and(storedRow($live)['deleted_at'])->not->toBeNull();
    })->with(['without trashed rows' => false, 'with trashed rows' => true])
        ->skip(fn () => ! method_exists(Builder::class, 'getTableAlias'), 'This Laravel version does not qualify soft deletes with a table alias.');

    it('keeps the deletion timestamp when a soft deleted model is deleted again', function () {
        $invoice = QueryLockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);
        $invoice->delete();
        $stored = storedRow($invoice->id)['deleted_at'];

        $this->travel(5)->minutes();
        $invoice->delete();

        expect(storedRow($invoice->id)['deleted_at'])->toBe($stored);
    });
});

describe('the model\'s own writes', function () {
    it('keeps the model guard and its change semantics for saves and increments', function () {
        $invoice = QueryGuardedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        $invoice->update(['paid' => true, 'number' => 'INV-001']);
        $invoice->increment('total', 5);
        $invoice->touch();

        $invoice->number = 'INV-999';

        expect(fn () => $invoice->save())
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->key)->toBe($invoice->id));
        expect(storedRow($invoice->id))->paid->toBeTruthy()->total->toBe(5)->number->toBe('INV-001');
    });

    it('rejects a save in the same second as the stored update timestamp when that timestamp is immutable', function () {
        $this->freezeSecond();
        $invoice = QueryTallyInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        $invoice->total = 5;

        expect(fn () => $invoice->save())
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['updated_at']));
        expect(storedRow($invoice->id)['total'])->toBe(0);
    });

    it('rejects an increment on a model that does not exist yet, which Laravel runs on the whole table', function () {
        $id = invoiceRow();

        expectRejectedWrite(fn () => (new QueryGuardedInvoice)->increment('customer_id'), QueryGuardedInvoice::class, ['customer_id']);
        expect(storedRow($id)['customer_id'])->toBe(7);
    });

    it('rejects touching a parent through $touches when its update timestamp is immutable', function () {
        $id = invoiceRow();

        expect(fn () => QueryTouchingLedgerEntry::query()->create(['amount' => 100, 'invoice_id' => $id]))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['updated_at']));
        expect(storedRow($id)['updated_at'])->toBe('2026-10-01 09:00:00');
    });

    it('rejects touching related models through belongsToMany() when their update timestamp is immutable', function () {
        $id = invoiceRow();
        $entry = QueryTouchingLedgerEntry::query()->create(['amount' => 100]);
        DB::table('invoice_ledger_entry')->insert(['invoice_id' => $id, 'ledger_entry_id' => $entry->id]);

        expect(fn () => $entry->invoices()->touch())
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['updated_at']));
        expect(storedRow($id)['updated_at'])->toBe('2026-10-01 09:00:00');
    });

    it('touches a parent through $touches when its update timestamp is mutable', function () {
        $id = invoiceRow();
        QueryTouchingLedgerEntry::$parent = QueryGuardedInvoice::class;

        QueryTouchingLedgerEntry::query()->create(['amount' => 100, 'invoice_id' => $id]);

        expect(storedRow($id)['updated_at'])->not->toBe('2026-10-01 09:00:00');
    });
});

describe('custom builders', function () {
    it('keeps a builder that extends the guarded one', function () {
        $id = invoiceRow();

        expect(BuiltInvoice::query())->toBeInstanceOf(InvoiceBuilder::class);

        BuiltInvoice::query()->unpaid()->whereKey($id)->update(['note' => 'Reminded']);

        expectRejectedWrite(fn () => BuiltInvoice::query()->unpaid()->whereKey($id)->update(['number' => 'INV-999']), BuiltInvoice::class, ['number']);
        expect(storedRow($id)['note'])->toBe('Reminded');
    });

    it('keeps a builder that uses the builder trait', function () {
        $id = invoiceRow();

        expect(ForeignBuiltInvoice::query())->toBeInstanceOf(ForeignBuilder::class);

        expectRejectedWrite(fn () => ForeignBuiltInvoice::query()->whereKey($id)->increment('customer_id'), ForeignBuiltInvoice::class, ['customer_id']);
    });

    it('keeps the guard of a builder that overrides a write and calls the aliased trait method', function () {
        $invoice = CountingBuiltInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        $invoice->update(['note' => 'Saved']);

        expectRejectedWrite(fn () => CountingBuiltInvoice::query()->whereKey($invoice->id)->update(['number' => 'INV-999']), CountingBuiltInvoice::class, ['number']);
        expect(CountingBuilder::$updates)->toBe(2);
    });

    it('works with Laravel\'s HasBuilder', function () {
        $id = invoiceRow();

        expect(HasBuilderInvoice::query())->toBeInstanceOf(InvoiceBuilder::class);

        expectRejectedWrite(fn () => HasBuilderInvoice::query()->whereKey($id)->update(['number' => 'INV-999']), HasBuilderInvoice::class, ['number']);
    });

    it('refuses a builder that does not guard its writes instead of replacing it', function () {
        expect(fn () => PlainBuiltInvoice::query())
            ->toThrow(LogicException::class, 'PlainBuilder], which does not guard immutable attributes');
    });

    it('refuses a builder named with #[UseEloquentBuilder] that does not guard its writes', function () {
        expect(fn () => AttributedBuilderInvoice::query())
            ->toThrow(LogicException::class, 'PlainBuilder], which does not guard immutable attributes');
    })->skip(! class_exists(UseEloquentBuilder::class), 'Laravel 12.0 has no #[UseEloquentBuilder].');

    it('refuses a model that defines its own newEloquentBuilder() when it boots', function () {
        expect(fn () => new SelfBuildingInvoice)
            ->toThrow(LogicException::class, 'defines its own newEloquentBuilder()');
    });
});

describe('outside the guard', function () {
    it('leaves writes around the Eloquent builder alone', function (Closure $write) {
        $id = invoiceRow();

        $write($id);

        expect(storedRow($id)['number'])->toBe('INV-999');
    })->with([
        'DB::table()' => fn (int $id) => DB::table('invoices')->where('id', $id)->update(['number' => 'INV-999']),
        'toBase()' => fn (int $id) => QueryGuardedInvoice::query()->whereKey($id)->toBase()->update(['number' => 'INV-999']),
        'getQuery()' => fn (int $id) => QueryGuardedInvoice::query()->whereKey($id)->getQuery()->update(['number' => 'INV-999']),
        'a raw statement' => fn (int $id) => DB::update('update invoices set number = ? where id = ?', ['INV-999', $id]),
    ]);

    it('does not inspect a raw expression in place of a column name in upsert()', function () {
        $id = invoiceRow();

        QueryGuardedInvoice::query()->upsert([[
            'id' => $id,
            'number' => 'INV-999',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ]], ['id'], [DB::raw('number')]);

        expect(storedRow($id)['number'])->toBe('INV-999');
    });
});
