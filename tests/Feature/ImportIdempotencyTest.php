<?php

namespace Tests\Feature;

use App\Jobs\ImportChunk;
use App\Models\Import;
use App\Models\ImportChunkRun;
use App\Models\Learner;
use App\Support\ImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the tests quick: the CPU burn is the point in production only.
        config(['demo.import_cpu_ms' => 0]);
    }

    public function test_it_queues_one_job_per_chunk(): void
    {
        Queue::fake();

        $import = app(ImportRunner::class)->start(rows: 1000, rowsPerChunk: 250);

        $this->assertSame(4, $import->total_chunks);
        $this->assertSame(250, $import->rows_per_chunk);
        Queue::assertPushed(ImportChunk::class, 4);
    }

    public function test_replaying_a_chunk_counts_it_once(): void
    {
        $import = $this->importWithoutDispatch(rows: 20, rowsPerChunk: 10);

        $job = new ImportChunk($import->id, 0, 10);
        $job->handle();
        $job->handle();
        $job->handle();

        $this->assertSame(1, ImportChunkRun::where('import_id', $import->id)->count());
        $this->assertSame(10, Learner::count());
        $this->assertSame(1, $import->fresh()->processed_chunks);
        $this->assertSame(Import::STATUS_RUNNING, $import->fresh()->status);
    }

    public function test_a_full_import_lands_on_exact_counts(): void
    {
        $import = $this->importWithoutDispatch(rows: 50, rowsPerChunk: 10);

        foreach (range(0, 4) as $chunkIndex) {
            (new ImportChunk($import->id, $chunkIndex, 10))->handle();
        }

        // One chunk retried after the import already finished.
        (new ImportChunk($import->id, 2, 10))->handle();

        $import->refresh();

        $this->assertSame(Import::STATUS_DONE, $import->status);
        $this->assertSame(5, $import->processed_chunks);
        $this->assertNotNull($import->finished_at);
        $this->assertSame(50, Learner::count());
        $this->assertSame(50, (int) ImportChunkRun::where('import_id', $import->id)->sum('rows'));
    }

    public function test_rows_are_deterministic_and_do_not_overlap_between_chunks(): void
    {
        $first = ImportChunk::rowsFor(0, 10);
        $again = ImportChunk::rowsFor(0, 10);
        $second = ImportChunk::rowsFor(1, 10);

        $this->assertSame(
            array_column($first, 'external_id'),
            array_column($again, 'external_id'),
        );

        $this->assertSame(
            [],
            array_intersect(array_column($first, 'external_id'), array_column($second, 'external_id')),
        );
    }

    public function test_a_chunk_whose_import_was_reset_does_nothing(): void
    {
        $import = $this->importWithoutDispatch(rows: 10, rowsPerChunk: 10);
        $importId = $import->id;
        $import->delete();

        (new ImportChunk($importId, 0, 10))->handle();

        $this->assertSame(0, Learner::count());
        $this->assertSame(0, ImportChunkRun::count());
    }

    private function importWithoutDispatch(int $rows, int $rowsPerChunk): Import
    {
        Queue::fake();

        return app(ImportRunner::class)->start($rows, $rowsPerChunk);
    }
}
