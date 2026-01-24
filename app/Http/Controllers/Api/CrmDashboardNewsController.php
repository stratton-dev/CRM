<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmDashboardNews;
use Illuminate\Http\Request;

class CrmDashboardNewsController extends Controller
{
    public function index(Request $request)
    {
        return CrmDashboardNews::query()
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tag' => 'nullable|string|max:50',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'published_at' => 'nullable|date',
        ]);
        $news = CrmDashboardNews::create($data);
        return response()->json($news, 201);
    }

    public function update(Request $request, CrmDashboardNews $crmDashboardNews)
    {
        $data = $request->validate([
            'tag' => 'nullable|string|max:50',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'published_at' => 'nullable|date',
        ]);
        $crmDashboardNews->update($data);
        return $crmDashboardNews;
    }

    public function destroy(CrmDashboardNews $crmDashboardNews)
    {
        $crmDashboardNews->delete();
        return response()->noContent();
    }
}
