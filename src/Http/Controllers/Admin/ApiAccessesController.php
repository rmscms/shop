<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RMS\Shop\Models\ShopApiAccess;

class ApiAccessesController extends ShopAdminController
{
    public function table(): string
    {
        return 'shop_api_accesses';
    }

    public function modelName(): string
    {
        return ShopApiAccess::class;
    }

    public function index(Request $request)
    {
        $this->title('دسترسی API');

        $ready = Schema::hasTable('shop_api_accesses');
        $accesses = $ready
            ? ShopApiAccess::query()->orderByDesc('id')->get()
            : collect();

        $adminPrefix = trim((string) config('shop.admin_api.prefix', 'api/v1/admin/shop'), '/');
        $panelPrefix = trim((string) config('shop.panel_api.prefix', 'api/v1/panel'), '/');
        $docsPath = trim((string) config('l5-swagger.documentations.default.routes.api', config('shop.swagger.route', 'api/documentation')), '/');

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('api-accesses')
            ->withVariables([
                'ready' => $ready,
                'accesses' => $accesses,
                'endpoints' => [
                    [
                        'title' => 'داک API',
                        'hint' => 'مستندات ادمین و پنل',
                        'path' => '/'.$docsPath,
                        'url' => url($docsPath),
                        'openable' => true,
                    ],
                    [
                        'title' => 'API ادمین',
                        'hint' => 'پایهٔ توکن همین صفحه',
                        'path' => '/'.$adminPrefix,
                        'url' => url($adminPrefix),
                        'openable' => true,
                    ],
                    [
                        'title' => 'API پنل',
                        'hint' => 'پایهٔ API مشتری',
                        'path' => '/'.$panelPrefix,
                        'url' => url($panelPrefix),
                        'openable' => true,
                    ],
                ],
            ]);

        return $this->view();
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'rate_limit' => ['required', 'integer', 'min:0'],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
        ]);

        $ips = $this->parseIps((string) ($data['allowed_ips'] ?? ''));

        if (ShopApiAccess::query()->where('name', $data['name'])->exists()) {
            return back()->withInput()->withErrors(['name' => 'این نام قبلاً ثبت شده است.']);
        }

        $plain = Str::random(48);
        ShopApiAccess::query()->create([
            'name' => $data['name'],
            'token_hash' => ShopApiAccess::hashToken($plain),
            'token' => $plain,
            'rate_limit' => (int) $data['rate_limit'],
            'allowed_ips' => $ips,
            'active' => true,
        ]);

        return redirect()
            ->route('admin.shop.api-accesses.index')
            ->with('success', 'دسترسی ساخته شد. توکن در لیست است و می‌توانید کپی کنید.');
    }

    public function update(Request $request, string|int $access): RedirectResponse
    {
        $row = ShopApiAccess::query()->findOrFail($access);
        $data = $request->validate([
            'rate_limit' => ['required', 'integer', 'min:0'],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
        ]);

        $ips = $this->parseIps((string) ($data['allowed_ips'] ?? ''));

        $row->forceFill([
            'rate_limit' => (int) $data['rate_limit'],
            'allowed_ips' => $ips,
        ])->save();

        return redirect()
            ->route('admin.shop.api-accesses.index')
            ->with('success', 'دسترسی به‌روز شد.');
    }

    public function revoke(ShopApiAccess $access): RedirectResponse
    {
        $access->forceFill(['active' => false])->save();

        return redirect()
            ->route('admin.shop.api-accesses.index')
            ->with('success', 'دسترسی غیرفعال شد.');
    }

    public function rotate(ShopApiAccess $access): RedirectResponse
    {
        $plain = Str::random(48);
        $access->forceFill([
            'token' => $plain,
            'token_hash' => ShopApiAccess::hashToken($plain),
        ])->save();

        return redirect()
            ->route('admin.shop.api-accesses.index')
            ->with('success', 'توکن تازه صادر شد و از همین لیست کپی می‌شود.');
    }

    public function activate(ShopApiAccess $access): RedirectResponse
    {
        $access->forceFill(['active' => true])->save();

        return redirect()
            ->route('admin.shop.api-accesses.index')
            ->with('success', 'دسترسی دوباره فعال شد.');
    }

    /**
     * @return array<int, string>
     */
    protected function parseIps(string $raw): array
    {
        $parts = preg_split('/[\s,]+/', $raw) ?: [];
        $ips = [];
        foreach ($parts as $part) {
            $ip = trim($part);
            if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $ips[] = $ip;
        }

        return array_values(array_unique($ips));
    }
}
