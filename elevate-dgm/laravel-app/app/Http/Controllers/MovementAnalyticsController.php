<?php

namespace App\Http\Controllers;

use App\Models\Checkpoint;
use App\Models\OrgUnit;
use App\Services\MovementAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MovementAnalyticsController extends Controller
{
    public function __invoke(Request $request, MovementAnalyticsService $analytics): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'checkpoint' => ['required', 'string', Rule::in(Checkpoint::pluck('key')->all())],
            'compare' => ['sometimes', Rule::in(['baseline', 'previous'])],
            'group' => ['nullable', 'string', Rule::in(OrgUnit::where('type', 'group')->pluck('group')->all())],
            'mode' => ['sometimes', Rule::in(['auto', 'reported', 'live'])],
            'format' => ['sometimes', Rule::in(['json', 'csv'])],
            'sample' => ['sometimes', 'boolean'],
        ]);
        abort_if(($data['sample'] ?? false) && ($data['format'] ?? 'json') === 'csv', 403, 'Sample data cannot be exported.');
        $requestId = (string) Str::uuid();
        try {
            $report = $analytics->dashboard($data['checkpoint'], $data['compare'] ?? 'baseline', $data['group'] ?? null, $data['mode'] ?? 'auto', (bool) ($data['sample'] ?? false));
        } catch (Throwable $exception) {
            Log::error('Movement analytics request failed.', [
                'request_id' => $requestId,
                'checkpoint' => $data['checkpoint'],
                'group' => $data['group'] ?? null,
                'mode' => $data['mode'] ?? 'auto',
                'exception' => $exception,
            ]);

            return response()->json([
                'metrics' => null,
                'completeness' => ['total' => 0, 'reported' => 0, 'live' => 0, 'missing' => 0, 'percent' => 0],
                'meta' => ['request_id' => $requestId],
                'message' => "We couldn't load the analytics. Please try again.",
            ], 500);
        }
        if (($data['format'] ?? 'json') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $file = fopen('php://output', 'wb');
                foreach ([$report['pivot']['columns'], ...$report['pivot']['rows']] as $row) {
                    $safe = array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v, $row);
                    fputcsv($file, $safe, ',', '"', '');
                }
                fclose($file);
            }, 'elevate-movement-'.$data['checkpoint'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        $root = collect($report['units'] ?? [])->first(fn (array $unit): bool => $unit['type'] === ($report['group'] ? 'group' : 'organization'));
        $report['metrics'] = $root['metrics'] ?? null;
        $report['completeness'] = array_merge(['total' => 0, 'reported' => 0, 'live' => 0, 'missing' => 0, 'percent' => 0], $report['completeness'] ?? []);
        $reported = (int) $report['completeness']['reported'];
        $live = (int) $report['completeness']['live'];
        $source = match (true) {
            $report['mode'] === 'live' => 'live',
            $report['mode'] === 'reported' => 'reported',
            $reported === 0 && $live > 0 => 'live_fallback',
            $reported > 0 && $live > 0 => 'mixed',
            $reported > 0 => 'reported',
            default => 'none',
        };
        $report['meta'] = ['request_id' => $requestId, 'display_source' => $source];

        return response()->json($report)
            ->header('Cache-Control', 'private, no-store');
    }
}
