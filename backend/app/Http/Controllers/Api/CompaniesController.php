<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompaniesController extends Controller
{
    public function index(Request $request)
    {
        $q = Company::query();
        if ($search = $request->string('search')->toString()) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%$search%")
                  ->orWhere('nip', 'like', "%$search%")
                  ->orWhere('city', 'like', "%$search%");
            });
        }
        return $q->latest()->paginate($request->integer('per_page', 25));
    }

    public function show(Company $company)
    {
        return $company->loadCount(['employees','offers']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => 'required|string|max:20|unique:companies,nip',
            'regon' => 'nullable|string|max:20',
            'krs' => 'nullable|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'required|string|max:12',
            'city' => 'required|string|max:255',
            'country' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        $company = Company::create($data);
        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nip' => 'sometimes|required|string|max:20|unique:companies,nip,'.$company->id,
            'regon' => 'nullable|string|max:20',
            'krs' => 'nullable|string|max:20',
            'address_line1' => 'sometimes|required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'postal_code' => 'sometimes|required|string|max:12',
            'city' => 'sometimes|required|string|max:255',
            'country' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:64',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        $company->update($data);
        return $company;
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return response()->noContent();
    }
}
