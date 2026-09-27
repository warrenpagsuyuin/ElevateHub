<?php
namespace App\Http\Controllers;

use App\Services\ThesisEvidenceService;
use Illuminate\Http\Request;

class ThesisEvidenceController extends Controller
{
    public function index(ThesisEvidenceService $service)
    {
        return response()->view('admin.thesis-evidence.index', ['report'=>$service->report()])->header('Cache-Control','private, no-store');
    }

    public function export(Request $request, ThesisEvidenceService $service)
    {
        $input = $request->validate(['format'=>'nullable|in:html,json', 'table'=>'nullable|in:11,12,13,14,18,19,20,22,23']);
        $report = $service->report();
        if (($input['format'] ?? 'html') === 'json') {
            return response()->json($report, 200, ['Cache-Control'=>'private, no-store','Content-Disposition'=>'attachment; filename="thesis-evidence.json"']);
        }
        return response()->view('admin.thesis-evidence.print', ['report'=>$report,'only'=>$input['table'] ?? null])
            ->header('Cache-Control','private, no-store')->header('Content-Disposition','attachment; filename="thesis-evidence.html"');
    }
}
