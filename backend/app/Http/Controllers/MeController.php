<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function update(Request $request): JsonResponse
    {
        $professional = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('professionals', 'slug')->ignore($professional->id)],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'payout_operator' => ['sometimes', 'nullable', Rule::in(['wave', 'orange', 'mtn', 'moov'])],
            'payout_phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ]);

        $professional->update($data);

        return response()->json($professional->fresh());
    }
}
