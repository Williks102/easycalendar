<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Services\Otp\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const PHONE_RULE = ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'];

    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['phone' => self::PHONE_RULE]);

        $otp->request($data['phone']);

        return response()->json(['message' => 'Code envoyé.'], 202);
    }

    /**
     * Connexion ou inscription : si le numéro n'a pas encore de compte, `name` et `slug`
     * sont obligatoires et le compte est créé. Renvoie un jeton Sanctum.
     */
    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => self::PHONE_RULE,
            'code' => ['required', 'digits:6'],
        ]);

        $professional = Professional::where('phone', $data['phone'])->first();

        if (! $professional) {
            $data += $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'unique:professionals,slug'],
                'business_name' => ['nullable', 'string', 'max:160'],
                'password' => ['nullable', 'string', 'min:8', 'max:100'],
            ]);
        }

        if (! $otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Code invalide ou expiré.']);
        }

        $created = false;
        if (! $professional) {
            $professional = new Professional(collect($data)->only(['phone', 'name', 'slug', 'business_name'])->all());
            $professional->password_hash = $data['password'] ?? null;
            $professional->save();
            $created = true;
        }

        if ($professional->status !== 'active') {
            return response()->json(['message' => 'Compte suspendu.'], 403);
        }

        return response()->json([
            'token' => $professional->createToken('api')->plainTextToken,
            'professional' => $professional->fresh(),
        ], $created ? 201 : 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
