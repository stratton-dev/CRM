<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmCommissionConfig;
use Illuminate\Http\Request;

class CrmCommissionConfigsController extends Controller
{
    public function show()
    {
        $config = CrmCommissionConfig::query()->latest()->first();
        if (!$config) {
            $config = CrmCommissionConfig::create([]);
        }
        return $config;
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sales_commission_first_month_lt14' => 'sometimes|required|numeric|min:0|max:1',
            'sales_commission_first_month_gt14' => 'sometimes|required|numeric|min:0|max:1',
            'sales_commission_renewal' => 'sometimes|required|numeric|min:0|max:1',
        ]);
        $config = CrmCommissionConfig::query()->latest()->first();
        if (!$config) {
            $config = CrmCommissionConfig::create($data);
            return response()->json($config, 201);
        }
        $config->update($data);
        return $config;
    }
}
