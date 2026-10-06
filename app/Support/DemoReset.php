<?php

namespace App\Support;

use Illuminate\Contracts\Queue\ClearableQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

/**
 * Back to a clean dashboard: no import, no learners, nothing left in the queue.
 */
class DemoReset
{
    public function run(): void
    {
        $this->clearQueue();

        Schema::withoutForeignKeyConstraints(function () {
            DB::table('import_chunk_runs')->truncate();
            DB::table('imports')->truncate();
            DB::table('learners')->truncate();
        });

        // Chunks that were already in flight land on a deleted import and stop
        // at the null check in ImportChunk::refreshProgress().
        $this->clearQueue();
    }

    private function clearQueue(): void
    {
        try {
            $connection = Queue::connection();

            if ($connection instanceof ClearableQueue) {
                $connection->clear('default');
            }
        } catch (\Throwable) {
            // A missing queue is not a reason to fail the reset.
        }
    }
}
