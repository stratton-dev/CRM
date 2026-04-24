<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Metric;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function index(Request $request)
    {
        $q = Metric::query();

        if ($userId = $request->integer('user_id')) {
            $q->where('user_id', $userId);
        }
        if ($key = $request->string('key')->toString()) {
            $q->where('key', $key);
        }
        if ($period = $request->string('period')->toString()) {
            $q->where('period', $period);
        }

        return $q->orderByDesc('id')->paginate($request->integer('per_page', 25));
    }

    public function show(Metric $metric)
    {
        return $metric;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'key' => 'required|string|max:255',
            'value' => 'required|integer',
            'period' => 'required|string|max:255',
        ]);
        $metric = Metric::create($data);
        return response()->json($metric, 201);
    }

    public function update(Request $request, Metric $metric)
    {
        $data = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'key' => 'sometimes|string|max:255',
            'value' => 'sometimes|integer',
            'period' => 'sometimes|string|max:255',
        ]);
        $metric->update($data);
        return $metric;
    }

    public function destroy(Metric $metric)
    {
        $metric->delete();
        return response()->noContent();
    }
}
