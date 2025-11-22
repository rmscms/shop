<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;
use RMS\Shop\Contracts\PanelApi\AuthDriver;
use RMS\Shop\Http\Requests\Api\Panel\RegisterRequest;
use RMS\Shop\Support\PanelApi\CartManager;
use RMS\Shop\Support\PanelApi\CartStorage;
use RuntimeException;

class AuthController extends BaseController
{
    public function __construct(
        protected CartStorage $storage,
        protected CartManager $cartManager
    ) {
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Login and issue Sanctum token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="device_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful (fields depend on configured auth driver)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string", format="email")
     *                 ),
     *                 @OA\Property(property="cart", ref="#/components/schemas/CartResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);
        $authResult = $this->resolveAuthDriver()->handle($request);
        $user = $authResult->user();

        $deviceName = $authResult->context('device_name')
            ?? $request->input('device_name')
            ?? config('shop.panel_api.auth.device_name', 'shop-panel');

        return $this->respondWithToken($request, $user, $cartKey, $cookie, $deviceName);
    }

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Auth"},
     *     summary="Register a new user and issue token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="کاربر جدید"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="device_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string")),
     *                 @OA\Property(property="cart", ref="#/components/schemas/CartResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);
        $userClass = $this->resolveUserModel();

        /** @var \Illuminate\Database\Eloquent\Model $user */
        $user = new $userClass();
        $user->forceFill([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);
        $user->save();

        return $this->respondWithToken($request, $user->refresh(), $cartKey, $cookie);
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     tags={"Auth"},
     *     summary="Get current user",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Current user information",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email")
     *             )
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->apiSuccess([
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Auth"},
     *     summary="Revoke current token",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="message", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $this->apiSuccess(['message' => 'خروج انجام شد']);
    }

    protected function resolveAuthDriver(): AuthDriver
    {
        static $driverInstance = null;

        if ($driverInstance instanceof AuthDriver) {
            return $driverInstance;
        }

        $config = config('shop.panel_api.auth', []);
        $driverKey = $config['driver'] ?? 'email';
        $drivers = $config['drivers'] ?? [];
        $driverClass = $drivers[$driverKey] ?? $driverKey;

        $driver = app($driverClass);

        if (!$driver instanceof AuthDriver) {
            throw new RuntimeException("Panel API auth driver [{$driverClass}] must implement ".AuthDriver::class);
        }

        return $driverInstance = $driver;
    }

    protected function resolveUserModel(): string
    {
        $userClass = config('shop.panel_api.auth.user_model') ?? config('auth.providers.users.model');
        if (!class_exists($userClass)) {
            throw new RuntimeException("Panel API register user model [{$userClass}] not found.");
        }

        return $userClass;
    }

    protected function respondWithToken(Request $request, $user, string $cartKey, $cookie = null, ?string $deviceName = null): JsonResponse
    {
        $this->cartManager->syncToUserCart($cartKey, (int) $user->getAuthIdentifier());

        $resolvedDevice = $deviceName
            ?? $request->input('device_name')
            ?? config('shop.panel_api.auth.device_name', 'shop-panel');

        $token = $user->createToken($resolvedDevice)->plainTextToken;

        $payload = [
            'token' => $token,
            'user' => [
                'id' => (int) $user->getAuthIdentifier(),
                'name' => $user->name,
                'email' => $user->email,
            ],
            'cart' => $this->cartManager->buildCartPayload($cartKey),
        ];

        $response = $this->apiSuccess($payload);
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}

