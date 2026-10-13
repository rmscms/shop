<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Core\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    /**
     * @OA\Post(
     *     path="/admin/shop/auth/login",
     *     tags={"Admin - Authentication"},
     *     summary="Admin login and get token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password"),
     *             @OA\Property(property="device_name", type="string", example="Admin API Client", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string", example="1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"),
     *                 @OA\Property(property="admin", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Admin User"),
     *                     @OA\Property(property="email", type="string", example="admin@example.com"),
     *                     @OA\Property(property="role", type="string", example="super_admin")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:190'],
        ]);

        $admin = Admin::where('email', $request->email)
            ->whereNull('deleted_at')
            ->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['اطلاعات ورود نامعتبر است.'],
            ]);
        }

        if (!$admin->active) {
            throw ValidationException::withMessages([
                'email' => ['حساب کاربری شما غیرفعال است.'],
            ]);
        }

        // Delete old tokens if needed (optional: limit to last 5 tokens)
        // $admin->tokens()->limit(5)->get()->each->delete();

        // Create new token with admin guard
        $deviceName = $request->input('device_name', 'Admin API Client');
        $token = $admin->createToken($deviceName, ['admin'])->plainTextToken;

        // Update last login
        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        return $this->apiSuccess([
            'token' => $token,
            'admin' => [
                'id' => (int) $admin->id,
                'name' => (string) $admin->name,
                'email' => (string) $admin->email,
                'role' => (string) ($admin->role ?? 'admin'),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/auth/logout",
     *     tags={"Admin - Authentication"},
     *     summary="Admin logout (revoke current token)",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message", type="string", example="با موفقیت خارج شدید")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user('admin')->currentAccessToken()->delete();

        return $this->apiSuccess([
            'message' => 'با موفقیت خارج شدید',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/auth/logout-all",
     *     tags={"Admin - Authentication"},
     *     summary="Admin logout from all devices (revoke all tokens)",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout from all devices successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message", type="string", example="از تمام دستگاه‌ها خارج شدید")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens
        $admin = $request->user('admin');
        if ($admin) {
            $admin->tokens()->delete();
        }

        return $this->apiSuccess([
            'message' => 'از تمام دستگاه‌ها خارج شدید',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/auth/me",
     *     tags={"Admin - Authentication"},
     *     summary="Get current authenticated admin",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current admin info",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Admin User"),
     *                 @OA\Property(property="email", type="string", example="admin@example.com"),
     *                 @OA\Property(property="role", type="string", example="super_admin"),
     *                 @OA\Property(property="active", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request)
    {
        $admin = $request->user('admin');

        return $this->apiSuccess([
            'id' => (int) $admin->id,
            'name' => (string) $admin->name,
            'email' => (string) $admin->email,
            'mobile' => $admin->mobile ? (string) $admin->mobile : null,
            'role' => (string) ($admin->role ?? 'admin'),
            'active' => (bool) $admin->active,
            'last_login_at' => optional($admin->last_login_at)->toIso8601String(),
            'avatar' => $admin->avatar ? asset($admin->avatar) : null,
        ]);
    }
}
