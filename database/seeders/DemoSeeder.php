<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Space\InstallUtils;
use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;
use Vinkla\Hashids\Facades\Hashids;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bamCurrencyId = Currency::where('code', 'BAM')->value('id') ?? 13;

        $user = User::firstOrCreate(
            [
                'email' => 'demo@invoiceshelf.com',
            ],
            [
                'name' => 'demo',
                'role' => Company::ROLE_MID_LEVEL_USER,
                'password' => 'demo',
            ]
        );

        $user->update([
            'name' => 'demo',
            'role' => Company::ROLE_MID_LEVEL_USER,
            'password' => 'demo',
        ]);

        $admin = User::where('email', 'admin@invoiceshelf.com')->first();
        $company = $admin?->companies()->first() ?? Company::first();

        if (! $company) {
            $company = Company::create([
                'name' => 'Olivera',
                'owner_id' => $admin?->id ?? $user->id,
                'slug' => 'olivera',
            ]);

            $company->unique_hash = Hashids::connection(Company::class)->encode($company->id);
            $company->save();
            $company->setupDefaultData();
        }

        $company->forceFill([
            'name' => 'Olivera',
            'owner_id' => $admin?->id ?? $company->owner_id ?? $user->id,
            'slug' => 'olivera',
        ])->save();

        $company->setupRoles();

        $user->companies()->syncWithoutDetaching([$company->id]);
        BouncerFacade::scope()->to($company->id);

        BouncerFacade::sync($user)->roles([Company::ROLE_MID_LEVEL_USER]);

        // Set default user settings
        $user->setSettings([
            'language' => 'en',
            'timezone' => 'UTC',
            'date_format' => 'DD-MM-YYYY',
            'currency_id' => $bamCurrencyId,
        ]);

        $lowLevelUser = User::firstOrCreate(
            [
                'email' => 'low@invoiceshelf.com',
            ],
            [
                'name' => 'low',
                'role' => Company::ROLE_LOW_LEVEL_OFS_USER,
                'password' => 'low',
            ]
        );

        $lowLevelUser->update([
            'name' => 'low',
            'role' => Company::ROLE_LOW_LEVEL_OFS_USER,
            'password' => 'low',
        ]);

        $lowLevelUser->companies()->syncWithoutDetaching([$company->id]);

        BouncerFacade::scope()->to($company->id);
        BouncerFacade::sync($lowLevelUser)->roles([Company::ROLE_LOW_LEVEL_OFS_USER]);

        $lowLevelUser->setSettings([
            'language' => 'sr',
            'timezone' => 'UTC',
            'date_format' => 'DD-MM-YYYY',
            'currency_id' => $bamCurrencyId,
        ]);

        CompanySetting::setSettings([
            'currency' => $bamCurrencyId,
            'date_format' => 'DD-MM-YYYY',
            'language' => 'en',
            'timezone' => 'UTC',
            'fiscal_year' => 'calendar_year',
            'tax_per_item' => false,
            'discount_per_item' => false,
            'invoice_prefix' => 'INV-',
            'estimate_prefix' => 'EST-',
            'payment_prefix' => 'PAY-',
        ], $company->id);

        Customer::factory()->count(5)->create([
            'company_id' => $company->id,
        ]);

        Setting::setSetting('profile_complete', 'COMPLETED');

        InstallUtils::createDbMarker();
    }
}
