{{-- Call only where predictions are already authorized. Omission hides all result data. --}}
@if ($showPrediction ?? false)
    @if ($decisionPath)
        <details class="sentiment-result" data-sentiment="{{ $sentiment ?? 'Unanalyzed' }}">
            <summary>
                <span class="sentiment-result-badge">{{ $sentiment ?? 'Unanalyzed' }}<svg class="sentiment-result-chevron" width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></span>
                @if ($confidence !== null)<small class="sentiment-result-confidence">{{ number_format($confidence * 100, 0) }}% confidence</small>@endif
            </summary>
            <ul class="sentiment-result-explanation">
                @foreach ($decisionPath as $step)<li>{{ $step }}</li>@endforeach
            </ul>
        </details>
    @else
        <div class="sentiment-result" data-sentiment="{{ $sentiment ?? 'Unanalyzed' }}">
            <span class="sentiment-result-badge">{{ $sentiment ?? 'Unanalyzed' }}</span>
            @if ($confidence !== null)<small class="sentiment-result-confidence">{{ number_format($confidence * 100, 0) }}% confidence</small>@endif
        </div>
    @endif
@endif
