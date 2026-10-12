<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Taldres\ImmutableAttributes\Attributes\Immutable;
use Taldres\ImmutableAttributes\Attributes\ImmutableModel;
use Taldres\ImmutableAttributes\Concerns\GuardsImmutableAttributes;
use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;
use Taldres\ImmutableAttributes\Exceptions\InvalidImmutableColumnsException;
use Taldres\ImmutableAttributes\Tests\Fixtures\Casts\AsCents;
use Taldres\ImmutableAttributes\Tests\Fixtures\Casts\Cents;
use Taldres\ImmutableAttributes\Tests\Fixtures\Concerns\HasEmptyImmutableList;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\AuditEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\ConfiguredInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\CreditNote;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\Invoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\LedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\LockedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\MisdeclaredLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\MistypedLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\NarrowedLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\NormalizedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\PostgresInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\RestampedInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\SoftDeletingLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\TouchingLedgerEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\UnusableInvoice;

/**
 * @param  array<string, mixed>  $attributes
 */
function invoice(array $attributes = []): Invoice
{
    return Invoice::query()->create([
        'number' => 'INV-001',
        'customer_id' => 7,
        'issued_at' => Carbon::parse('2026-10-01 09:00:00'),
        'total' => 1000,
        'options' => ['currency' => 'EUR'],
        ...$attributes,
    ]);
}

function onPostgres(): bool
{
    return DB::connection()->getDriverName() === 'pgsql';
}

function storedNumber(Model $model): mixed
{
    return DB::table('invoices')->where('id', $model->getKey())->value('number');
}

describe('inserting', function () {
    it('sets immutable attributes on create', function () {
        $invoice = invoice();

        expect(storedNumber($invoice))->toBe('INV-001');
    });

    it('changes immutable attributes freely before the first save', function () {
        $invoice = new Invoice([
            'number' => 'DRAFT',
            'customer_id' => 7,
            'issued_at' => now(),
        ]);

        $invoice->number = 'INV-002';
        $invoice->save();

        expect(storedNumber($invoice))->toBe('INV-002');
    });
});

