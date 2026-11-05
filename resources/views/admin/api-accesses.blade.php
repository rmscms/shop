@extends('cms::admin.layout.index')

@php
  $total = $accesses->count();
  $activeCount = $accesses->filter(fn ($a) => $a->isUsable())->count();
  $expiryPresets = \RMS\Shop\Models\ShopApiAccess::EXPIRY_PRESETS;
  $expiryDefault = \RMS\Shop\Models\ShopApiAccess::EXPIRY_DEFAULT;
  $writeCount = $accesses->where('permission', 'read_write')->count();
  $readCount = $total - $writeCount;
  $adminApi = collect($endpoints ?? [])->firstWhere('title', 'API ادمین')['url'] ?? url('api/v1/admin/shop');
  $endpointIcons = ['داک API' => ['ph-book-open-text', 'info'], 'API ادمین' => ['ph-shield-check', 'warning'], 'API پنل' => ['ph-users-three', 'success']];
  $docsUrl = collect($endpoints ?? [])->firstWhere('title', 'داک API')['url'] ?? url('api/documentation');
  // Everything an agent needs in one paste, with the token inside.
  $connectionText = function ($access) use ($adminApi, $docsUrl) {
      $level = $access->permission === 'read_write' ? 'خواندن و نوشتن (قبل از هر نوشتن از اپراتور اجازه بگیر)' : 'فقط خواندن (پیشنهاد و بررسی ثبت می‌شود و ادمین تأیید می‌کند)';

      return "اتصال به API فروشگاه digitalpart.ir\n"
          ."آدرس API: {$adminApi}\n"
          ."توکن (Bearer): {$access->token}\n"
          ."سطح این توکن: {$level}\n"
          ."اعتبار توکن: ".($access->expires_at ? $access->expires_at->format('Y-m-d H:i').' ('.$access->expiryLabel().')؛ بعد از آن 401 می‌دهد و باید توکن تازه گرفت' : 'بدون انقضا')."\n"
          ."مستندات: {$docsUrl}\n"
          ."راهنمای ایجنت: GET {$adminApi}/ai/guide\n"
          ."صندوق کارها: GET {$adminApi}/ai/inbox\n\n"
          ."روش کار:\n"
          ."1. همهٔ درخواست‌ها با هدر Authorization: Bearer <توکن> انجام می‌شود.\n"
          ."2. اول راهنما (ai/guide) و بعد صندوق (ai/inbox) را بخوان و طبق آن کار کن.\n"
          ."3. نتیجهٔ هر کار را در سیستم ثبت کن (بررسی با ai/reviews، پیشنهاد تغییر با ai/proposals، یادداشت با ai/notes)؛ فقط در چت گزارش نده.\n"
          ."4. بدون «اعمال کن» صریح اپراتور چیزی را روی محصول، دسته یا برند مستقیم تغییر نده.\n"
          ."5. توکن را در جای عمومی یا مخزن کد ننویس.";
  };
  $agentPrompt ="آدرس API: {$adminApi}\nتوکن: (توکن این صفحه را اینجا بگذار)\nاول GET {$adminApi}/ai/guide و بعد GET {$adminApi}/ai/inbox را با هدر Authorization: Bearer بخوان و طبق راهنما کار کن.\nنتیجهٔ هر کار را در سیستم ثبت کن (نه فقط در چت) و بدون «اعمال کن» صریح من چیزی را روی محصول تغییر نده.";
@endphp

