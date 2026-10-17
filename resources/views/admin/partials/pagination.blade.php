@if ($paginator->hasPages())
    <nav class="shop-pagination d-flex flex-wrap align-items-center justify-content-between gap-2" aria-label="صفحه‌بندی">
        @if (method_exists($paginator, 'total'))
            <p class="small text-muted mb-0">
                نمایش
                <span class="fw-semibold">{{ number_format((int) $paginator->firstItem()) }}</span>
                تا
                <span class="fw-semibold">{{ number_format((int) $paginator->lastItem()) }}</span>
                از
                <span class="fw-semibold">{{ number_format((int) $paginator->total()) }}</span>
                مورد
            </p>
        @endif

        <ul class="pagination pagination-sm mb-0">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true"><i class="ph-caret-right"></i></span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="قبلی">
                        <i class="ph-caret-right"></i>
                    </a>
                </li>
            @endif

            @foreach ($elements ?? [] as $element)
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="بعدی">
                        <i class="ph-caret-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link" aria-hidden="true"><i class="ph-caret-left"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
