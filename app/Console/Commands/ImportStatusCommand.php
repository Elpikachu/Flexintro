<?php

namespace App\Console\Commands;

use App\Support\ImportStats;
use Illuminate\Console\Command;

class ImportStatusCommand extends Command
{
    protected $signature = 'demo:import-status {--quiet-if-done : Exit 0 silently when nothing is in flight, 1 otherwise}';

    protected $description = 'Print the latest import progress';

    public function handle(ImportStats $stats): int
    {
        $snapshot = $stats->snapshot();
        $import = $snapshot['import'];
        $inFlight = $import !== null && in_array($import['status'], ['pending', 'running'], true);

        // scripts/scale-for-import.sh polls this to know when to scale back in.
        if ($this->option('quiet-if-done')) {
            return $inFlight ? self::FAILURE : self::SUCCESS;
        }

        if ($import === null) {
            $this->line('No import yet.');

            return self::SUCCESS;
        }

        $this->line("Import #{$import['id']}  {$import['status']}");
        $this->line('  chunks     '.number_format($import['processed_chunks']).' of '.number_format($import['total_chunks'])." ({$import['percent']}%)");
        $this->line('  rows       '.number_format($import['rows_imported']).' of '.number_format($import['rows_total']));
        $this->line('  elapsed    '.$import['elapsed_seconds'].' s');
        $this->line('  queued     '.number_format($snapshot['queue_size']).' jobs');
        $this->line('  throughput '.number_format($snapshot['chunks_per_minute']).' chunks per minute');

        if ($snapshot['workers'] === []) {
            $this->line('  workers    none active in the last '.$snapshot['window_seconds'].' s');
        } else {
            $this->line('  workers    '.count($snapshot['workers']).' active');

            foreach ($snapshot['workers'] as $worker) {
                $this->line("    {$worker['host']}  {$worker['chunks_per_minute']} chunks per minute");
            }
        }

        return self::SUCCESS;
    }
}
