<?php

namespace App\Jobs;

use App\Models\ImportChunkRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One slice of a synthetic import.
 *
 * The job is idempotent on purpose: scaling the queue worker redeploys the
 * environment, which can kill an instance mid chunk. The same chunk then runs
 * again on another instance and must not be counted twice.
 */
class ImportChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 2;

    // Stays below the redis queue's retry_after (90s) so a slow chunk is never
    // released while it is still running.
    public int $timeout = 60;

    public function __construct(
        public int $importId,
        public int $chunkIndex,
        public int $rowsPerChunk,
    ) {}

    public function handle(): void
    {
        // `demo:reset` can land between the dispatch and this job. Nothing to
        // write, and no failed job to explain in front of the customer.
        if (! DB::table('imports')->where('id', $this->importId)->exists()) {
            return;
        }

        $startedAt = hrtime(true);

        $this->burnCpu(config('demo.import_cpu_ms'));

        $rows = self::rowsFor($this->chunkIndex, $this->rowsPerChunk);

        // Upserts only: a replayed chunk rewrites the same learners.
        foreach (array_chunk($rows, 500) as $slice) {
            DB::table('learners')->upsert(
                $slice,
                ['external_id'],
                ['name', 'email', 'course_code', 'score', 'updated_at'],
            );
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($this->recordRun(count($rows), $durationMs)) {
            $this->refreshProgress();
        }
    }

    /**
     * Rows are derived from the chunk index, so no file and no upload is
     * involved and a replay produces exactly the same data.
     *
     * @return list<array<string, mixed>>
     */
    public static function rowsFor(int $chunkIndex, int $rowsPerChunk): array
    {
        static $firstNames = ['Alex', 'Billie', 'Camille', 'Dana', 'Eli', 'Flo', 'Gabi', 'Hugo', 'Iris', 'Jo', 'Kim', 'Lou', 'Mika', 'Noa', 'Oli', 'Remy'];
        static $lastNames = ['Adam', 'Benali', 'Costa', 'Dubois', 'Esposito', 'Fontaine', 'Garnier', 'Haddad', 'Ivanov', 'Jensen', 'Klein', 'Lambert', 'Moreau', 'Novak', 'Oliveira', 'Petit'];

        $now = Carbon::now()->toDateTimeString();
        $offset = $chunkIndex * $rowsPerChunk;
        $rows = [];

        for ($i = 0; $i < $rowsPerChunk; $i++) {
            $n = $offset + $i;
            $first = $firstNames[$n % count($firstNames)];
            $last = $lastNames[intdiv($n, count($firstNames)) % count($lastNames)];
            $externalId = sprintf('L%08d', $n);

            $rows[] = [
                'external_id' => $externalId,
                'name' => $first.' '.$last,
                'email' => strtolower($first.'.'.$last.'.'.$n).'@example.test',
                'course_code' => sprintf('C%03d', ($n % 40) + 1),
                'score' => ($n * 7919) % 101,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Spend a fixed amount of CPU so the import behaves like the customer's:
     * worker bound, not database bound.
     */
    private function burnCpu(int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            return;
        }

        $deadline = hrtime(true) + ($milliseconds * 1_000_000);
        $value = 'chunk-'.$this->importId.'-'.$this->chunkIndex;

        do {
            for ($i = 0; $i < 200; $i++) {
                $value = hash('sha256', $value);
            }
        } while (hrtime(true) < $deadline);
    }

    /**
     * Keyed on (import_id, chunk_index), so a retry overwrites its own row.
     * Returns false when the import was reset while this chunk was running.
     */
    private function recordRun(int $rows, int $durationMs): bool
    {
        try {
            ImportChunkRun::upsert(
                [[
                    'import_id' => $this->importId,
                    'chunk_index' => $this->chunkIndex,
                    'worker_host' => substr(gethostname() ?: 'unknown', 0, 128),
                    'rows' => $rows,
                    'duration_ms' => $durationMs,
                    'finished_at' => Carbon::now()->toDateTimeString(),
                ]],
                ['import_id', 'chunk_index'],
                ['worker_host', 'rows', 'duration_ms', 'finished_at'],
            );
        } catch (QueryException) {
            // The import row went away mid chunk: the foreign key refuses the
            // write, which is exactly what we want. Stop here.
            return false;
        }

        return true;
    }

    /**
     * Counts distinct finished chunks rather than incrementing by one, so a
     * replay cannot inflate the total. The `<` guard keeps concurrent workers
     * from writing a stale, lower count last.
     */
    private function refreshProgress(): void
    {
        $import = DB::table('imports')->where('id', $this->importId)->first();

        if ($import === null) {
            return;
        }

        $processed = DB::table('import_chunk_runs')
            ->where('import_id', $this->importId)
            ->count();

        $done = $processed >= $import->total_chunks;

        DB::table('imports')
            ->where('id', $this->importId)
            ->where('processed_chunks', '<', $processed)
            ->update([
                'processed_chunks' => $processed,
                'status' => $done ? 'done' : 'running',
                'finished_at' => $done ? Carbon::now()->toDateTimeString() : null,
                'updated_at' => Carbon::now()->toDateTimeString(),
            ]);
    }
}
