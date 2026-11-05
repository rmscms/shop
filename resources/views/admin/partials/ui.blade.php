{{--
  Loads the shared shop admin look (public/admin/css/shop-ui.css). Include once per page:
    @include('shop::admin.partials.ui')
  and give the page wrapper the scope class:  <div class="content shop-ui"> ... </div>
--}}
@once
  @push('styles')
    @php($shopUiCss = public_path('admin/css/shop-ui.css'))
    <link rel="stylesheet" href="{{ asset('admin/css/shop-ui.css') }}?v={{ is_file($shopUiCss) ? filemtime($shopUiCss) : 1 }}">
  @endpush
@endonce
