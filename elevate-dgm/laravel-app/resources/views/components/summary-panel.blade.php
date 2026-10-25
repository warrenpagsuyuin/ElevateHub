<section class="summary-panel" aria-labelledby="{{ $id }}">
    <h2 id="{{ $id }}">{{ $title }}</h2>
    <dl class="mini-stat-list">
        @foreach($items as $item)
            <div class="mini-stat elevate-stat-card">
                <dt class="summary-row-label">{{ $item['label'] }}</dt>
                <dd class="summary-row-value">{{ number_format($item['value']) }}</dd>
            </div>
        @endforeach
    </dl>
</section>