@section('content')
<div class="content api-access-page">
  @if(session('success'))
    <div class="alert alert-success d-flex align-items-center"><i class="ph-check-circle me-2"></i>{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger d-flex align-items-center"><i class="ph-warning-circle me-2"></i>{{ $errors->first() }}</div>
  @endif

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول دسترسی هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    {{-- Header + numbers --}}
    <div class="d-flex align-items-center flex-wrap gap-3 mb-3">
      <span class="api-icon bg-primary bg-opacity-10 text-primary"><i class="ph-key ph-2x"></i></span>
      <div>
        <h4 class="mb-0">دسترسی API</h4>
        <div class="text-muted">توکن‌های اتصال اپ‌ها و ایجنت‌ها به فروشگاه</div>
      </div>
      <button class="btn btn-primary ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#new-access">
        <i class="ph-plus me-1"></i> توکن تازه
      </button>
    </div>

    <div class="row g-3 mb-3">
      @foreach([
        ['کل توکن‌ها', $total, 'ph-key', 'primary'],
        ['فعال', $activeCount, 'ph-check-circle', 'success'],
        ['خواندن و نوشتن', $writeCount, 'ph-pencil-simple', 'warning'],
        ['فقط خواندن', $readCount, 'ph-eye', 'info'],
      ] as [$label, $value, $icon, $color])
        <div class="col-6 col-lg-3">
          <div class="card mb-0">
            <div class="card-body d-flex align-items-center gap-3 py-3">
              <span class="api-icon api-icon-sm bg-{{ $color }} bg-opacity-10 text-{{ $color }}"><i class="{{ $icon }}"></i></span>
              <div>
                <div class="fs-4 fw-semibold lh-1">{{ $value }}</div>
                <div class="text-muted fs-sm mt-1">{{ $label }}</div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Endpoints --}}
    <div class="row g-3 mb-3">
      @foreach($endpoints as $endpoint)
        @php
          [$eIcon, $eColor] = $endpointIcons[$endpoint['title']] ?? ['ph-link', 'secondary'];
        @endphp
        <div class="col-md-4">
          <div class="card h-100 mb-0">
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="api-icon api-icon-sm bg-{{ $eColor }} bg-opacity-10 text-{{ $eColor }}"><i class="{{ $eIcon }}"></i></span>
                <div>
                  <div class="fw-semibold">{{ $endpoint['title'] }}</div>
                  <div class="text-muted fs-sm">{{ $endpoint['hint'] }}</div>
                </div>
              </div>
              <div class="d-flex gap-2 align-items-center">
                <code class="api-path flex-grow-1" dir="ltr">{{ $endpoint['path'] }}</code>
                <button type="button" class="btn btn-sm btn-light text-nowrap" data-copy="{{ $endpoint['url'] }}" title="کپی آدرس"><i class="ph-copy"></i></button>
                @if(!empty($endpoint['openable']))
                  <a class="btn btn-sm btn-light text-nowrap" href="{{ $endpoint['url'] }}" target="_blank" rel="noopener" title="باز کردن"><i class="ph-arrow-square-out"></i></a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Agent quick start --}}
    <div class="card mb-3 border-start border-warning border-3">
      <div class="card-body d-flex flex-wrap align-items-start gap-3">
        <span class="api-icon api-icon-sm bg-warning bg-opacity-10 text-warning"><i class="ph-robot"></i></span>
        <div class="flex-grow-1" style="min-width:260px">
          <div class="fw-semibold mb-1">اتصال یک ایجنت AI</div>
          <div class="text-muted fs-sm mb-2">ساده‌ترین راه: روی هر توکن دکمهٔ «کپی متن اتصال» را بزن و همان را به ایجنت بده؛ توکن، آدرس‌ها، مستندات و روش کار داخلش است. این کارت فقط نمونهٔ بدون توکن است. برای کار روزمره سطح «فقط خواندن» کافی است.</div>
          <pre class="api-prompt mb-0" dir="auto">{{ $agentPrompt }}</pre>
        </div>
        <button type="button" class="btn btn-sm btn-light text-nowrap" data-copy="{{ $agentPrompt }}"><i class="ph-copy me-1"></i> کپی متن</button>
      </div>
    </div>

    {{-- New token --}}
    <div class="collapse {{ $errors->any() ? 'show' : '' }} mb-3" id="new-access">
      <div class="card mb-0">
        <div class="card-header d-flex align-items-center">
          <h6 class="mb-0"><i class="ph-plus-circle me-1"></i> توکن تازه</h6>
        </div>
        <div class="card-body">
          <form method="post" action="{{ route('admin.shop.api-accesses.store') }}" class="row g-3 align-items-start">
            @csrf
            <div class="col-md-4">
              <label class="form-label" for="name">نام</label>
              <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="190" placeholder="مثلاً ایجنت محتوا">
              @error('name')<div class="form-text text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label" for="rate_limit">سقف در دقیقه</label>
              <input type="text" class="form-control" id="rate_limit" name="rate_limit" value="{{ old('rate_limit', '60') }}" inputmode="numeric" autocomplete="off" required>
              <div class="form-text">صفر یعنی بدون سقف.</div>
              @error('rate_limit')<div class="form-text text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
              <label class="form-label">سطح دسترسی</label>
              <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="permission" id="perm-read-new" value="read" autocomplete="off" @checked(old('permission', 'read') === 'read')>
                <label class="btn btn-outline-info" for="perm-read-new"><i class="ph-eye me-1"></i> فقط خواندن</label>
                <input type="radio" class="btn-check" name="permission" id="perm-rw-new" value="read_write" autocomplete="off" @checked(old('permission') === 'read_write')>
                <label class="btn btn-outline-warning" for="perm-rw-new"><i class="ph-pencil-simple me-1"></i> خواندن و نوشتن</label>
              </div>
              <div class="form-text">پیش‌فرض امن: فقط خواندن. نوشتن را فقط برای اپ مدیریت یا کار اعمال مستقیم بده.</div>
              @error('permission')<div class="form-text text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
              <label class="form-label">مدت اعتبار</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach($expiryPresets as $key => $preset)
                  <input type="radio" class="btn-check" name="expiry" id="exp-new-{{ $key }}" value="{{ $key }}" autocomplete="off" @checked(old('expiry', $expiryDefault) === $key)>
                  <label class="btn btn-outline-{{ $key === 'never' ? 'danger' : 'secondary' }}" for="exp-new-{{ $key }}">
                    @if($key === 'never')<i class="ph-infinity me-1"></i>@endif{{ $preset['label'] }}
                  </label>
                @endforeach
              </div>
              <div class="form-text">بعد از این مدت توکن خودش از کار می‌افتد و درخواست‌ها 401 می‌گیرند؛ اگر فراموش کنی حذفش کنی، امن می‌ماند. «بدون انقضا» فقط برای اپ‌های دائمی.</div>
              @error('expiry')<div class="form-text text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-9">
              <label class="form-label" for="allowed_ips_input">آی‌پی‌های مجاز</label>
              <div class="ip-tags" data-ip-tags>
                <input type="hidden" name="allowed_ips" value="{{ old('allowed_ips') }}">
                <input type="text" class="ip-tags-input" id="allowed_ips_input" dir="ltr" placeholder="127.0.0.1" autocomplete="off">
              </div>
              <div class="form-text">اختیاری. با اینتر یا تب تگ می‌شود. خالی یعنی همهٔ آی‌پی‌ها.</div>
              @error('allowed_ips')<div class="form-text text-danger">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100"><i class="ph-key me-1"></i> ساخت توکن</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Tokens --}}
    <div class="d-flex flex-column gap-3">
      @forelse($accesses as $access)
        @php
          $canWrite = $access->permission === 'read_write';
          $accent = $canWrite ? 'warning' : 'info';
        @endphp
        <div class="card mb-0 api-token border-start border-{{ $accent }} border-3 {{ $access->isUsable() ? '' : 'api-token-off' }}">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-3">
              <span class="api-icon api-icon-sm bg-{{ $accent }} bg-opacity-10 text-{{ $accent }}"><i class="{{ $canWrite ? 'ph-pencil-simple' : 'ph-eye' }}"></i></span>
              <div>
                <div class="fw-semibold">{{ $access->name }}</div>
                <div class="text-muted fs-sm">
                  <i class="ph-clock me-1"></i>{{ $access->last_used_at ? 'آخرین استفاده '.$access->last_used_at->format('Y-m-d H:i') : 'هنوز استفاده نشده' }}
                </div>
              </div>
            </div>
            <div class="api-actions">
              <span class="badge bg-{{ $accent }} bg-opacity-20 text-{{ $accent }}">{{ $canWrite ? 'خواندن و نوشتن' : 'فقط خواندن' }}</span>
              @php
                $expClass = $access->isExpired() ? 'danger' : ($access->expiresSoon() ? 'warning' : ($access->expires_at ? 'secondary' : 'info'));
              @endphp
              <span class="badge bg-{{ $expClass }} bg-opacity-20 text-{{ $expClass }}" title="{{ $access->expires_at ? 'تا '.$access->expires_at->format('Y-m-d H:i') : 'هیچ‌وقت منقضی نمی‌شود' }}">
                <i class="ph-hourglass me-1"></i>{{ $access->expiryLabel() }}
              </span>
              @if($access->expires_at)
                <div class="dropdown">
                  <button type="button" class="btn {{ $access->isExpired() || $access->expiresSoon() ? 'btn-warning' : 'btn-outline-primary' }}" data-bs-toggle="dropdown">
                    <i class="ph-plus-circle me-1"></i> تمدید
                  </button>
                  <form method="post" action="{{ route('admin.shop.api-accesses.extend', $access) }}" class="dropdown-menu dropdown-menu-end p-0">
                    @csrf
                    <div class="dropdown-header">اضافه شود به اعتبار فعلی</div>
                    @foreach($expiryPresets as $key => $preset)
                      @if($key !== 'never')
                        <button type="submit" name="add" value="{{ $key }}" class="dropdown-item"><i class="ph-plus me-2"></i>{{ $preset['label'] }}</button>
                      @endif
                    @endforeach
                  </form>
                </div>
              @endif
              @if($access->active)
                <span class="badge bg-success bg-opacity-20 text-success">فعال</span>
                <form method="post" action="{{ route('admin.shop.api-accesses.revoke', $access) }}">
                  @csrf
                  <button type="submit" class="btn btn-outline-warning"><i class="ph-prohibit me-1"></i> غیرفعال کن</button>
                </form>
              @else
                <span class="badge bg-secondary bg-opacity-20 text-secondary">غیرفعال</span>
                <form method="post" action="{{ route('admin.shop.api-accesses.activate', $access) }}">
                  @csrf
                  <button type="submit" class="btn btn-outline-success"><i class="ph-play me-1"></i> فعال کن</button>
                </form>
              @endif
              <form method="post" action="{{ route('admin.shop.api-accesses.destroy', $access) }}"
                    onsubmit="return confirm('توکن «{{ $access->name }}» برای همیشه حذف شود؟ این کار برگشت‌پذیر نیست.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-icon" title="حذف توکن"><i class="ph-trash"></i></button>
              </form>
            </div>
          </div>

          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fs-sm text-muted mb-1">توکن</label>
              @if($access->token)
                <div class="api-token-row">
                  <input type="password" class="form-control font-monospace" dir="ltr" readonly value="{{ $access->token }}" data-secret>
                  <button type="button" class="btn btn-light btn-icon" data-toggle-secret title="نمایش یا پنهان"><i class="ph-eye"></i></button>
                  <button type="button" class="btn btn-primary" data-copy="{{ $connectionText($access) }}" title="توکن + آدرس‌ها + مستندات + روش کار، یک‌جا برای ایجنت"><i class="ph-copy me-1"></i> کپی متن اتصال</button>
                  <button type="button" class="btn btn-light btn-icon" data-copy="{{ $access->token }}" title="فقط خود توکن"><i class="ph-key"></i></button>
                  <button type="submit" class="btn btn-light btn-icon" form="rotate-{{ $access->id }}" title="صدور توکن تازه" onclick="return confirm('توکن قبلی از کار می‌افتد. ادامه؟')"><i class="ph-arrows-clockwise"></i></button>
                </div>
                <form id="rotate-{{ $access->id }}" method="post" action="{{ route('admin.shop.api-accesses.rotate', $access) }}">@csrf</form>
              @else
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="text-muted fs-sm"><i class="ph-info me-1"></i>این توکن قبل از ذخیرهٔ متن ساخته شده و دیگر قابل نمایش نیست.</span>
                  <form method="post" action="{{ route('admin.shop.api-accesses.rotate', $access) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ph-arrows-clockwise me-1"></i> صدور توکن تازه</button>
                  </form>
                </div>
              @endif
            </div>

            <form method="post" action="{{ route('admin.shop.api-accesses.update', $access) }}" class="row g-3 align-items-end border-top pt-3">
              @csrf
              @method('PUT')
              <div class="col-md-2">
                <label class="form-label fs-sm text-muted">سقف در دقیقه</label>
                <input type="text" class="form-control" name="rate_limit" value="{{ $access->rate_limit }}" inputmode="numeric" autocomplete="off" required>
              </div>
              <div class="col-md-5">
                <label class="form-label fs-sm text-muted">آی‌پی‌های مجاز</label>
                <div class="ip-tags" data-ip-tags>
                  <input type="hidden" name="allowed_ips" value="{{ implode("\n", $access->allowed_ips ?? []) }}">
                  <input type="text" class="ip-tags-input" dir="ltr" placeholder="خالی = همهٔ آی‌پی‌ها" autocomplete="off">
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label fs-sm text-muted">سطح دسترسی</label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="permission" id="perm-read-{{ $access->id }}" value="read" autocomplete="off" @checked(!$canWrite)>
                  <label class="btn btn-outline-info" for="perm-read-{{ $access->id }}"><i class="ph-eye me-1"></i> خواندن</label>
                  <input type="radio" class="btn-check" name="permission" id="perm-rw-{{ $access->id }}" value="read_write" autocomplete="off" @checked($canWrite)>
                  <label class="btn btn-outline-warning" for="perm-rw-{{ $access->id }}"><i class="ph-pencil-simple me-1"></i> نوشتن</label>
                </div>
              </div>
              <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="ph-floppy-disk me-1"></i> ذخیره</button>
              </div>
              <div class="col-12">
                <label class="form-label fs-sm text-muted">تمدید یا تغییر اعتبار (از همین الان حساب می‌شود)</label>
                <div class="d-flex flex-wrap gap-2">
                  <input type="radio" class="btn-check" name="expiry" id="exp-{{ $access->id }}-keep" value="keep" autocomplete="off" checked>
                  <label class="btn btn-outline-primary" for="exp-{{ $access->id }}-keep">بدون تغییر ({{ $access->expiryLabel() }})</label>
                  @foreach($expiryPresets as $key => $preset)
                    <input type="radio" class="btn-check" name="expiry" id="exp-{{ $access->id }}-{{ $key }}" value="{{ $key }}" autocomplete="off">
                    <label class="btn btn-outline-{{ $key === 'never' ? 'danger' : 'secondary' }}" for="exp-{{ $access->id }}-{{ $key }}">{{ $preset['label'] }}</label>
                  @endforeach
                </div>
              </div>
            </form>
          </div>
        </div>
      @empty
        <div class="card mb-0">
          <div class="card-body text-center text-muted py-5">
            <i class="ph-key ph-3x d-block mb-2"></i>
            هنوز دسترسی‌ای ثبت نشده است. با «توکن تازه» اولین را بساز.
          </div>
        </div>
      @endforelse
    </div>
  @endif
