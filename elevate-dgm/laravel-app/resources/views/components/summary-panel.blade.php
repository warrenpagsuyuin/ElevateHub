<section class="summary-panel" aria-labelledby="{{ $id }}">
    <h3 id="{{ $id }}">{{ $title }}</h3>
    <dl class="mini-stat-list">
        @foreach($items as $item)
            <div class="summary-row">
                <dt class="summary-row-label" title="{{ $item['label'] }}">{{ $item['label'] }}</dt>
                <dd class="summary-row-value">{{ number_format($item['value']) }}</dd>
            </div>
        @endforeach
    </dl>
</section>
