<?php

use App\Models\Customer;
use App\Models\CustomField;
use App\Models\Estimate;
use App\Models\ExchangeRateProvider;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Note;
use App\Models\Payment;
use App\Models\RecurringInvoice;
use App\Models\TaxType;

return [
    /*
    * Minimum php version.
    */
    'min_php_version' => '8.2.0',

    /*
    * Minimum mysql version.
    */

    'min_mysql_version' => '5.7.7',

    /*
    * Minimum mariadb version.
    */

    'min_mariadb_version' => '10.2.7',

    /*
    * Minimum pgsql version.
    */

    'min_pgsql_version' => '9.2.0',

    /*
    * Minimum sqlite version.
    */

    'min_sqlite_version' => '3.35.0',

    /*
    * Marketplace url.
    */
    'base_url' => 'https://invoiceshelf.com',

    /*
    * List of languages supported by InvoiceShelf.
    */
    'languages' => [
        ['code' => 'ar', 'name' => 'Arapski'],
        ['code' => 'bg', 'name' => 'Bugarski'],
        ['code' => 'zh_CN', 'name' => 'Kineski (pojednostavljeni)'],
        ['code' => 'zh', 'name' => 'Kineski (tradicionalni)'],
        ['code' => 'hr', 'name' => 'Hrvatski'],
        ['code' => 'cs', 'name' => 'Češki'],
        ['code' => 'nl', 'name' => 'Holandski'],
        ['code' => 'en', 'name' => 'Engleski'],
        ['code' => 'fi', 'name' => 'Finski'],
        ['code' => 'fr', 'name' => 'Francuski'],
        ['code' => 'de', 'name' => 'Njemački'],
        ['code' => 'el', 'name' => 'Grčki'],
        ['code' => 'hi', 'name' => 'Hindi'],
        ['code' => 'id', 'name' => 'Indonežanski'],
        ['code' => 'it', 'name' => 'Italijanski'],
        ['code' => 'ja', 'name' => 'Japanski'],
        ['code' => 'ko', 'name' => 'Korejski'],
        ['code' => 'lv', 'name' => 'Letonski'],
        ['code' => 'lt', 'name' => 'Litvanski'],
        ['code' => 'mk', 'name' => 'Makedonski'],
        ['code' => 'no', 'name' => 'Norveški'],
        ['code' => 'fa', 'name' => 'Perzijski'],
        ['code' => 'pl', 'name' => 'Poljski'],
        ['code' => 'pt', 'name' => 'Portugalski'],
        ['code' => 'pt_BR', 'name' => 'Portugalski (brazilski)'],
        ['code' => 'ro', 'name' => 'Rumunski'],
        ['code' => 'ru', 'name' => 'Ruski'],
        ['code' => 'sr', 'name' => 'Srpski (ijekavica, BiH)'],
        ['code' => 'sk', 'name' => 'Slovački'],
        ['code' => 'sl', 'name' => 'Slovenački'],
        ['code' => 'es', 'name' => 'Španski'],
        ['code' => 'sv', 'name' => 'Švedski'],
        ['code' => 'th', 'name' => 'Tajlandski'],
        ['code' => 'vi', 'name' => 'Vijetnamski'],
        ['code' => 'tr', 'name' => 'Turski'],
        ['code' => 'uk', 'name' => 'Ukrajinski'],
    ],

    /*
    * List of Fiscal Years
    */
    'fiscal_years' => [
        ['key' => 'settings.preferences.fiscal_years.january_december', 'value' => '1-12'],
        ['key' => 'settings.preferences.fiscal_years.february_january', 'value' => '2-1'],
        ['key' => 'settings.preferences.fiscal_years.march_february', 'value' => '3-2'],
        ['key' => 'settings.preferences.fiscal_years.april_march', 'value' => '4-3'],
        ['key' => 'settings.preferences.fiscal_years.may_april', 'value' => '5-4'],
        ['key' => 'settings.preferences.fiscal_years.june_may', 'value' => '6-5'],
        ['key' => 'settings.preferences.fiscal_years.july_june', 'value' => '7-6'],
        ['key' => 'settings.preferences.fiscal_years.august_july', 'value' => '8-7'],
        ['key' => 'settings.preferences.fiscal_years.september_august', 'value' => '9-8'],
        ['key' => 'settings.preferences.fiscal_years.october_september', 'value' => '10-9'],
        ['key' => 'settings.preferences.fiscal_years.november_october', 'value' => '11-10'],
        ['key' => 'settings.preferences.fiscal_years.december_november', 'value' => '12-11'],
    ],

    /*
    * List of convert estimate options
    */
    'convert_estimate_options' => [
        ['key' => 'settings.preferences.no_action', 'value' => 'no_action'],
        ['key' => 'settings.preferences.delete_estimate', 'value' => 'delete_estimate'],
        ['key' => 'settings.preferences.mark_estimate_as_accepted', 'value' => 'mark_estimate_as_accepted'],
    ],

    /*
    * List of retrospective edits
    */
    'retrospective_edits' => [
        ['key' => 'settings.preferences.allow', 'value' => 'allow'],
        ['key' => 'settings.preferences.disable_on_invoice_partial_paid', 'value' => 'disable_on_invoice_partial_paid'],
        ['key' => 'settings.preferences.disable_on_invoice_paid', 'value' => 'disable_on_invoice_paid'],
        ['key' => 'settings.preferences.disable_on_invoice_sent', 'value' => 'disable_on_invoice_sent'],
    ],

    /*
    * List of setting menu
    */
    'setting_menu' => [
        [
            'title' => 'settings.menu_title.account_settings',
            'group' => '',
            'name' => 'Podešavanja naloga',
            'link' => '/admin/settings/account-settings',
            'icon' => 'UserIcon',
            'owner_only' => false,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.company_information',
            'group' => '',
            'name' => 'Podaci o kompaniji',
            'link' => '/admin/settings/company-info',
            'icon' => 'BuildingOfficeIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.preferences',
            'group' => '',
            'name' => 'Postavke',
            'link' => '/admin/settings/preferences',
            'icon' => 'CogIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.customization',
            'group' => '',
            'name' => 'Prilagođavanje',
            'link' => '/admin/settings/customization',
            'icon' => 'PencilSquareIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.pdf_generation',
            'group' => '',
            'name' => 'Generisanje PDF-a',
            'link' => '/admin/settings/pdf-generation',
            'icon' => 'DocumentIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.roles.title',
            'group' => '',
            'name' => 'Uloge',
            'link' => '/admin/settings/roles-settings',
            'icon' => 'UserGroupIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.exchange_rate',
            'group' => '',
            'name' => 'Pružalac kursne liste',
            'link' => '/admin/settings/exchange-rate-provider',
            'icon' => 'BanknotesIcon',
            'owner_only' => false,
            'ability' => 'view-exchange-rate-provider',
            'model' => ExchangeRateProvider::class,
        ],
        [
            'title' => 'settings.menu_title.notifications',
            'group' => '',
            'name' => 'Obavještenja',
            'link' => '/admin/settings/notifications',
            'icon' => 'BellIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.tax_types',
            'group' => '',
            'name' => 'Vrste poreza',
            'link' => '/admin/settings/tax-types',
            'icon' => 'CheckCircleIcon',
            'owner_only' => false,
            'ability' => 'view-tax-type',
            'model' => TaxType::class,
        ],
        [
            'title' => 'settings.menu_title.payment_modes',
            'group' => '',
            'name' => 'Načini plaćanja',
            'link' => '/admin/settings/payment-mode',
            'icon' => 'CreditCardIcon',
            'owner_only' => false,
            'ability' => 'view-payment',
            'model' => Payment::class,
        ],
        [
            'title' => 'settings.menu_title.custom_fields',
            'group' => '',
            'name' => 'Prilagođena polja',
            'link' => '/admin/settings/custom-fields',
            'icon' => 'CubeIcon',
            'owner_only' => false,
            'ability' => 'view-custom-field',
            'model' => CustomField::class,
        ],
        [
            'title' => 'settings.menu_title.notes',
            'group' => '',
            'name' => 'Napomene',
            'link' => '/admin/settings/notes',
            'icon' => 'ClipboardDocumentCheckIcon',
            'owner_only' => false,
            'ability' => 'view-all-notes',
            'model' => Note::class,
        ],
        [
            'title' => 'settings.menu_title.expense_category',
            'group' => '',
            'name' => 'Kategorija rashoda',
            'link' => '/admin/settings/expense-category',
            'icon' => 'ClipboardDocumentListIcon',
            'owner_only' => false,
            'ability' => 'view-expense',
            'model' => Expense::class,
        ],
        [
            'title' => 'settings.mail.mail_config',
            'group' => '',
            'name' => 'Podešavanje maila',
            'link' => '/admin/settings/mail-configuration',
            'icon' => 'EnvelopeIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.file_disk',
            'group' => '',
            'name' => 'Disk za fajlove',
            'link' => '/admin/settings/file-disk',
            'icon' => 'FolderIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.backup',
            'group' => '',
            'name' => 'Sigurnosna kopija',
            'link' => '/admin/settings/backup',
            'icon' => 'CircleStackIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'settings.menu_title.update_app',
            'group' => '',
            'name' => 'Ažuriranje aplikacije',
            'link' => '/admin/settings/update-app',
            'icon' => 'ArrowPathIcon',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
    ],

    /*
    * List of main menu
    */
    'main_menu' => [
        [
            'title' => 'navigation.dashboard',
            'group' => 1,
            'link' => '/admin/dashboard',
            'icon' => 'HomeIcon',
            'name' => 'Kontrolna tabla',
            'owner_only' => false,
            'ability' => 'dashboard',
            'model' => '',
        ],
        [
            'title' => 'navigation.customers',
            'group' => 1,
            'link' => '/admin/customers',
            'icon' => 'UserIcon',
            'name' => 'Klijenti',
            'owner_only' => false,
            'ability' => 'view-customer',
            'model' => Customer::class,
        ],
        [
            'title' => 'navigation.items',
            'group' => 1,
            'link' => '/admin/items',
            'icon' => 'StarIcon',
            'name' => 'Stavke',
            'owner_only' => false,
            'ability' => 'view-item',
            'model' => Item::class,
        ],
        [
            'title' => 'navigation.estimates',
            'group' => 2,
            'link' => '/admin/estimates',
            'icon' => 'DocumentIcon',
            'name' => 'Profakture',
            'owner_only' => false,
            'ability' => 'view-estimate',
            'model' => Estimate::class,
        ],
        [
            'title' => 'navigation.invoices',
            'group' => 2,
            'link' => '/admin/invoices',
            'icon' => 'DocumentTextIcon',
            'name' => 'Fakture',
            'owner_only' => false,
            'ability' => 'view-invoice',
            'model' => Invoice::class,
        ],
        [
            'title' => 'navigation.credit_notes',
            'group' => 2,
            'link' => '/admin/invoices?document_type=credit_note',
            'icon' => 'ArrowUturnLeftIcon',
            'name' => 'Knjižna odobrenja',
            'owner_only' => false,
            'ability' => 'view-invoice',
            'model' => Invoice::class,
        ],
        [
            'title' => 'navigation.recurring-invoices',
            'group' => 2,
            'link' => '/admin/recurring-invoices',
            'icon' => 'DocumentTextIcon',
            'name' => 'Ponavljajuće fakture',
            'owner_only' => false,
            'ability' => 'view-recurring-invoice',
            'model' => RecurringInvoice::class,
        ],
        [
            'title' => 'navigation.payments',
            'group' => 2,
            'link' => '/admin/payments',
            'icon' => 'CreditCardIcon',
            'name' => 'Uplate',
            'owner_only' => false,
            'ability' => 'view-payment',
            'model' => Payment::class,
        ],
        [
            'title' => 'navigation.expenses',
            'group' => 2,
            'link' => '/admin/expenses',
            'icon' => 'CalculatorIcon',
            'name' => 'Rashodi',
            'owner_only' => false,
            'ability' => 'view-expense',
            'model' => Expense::class,
        ],
        // TODO: remove env check once the module management os implemented.
        ...(
            env('APP_ENV', 'production') == 'development' ? [
                [
                    'title' => 'navigation.modules',
                    'group' => 3,
                    'link' => '/admin/modules',
                    'icon' => 'PuzzlePieceIcon',
                    'name' => 'Moduli',
                    'owner_only' => true,
                    'ability' => '',
                    'model' => '',
                ],
            ] : []
        ),
        [
            'title' => 'navigation.users',
            'group' => 3,
            'link' => '/admin/users',
            'icon' => 'UsersIcon',
            'name' => 'Korisnici',
            'owner_only' => true,
            'ability' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.reports',
            'group' => 3,
            'link' => '/admin/reports',
            'icon' => 'ChartBarIcon',
            'name' => 'Izvještaji',
            'owner_only' => false,
            'ability' => 'view-financial-reports',
            'model' => '',
        ],
        [
            'title' => 'navigation.settings',
            'group' => 3,
            'link' => '/admin/settings',
            'icon' => 'CogIcon',
            'name' => 'Podešavanja',
            'owner_only' => false,
            'ability' => '',
            'model' => '',
        ],
    ],

    /*
    * List of customer portal menu
    */
    'customer_menu' => [
        [
            'title' => 'navigation.dashboard',
            'link' => '/customer/dashboard',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.invoices',
            'link' => '/customer/invoices',
            'icon' => '',
            'name' => '',
            'ability' => '',
            'owner_only' => false,
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.estimates',
            'link' => '/customer/estimates',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.payments',
            'link' => '/customer/payments',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
        [
            'title' => 'navigation.settings',
            'link' => '/customer/settings',
            'icon' => '',
            'name' => '',
            'owner_only' => false,
            'ability' => '',
            'group' => '',
            'model' => '',
        ],
    ],

    /*
    * List of recurring invoice status
    */
    'recurring_invoice_status' => [
        'create_status' => [
            ['key' => 'settings.preferences.active', 'value' => 'ACTIVE'],
            ['key' => 'settings.preferences.on_hold', 'value' => 'ON_HOLD'],
        ],
        'update_status' => [
            ['key' => 'settings.preferences.active', 'value' => 'ACTIVE'],
            ['key' => 'settings.preferences.on_hold', 'value' => 'ON_HOLD'],
            ['key' => 'settings.preferences.completed', 'value' => 'COMPLETED'],
        ],
    ],

    /*
    * List of exchange rate provider (currency converter server's)
    */
    'currency_converter_servers' => [
        ['key' => 'settings.preferences.premium', 'value' => 'PREMIUM'],
        ['key' => 'settings.preferences.prepaid', 'value' => 'PREPAID'],
        ['key' => 'settings.preferences.free', 'value' => 'FREE'],
        ['key' => 'settings.preferences.dedicated', 'value' => 'DEDICATED'],
    ],

    /*
    * List of exchange rate drivers
    */
    'exchange_rate_drivers' => [
        ['key' => 'settings.exchange_rate.currency_converter', 'value' => 'currency_converter'],
        ['key' => 'settings.exchange_rate.currency_freak', 'value' => 'currency_freak'],
        ['key' => 'settings.exchange_rate.currency_layer', 'value' => 'currency_layer'],
        ['key' => 'settings.exchange_rate.open_exchange_rate', 'value' => 'open_exchange_rate'],
    ],

    /*
    * List of Custom field supported models
    */
    'custom_field_models' => [
        'Customer',
        'Estimate',
        'Invoice',
        'Payment',
        'Expense',
    ],
];