describe('updating', function () {
    it('throws when an immutable attribute changes', function () {
        $invoice = invoice();

        $invoice->number = 'INV-999';

        expect(fn () => $invoice->save())->toThrow(ImmutableAttributeException::class);
        expect(storedNumber($invoice))->toBe('INV-001');
    });

    it('lets mutable attributes change', function () {
        $invoice = invoice();

        $invoice->update(['paid' => true, 'note' => 'Paid by card']);

        expect($invoice->fresh())
            ->paid->toBeTrue()
            ->note->toBe('Paid by card');
    });

    it('allows setting the same value again', function () {
        $invoice = invoice();

        $invoice->number = 'INV-001';
        $invoice->customer_id = 7;
        $invoice->issued_at = Carbon::parse('2026-10-01 09:00:00');
        $invoice->options = ['currency' => 'EUR'];
        $invoice->paid = true;
        $invoice->save();

        expect($invoice->fresh()?->paid)->toBeTrue();
    });

    it('compares the way Eloquent decides what is dirty', function () {
        $invoice = invoice();

        $invoice->issued_at = '2026-10-01 09:00:00';
        $invoice->customer_id = '7';
        $invoice->paid = true;

        expect($invoice->save())->toBeTrue()
            ->and($invoice->wasChanged('paid'))->toBeTrue()
            ->and($invoice->wasChanged(['issued_at', 'customer_id']))->toBeFalse();
    });

    it('throws on changes to cast values', function () {
        $invoice = invoice();

        $invoice->issued_at = Carbon::parse('2026-10-02 09:00:00');

        expect(fn () => $invoice->save())->toThrow(ImmutableAttributeException::class);
    });

    it('throws on changes inside a json column', function () {
        $invoice = invoice();

        expect(fn () => $invoice->update(['options->currency' => 'USD']))
            ->toThrow(ImmutableAttributeException::class);
    });

    it('throws for every way of saving the model', function (Closure $change) {
        $invoice = invoice();

        expect(fn () => $change($invoice))->toThrow(ImmutableAttributeException::class);
        expect(storedNumber($invoice))->toBe('INV-001');
    })->with([
        'update()' => fn (Invoice $invoice) => $invoice->update(['number' => 'INV-999']),
        'fill() and save()' => fn (Invoice $invoice) => $invoice->fill(['number' => 'INV-999'])->save(),
        'forceFill() and save()' => fn (Invoice $invoice) => $invoice->forceFill(['number' => 'INV-999'])->save(),
        'saveQuietly()' => function (Invoice $invoice) {
            $invoice->number = 'INV-999';
            $invoice->saveQuietly();
        },
        'updateQuietly()' => fn (Invoice $invoice) => $invoice->updateQuietly(['number' => 'INV-999']),
        'withoutEvents()' => fn (Invoice $invoice) => Model::withoutEvents(
            fn () => $invoice->update(['number' => 'INV-999']),
        ),
        'push()' => function (Invoice $invoice) {
            $invoice->number = 'INV-999';
            $invoice->push();
        },
        'touch() of an immutable column' => fn (Invoice $invoice) => $invoice->touch('issued_at'),
    ]);

    it('throws when a later updating listener changes an immutable attribute', function () {
        $invoice = invoice();

        Invoice::updating(function (Invoice $invoice): void {
            $invoice->number = 'INV-999';
        });

        expect(fn () => $invoice->update(['paid' => true]))->toThrow(ImmutableAttributeException::class);
        expect(storedNumber($invoice))->toBe('INV-001');
    });

    it('keeps the unsaved changes after a violation', function () {
        $invoice = invoice();

        $invoice->number = 'INV-999';

        expect(fn () => $invoice->save())->toThrow(ImmutableAttributeException::class);
        expect($invoice->isDirty('number'))->toBeTrue();
        expect(fn () => $invoice->save())->toThrow(ImmutableAttributeException::class);

        $invoice->refresh();

        expect($invoice->number)->toBe('INV-001')
            ->and($invoice->save())->toBeTrue();
    });

    it('writes nothing of a save that changes an immutable attribute', function () {
        $invoice = invoice();

        expect(fn () => $invoice->update(['number' => 'INV-999', 'total' => 2000]))->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('total'))->toBe(1000);
    });

    it('asks isImmutableAttribute() about every written key', function () {
        $model = new class extends Invoice
        {
            public function isImmutableAttribute(string $key): bool
            {
                return $key === 'note';
            }
        };

        $invoice = $model->newQuery()->findOrFail(invoice()->id);

        expect(fn () => $invoice->update(['note' => 'Changed']))->toThrow(ImmutableAttributeException::class, '[note]')
            ->and($invoice->refresh()->update(['number' => 'INV-002']))->toBeTrue();
    });

    it('names every changed immutable attribute', function () {
        $invoice = invoice();

        expect(fn () => $invoice->update(['number' => 'INV-999', 'customer_id' => 8, 'paid' => true]))
            ->toThrow(function (ImmutableAttributeException $e) use ($invoice): void {
                expect($e)
                    ->model->toBe(Invoice::class)
                    ->key->toBe($invoice->id)
                    ->attributes->toBe(['number', 'customer_id'])
                    ->getMessage()->toBe(
                        'Attempted to change immutable attribute(s) [number, customer_id] on model ['.Invoice::class."] with key [{$invoice->id}].",
                    );
            });
    });
});

