@props(['tabs', 'current' => 'active'])

<nav class="status-tabs" aria-label="Status filter">
    @foreach ($tabs as $tab)
        <a class="status-tabs__link" href="{{ $tab['url'] }}" @if ($tab['key'] === $current) aria-current="page" @endif>
            {{ $tab['label'] }} ({{ $tab['count'] }})
        </a>
    @endforeach
</nav>
