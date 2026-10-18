<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" data-color-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? trans('auth.login') }} - {{ config('app.name') }}</title>
    @includeIf('admin.layout.favicon')

    <link rel="preload" href="{{ asset(config('cms.admin_theme') . '/css/bootstrap.min.css') }}" as="style">
    <link rel="stylesheet" href="{{ asset(config('cms.admin_theme') . '/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset(config('cms.admin_theme') . '/css/fonts/font-face.css') }}">
    <link rel="stylesheet" href="{{ asset(config('cms.admin_theme') . '/css/icons/phosphor/styles.min.css') }}">
    <link rel="stylesheet" href="{{ asset(config('cms.admin_theme') . '/css/login.css') }}">

    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">

    <script>
        (function() {
            document.documentElement.setAttribute('data-color-theme', 'dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
</head>
<body>
@php
    $theme = trim((string) config('cms.admin_theme', 'admin'), '/');
    $loginField = config('cms.admin_login_field', 'email');

    $resolvePublic = function (?string $relative) use ($theme): ?string {
        if ($relative === null || $relative === '') {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $candidates = [$relative];
        if (! str_starts_with($relative, $theme.'/')) {
            $candidates[] = $theme.'/'.$relative;
        }

        foreach ($candidates as $candidate) {
            if (is_file(public_path($candidate))) {
                return asset($candidate);
            }
        }

        return null;
    };

    $firstExisting = function (array $relatives) use ($resolvePublic): ?string {
        foreach ($relatives as $relative) {
            if ($url = $resolvePublic($relative)) {
                return $url;
            }
        }

        return null;
    };

    $logo = $resolvePublic(config('shop.admin_login.logo'))
        ?: $firstExisting([
            'images/logo_text_light.svg',
            'images/logo_text.svg',
            'images/logo.svg',
            'images/logo_icon.svg',
        ]);
    $mark = $resolvePublic(config('shop.admin_login.mark'))
        ?: $firstExisting([
            'images/logo_icon.svg',
        ]);
    $cover = $resolvePublic(config('shop.admin_login.cover'))
        ?: $firstExisting([
            'images/login_cover.jpg',
        ]);

    $tagline = \Illuminate\Support\Facades\Lang::has('auth.tagline')
        ? trans('auth.tagline')
        : trans('shop::shop.admin_login.tagline');
    $slogan = \Illuminate\Support\Facades\Lang::has('auth.slogan')
        ? trans('auth.slogan')
        : trans('shop::shop.admin_login.slogan');
    $loginPlaceholder = \Illuminate\Support\Facades\Lang::has('auth.email_or_username_placeholder')
        ? trans('auth.email_or_username_placeholder')
        : trans('shop::shop.admin_login.email_or_username_placeholder');
@endphp

<div class="login-page">
    <div class="login-cover" @if($cover) style="background-image: url('{{ $cover }}');" @endif></div>
    <div class="login-scrim"></div>
    <div class="login-pattern" aria-hidden="true">
        <i class="ph ph-gear"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear-six"></i>
        <i class="ph ph-cpu"></i>
        <i class="ph ph-gear"></i>
        <i class="ph ph-wifi-high"></i>
        <i class="ph ph-wrench"></i>
        <i class="ph ph-device-mobile"></i>
    </div>

    <div class="login-shell">
        <header class="login-brand">
            <div class="login-logo">
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" width="250" height="80">
                @else
                    <p class="login-logo-text">{{ config('app.name') }}</p>
                @endif
            </div>
            <p class="login-tagline">
                <span>{{ $tagline }}</span>
            </p>
        </header>

        <div class="login-card">
            <div class="login-card-avatar">
                @if($mark)
                    <img src="{{ $mark }}" width="56" height="56" alt="{{ config('app.name') }}">
                @else
                    <i class="ph ph-user" aria-hidden="true"></i>
                @endif
            </div>

            @if(session('message'))
                <div class="login-alert login-alert-success" role="alert">
                    <div class="alert-item">{{ session('message') }}</div>
                </div>
            @endif

            @if (isset($errors) && $errors->any())
                <div class="login-alert login-alert-danger" role="alert">
                    @foreach($errors->all() as $error)
                        <div class="alert-item">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" id="loginForm" class="login-form" novalidate>
                @csrf

                @if(request('redirect'))
                    <input type="hidden" name="redirect" value="{{ request('redirect') }}">
                @endif

                <div class="login-field">
                    <label for="login_field" class="visually-hidden">
                        {{ trans('auth.' . $loginField) }}
                    </label>
                    <div class="login-field-control">
                        <i class="ph ph-envelope-simple login-field-icon" aria-hidden="true"></i>
                        <input type="{{ $loginField === 'email' ? 'email' : 'text' }}"
                               name="{{ $loginField }}"
                               class="login-input @error($loginField) is-invalid @enderror"
                               id="login_field"
                               value="{{ old($loginField) }}"
                               placeholder="{{ $loginPlaceholder }}"
                               autocomplete="{{ $loginField === 'email' ? 'email' : 'username' }}"
                               aria-describedby="login_field_error"
                               required
                               autofocus>
                    </div>
                    @error($loginField)
                        <span class="error-message" id="login_field_error" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <div class="login-field login-field--password">
                    <label for="password" class="visually-hidden">{{ trans('auth.password') }}</label>
                    <div class="login-field-control">
                        <i class="ph ph-lock-simple login-field-icon" aria-hidden="true"></i>
                        <input type="password"
                               name="password"
                               class="login-input @error('password') is-invalid @enderror"
                               id="password"
                               placeholder="{{ trans('auth.password') }}"
                               autocomplete="current-password"
                               aria-describedby="password_error"
                               required>
                        <button type="button"
                                class="login-field-toggle"
                                id="togglePassword"
                                aria-label="{{ trans('auth.toggle_password_visibility') }}"
                                aria-pressed="false">
                            <i class="ph ph-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <span class="error-message" id="password_error" role="alert">{{ $message }}</span>
                    @enderror
                </div>

                <label class="login-remember" for="remember">
                    <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <span class="login-remember-box" aria-hidden="true">
                        <i class="ph ph-check"></i>
                    </span>
                    <span class="login-remember-text">{{ trans('auth.remember_me') }}</span>
                </label>

                <button type="submit" class="login-btn" id="submitBtn">
                    <span class="loading-spinner" id="loadingSpinner" hidden></span>
                    <span id="submitText">{{ trans('auth.login') }}</span>
                    <i class="ph ph-arrow-right login-btn-arrow" aria-hidden="true"></i>
                </button>

                @if(config('cms.admin_password_reset_enabled', false))
                    <div class="login-or">{{ trans('auth.or') }}</div>
                    <div class="login-links">
                        <a href="{{ route('admin.password.request') }}" class="login-link">
                            <i class="ph ph-lock-key" aria-hidden="true"></i>
                            <span>{{ trans('auth.forgot_password') }}</span>
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <p class="login-slogan">
            <i class="ph ph-gear-six" aria-hidden="true"></i>
            <span>{{ $slogan }}</span>
            <i class="ph ph-gear-six" aria-hidden="true"></i>
        </p>
    </div>
</div>

<script src="{{ asset(config('cms.admin_theme') . '/js/bootstrap.bundle.min.js') }}"></script>
<script>
    document.getElementById('loginForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const submitText = document.getElementById('submitText');

        submitBtn.disabled = true;
        loadingSpinner.hidden = false;
        submitText.textContent = '{{ trans('auth.logging_in') }}';

        setTimeout(() => {
            submitBtn.disabled = false;
            loadingSpinner.hidden = true;
            submitText.textContent = '{{ trans('auth.login') }}';
        }, 10000);
    });

    document.addEventListener('DOMContentLoaded', function() {
        const loginField = document.getElementById('login_field');
        const passwordField = document.getElementById('password');

        if (!loginField.value) {
            loginField.focus();
        } else {
            passwordField.focus();
        }

        const toggle = document.getElementById('togglePassword');
        const icon = document.getElementById('togglePasswordIcon');
        if (toggle && passwordField && icon) {
            toggle.addEventListener('click', function() {
                const show = passwordField.type === 'password';
                passwordField.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                icon.classList.toggle('ph-eye', !show);
                icon.classList.toggle('ph-eye-slash', show);
            });
        }
    });
</script>
</body>
</html>
