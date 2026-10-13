<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The guard decides from the key it is given which column the database will
 * change. These tests pin that assumption against the connection the suite
 * runs on, so the databases in CI show when a driver stops agreeing with it.
 * The query builder is used on purpose: nothing here goes through the guard.
 */
function insertInvoiceRow(): int
{
    return DB::table('invoices')->insertGetId([
        'number' => 'INV-001',
        'customer_id' => 7,
        'issued_at' => '2026-10-01 09:00:00',
        'options' => json_encode(['currency' => 'EUR']),
    ]);
}

/**
 * @param  array<string, mixed>  $values
 */
function updateInvoiceRow(int $id, array $values): void
{
    DB::table('invoices')->where('id', $id)->update($values);
}

function storedInvoiceNumber(int $id): mixed
{
    return DB::table('invoices')->where('id', $id)->value('number');
}

describe('how the database reads a column name', function () {
    it('resolves a column name in any case outside PostgreSQL', function () {
        $id = insertInvoiceRow();

        updateInvoiceRow($id, ['NUMBER' => 'INV-999']);

        expect(storedInvoiceNumber($id))->toBe('INV-999');
    })->skip(fn () => onPostgres(), 'PostgreSQL quotes identifiers, so "NUMBER" is another column.');

    it('has no column that differs in case on PostgreSQL', function () {
        $id = insertInvoiceRow();

        expect(fn () => updateInvoiceRow($id, ['NUMBER' => 'INV-999']))->toThrow(QueryException::class);
        expect(storedInvoiceNumber($id))->toBe('INV-001');
    })->skip(fn () => ! onPostgres(), 'Only PostgreSQL tells "NUMBER" and "number" apart.');

    it('resolves the table name in front of a column', function () {
        $id = insertInvoiceRow();

        updateInvoiceRow($id, ['invoices.number' => 'INV-999']);

        expect(storedInvoiceNumber($id))->toBe('INV-999');
    });

    it('either drops an unknown qualifier or rejects it', function () {
        $id = insertInvoiceRow();

        match (DB::connection()->getDriverName()) {
            'sqlite', 'pgsql' => updateInvoiceRow($id, ['anything.number' => 'INV-999']),
            default => expect(fn () => updateInvoiceRow($id, ['anything.number' => 'INV-999']))->toThrow(QueryException::class),
        };

        expect(storedInvoiceNumber($id))->toBe(in_array(DB::connection()->getDriverName(), ['sqlite', 'pgsql'], true) ? 'INV-999' : 'INV-001');
    });

    it('folds case beyond ASCII in MySQL and MariaDB only, and never accents', function () {
        Schema::create('accented_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('café')->nullable();
        });

        try {
            $folds = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
            $id = DB::table('accented_rows')->insertGetId(['café' => 'a']);

            if ($folds) {
                DB::table('accented_rows')->where('id', $id)->update(['CAFÉ' => 'b']);

                expect(DB::table('accented_rows')->where('id', $id)->value('café'))->toBe('b');
            } else {
                expect(fn () => DB::table('accented_rows')->where('id', $id)->update(['CAFÉ' => 'b']))->toThrow(QueryException::class);
            }

            expect(fn () => DB::table('accented_rows')->where('id', $id)->update(['cafe' => 'c']))->toThrow(QueryException::class);
        } finally {
            Schema::dropIfExists('accented_rows');
        }
    });

    it('writes a JSON path into its column', function () {
        $id = insertInvoiceRow();

        updateInvoiceRow($id, ['options->currency' => 'USD']);

        expect(json_decode((string) DB::table('invoices')->where('id', $id)->value('options'), true))->toBe(['currency' => 'USD'])
            ->and(storedInvoiceNumber($id))->toBe('INV-001');
    });
});
