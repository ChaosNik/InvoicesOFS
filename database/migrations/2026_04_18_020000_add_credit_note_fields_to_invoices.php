<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_type', 32)->default('invoice')->index()->after('id');
            $table->integer('original_invoice_id')->unsigned()->nullable()->after('document_type');
            $table->foreign('original_invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->string('referent_document_number')->nullable()->after('reference_number');
            $table->dateTimeTz('referent_document_dt')->nullable()->after('referent_document_number');
            $table->text('credit_note_reason')->nullable()->after('referent_document_dt');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['original_invoice_id']);
            $table->dropColumn([
                'document_type',
                'original_invoice_id',
                'referent_document_number',
                'referent_document_dt',
                'credit_note_reason',
            ]);
        });
    }
};
