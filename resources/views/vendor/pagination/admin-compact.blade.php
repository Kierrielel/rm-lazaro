@if ($paginator->hasPages())
    <nav class="admin-pagination" role="navigation" aria-label="{{ __('Pagination Navigation') }}">
        <p class="admin-pagination-summary">
            {{ __('Showing') }}
            <strong>{{ $paginator->firstItem() ?? 0 }}</strong>
            {{ __('to') }}
            <strong>{{ $paginator->lastItem() ?? 0 }}</strong>
            {{ __('of') }}
            <strong>{{ $paginator->total() }}</strong>
            {{ __('results') }}
        </p>

        <ul class="inventory-pagination">
            @if ($paginator->onFirstPage())
                <li aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <span class="is-disabled" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}">&lsaquo;</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li aria-disabled="true"><span class="is-disabled">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li aria-current="page"><span class="is-current">{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}">&rsaquo;</a>
                </li>
            @else
                <li aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <span class="is-disabled" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
