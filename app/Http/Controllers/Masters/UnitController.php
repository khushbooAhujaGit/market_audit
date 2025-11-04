<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Unit;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    public function index()
    {
        $units = Unit::with('getZone.getCompany')->paginate(10);
        return view('masters.units.index', compact('units'));
    }

    public function create()
    {
        $companies = Company::all();
        return view('masters.units.add', compact('companies'));
    }

    public function store(Request $request)
    {
        $formFields = $request->validate([
            'unit_name' => [
                'required',
                Rule::unique('units')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->input('company_id'))
                        ->where('zone_id', $request->input('zone_id'));
                }),
            ],
            'unit_code' => [
                'required',
                Rule::unique('units')->where(function ($query) use ($request) {
                    return $query->where([
                        ['company_id', $request->input('company_id')],
                        ['zone_id', $request->input('zone_id')],
                        ['unit_code', $request->input('unit_code')],
                    ]);
                }), // Add ignore rule if updating
            ],
            'company_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer']
        ], [
            'unit_name.unique' => 'The combination of company, zone  and unit is already taken.',
            'unit_code.unique' => 'The combination of company, zone and unit code is already taken.',

        ]);
        Unit::create($formFields);
        return redirect(route('unit.list'))->with('message', "Unit Created Successfully");
    }

    public function update(Request $request, $id)
    {
        $unit = Unit::find($id);
        $formFields = $request->validate([
            'unit_name' => [
                'required',
                Rule::unique('units')->where(function ($query) use ($request, $unit) {
                    return $query->where('company_id', $request->input('company_id'))
                        ->where('zone_id', $request->input('zone_id'))
                        ->whereNotIn('id', [$unit->id]); // Exclude the current unit being updated
                }),
            ],
            'unit_code' => [
                'required',
                Rule::unique('units')->ignore($id)->where(function ($query) use ($request) {
                    return $query->where([
                        ['company_id', $request->input('company_id')],
                        ['zone_id', $request->input('zone_id')],
                        ['unit_code', $request->input('unit_code')],
                    ]);
                }), // Add ignore rule if updating
            ],
            'company_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer']
        ], [
            'unit_name.unique' => 'The combination of company, zone and unit is already taken.',
            'unit_code.unique' => 'The combination of company, zone and unit code is already taken.',
        ]);
        $unit->update($formFields);
        return redirect(route('unit.list'))->with('message', 'Unit Updated Successfully');
    }

    public function edit($id)
    {
        $unit = Unit::with('getZone.getCompany')->find($id);
        $companies = Company::all();
        $zones = Zone::all();
        return view('masters.units.edit', compact('unit', 'companies', 'zones'));
    }
    public function destroy(Request $request)
    {
        $unit = Unit::findOrFail($request->id);
        $unit->delete();
        return "Success";
    }
    public function get_company_zones(Request $request){
        $zones = Zone::where('company_id', $request->id)->get();
        return response()->json(['message' => "Success", 'related_zones' => $zones]);
    }
}
