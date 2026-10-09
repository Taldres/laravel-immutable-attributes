<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->unsignedBigInteger('customer_id');
            $table->timestamp('issued_at');
            $table->integer('total')->default(0);
            $table->boolean('paid')->default(false);
            $table->string('note')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->integer('amount');
            $table->string('memo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('invoices');
    }
};
