@extends('layout')

@section('title', 'Files')

@push('styles')
<style>
    .files-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 24px;
        align-items: start;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th, td {
        text-align: left;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
        vertical-align: baseline;
    }

    th {
        font-size: 15px;
        font-weight: 600;
        color: var(--muted);
    }

    tbody tr:last-child td { border-bottom: none; }

    tbody tr:hover { background: var(--bg); }

    td a {
        font-weight: 600;
        text-decoration: none;
    }

    td a:hover { text-decoration: underline; }

    .folder {
        display: block;
        font-size: 14px;
        color: var(--muted);
        font-family: var(--mono);
    }

    .num {
        font-family: var(--mono);
        font-size: 15px;
        color: var(--muted);
        white-space: nowrap;
    }

    .where h2 {
        font-size: 20px;
        margin: 0 0 16px;
    }

    .where dl {
        margin: 0 0 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .where dt {
        font-size: 14px;
        color: var(--muted);
    }

    .where dd {
        margin: 2px 0 0;
        font-family: var(--mono);
        font-size: 16px;
        word-break: break-all;
    }

    .where p {
        margin: 0;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        color: var(--muted);
        font-size: 16px;
    }

    .empty {
        padding: 48px 24px;
        text-align: center;
        color: var(--muted);
    }

    .empty code {
        font-family: var(--mono);
        background: var(--bg);
        padding: 4px 8px;
        border-radius: 6px;
        color: var(--text);
    }

    @media (max-width: 860px) {
        .files-grid { grid-template-columns: 1fr; }
        .hide-small { display: none; }
    }
</style>
@endpush

@section('content')
    <h1>Course files</h1>
    <p class="lede">
        Stored in the application object storage bucket, served to the browser by a Laravel route.
    </p>

    @if ($error)
        <div class="notice">
            <strong>Object storage is not reachable.</strong>
            <span class="muted mono">{{ $error }}</span>
        </div>
    @endif

    <div class="files-grid">
        <div class="card" style="padding: 8px 8px 0;">
            @if ($files === [])
                <div class="empty">
                    No files yet. Run <code>php artisan demo:seed-files</code>.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Size</th>
                            <th scope="col" class="hide-small">Last modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td>
                                    <a href="{{ url('/files/'.$file['path']) }}">{{ $file['name'] }}</a>
                                    @if ($file['folder'] !== '')
                                        <span class="folder">{{ $file['folder'] }}/</span>
                                    @endif
                                </td>
                                <td class="num">{{ $file['size'] >= 1024 ? number_format($file['size'] / 1024, 1).' kB' : $file['size'].' B' }}</td>
                                <td class="num hide-small">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['last_modified'])->format('j M Y, H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <aside class="card where">
            <h2>Where these files live</h2>
            <dl>
                <div>
                    <dt>Storage endpoint</dt>
                    <dd>{{ $storage['host'] ?: 'not configured' }}@if ($storage['port']):{{ $storage['port'] }}@endif</dd>
                </div>
                <div>
                    <dt>Bucket</dt>
                    <dd>{{ $storage['bucket'] ?: 'not configured' }}</dd>
                </div>
            </dl>
            <p>
                This endpoint is only reachable inside the project. Files reach the browser through the app.
            </p>
        </aside>
    </div>
@endsection
