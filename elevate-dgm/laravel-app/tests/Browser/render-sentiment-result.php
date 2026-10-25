<?php

// Render only the shared result component; no records or model services are used.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = storage_path('framework/testing/sentiment-result');
if (!is_dir($dir)) mkdir($dir, 0700, true);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
$css = '/build/'.$manifest['resources/css/shared-theme.css']['file'];
$html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="'.$css.'"></head><body><table style="width:100%;table-layout:fixed"><tbody>';
foreach (['Neutral', 'Positive', 'Negative'] as $sentiment) {
    $explanation = $sentiment === 'Neutral' ? 'No text was available, so sentiment defaults to Neutral.' : 'Existing '.$sentiment.' decision-path explanation. '.str_repeat('Long explanation text ', 25).str_repeat('unbroken', 30);
    $html .= '<tr><td>'.view('components.sentiment-result', ['showPrediction' => true, 'sentiment' => $sentiment, 'confidence' => .5, 'decisionPath' => [$explanation]])->render().'</td></tr>';
}
$html .= '</tbody></table></body></html>';
file_put_contents($dir.'/results.html', $html);
echo "Rendered sentiment result fixture without database access.\n";
