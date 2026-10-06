<?php

namespace App\Support;

use App\Models\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Everything the dashboard polls, in one query set.
 */
class ImportStats
{
    public function snapshot(): array
    {
        $import = Import::query()->latest('id')->first();
        $windowSeconds = (int) config('demo.window_seconds');

        return [
            'import' => $this->importPayload($import),
            'queue_size' => $this->queueSize(),
            'window_seconds' => $windowSeconds,
            'chunks_per_minute' => $import ? $this->chunksInWindow($import, $windowSeconds) : 0,
            'workers' => $import ? $this->workers($import, $windowSeconds) : [],
            'server_time' => Carbon::now()->toIso8601String(),
        ];
    }

    private function importPayload(?Import $import): ?array
    {
        if ($import === null) {
            return null;
        }

        $rowsImported = (int) DB::table('import_chunk_runs')
            ->where('import_id', $import->id)
            ->sum('rows');

        $elapsed = $import->started_at
            ? ($import->finished_at ?? Carbon::now())->diffInSeconds($import->started_at, true)
            : 0;

        return [
            'id' => $import->id,
            'status' => $import->status,
            'total_chunks' => $import->total_chunks,
            'processed_chunks' => $import->processed_chunks,
            'rows_per_chunk' => $import->rows_per_chunk,
            'rows_total' => $import->total_chunks * $import->rows_per_chunk,
            'rows_imported' => $rowsImported,
            'percent' => $import->total_chunks > 0
                ? round($import->processed_chunks / $import->total_chunks * 100, 1)
                : 0.0,
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
            'elapsed_seconds' => (int) $elapsed,
        ];
    }

    private function chunksInWindow(Import $import, int $windowSeconds): int
    {
        return DB::table('import_chunk_runs')
            ->where('import_id', $import->id)
            ->where('finished_at', '>=', Carbon::now()->subSeconds($windowSeconds))
            ->count();
    }

    /**
     * One entry per worker instance that finished a chunk inside the window.
     * This is the strip that goes from one tile to three on scale up.
     */
    private function workers(Import $import, int $windowSeconds): array
    {
        $since = Carbon::now()->subSeconds($windowSeconds);

        $active = DB::table('import_chunk_runs')
            ->where('import_id', $import->id)
            ->where('finished_at', '>=', $since)
            ->groupBy('worker_host')
            ->selectRaw('worker_host, count(*) as chunks, max(finished_at) as last_seen, avg(duration_ms) as avg_duration_ms')
            ->get();

        // When did each host first show up in this import? The one that joined
        // last is the instance the presenter just added.
        $joinedAt = DB::table('import_chunk_runs')
            ->where('import_id', $import->id)
            ->groupBy('worker_host')
            ->selectRaw('worker_host, min(finished_at) as joined_at')
            ->pluck('joined_at', 'worker_host');

        $newest = null;

        if ($active->count() > 1) {
            $newest = $active
                ->sortByDesc(fn ($row) => $joinedAt[$row->worker_host] ?? '')
                ->first()?->worker_host;
        }

        return $active
            ->sortBy(fn ($row) => $joinedAt[$row->worker_host] ?? '')
            ->values()
            ->map(fn ($row) => [
                'host' => $row->worker_host,
                'chunks_per_minute' => (int) $row->chunks,
                'avg_duration_ms' => (int) round((float) $row->avg_duration_ms),
                'joined_at' => $joinedAt[$row->worker_host] ?? null,
                'last_seen' => $row->last_seen,
                'is_newest' => $row->worker_host === $newest,
            ])
            ->all();
    }

    /**
     * Never let a queue hiccup take the dashboard down mid demo.
     */
    private function queueSize(): int
    {
        try {
            return Queue::size();
        } catch (\Throwable) {
            return 0;
        }
    }
}
