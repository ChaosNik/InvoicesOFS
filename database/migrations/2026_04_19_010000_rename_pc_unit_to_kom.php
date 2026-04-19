<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('units')
            ->where('name', 'pc')
            ->orderBy('id')
            ->get()
            ->each(function ($unit) {
                $existingKom = DB::table('units')
                    ->where('company_id', $unit->company_id)
                    ->where('name', 'kom')
                    ->first();

                if ($existingKom) {
                    DB::table('items')
                        ->where('unit_id', $unit->id)
                        ->update(['unit_id' => $existingKom->id]);

                    DB::table('units')->where('id', $unit->id)->delete();

                    return;
                }

                DB::table('units')
                    ->where('id', $unit->id)
                    ->update(['name' => 'kom']);
            });
    }

    public function down(): void
    {
        //
    }
};
