<article class="stat-card admin-summary-card">
    <div class="info">
        <a class="stat-value summary-number" href="{{ route('admin.dashboard.directory', ['directory' => $card['key']]) }}" aria-label="View all {{ number_format($card['total']) }} {{ $card['title'] }}">{{ number_format($card['total']) }}</a>
        <span class="stat-label">{{ $card['label'] }}</span>
    </div>
    <div class="summary-breakdown">
        @foreach ($card['segments'] as $segment)
        <a class="summary-segment" href="{{ route('admin.dashboard.directory', ['directory' => $card['key'], 'category' => strtolower($segment['label'])]) }}" aria-label="View {{ number_format($segment['value']) }} {{ $segment['label'] }} {{ $card['title'] }}">
            <strong>{{ number_format($segment['value']) }}</strong><span>{{ $segment['label'] }}</span>
        </a>
        @endforeach
    </div>
</article>