</div>

<style>
  .api-access-page .api-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    flex-shrink: 0;
  }
  .api-access-page .api-icon-sm { width: 40px; height: 40px; border-radius: 10px; font-size: 1.25rem; }
  /* One control height everywhere so rows line up: inputs, buttons, segmented labels. */
  .api-access-page { --api-h: 2.5rem; }
  .api-access-page .form-control,
  .api-access-page .form-select,
  .api-access-page .btn:not(.btn-sm),
  .api-access-page .ip-tags { min-height: var(--api-h); }
  .api-access-page .btn-icon { width: var(--api-h); height: var(--api-h); padding: 0; display: inline-flex; align-items: center; justify-content: center; }
  .api-access-page .api-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
  .api-access-page .api-actions form { margin: 0; }
  .api-access-page .api-token-row { display: flex; align-items: stretch; gap: .5rem; }
  .api-access-page .api-token-row .form-control { flex: 1 1 auto; min-width: 0; }
  .api-access-page .btn-group .btn { display: inline-flex; align-items: center; justify-content: center; }
  .api-access-page .api-token { transition: box-shadow .15s ease; }
  .api-access-page .api-token:hover { box-shadow: 0 .25rem 1rem rgba(0, 0, 0, .12); }
  .api-access-page .api-token-off { opacity: .65; }
  .api-access-page .api-prompt {
    padding: .75rem 1rem;
    border-radius: var(--border-radius, .375rem);
    border: 1px dashed var(--border-color, var(--gray-400, #ced4da));
    background: color-mix(in srgb, var(--body-color, #212529) 5%, transparent);
    color: inherit;
    font-size: .8125rem;
    white-space: pre-wrap;
  }
  .api-access-page .form-control,
  .api-access-page .api-path,
  .api-access-page .ip-tags {
    background-color: var(--input-bg, var(--gray-100, #fff));
    color: var(--body-color, #212529);
    border-color: var(--border-color, var(--gray-400, #ced4da));
  }
  .api-access-page .form-control:focus {
    background-color: var(--input-bg, var(--gray-100, #fff));
    color: var(--body-color, #212529);
    border-color: var(--component-active-bg, #0c83ff);
    box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--component-active-bg, #0c83ff) 25%, transparent);
  }
  .api-access-page .form-control::placeholder,
  .api-access-page .ip-tags-input::placeholder {
    color: var(--gray-600, #6c757d);
    opacity: .85;
  }
  .api-path {
    display: block;
    padding: .45rem .6rem;
    border: 1px solid var(--border-color, var(--gray-400, #ced4da));
    border-radius: var(--border-radius, .375rem);
    font-size: .85rem;
    min-width: 0;
    overflow: auto;
  }
  .ip-tags {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem;
    min-height: 2.5rem;
    padding: .35rem .5rem;
    border: 1px solid var(--border-color, var(--gray-400, #ced4da));
    border-radius: var(--border-radius, .375rem);
    cursor: text;
  }
  .ip-tags:focus-within {
    border-color: var(--component-active-bg, #0c83ff);
    box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--component-active-bg, #0c83ff) 25%, transparent);
  }
  .ip-tags.is-invalid { border-color: var(--danger, #dc3545); }
  .ip-tag {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    padding: .1rem .45rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--component-active-bg, #0c83ff) 18%, transparent);
    color: var(--component-active-bg, #0c83ff);
    font-size: .8125rem;
    direction: ltr;
    line-height: 1.4;
  }
  .ip-tag button {
    border: 0;
    background: transparent;
    color: inherit;
    padding: 0;
    line-height: 1;
    cursor: pointer;
    font-size: 1rem;
  }
  .ip-tags-input {
    flex: 1 1 7rem;
    min-width: 7rem;
    border: 0;
    outline: none;
    background: transparent;
    color: inherit;
    direction: ltr;
    text-align: left;
    padding: .1rem 0;
  }
</style>
@endsection

@push('scripts')
<script>
(function () {
  var v4 = /^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/;

  function isIpv6(value) {
    if (!value || value.indexOf(':') === -1 || value.indexOf(':::') !== -1) return false;
    var parts = value.split('::');
    if (parts.length > 2) return false;
    var left = parts[0] ? parts[0].split(':') : [];
    var right = parts.length === 2 && parts[1] ? parts[1].split(':') : [];
    if (parts.length === 1 && left.length !== 8) return false;
    if (parts.length === 2 && (left.length + right.length) > 7) return false;
    return left.concat(right).every(function (group) {
      return /^[0-9a-fA-F]{1,4}$/.test(group) || v4.test(group);
    });
  }

  function isIp(value) {
    return v4.test(value) || isIpv6(value);
  }

  function mount(box) {
    var hidden = box.querySelector('input[type="hidden"]');
    var input = box.querySelector('.ip-tags-input');
    var tags = (hidden.value || '').split(/[\s,]+/).filter(Boolean);

    function paint() {
      box.querySelectorAll('.ip-tag').forEach(function (node) { node.remove(); });
      tags.forEach(function (ip, index) {
        var chip = document.createElement('span');
        chip.className = 'ip-tag';
        chip.appendChild(document.createTextNode(ip));
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.setAttribute('aria-label', 'حذف');
        remove.textContent = '×';
        remove.addEventListener('click', function () {
          tags.splice(index, 1);
          paint();
          input.focus();
        });
        chip.appendChild(remove);
        box.insertBefore(chip, input);
      });
      hidden.value = tags.join('\n');
    }

    function commit(raw) {
      var invalid = false;
      raw.split(/[\s,]+/).forEach(function (part) {
        var ip = part.trim();
        if (!ip) return;
        if (!isIp(ip)) {
          invalid = true;
          return;
        }
        if (tags.indexOf(ip) === -1) tags.push(ip);
      });
      box.classList.toggle('is-invalid', invalid);
      if (!invalid) input.value = '';
      paint();
      return !invalid;
    }

    box.addEventListener('click', function () { input.focus(); });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit(input.value);
        return;
      }
      if (event.key === 'Tab' && input.value.trim() !== '') {
        if (!commit(input.value)) event.preventDefault();
        return;
      }
      if (event.key === 'Backspace' && input.value === '' && tags.length) {
        tags.pop();
        paint();
      }
    });
    input.addEventListener('blur', function () {
      if (input.value.trim() !== '') commit(input.value);
    });

    var form = box.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (input.value.trim() !== '') commit(input.value);
      });
    }

    paint();
  }

  document.querySelectorAll('[data-ip-tags]').forEach(mount);

  function copyText(value) {
    function fallback() {
      var field = document.createElement('textarea');
      field.value = value;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.top = '0';
      field.style.left = '0';
      document.body.appendChild(field);
      field.focus();
      field.select();
      var ok = document.execCommand('copy');
      field.remove();
      return ok ? Promise.resolve() : Promise.reject();
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(value).catch(fallback);
    }
    return fallback();
  }

  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var value = button.getAttribute('data-copy') || '';
      copyText(value).then(function () {
        var previous = button.innerHTML;
        button.innerHTML = '<i class="ph-check text-success"></i>';
        setTimeout(function () { button.innerHTML = previous; }, 1200);
      });
    });
  });

  document.querySelectorAll('[data-toggle-secret]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = button.parentNode.querySelector('[data-secret]');
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.innerHTML = show ? '<i class="ph-eye-slash"></i>' : '<i class="ph-eye"></i>';
    });
  });
})();
</script>
@endpush
