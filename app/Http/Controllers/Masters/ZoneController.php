<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    public function index()
    {
        $zones = Zone::with('getCompany')->paginate(10);
        return view('masters.zones.index', compact('zones'));
    }

    public function create()
    {
        $companies = Company::all();
        return view('masters.zones.add', compact('companies'));
    }

    public function store(Request $request)
    {
        $formFields = $request->validate([
            'zone_name' => [
                'required',
                Rule::unique('zones')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->input('company_id'));
                }), // Add ignore rule if updating
            ],
            'zone_code' => [
                'required',
                Rule::unique('zones')->where(function ($query) use ($request) {
                    return $query->where([
                        ['company_id', $request->input('company_id')],
                        ['zone_code', $request->input('zone_code')],
                    ]);
                }), // Add ignore rule if updating
            ],
            'company_id' => ['required', 'integer']
        ], [
            'zone_name.unique' => 'The combination of company and zone is already taken.',
            'zone_code.unique' => 'The combination of company and zone code is already taken.',
        ]);

        Zone::create($formFields);
        return redirect(route('zone.list'))->with('message', "Zone Created Successfully");


    }

    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'zone_name' => [
                'required',
                Rule::unique('zones')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->input('company_id'));
                })->ignore($id), // Exclude the current record being updated
            ],
            'zone_code' => [
                'required',
                Rule::unique('zones')->ignore($id)->where(function ($query) use ($request) {
                    return $query->where([
                        ['company_id', $request->input('company_id')],
                        ['zone_code', $request->input('zone_code')],
                    ]);
                }), // Add ignore rule if updating
            ],

            'company_id' => ['required', 'integer']
        ], [
            'zone_name.unique' => 'The combination of company and zone is already taken.',
            'zone_code.unique' => 'The combination of company and zone code is already taken.',

        ]);
        $zone = Zone::find($id);
        $zone->update($formFields);
        return redirect(route('zone.list'))->with('message', 'Zone Updated Successfully');


    }

    public function edit($id)
    {
        $zone = Zone::find($id);
        $companies = Company::all();
        return view('masters.zones.edit', compact('zone', 'companies'));
    }

    public function destroy(Request $request)
    {
        $zone = Zone::findOrFail($request->id);
        $zone->delete();
        return "Success";

    }
}
