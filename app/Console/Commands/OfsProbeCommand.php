<?php

namespace App\Console\Commands;

use App\Services\Ofs\Contracts\OfsClient;
use App\Services\Ofs\OfsException;
use Illuminate\Console\Command;

class OfsProbeCommand extends Command
{
    protected $signature = 'ofs:probe';

    protected $description = 'Verify the configured OFS ESIR connection.';

    public function handle(OfsClient $client): int
    {
        try {
            $result = $client->probe();
        } catch (OfsException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('OFS probe successful.');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
