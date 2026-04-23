<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('invoice_access_scope', 32)->default('all')->after('level');
            $table->string('dashboard_invoice_scope', 32)->default('all')->after('invoice_access_scope');
            $table->boolean('can_toggle_dashboard_invoice_scope')->default(false)->after('dashboard_invoice_scope');
        });

        Company::query()->each(function (Company $company) {
            $company->setupRoles();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_access_scope',
                'dashboard_invoice_scope',
                'can_toggle_dashboard_invoice_scope',
            ]);
        });
    }
};
