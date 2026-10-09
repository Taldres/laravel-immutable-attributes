<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Taldres\ImmutableAttributes\Exceptions\ImmutableAttributeException;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\AuditEntry;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\ConfiguredInvoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\CreditNote;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\Invoice;
use Taldres\ImmutableAttributes\Tests\Fixtures\Models\LedgerEntry;

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
        $invoice->save();

        expect($invoice->wasChanged())->toBeFalse();
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

describe('incrementing', function () {
    it('throws on increment() of an immutable attribute', function () {
        $invoice = invoice();

        expect(fn () => $invoice->increment('customer_id'))->toThrow(ImmutableAttributeException::class);
        expect(DB::table('invoices')->value('customer_id'))->toBe(7);
    });

    it('throws on decrement() with extra immutable attributes', function () {
        $invoice = invoice();

        expect(fn () => $invoice->decrement('total', 100, ['number' => 'INV-999']))
            ->toThrow(ImmutableAttributeException::class);
    });

    it('increments mutable attributes', function () {
        $invoice = invoice();

        $invoice->increment('total', 50);

        expect($invoice->fresh()?->total)->toBe(1050);
    });
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

    it('guards the merged attributes', function () {
        $note = CreditNote::query()->create([
            'number' => 'CN-001',
            'customer_id' => 7,
            'issued_at' => now(),
            'total' => -500,
            'note' => 'Refund',
        ]);

        expect(fn () => $note->update(['note' => 'Changed']))->toThrow(ImmutableAttributeException::class);
        expect(fn () => $note->update(['total' => -400]))->toThrow(ImmutableAttributeException::class);
        expect(fn () => $note->update(['number' => 'CN-002']))->toThrow(ImmutableAttributeException::class);
    });

    it('reads attributes that extend #[Immutable]', function () {
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
    });
});

describe('outside the model', function () {
    it('leaves query builder updates alone', function () {
        $invoice = invoice();

        Invoice::query()->whereKey($invoice->id)->update(['number' => 'INV-999']);

        expect(storedNumber($invoice))->toBe('INV-999');
    });

    it('leaves incrementQuietly() alone, which fires no events', function () {
        $invoice = invoice();

        $invoice->incrementQuietly('customer_id');

        expect(DB::table('invoices')->value('customer_id'))->toBe(8);
    });

    it('leaves deleting alone', function () {
        $entry = LedgerEntry::query()->create(['amount' => 100]);

        $entry->delete();

        expect(LedgerEntry::query()->count())->toBe(0);
    });
});
