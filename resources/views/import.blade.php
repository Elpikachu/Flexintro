@extends('layout')

@section('title', 'Import')

@push('styles')
<style>
    .toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 32px;
    }

    .toolbar form { margin: 0; }

    .toolbar .spacer { flex: 1; }

    /* The worker strip carries the demo, so it gets the weight. */

    .workers {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        margin-bottom: 24px;
    }

    .workers-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 12px;
        margin-bottom: 20px;
    }

    .workers-head h2 {
        font-size: 24px;
        margin: 0;
    }

    .workers-head .count {
        font-size: 24px;
        font-weight: 700;
        color: var(--violet);
    }

    .workers-head .hint {
        margin-left: auto;
        font-size: 15px;
        color: var(--muted);
    }

    .worker-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 16px;
        min-height: 150px;
    }

    .worker-tile {
        border: 2px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        background: var(--bg);
    }

    .worker-tile.is-newest {
        background: var(--lime);
        border-color: var(--lime);
        color: #0C0F10;
    }

    .worker-tile .tile-host {
        font-family: var(--mono);
        font-size: 17px;
        font-weight: 500;
        word-break: break-all;
        margin-bottom: 14px;
    }

    .worker-tile .tile-rate {
        font-size: 40px;
        font-weight: 700;
        line-height: 1.05;
        letter-spacing: -0.02em;
    }

    .worker-tile .tile-unit {
        font-size: 15px;
        color: var(--muted);
    }

    .worker-tile.is-newest .tile-unit { color: #0C0F10; opacity: 0.75; }

    .worker-empty {
        grid-column: 1 / -1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--muted);
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        min-height: 150px;
        text-align: center;
        padding: 16px;
    }

    /* Quiet supporting numbers. */

    .progress-card { margin-bottom: 24px; }

    .progress-head {
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 12px;
        margin-bottom: 14px;
    }

    .progress-head .chunks { font-weight: 600; }

    .progress-head .status {
        margin-left: auto;
        font-size: 15px;
        color: var(--muted);
    }

    .bar {
        height: 14px;
        border-radius: 999px;
        background: var(--bg);
        border: 1px solid var(--border);
        overflow: hidden;
    }

    .bar > div {
        height: 100%;
        width: 0;
        background: var(--violet);
        transition: width 0.4s ease;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 1px;
        background: var(--border);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .stat {
        background: var(--surface);
        padding: 18px 20px;
    }

    .stat dt {
        font-size: 15px;
        color: var(--muted);
        margin-bottom: 4px;
    }

    .stat dd {
        margin: 0;
        font-size: 26px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }

    .cli {
        margin-top: 24px;
        font-family: var(--mono);
        font-size: 15px;
        color: var(--muted);
    }
</style>
@endpush

@section('content')
    <h1>Espace Pro import</h1>
    <p class="lede">
        Rows are generated in the queue, one job per chunk. Scale the queue worker out and the strip below fills up.
    </p>

    <div class="toolbar">
        <form method="POST" action="{{ route('import.start') }}">
            @csrf
            <input type="hidden" name="rows" value="{{ $defaults['rows'] }}">
            <input type="hidden" name="chunk" value="{{ $defaults['chunk'] }}">
            <button type="submit">Start import</button>
        </form>
        <span class="muted" style="font-size: 15px;">
            {{ number_format($defaults['rows']) }} rows, {{ number_format($defaults['chunk']) }} per chunk
        </span>
        <span class="spacer"></span>
        <form method="POST" action="{{ route('import.reset') }}"
              onsubmit="return confirm('Clear imports, learners and the queue?');">
            @csrf
            <button type="submit" class="secondary">Reset demo data</button>
        </form>
    </div>

    <section class="workers" aria-labelledby="workers-heading">
        <div class="workers-head">
            <h2 id="workers-heading">Worker instances</h2>
            <span class="count" data-worker-count>0</span>
            <span class="hint">Active in the last {{ $snapshot['window_seconds'] }} seconds</span>
        </div>
        <div class="worker-strip" data-worker-strip aria-live="polite">
            <div class="worker-empty">No worker has finished a chunk yet.</div>
        </div>
    </section>

    <section class="card progress-card" aria-label="Import progress">
        <div class="progress-head">
            <span class="chunks" data-chunks>0 of 0 chunks</span>
            <span class="status" data-status>No import yet</span>
        </div>
        <div class="bar"><div data-bar></div></div>
    </section>

    <dl class="stats">
        <div class="stat">
            <dt>Rows imported</dt>
            <dd data-rows>0</dd>
        </div>
        <div class="stat">
            <dt>Chunks per minute</dt>
            <dd data-rate>0</dd>
        </div>
        <div class="stat">
            <dt>Jobs in the queue</dt>
            <dd data-queue>0</dd>
        </div>
        <div class="stat">
            <dt>Elapsed</dt>
            <dd data-elapsed>0:00</dd>
        </div>
    </dl>

    <p class="cli">upsun resources:set --count &lt;queue-worker&gt;:3</p>
@endsection

@push('scripts')
<script>
    (function () {
        var endpoint = @json(route('import.status'));
        var strip = document.querySelector('[data-worker-strip]');
        var el = {
            count: document.querySelector('[data-worker-count]'),
            chunks: document.querySelector('[data-chunks]'),
            status: document.querySelector('[data-status]'),
            bar: document.querySelector('[data-bar]'),
            rows: document.querySelector('[data-rows]'),
            rate: document.querySelector('[data-rate]'),
            queue: document.querySelector('[data-queue]'),
            elapsed: document.querySelector('[data-elapsed]')
        };

        function number(value) {
            return (value || 0).toLocaleString('en-GB');
        }

        function clock(seconds) {
            var s = Math.max(0, seconds || 0);
            return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        }

        function renderWorkers(workers) {
            el.count.textContent = workers.length;

            if (!workers.length) {
                strip.innerHTML = '<div class="worker-empty">No worker has finished a chunk yet.</div>';
                return;
            }

            strip.innerHTML = '';

            workers.forEach(function (worker) {
                var tile = document.createElement('div');
                tile.className = 'worker-tile' + (worker.is_newest ? ' is-newest' : '');

                var host = document.createElement('div');
                host.className = 'tile-host';
                host.textContent = worker.host;

                var rate = document.createElement('div');
                rate.className = 'tile-rate';
                rate.textContent = number(worker.chunks_per_minute);

                var unit = document.createElement('div');
                unit.className = 'tile-unit';
                unit.textContent = 'chunks per minute';

                tile.appendChild(host);
                tile.appendChild(rate);
                tile.appendChild(unit);
                strip.appendChild(tile);
            });
        }

        function render(data) {
            var run = data.import;

            renderWorkers(data.workers || []);

            el.queue.textContent = number(data.queue_size);
            el.rate.textContent = number(data.chunks_per_minute);

            if (!run) {
                el.chunks.textContent = '0 of 0 chunks';
                el.status.textContent = 'No import yet';
                el.bar.style.width = '0%';
                el.rows.textContent = '0';
                el.elapsed.textContent = '0:00';
                return;
            }

            el.chunks.textContent = number(run.processed_chunks) + ' of ' + number(run.total_chunks) + ' chunks';
            el.status.textContent = 'Import #' + run.id + ', ' + run.status;
            el.bar.style.width = run.percent + '%';
            el.rows.textContent = number(run.rows_imported);
            el.elapsed.textContent = clock(run.elapsed_seconds);
        }

        function poll() {
            fetch(endpoint, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (data) { if (data) { render(data); } })
                .catch(function () { /* A dropped poll on a weak network is not worth showing. */ });
        }

        render(@json($snapshot));
        setInterval(poll, 2000);
    })();
</script>
@endpush
