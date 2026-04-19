<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('ofs_gtin', 14)->nullable();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('ofs_gtin', 14)->nullable();
        });

        Schema::table('tax_types', function (Blueprint $table) {
            $table->string('ofs_label', 16)->nullable();
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('ofs_payment_type', 50)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->integer('fiscal_payment_method_id')->unsigned()->nullable();
            $table->foreign('fiscal_payment_method_id')->references('id')->on('payment_methods')->nullOnDelete();
            $table->string('fiscal_status')->nullable()->index();
            $table->string('fiscal_invoice_number')->nullable()->index();
            $table->timestamp('fiscalized_at')->nullable();
            $table->text('fiscal_verification_url')->nullable();
        });

        Schema::create('ofs_fiscalizations', function (Blueprint $table) {
            $table->id();
            $table->integer('invoice_id')->unsigned();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->integer('company_id')->unsigned();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->uuid('request_id')->unique();
            $table->string('status')->index();
            $table->string('driver')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('fiscal_invoice_number')->nullable()->index();
            $table->dateTimeTz('sdc_date_time')->nullable();
            $table->text('verification_url')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_payload')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ofs_fiscalizations');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['fiscal_payment_method_id']);
            $table->dropColumn([
                'fiscal_payment_method_id',
                'fiscal_status',
                'fiscal_invoice_number',
                'fiscalized_at',
                'fiscal_verification_url',
            ]);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('ofs_payment_type');
        });

        Schema::table('tax_types', function (Blueprint $table) {
            $table->dropColumn('ofs_label');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('ofs_gtin');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('ofs_gtin');
        });
    }
};
