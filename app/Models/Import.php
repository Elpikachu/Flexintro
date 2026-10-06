<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_chunks' => 'integer',
            'processed_chunks' => 'integer',
            'rows_per_chunk' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function chunkRuns(): HasMany
    {
        return $this->hasMany(ImportChunkRun::class);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
