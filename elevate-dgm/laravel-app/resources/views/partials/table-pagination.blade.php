@if ($paginator->total() > 0)
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $pageLinks = collect([1, $lastPage, $currentPage - 1, $currentPage, $currentPage + 1])
            ->filter(fn ($page) => $page >= 1 && $page <= $lastPage)
            ->unique()->sort()->values();
        $pageSizes = $pageSizes ?? [10, 25, 50, 100];
        $currentPageSize = $currentPageSize ?? (int) request('per_page', $paginator->perPage());
        $recordLabel = $recordLabel ?? 'records';
        $tableId = $tableId ?? 'table';
    @endphp

    <nav class="pagination-bar" id="{{ $tableId }}-pagination" aria-label="Pagination" data-pagination-nav>
        <div class="pagination-summary">
            Showing {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ $recordLabel }}
        </div>

        <form method="GET" action="{{ url()->current() }}" class="pagination-page-size">
            @foreach (request()->except(['page', 'per_page']) as $key => $value)
                @if (is_array($value))
                    @foreach ($value as $nestedValue)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $nestedValue }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="per-page-{{ spl_object_id($paginator) }}">Rows per page</label>
            <select id="per-page-{{ spl_object_id($paginator) }}" name="per_page" data-pagination-page-size>
                @foreach ($pageSizes as $pageSize)
                    <option value="{{ $pageSize }}" @selected((int) $currentPageSize === (int) $pageSize)>{{ $pageSize }}</option>
                @endforeach
            </select>
        </form>

        @if ($paginator->hasPages())
            <div class="pagination-links">
                @if ($paginator->onFirstPage())
                    <button type="button" class="pagination-disabled" disabled>&lsaquo; Previous</button>
                @else
                    <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" aria-label="Go to previous page" data-pagination-link>&lsaquo; Previous</a>
                @endif

                <span class="pagination-mobile-status">Page {{ $currentPage }} of {{ $lastPage }}</span>
                @php $previousPage = null; @endphp
                @foreach ($pageLinks as $page)
                    @if ($previousPage !== null && $page > $previousPage + 1)<span class="pagination-ellipsis" aria-hidden="true">&hellip;</span>@endif
                    @if ($page === $lastPage && $currentPage < $lastPage - 1)<span class="pagination-mobile-ellipsis" aria-hidden="true">&hellip;</span>@endif
                    @if ($page === $currentPage)
                        <span class="pagination-current" aria-current="page" aria-label="Page {{ $page }}, current page">{{ $page }}</span>
                    @else
                        <a @class(['pagination-link', 'pagination-page-number', 'pagination-last-page' => $page === $lastPage]) href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}" data-pagination-link>{{ $page }}</a>
                    @endif
                    @php $previousPage = $page; @endphp
                @endforeach

                @if ($paginator->hasMorePages())
                    <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" aria-label="Go to next page" data-pagination-link>Next &rsaquo;</a>
                @else
                    <button type="button" class="pagination-disabled" disabled>Next &rsaquo;</button>
                @endif
            </div>
        @endif
        <span class="pagination-loading-status" data-pagination-loading-status role="status" aria-live="polite"></span>
    </nav>
@endif

@once
<script>
    (() => {
        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const link = event.target.closest('[data-pagination-link]');
            const pagination = link?.closest('[data-pagination-nav]');

            if (!link || !pagination) {
                return;
            }

            pagination.classList.add('pagination-loading');
            pagination.querySelector('[data-pagination-loading-status]').textContent = 'Loading page...';
            pagination.querySelectorAll('a').forEach((control) => { control.setAttribute('aria-disabled', 'true'); control.style.pointerEvents = 'none'; });
            sessionStorage.setItem('pagination-scroll-target', pagination.id);
        });

        document.addEventListener('change', (event) => {
            if (!(event.target instanceof HTMLSelectElement) || !event.target.matches('[data-pagination-page-size]')) {
                return;
            }

            event.target.form?.requestSubmit();
        });

        const restorePaginationScroll = () => {
            const paginationScrollTarget = sessionStorage.getItem('pagination-scroll-target');

            if (!paginationScrollTarget) {
                return;
            }

            sessionStorage.removeItem('pagination-scroll-target');
            document.getElementById(paginationScrollTarget)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restorePaginationScroll, { once: true });
        } else {
            restorePaginationScroll();
        }
    })();
</script>
@endonce
