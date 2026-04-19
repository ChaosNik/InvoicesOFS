<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $templates = Storage::disk('views')->files('/app/pdf/invoice');

        foreach ($templates as $key => $template) {
            if (! Str::endsWith($template, '.blade.php')) {
                continue;
            }

            $templateName = Str::before(basename($template), '.blade.php');
            $source = public_path("/assets/img/PDF/{$templateName}.png");

            if (file_exists($source) && ! file_exists(resource_path("/static/img/PDF/{$templateName}.png"))) {
                copy($source, public_path("/build/img/PDF/{$templateName}.png"));
                copy($source, resource_path("/static/img/PDF/{$templateName}.png"));
            }
        }

        $templates = Storage::disk('views')->files('/app/pdf/estimate');

        foreach ($templates as $key => $template) {
            if (! Str::endsWith($template, '.blade.php')) {
                continue;
            }

            $templateName = Str::before(basename($template), '.blade.php');
            $source = public_path("/assets/img/PDF/{$templateName}.png");

            if (file_exists($source) && ! file_exists(resource_path("/static/img/PDF/{$templateName}.png"))) {
                copy($source, public_path("/build/img/PDF/{$templateName}.png"));
                copy($source, resource_path("/static/img/PDF/{$templateName}.png"));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
