<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        // SQLite cannot add composite foreign keys without rebuilding the ledger.
        // Add real customer FKs in place, then enforce the tenant half with triggers.
        if ($sqlite) {
            DB::statement('ALTER TABLE billing_invoices ADD COLUMN patient_id INTEGER REFERENCES patients(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE billing_invoices ADD COLUMN salon_client_id INTEGER REFERENCES salon_clients(id) ON DELETE RESTRICT');
        }
        Schema::table('billing_invoices', function (Blueprint $table) use ($sqlite) {
            foreach (['patient_id' => 'patients', 'salon_client_id' => 'salon_clients'] as $column => $parent) {
                if (!$sqlite) {
                    $table->unsignedBigInteger($column)->nullable();
                    $table->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($parent)->restrictOnDelete();
                }
                $table->index(['tenant_id', $column]);
            }
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedTinyInteger('snapshot_version')->default(1);
            $table->index(['tenant_id', 'branch_id', 'issued_at']);
        });
        if ($sqlite) {
            foreach (['patient_id' => 'patients', 'salon_client_id' => 'salon_clients'] as $column => $parent) {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("CREATE TRIGGER billing_{$column}_".strtolower($event)." BEFORE {$event} ON billing_invoices
                        WHEN NEW.{$column} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$parent} WHERE id = NEW.{$column} AND tenant_id = NEW.tenant_id)
                        BEGIN SELECT RAISE(ABORT, 'Billing customer tenant mismatch'); END");
                }
                DB::unprepared("CREATE TRIGGER billing_{$column}_parent BEFORE UPDATE OF tenant_id ON {$parent}
                    WHEN EXISTS (SELECT 1 FROM billing_invoices WHERE {$column} = OLD.id AND tenant_id != NEW.tenant_id)
                    BEGIN SELECT RAISE(ABORT, 'Billing customer tenant mismatch'); END");
            }
        }
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            // Existing amount means the charge before invoice-level adjustments. Never reinterpret it.
            $table->decimal('line_total', 12, 2)->nullable();
        });
        DB::table('billing_invoices')->orderBy('id')->chunkById(200, function ($invoices) {
            foreach ($invoices as $invoice) {
                $metadata = ['issued_at' => $invoice->created_at];
                if ($invoice->source_type === 'salon_appointment') {
                    $source = DB::table('salon_appointments as a')
                        ->join('salon_clients as c', fn ($join) => $join->on('c.id', '=', 'a.client_id')->on('c.tenant_id', '=', 'a.tenant_id'))
                        ->join('tenants as t', 't.id', '=', 'a.tenant_id')->join('business_types as b', 'b.id', '=', 't.business_type_id')
                        ->where('b.slug', 'beauty-salon')->where('a.tenant_id', $invoice->tenant_id)
                        ->where('a.branch_id', $invoice->branch_id)->where('a.id', $invoice->source_id)->select('a.client_id', 'a.tax_rate')->first();
                    if ($source) $metadata += ['salon_client_id' => $source->client_id, 'tax_rate' => $source->tax_rate];
                }
                DB::table('billing_invoices')->where('id', $invoice->id)->update($metadata);
            }
        });
    }

    public function down(): void
    {
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        if ($sqlite) foreach (['patient_id', 'salon_client_id'] as $column) {
            foreach (['insert', 'update', 'parent'] as $suffix) DB::unprepared("DROP TRIGGER IF EXISTS billing_{$column}_{$suffix}");
        }
        Schema::table('billing_invoice_items', fn (Blueprint $table) => $table->dropColumn(['source_type', 'source_id', 'discount_amount', 'tax_rate', 'tax_amount', 'line_total']));
        Schema::table('billing_invoices', function (Blueprint $table) use ($sqlite) {
            foreach (['patient_id', 'salon_client_id'] as $column) {
                if (!$sqlite) $table->dropForeign(['tenant_id', $column]);
                $table->dropIndex(['tenant_id', $column]);
            }
            $table->dropIndex(['tenant_id', 'branch_id', 'issued_at']);
            $table->dropColumn(['patient_id', 'salon_client_id', 'issued_at', 'due_at', 'tax_rate', 'voided_at', 'void_reason', 'snapshot_version']);
        });
    }
};