describe('column name aliases', function () {
    it('treats case, a qualifier and a JSON path as the column they write', function (Closure $change) {
        $invoice = invoice();

        expect(fn () => $change($invoice))->toThrow(ImmutableAttributeException::class);
        expect(storedNumber($invoice))->toBe('INV-001')
            ->and(json_decode((string) DB::table('invoices')->value('options'), true))->toBe(['currency' => 'EUR']);
    })->with([
        'table prefix on save' => fn (Invoice $invoice) => $invoice->forceFill(['invoices.number' => 'INV-999'])->save(),
        'any qualifier on save' => fn (Invoice $invoice) => $invoice->forceFill(['anything.number' => 'INV-999'])->save(),
        'JSON path in increment extras' => fn (Invoice $invoice) => $invoice->increment('total', 1, ['options->currency' => 'USD']),
        'table prefix in increment extras' => fn (Invoice $invoice) => $invoice->increment('total', 1, ['invoices.number' => 'INV-999']),
        'any qualifier in increment extras' => fn (Invoice $invoice) => $invoice->increment('total', 1, ['anything.number' => 'INV-999']),
    ]);

    it('treats case as the same column outside PostgreSQL', function (Closure $change) {
        $invoice = invoice();

        expect(fn () => $change($invoice))->toThrow(ImmutableAttributeException::class);
        expect(storedNumber($invoice))->toBe('INV-001');
    })->with([
        'upper case on save' => fn (Invoice $invoice) => $invoice->forceFill(['NUMBER' => 'INV-999'])->save(),
        'upper case in increment extras' => fn (Invoice $invoice) => $invoice->increment('total', 1, ['NUMBER' => 'INV-999']),
    ])->skip(fn () => onPostgres(), 'PostgreSQL tells "NUMBER" and "number" apart.');

    it('leaves a column that differs in case to PostgreSQL, which has no such column', function () {
        $invoice = invoice();

        expect(fn () => $invoice->forceFill(['NUMBER' => 'INV-999'])->save())->toThrow(QueryException::class);
        expect(storedNumber($invoice))->toBe('INV-001');
    })->skip(fn () => ! onPostgres(), 'Only PostgreSQL tells "NUMBER" and "number" apart.');

    it('names each declared attribute once', function (Closure $change, array $attributes) {
        $invoice = invoice();

        expect(fn () => $change($invoice))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe($attributes));
    })->with([
        'a table prefix' => [fn (Invoice $invoice) => $invoice->forceFill(['invoices.number' => 'INV-999'])->save(), ['number']],
        'a JSON path and its column' => [fn (Invoice $invoice) => $invoice->increment('total', 1, ['options->currency' => 'USD']), ['options']],
    ]);

    it('reports the column a key writes as immutable', function () {
        $invoice = new Invoice;

        expect($invoice->isImmutableAttribute('NUMBER'))->toBe(! onPostgres())
            ->and($invoice->isImmutableAttribute('public.invoices.number'))->toBeTrue()
            ->and($invoice->isImmutableAttribute('options->currency'))->toBeTrue()
            ->and($invoice->isImmutableAttribute('invoices.total'))->toBeFalse();
    });

    it('keeps the case of column names on PostgreSQL', function () {
        $invoice = new PostgresInvoice;

        expect($invoice->isImmutableAttribute('number'))->toBeTrue()
            ->and($invoice->isImmutableAttribute('anything.number'))->toBeTrue()
            ->and($invoice->isImmutableAttribute('options->currency'))->toBeTrue()
            ->and($invoice->isImmutableAttribute('NUMBER'))->toBeFalse()
            ->and($invoice->isImmutableAttribute('invoices.NUMBER'))->toBeFalse();
    });

    it('decides without a database connection unless only the case differs', function () {
        $resolver = Model::getConnectionResolver();
        Model::unsetConnectionResolver();

        try {
            $invoice = new Invoice;

            expect($invoice->isImmutableAttribute('number'))->toBeTrue()
                ->and($invoice->isImmutableAttribute('invoices.number'))->toBeTrue()
                ->and($invoice->isImmutableAttribute('total'))->toBeFalse();
        } finally {
            Model::setConnectionResolver($resolver);
        }
    });

    it('names the written columns when the whole model is guarded', function () {
        $invoice = LockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'total' => 1000]);

        expect(fn () => $invoice->increment('total', 1, ['invoices.note' => 'Changed', 'options->currency' => 'USD']))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['total', 'note', 'options', 'updated_at']));
    });
});

