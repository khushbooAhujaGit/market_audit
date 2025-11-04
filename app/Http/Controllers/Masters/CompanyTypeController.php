<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\CompanyType;
use Illuminate\Http\Request;

class CompanyTypeController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    // here are the method for the company type
    public function index()
    {
        $companyTypes = CompanyType::paginate(10);
        return view('masters.company_types.index', compact('companyTypes'));
    }

    public function create()
    {
        return view('masters.company_types.add');
    }

    public function store(Request $request)
    {

        $formFields = $request->validate([
            'company_type_name' => ['required', 'unique:company_types,company_type_name'],
        ]);


        CompanyType::create($formFields);
        return redirect(route('company_type.list'))->with('message', "New Company Type created successfully");


    }

    public function update(Request $request, $id)
    {

        $formFields = $request->validate([
            'company_type_name' => ['required'],
        ]);
        $companyType = CompanyType::findOrFail($id);
        $companyType->update($formFields);
        return redirect(route('company_type.list'))->with('message', 'Company Type Updated successfully');
    }

    public function edit($id)
    {
        $companyType = CompanyType::findOrFail($id);
        return view('masters.company_types.edit', compact('companyType'));
    }

    public function destroy(Request $request)
    {
        $companyType = CompanyType::findOrFail($request->id);
        $companyType->delete();
        return "Success";
    }
}
