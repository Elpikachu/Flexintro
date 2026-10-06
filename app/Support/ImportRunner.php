<?php

namespace App\Support;

use App\Jobs\ImportChunk;
use App\Models\Import;
use Illuminate\Support\Carbon;

/**
 * Creates an import and fans it out over the redis queue. Shared by the
 * `demo:import` command and the "Start import" button so both behave the same.
 */
class ImportRunner
{
    public function start(int $rows, int $rowsPerChunk): Import
    {
        $rows = max(1, $rows);
        $rowsPerChunk = max(1, $rowsPerChunk);
        $totalChunks = (int) ceil($rows / $rowsPerChunk);

        $import = Import::create([
            'status' => Import::STATUS_PENDING,
            'total_chunks' => $totalChunks,
            'processed_chunks' => 0,
            'rows_per_chunk' => $rowsPerChunk,
            'started_at' => Carbon::now(),
            'finished_at' => null,
        ]);

        for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
            ImportChunk::dispatch($import->id, $chunkIndex, $rowsPerChunk);
        }

        return $import;
    }
}