describe('incrementing', function () {
    it('leaves an increment by zero alone', function () {
        $invoice = invoice();

        $invoice->increment('customer_id', 0);

        expect(DB::table('invoices')->value('customer_id'))->toBe(7);
    });

    it('still throws on an increment by zero with an immutable extra attribute', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('customer_id', 0, ['number' => 'INV-001']))
            ->toThrow(ImmutableAttributeException::class);
    });

    it('throws on increment() of an immutable attribute', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('customer_id'))->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('customer_id'))->toBe(7);
    });

    it('names an attribute once when it is dirty and written by the increment', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('total', 1, ['number' => 'INV-999']))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['number']));
    });

    it('throws on decrement() with extra immutable attributes', function () {
        $invoice = invoice();

        expect(fn () => $invoice->decrement('total', 100, ['number' => 'INV-999']))
            ->toThrow(ImmutableAttributeException::class);
    });

    it('throws when an immutable extra attribute keeps its current value', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('total', 1, ['number' => 'INV-001']))
            ->toThrow(ImmutableAttributeException::class);
    });

    it('throws when a setter hides a change to an extra attribute', function () {
        $invoice = NormalizedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        expect(fn () => $invoice->increment('total', 1, ['number' => ' inv-001 ']))
            ->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('number'))->toBe('INV-001');
    });

    it('throws when a cast hides a change to the incremented attribute', function () {
        $invoice = NormalizedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'paid' => true]);

        expect(fn () => $invoice->increment('paid'))->toThrow(ImmutableAttributeException::class);
        expect((bool) DB::table('invoices')->value('paid'))->toBeTrue();
    });

    it('takes a numeric string as the amount, as Laravel does', function () {
        $invoice = invoice();

        $invoice->increment('total', '5');

        expect(DB::table('invoices')->value('total'))->toBe(1005);
        expect(fn () => $invoice->increment('customer_id', '1'))->toThrow(ImmutableAttributeException::class);
    });

    it('takes a value object as the amount of a cast that increments it', function () {
        $invoice = invoice();
        $invoice->mergeCasts(['total' => AsCents::class, 'customer_id' => AsCents::class]);

        $invoice->increment('total', new Cents(5));

        expect(DB::table('invoices')->value('total'))->toBe(1005);
        expect(fn () => $invoice->increment('customer_id', new Cents(1)))->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('customer_id'))->toBe(7);
    });

    it('counts the update timestamp an increment writes', function () {
        $invoice = LockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'total' => 1000]);
        $updatedAt = DB::table('invoices')->value('updated_at');

        $this->travel(5)->minutes();

        expect(fn () => $invoice->increment('total', 0))
            ->toThrow(fn (ImmutableAttributeException $e) => expect($e->attributes)->toBe(['updated_at']));
        expect(DB::table('invoices')->value('updated_at'))->toBe($updatedAt);
    });

    it('throws on an increment when the update timestamp is immutable', function () {
        ConfiguredInvoice::$frozen = ['updated_at'];

        $invoice = ConfiguredInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        expect(fn () => $invoice->increment('total'))->toThrow(ImmutableAttributeException::class, '[updated_at]');
        expect(DB::table('invoices')->value('total'))->toBe(0);
    });

    it('follows the update timestamp to its configured column', function () {
        RestampedInvoice::$updatedAtColumn = 'note';
        RestampedInvoice::$frozen = ['updated_at'];

        $invoice = RestampedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'total' => 1000]);
        $invoice->increment('total');

        RestampedInvoice::$frozen = ['note'];

        expect(fn () => $invoice->increment('total'))->toThrow(ImmutableAttributeException::class, '[note]');
        expect(DB::table('invoices')->value('total'))->toBe(1001);
    });

    it('leaves an increment alone when the model has no update timestamp', function () {
        RestampedInvoice::$updatedAtColumn = null;
        RestampedInvoice::$frozen = ['updated_at'];

        $invoice = RestampedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'total' => 1000]);
        $invoice->increment('total');

        expect(DB::table('invoices')->value('total'))->toBe(1001);
    });

    it('leaves an increment by zero without timestamps alone', function () {
        $invoice = LockedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now(), 'total' => 1000]);

        expect(fn () => LockedInvoice::withoutTimestamps(fn () => $invoice->increment('total', 0)))
            ->not->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('total'))->toBe(1000);
    });

    it('forgets the columns of an increment that threw', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('total', 1, ['number' => 'INV-001']))->toThrow(ImmutableAttributeException::class);

        $invoice->refresh()->update(['note' => 'Checked']);

        expect(DB::table('invoices')->value('note'))->toBe('Checked');
    });

    // @todo Laravel 13.3: Drop the skip once composer.json requires ^13.3.
    it('leaves incrementEach() by zero alone and throws on a non-zero immutable column', function () {
        $invoice = invoice();

        $invoice->incrementEach(['customer_id' => 0, 'total' => 5]);

        expect(DB::table('invoices')->value('customer_id'))->toBe(7)
            ->and(DB::table('invoices')->value('total'))->toBe(1005);
        expect(fn () => $invoice->incrementEach(['customer_id' => 1, 'total' => 0]))
            ->toThrow(ImmutableAttributeException::class);
    })->skip(! method_exists(Model::class, 'incrementEach'), 'Model::incrementEach() arrived in Laravel 13.3.');

    // @todo Laravel 13.3: Drop the skip once composer.json requires ^13.3.
    it('throws on incrementEach() when a setter hides a change to an extra attribute', function () {
        $invoice = NormalizedInvoice::query()->create(['number' => 'INV-001', 'customer_id' => 7, 'issued_at' => now()]);

        expect(fn () => $invoice->incrementEach(['total' => 1], ['number' => ' inv-001 ']))
            ->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('number'))->toBe('INV-001');
    })->skip(! method_exists(Model::class, 'incrementEach'), 'Model::incrementEach() arrived in Laravel 13.3.');

    it('increments mutable attributes', function () {
        $invoice = invoice();

        $invoice->increment('total', 50);

        expect($invoice->fresh()?->total)->toBe(1050);
    });

    // @todo Laravel 13.3: Drop the skip once composer.json requires ^13.3.
    it('throws on incrementEach() and decrementEach()', function (Closure $change) {
        $invoice = invoice();

        expect(fn () => $change($invoice))->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('customer_id'))->toBe(7)
            ->and(storedNumber($invoice))->toBe('INV-001');
    })->with([
        'incrementEach()' => fn (Invoice $invoice) => $invoice->incrementEach(['customer_id' => 1]),
        'decrementEach() with extra columns' => fn (Invoice $invoice) => $invoice->decrementEach(['total' => 1], ['number' => 'INV-999']),
    ])->skip(! method_exists(Model::class, 'incrementEach'), 'Model::incrementEach() arrived in Laravel 13.3.');
});

