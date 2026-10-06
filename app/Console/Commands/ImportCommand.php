<?php

namespace App\Console\Commands;

use App\Support\ImportRunner;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'demo:import {--rows=200000 : Total synthetic rows} {--chunk=500 : Rows per queued chunk}';

    protected $description = 'Start a synthetic import: one queued chunk job per slice of rows';

    public function handle(ImportRunner $runner): int
    {
        $rows = (int) $this->option('rows');
        $chunk = (int) $this->option('chunk');

        if ($rows < 1 || $chunk < 1) {
            $this->error('--rows and --chunk must both be at least 1.');

            return self::FAILURE;
        }

        $import = $runner->start($rows, $chunk);

        $this->info("Import #{$import->id} queued.");
        $this->line('  chunks         '.number_format($import->total_chunks));
        $this->line('  rows per chunk '.number_format($import->rows_per_chunk));
        $this->line('  rows total     '.number_format($import->total_chunks * $import->rows_per_chunk));
        $this->line('  cpu per chunk  '.config('demo.import_cpu_ms').' ms');

        return self::SUCCESS;
    }
}
