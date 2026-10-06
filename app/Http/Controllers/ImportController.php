<?php

namespace App\Http\Controllers;

use App\Support\DemoReset;
use App\Support\ImportRunner;
use App\Support\ImportStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function index(ImportStats $stats): Response
    {
        return response()->view('import', [
            'snapshot' => $stats->snapshot(),
            'defaults' => config('demo.import'),
        ]);
    }

    public function status(ImportStats $stats): JsonResponse
    {
        return response()->json($stats->snapshot());
    }

    public function start(Request $request, ImportRunner $runner): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => ['nullable', 'integer', 'min:1', 'max:5000000'],
            'chunk' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $import = $runner->start(
            (int) ($validated['rows'] ?? config('demo.import.rows')),
            (int) ($validated['chunk'] ?? config('demo.import.chunk')),
        );

        return redirect()
            ->route('import.index')
            ->with('status', "Import #{$import->id} queued: ".number_format($import->total_chunks).' chunks.');
    }

    public function reset(DemoReset $reset): RedirectResponse
    {
        $reset->run();

        return redirect()
            ->route('import.index')
            ->with('status', 'Demo data cleared.');
    }
}
