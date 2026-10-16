@extends('cms::admin.layout.index')

@section('content')
<div class="content api-access-page">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if(!$ready)
    <div class="alert alert-warning mb-0">جدول دسترسی هنوز ساخته نشده است. مهاجرت shop را اجرا کنید.</div>
  @else
    <div class="row g-3 mb-3">
      @foreach($endpoints as $endpoint)
        <div class="col-md-4">
          <div class="card h-100 mb-0">
            <div class="card-body">
              <div class="text-muted small mb-1">{{ $endpoint['hint'] }}</div>
              <div class="fw-semibold mb-2">{{ $endpoint['title'] }}</div>
              <div class="d-flex gap-2 align-items-center">
                <code class="api-path flex-grow-1" dir="ltr">{{ $endpoint['path'] }}</code>
                <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" data-copy="{{ $endpoint['url'] }}">کپی</button>
                @if(!empty($endpoint['openable']))
                  <a class="btn btn-sm btn-outline-primary text-nowrap" href="{{ $endpoint['url'] }}" target="_blank" rel="noopener">باز کردن</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h6 class="mb-0">دسترسی تازه</h6>
      </div>
      <div class="card-body">
        <form method="post" action="{{ route('admin.shop.api-accesses.store') }}" class="row g-3 align-items-start">
          @csrf
          <div class="col-md-3">
            <label class="form-label" for="name">نام</label>
            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required maxlength="190">
            @error('name')<div class="form-text text-danger">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-2">
            <label class="form-label" for="rate_limit">سقف در دقیقه</label>
            <input type="text" class="form-control" id="rate_limit" name="rate_limit" value="{{ old('rate_limit', '0') }}" inputmode="numeric" autocomplete="off" required>
            <div class="form-text">صفر یعنی بدون سقف.</div>
            @error('rate_limit')<div class="form-text text-danger">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-5">
            <label class="form-label" for="allowed_ips_input">آی‌پی‌های مجاز</label>
            <div class="ip-tags" data-ip-tags>
              <input type="hidden" name="allowed_ips" value="{{ old('allowed_ips') }}">
              <input type="text" class="ip-tags-input" id="allowed_ips_input" dir="ltr" placeholder="127.0.0.1" autocomplete="off">
            </div>
            <div class="form-text">اختیاری. با اینتر یا تب تگ می‌شود. خالی یعنی همهٔ آی‌پی‌ها.</div>
            @error('allowed_ips')<div class="form-text text-danger">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-2">
            <label class="form-label d-none d-md-block">&nbsp;</label>
            <button type="submit" class="btn btn-primary w-100">ساخت توکن</button>
          </div>
        </form>
      </div>
    </div>

    <div class="d-flex flex-column gap-3">
      @forelse($accesses as $access)
        <div class="card mb-0">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
              <div class="fw-semibold">{{ $access->name }}</div>
              <div class="small text-muted">{{ $access->last_used_at ? 'آخرین استفاده '.$access->last_used_at->format('Y-m-d H:i') : 'هنوز استفاده نشده' }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
              @if($access->active)
                <span class="badge bg-success">فعال</span>
              @else
                <span class="badge bg-secondary">غیرفعال</span>
              @endif
              @if($access->active)
                <form method="post" action="{{ route('admin.shop.api-accesses.revoke', $access) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger">غیرفعال</button>
                </form>
              @else
                <form method="post" action="{{ route('admin.shop.api-accesses.activate', $access) }}">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-success">فعال</button>
                </form>
              @endif
            </div>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">توکن</label>
              @if($access->token)
                <div class="input-group">
                  <input type="text" class="form-control font-monospace" dir="ltr" readonly value="{{ $access->token }}">
                  <button type="button" class="btn btn-outline-primary" data-copy="{{ $access->token }}">کپی</button>
                </div>
              @else
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="text-muted small">این توکن قبل از ذخیرهٔ متن ساخته شده و دیگر قابل نمایش نیست.</span>
                  <form method="post" action="{{ route('admin.shop.api-accesses.rotate', $access) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">صدور توکن تازه</button>
                  </form>
                </div>
              @endif
            </div>

            <form method="post" action="{{ route('admin.shop.api-accesses.update', $access) }}" class="row g-3 align-items-start">
              @csrf
              @method('PUT')
              <div class="col-md-3">
                <label class="form-label">سقف در دقیقه</label>
                <input type="text" class="form-control" name="rate_limit" value="{{ $access->rate_limit }}" inputmode="numeric" autocomplete="off" required>
              </div>
              <div class="col-md-7">
                <label class="form-label">آی‌پی‌های مجاز</label>
                <div class="ip-tags" data-ip-tags>
                  <input type="hidden" name="allowed_ips" value="{{ implode("\n", $access->allowed_ips ?? []) }}">
                  <input type="text" class="ip-tags-input" dir="ltr" placeholder="127.0.0.1" autocomplete="off">
                </div>
              </div>
              <div class="col-md-2">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button type="submit" class="btn btn-outline-primary w-100">ذخیره</button>
              </div>
            </form>
          </div>
        </div>
      @empty
        <div class="card mb-0">
          <div class="card-body text-muted">هنوز دسترسی‌ای ثبت نشده است.</div>
        </div>
      @endforelse
    </div>
  @endif
</div>

<style>
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
        var previous = button.textContent;
        button.textContent = 'کپی شد';
        setTimeout(function () { button.textContent = previous; }, 1200);
      });
    });
  });
})();
</script>
@endpush
