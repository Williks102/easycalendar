<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->services()->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $service = $request->user()->services()->create($this->validated($request));

        return response()->json($service->fresh(), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->find($request, $id));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $service = $this->find($request, $id);

        $service->fill($this->validated($request, $service));
        $this->assertDepositWithinPrice($service);
        $service->save();

        return response()->json($service->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $service = $this->find($request, $id);

        // Les rendez-vous passés référencent le service : on le désactive plutôt que de le supprimer.
        if ($service->bookings()->exists()) {
            return response()->json(['message' => 'Ce service a des rendez-vous : désactivez-le (is_active) au lieu de le supprimer.'], 409);
        }

        $service->delete();

        return response()->json(null, 204);
    }

    private function find(Request $request, int $id): Service
    {
        return $request->user()->services()->findOrFail($id);
    }

    private function validated(Request $request, ?Service $current = null): array
    {
        $required = $current ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'duration_min' => [$required, 'integer', 'min:5', 'max:720'],
            'price_xof' => [$required, 'integer', 'min:0', 'max:100000000'],
            'deposit_xof' => [$required, 'integer', 'min:0'],
            'buffer_min' => ['sometimes', 'integer', 'min:0', 'max:240'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! $current) {
            $this->assertDepositWithinPrice(new Service($data));
        }

        return $data;
    }

    private function assertDepositWithinPrice(Service $service): void
    {
        if ($service->deposit_xof > $service->price_xof) {
            throw ValidationException::withMessages(['deposit_xof' => "L'acompte ne peut pas dépasser le prix."]);
        }
    }
}