describe('the whole model', function () {
    it('is inserted normally', function () {
        $entry = LedgerEntry::query()->create(['amount' => 100]);

        expect($entry->exists)->toBeTrue();
    });

    it('throws on any change', function () {
        $entry = LedgerEntry::query()->create(['amount' => 100]);

        expect(fn () => $entry->update(['memo' => 'correction']))
            ->toThrow(ImmutableAttributeException::class, 'immutable attribute(s) [memo]');
    });

    it('saves without changes', function () {
        $entry = LedgerEntry::query()->create(['amount' => 100]);

        expect($entry->save())->toBeTrue();
    });

    it('throws on touch()', function () {
        $invoice = LockedInvoice::query()->create([
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => now(),
        ]);

        $this->travel(5)->minutes();

        expect(fn () => $invoice->touch())
            ->toThrow(function (ImmutableAttributeException $e): void {
                expect($e->attributes)->toBe(['updated_at']);
            });
    });

    it('throws on restore() once soft deleted', function () {
        $entry = SoftDeletingLedgerEntry::query()->create(['amount' => 100]);

        $entry->delete();

        expect(fn () => $entry->restore())->toThrow(ImmutableAttributeException::class, '[deleted_at]');
        expect(DB::table('ledger_entries')->value('deleted_at'))->not->toBeNull();
    });
});

