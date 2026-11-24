<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityController extends Controller
{
    private const TIME = 'date_format:H:i';

    public function show(Request $request): JsonResponse
    {
        $pro = $request->user();

        return response()->json([
            'rules' => $pro->availabilityRules()->orderBy('weekday')->orderBy('start_time')->get(),
            'exceptions' => $pro->availabilityExceptions()->orderBy('date')->get(),
        ]);
    }

    /** Remplace tous les horaires hebdomadaires (weekday : 0 = lundi ... 6 = dimanche). */
    public function replaceRules(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rules' => ['present', 'array', 'max:50'],
            'rules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'rules.*.start_time' => ['required', self::TIME],
            'rules.*.end_time' => ['required', self::TIME],
        ]);

        $this->assertValidRanges($data['rules']);

        $pro = $request->user();
        DB::transaction(function () use ($pro, $data) {
            $pro->availabilityRules()->delete();
            $pro->availabilityRules()->createMany($data['rules']);
        });

        return $this->show($request);
    }

    public function storeException(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', 'in:closed,custom'],
            'start_time' => ['required_if:type,custom', 'nullable', self::TIME],
            'end_time' => ['required_if:type,custom', 'nullable', self::TIME, 'after:start_time'],
        ]);

        if ($data['type'] === AvailabilityException::TYPE_CLOSED) {
            $data['start_time'] = $data['end_time'] = null;
        }

        // Une seule exception par jour : la nouvelle remplace l'ancienne.
        $exception = $request->user()->availabilityExceptions()->updateOrCreate(
            ['date' => $data['date']],
            $data,
        );

        return response()->json($exception->fresh(), 201);
    }

    public function destroyException(Request $request, int $id): JsonResponse
    {
        $request->user()->availabilityExceptions()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /** Refuse les plages incohérentes ou qui se chevauchent le même jour. */
    private function assertValidRanges(array $rules): void
    {
        foreach ($rules as $i => $rule) {
            if ($rule['end_time'] <= $rule['start_time']) {
                throw ValidationException::withMessages(["rules.$i.end_time" => "L'heure de fin doit suivre l'heure de début."]);
            }
        }

        $byDay = collect($rules)->groupBy('weekday');
        foreach ($byDay as $day => $ranges) {
            $sorted = $ranges->sortBy('start_time')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['start_time'] < $sorted[$i - 1]['end_time']) {
                    throw ValidationException::withMessages(['rules' => "Les plages du jour $day se chevauchent."]);
                }
            }
        }
    }
}
