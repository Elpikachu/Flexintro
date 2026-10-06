<?php

namespace App\Console\Commands;

use App\Support\DemoReset;
use Illuminate\Console\Command;

class ResetCommand extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Clear imports, chunk runs, learners and the queue';

    public function handle(DemoReset $reset): int
    {
        $reset->run();

        $this->info('Demo data cleared. The dashboard is back to an empty state.');

        return self::SUCCESS;
    }
}
