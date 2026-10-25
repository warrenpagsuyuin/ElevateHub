@php
    $valueText = $emptyPeriod ? '—' : number_format($card['total']);
    $accessibleValue = $emptyPeriod ? 'no data' : number_format($card['total']);
    $accessibleName = $card['label'].', '.$accessibleValue;
    if (isset($card['accessiblePeriod'])) {
        $accessibleName .= ', '.$card['accessiblePeriod'];
    }
@endphp
<a class="stat-card elevate-stat-card stat-card-link"
   data-focus-key="card-{{ $index }}"
   href="{{ $card['href'] }}"
   aria-label="{{ $accessibleName }}">
    <span class="stat-label stat-card-label" title="{{ $card['label'] }}">{{ $card['label'] }}</span>
    <div class="stat-card-value-row">
        <strong class="stat-value stat-card-value">{{ $valueText }}</strong>
        <span class="card-chevron" aria-hidden="true">›</span>
    </div>
</a>
