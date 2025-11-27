<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalculatorConfig;
use Illuminate\Http\Request;

class CalculatorConfigsController extends Controller
{
    public function index(Request $request)
    {
        $q = CalculatorConfig::query();

        if ($scope = $request->string('scope')->toString()) {
            $q->where('scope', $scope);
        }
        if (!is_null($request->input('scope_id'))) {
            $q->where('scope_id', $request->input('scope_id'));
        }
        if ($key = $request->string('key')->toString()) {
            $q->where('key', $key);
        }
        if (!is_null($request->boolean('is_active'))) {
            $q->where('is_active', $request->boolean('is_active'));
        }
        if ($effectiveOn = $request->date('effective_on')) {
            $q->where(function ($w) use ($effectiveOn) {
                $w->whereNull('effective_from')->orWhere('effective_from', '<=', $effectiveOn);
            })->where(function ($w) use ($effectiveOn) {
                $w->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveOn);
            });
        }

        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(CalculatorConfig $calculatorConfig)
    {
        return $calculatorConfig;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:global,company,offer',
            'scope_id' => 'nullable|integer',
            'key' => 'required|string|max:255',
            'version' => 'nullable|string|max:64',
            'value_json' => 'required|array',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
            'created_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);
        $cfg = CalculatorConfig::create($data);
        return response()->json($cfg, 201);
    }

    public function update(Request $request, CalculatorConfig $calculatorConfig)
    {
        $data = $request->validate([
            'scope' => 'sometimes|in:global,company,offer',
            'scope_id' => 'nullable|integer',
            'key' => 'sometimes|string|max:255',
            'version' => 'nullable|string|max:64',
            'value_json' => 'sometimes|array',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
            'created_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);
        $calculatorConfig->update($data);
        return $calculatorConfig->refresh();
    }

    public function destroy(CalculatorConfig $calculatorConfig)
    {
        $calculatorConfig->delete();
        return response()->noContent();
    }
}
