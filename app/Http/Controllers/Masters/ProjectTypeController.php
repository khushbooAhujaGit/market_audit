<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\ProjectType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTypeController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    // here are the method for the company type
    public function index()
    {
        $projectTypes = ProjectType::paginate(10);
        return view('masters.project_types.index', compact('projectTypes'));
    }

    public function create()
    {
        return view('masters.project_types.add');
    }

    public function store(Request $request)
    {

        $formFields = $request->validate([
            'project_type_name' => ['required', 'unique:project_types,project_type_name'],
            'project_type_code' => ['required', 'unique:project_types,project_type_code'],
        ]);


        ProjectType::create($formFields);
        return redirect(route('project_type.list'))->with('message', "New Project Type created successfully");


    }

    public function update(Request $request, $id)
    {

        $formFields = $request->validate([
            'project_type_name' => ['required', Rule::unique('project_types', 'project_type_name')->ignore($id)],
            'project_type_code' => ['required', Rule::unique('project_types', 'project_type_code')->ignore($id)],
        ]);
        $projectType = ProjectType::findOrFail($id);
        $projectType->update($formFields);
        return redirect(route('project_type.list'))->with('message', 'Project Type Updated successfully');
    }

    public function edit($id)
    {
        $projectType = ProjectType::findOrFail($id);
        return view('masters.project_types.edit', compact('projectType'));
    }

    public function destroy(Request $request)
    {
        $projectType = ProjectType::findOrFail($request->id);
        $projectType->delete();
        return "Success";
    }
}