describe('resolving the attributes', function () {
    it('reads #[Immutable] from the model', function () {
        expect((new Invoice)->getImmutableAttributes())
            ->toBe(['number', 'customer_id', 'issued_at', 'options']);
    });

    it('merges #[Immutable] from parents and traits', function () {
        expect((new CreditNote)->getImmutableAttributes())
            ->toEqualCanonicalizing(['total', 'note', 'number', 'customer_id', 'issued_at', 'options']);
    });

    it('guards the merged attributes', function (string $column, mixed $value) {
        $note = CreditNote::query()->create([
            'number' => 'CN-001',
            'customer_id' => 7,
            'issued_at' => now(),
            'total' => -500,
            'note' => 'Refund',
        ]);

        expect(fn () => $note->update([$column => $value]))
            ->toThrow(function (ImmutableAttributeException $e) use ($column): void {
                expect($e->attributes)->toBe([$column]);
            });
    })->with([
        'from the model' => ['total', -400],
        'from a trait used by a trait' => ['note', 'Changed'],
        'from the parent' => ['number', 'CN-002'],
    ]);

    it('inherits the whole chain without an attribute of its own', function () {
        expect((new class extends CreditNote {})->getImmutableAttributes())
            ->toEqualCanonicalizing(['total', 'note', 'number', 'customer_id', 'issued_at', 'options']);
    });

    it('merges repeated #[Immutable] on one class', function () {
        $model = new #[Immutable('number')] #[Immutable(['customer_id', 'issued_at'])] class extends Model
        {
            use GuardsImmutableAttributes;
        };

        expect($model->getImmutableAttributes())->toBe(['number', 'customer_id', 'issued_at']);
    });

    it('guards nothing without any #[Immutable]', function () {
        $model = new class extends Model
        {
            use GuardsImmutableAttributes;
        };

        expect($model->getImmutableAttributes())->toBe([]);
    });

    it('guards everything when #[ImmutableModel] meets column lists', function (Model $model) {
        expect($model->isImmutableAttribute('amount'))->toBeTrue();
    })->with([
        'a child adds columns to a whole-model parent' => fn () => new #[Immutable('memo')] class extends LedgerEntry {},
        'both on one class' => fn () => new #[Immutable('memo')] #[ImmutableModel] class extends Model
        {
            use GuardsImmutableAttributes;
        },
    ]);

    it('rejects #[Immutable] without columns when the model boots, naming where it is declared', function () {
        expect(fn () => new MisdeclaredLedgerEntry)->toThrow(
            InvalidImmutableColumnsException::class,
            'Use #[ImmutableModel] to guard the whole model. Declared on ['.HasEmptyImmutableList::class.'].',
        );
    });

    it('rejects a value that is not a column name the same way', function () {
        expect(fn () => new MistypedLedgerEntry)->toThrow(
            InvalidImmutableColumnsException::class,
            'takes column names as non-empty strings, int given. Declared on ['.MistypedLedgerEntry::class.'].',
        );
    });

    it('rejects columns on #[ImmutableModel] the same way', function () {
        expect(fn () => new NarrowedLedgerEntry)->toThrow(
            InvalidImmutableColumnsException::class,
            'Use #[Immutable] to guard single columns. Declared on ['.NarrowedLedgerEntry::class.'].',
        );
    });

    it('rejects a getImmutableAttributes() override that returns anything but column names', function (array $returned, string $given) {
        UnusableInvoice::$returned = $returned;

        try {
            $invoice = new UnusableInvoice;
            $invoice->setRawAttributes(['id' => 1, 'number' => 'INV-001'], true)->exists = true;
            $invoice->number = 'INV-999';

            expect(fn () => $invoice->isImmutableAttribute('number'))->toThrow(
                InvalidImmutableColumnsException::class,
                UnusableInvoice::class."::getImmutableAttributes() must return column names as non-empty strings, {$given} given.",
            );
            expect(fn () => $invoice->save())->toThrow(InvalidImmutableColumnsException::class);
        } finally {
            UnusableInvoice::$returned = [];
        }
    })->with([
        'an integer' => [[123], 'int'],
        'a nested list' => [[['number']], 'array'],
        'an empty string' => [[''], 'an empty string'],
        'null next to a column' => [['number', null], 'null'],
    ]);

    it('reads presets that extend #[ImmutableModel]', function () {
        $entry = AuditEntry::query()->create(['amount' => 100]);

        expect($entry->getImmutableAttributes())->toBe(['*']);
        expect(fn () => $entry->update(['memo' => 'correction']))->toThrow(ImmutableAttributeException::class);
    });

    it('tells whether an attribute is immutable', function () {
        expect(new Invoice)
            ->isImmutableAttribute('number')->toBeTrue()
            ->isImmutableAttribute('paid')->toBeFalse();

        expect((new LedgerEntry)->isImmutableAttribute('anything'))->toBeTrue();
    });

    it('lets a model decide at runtime', function () {
        ConfiguredInvoice::$frozen = ['note'];

        $invoice = ConfiguredInvoice::query()->create([
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => now(),
            'note' => 'Original',
        ]);

        $invoice->update(['number' => 'INV-002']);

        expect(fn () => $invoice->update(['note' => 'Changed']))->toThrow(ImmutableAttributeException::class);

        ConfiguredInvoice::$frozen = ['number'];
        $invoice->refresh();

        expect(fn () => $invoice->update(['number' => 'INV-003']))->toThrow(ImmutableAttributeException::class)
            ->and($invoice->refresh()->update(['note' => 'Changed']))->toBeTrue();
    });

    it('lets a model guard everything at runtime', function () {
        ConfiguredInvoice::$frozen = ['*'];

        $invoice = ConfiguredInvoice::query()->create([
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => now(),
        ]);

        expect($invoice->isImmutableAttribute('anything'))->toBeTrue();
        expect(fn () => $invoice->update(['note' => 'Changed']))->toThrow(ImmutableAttributeException::class);
    });
});

