@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            {{-- small screens: simple prev / next using daisyUI buttons --}}
            @if ($paginator->onFirstPage())
                <button class="btn btn-ghost" disabled aria-label="{{ __('pagination.previous') }}">{!! __('pagination.previous') !!}</button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn">{!! __('pagination.previous') !!}</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn">{!! __('pagination.next') !!}</a>
            @else
                <button class="btn btn-ghost" disabled aria-label="{{ __('pagination.next') }}">{!! __('pagination.next') !!}</button>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-base-content leading-5">
                    Mostrando
                    @if ($paginator->firstItem())
                        <span class="font-medium">{{ $paginator->firstItem() }}</span>
                        a
                        <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    di
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    risultati
                </p>
            </div>

            <div>
                <div class="join" role="navigation" aria-label="Pagination Links">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <button aria-disabled="true" aria-label="{{ __('pagination.previous') }}" class="btn join-item btn-ghost" disabled>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn join-item" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <button class="btn join-item btn-ghost" disabled aria-disabled="true">{{ $element }}</button>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <button aria-current="page" class="btn join-item btn-primary" aria-label="Vai alla pagina {{ $page }}">{{ $page }}</button>
                                @else
                                    <a href="{{ $url }}" class="btn join-item" aria-label="Vai alla pagina {{ $page }}">{{ $page }}</a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn join-item" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <button aria-disabled="true" aria-label="{{ __('pagination.next') }}" class="btn join-item btn-ghost" disabled>
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </nav>
@endif