describe('outside the model', function () {
    it('leaves query builder updates alone', function () {
        $invoice = invoice();

        Invoice::query()->whereKey($invoice->id)->update(['number' => 'INV-999']);

        expect(storedNumber($invoice))->toBe('INV-999');
    });

    it('leaves upsert() alone', function () {
        $invoice = invoice();

        Invoice::query()->upsert([[
            'id' => $invoice->id,
            'number' => 'INV-999',
            'customer_id' => 7,
            'issued_at' => '2026-10-01 09:00:00',
        ]], ['id'], ['number']);

        expect(storedNumber($invoice))->toBe('INV-999');
    });

    it('leaves incrementQuietly() and decrementQuietly() alone, which fire no events', function () {
        $invoice = invoice();

        $invoice->incrementQuietly('customer_id');
        $invoice->decrementQuietly('customer_id', 2);

        expect(DB::table('invoices')->value('customer_id'))->toBe(6);
    });

    it('leaves increment() inside Model::withoutEvents() alone', function () {
        $invoice = invoice();

        Model::withoutEvents(fn () => $invoice->increment('customer_id'));

        expect(DB::table('invoices')->value('customer_id'))->toBe(8);
    });

    // @todo Laravel 13.20: Drop the skip once composer.json requires ^13.20.
    it('leaves incrementEachQuietly() and decrementEachQuietly() alone', function () {
        $invoice = invoice();

        $invoice->incrementEachQuietly(['customer_id' => 2]);
        $invoice->decrementEachQuietly(['customer_id' => 1]);

        expect(DB::table('invoices')->value('customer_id'))->toBe(8);
    })->skip(! method_exists(Model::class, 'incrementEachQuietly'), 'Model::incrementEachQuietly() arrived in Laravel 13.20.');

    // @todo Laravel 13.3: Remove with its README row once composer.json requires ^13.3.
    it('leaves incrementEach() before Laravel 13.3 alone, which updates every row', function () {
        $invoice = invoice();
        invoice(['number' => 'INV-002', 'customer_id' => 8]);

        $invoice->incrementEach(['customer_id' => 1]);

        expect(DB::table('invoices')->orderBy('id')->pluck('customer_id')->all())->toBe([8, 9]);
    })->skip(method_exists(Model::class, 'incrementEach'), 'Model::incrementEach() updates only its own row since Laravel 13.3.');

    it('leaves parent timestamps touched through $touches alone', function () {
        $invoice = LockedInvoice::query()->create([
            'number' => 'INV-001',
            'customer_id' => 7,
            'issued_at' => now(),
        ]);

        $this->travel(5)->minutes();

        TouchingLedgerEntry::query()->create(['amount' => 100, 'invoice_id' => $invoice->id]);

        expect(Carbon::parse(DB::table('invoices')->value('updated_at'))->greaterThan($invoice->updated_at))->toBeTrue();
    });

    it('leaves soft deleting alone', function () {
        $entry = SoftDeletingLedgerEntry::query()->create(['amount' => 100]);

        $entry->delete();

        expect($entry->trashed())->toBeTrue();

        $entry->forceDelete();

        expect(DB::table('ledger_entries')->count())->toBe(0);
    });

    it('leaves deleting alone', function () {
        $entry = LedgerEntry::query()->create(['amount' => 100]);

        $entry->delete();

        expect(LedgerEntry::query()->count())->toBe(0);
    });
});
