<?php

namespace App\Http\Controllers\Masters;

use App\Exports\RemarkExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\RemarkMaster;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RemarkController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function index()
    {
        $remarks = RemarkMaster::with('project')
            ->whereHas('project')   // only remarks where project exists
            ->latest()->paginate(10);
        return view('masters.remarks.index', compact('remarks'));
    }

    public function create()
    {
        //khushboo 15-05-25
        $projects = Project::all();
        //khushboo 15-05-25
        return view('masters.remarks.add', compact('projects'));
    }

    public function store(Request $request)
    {
        // Convert comma-separated string → array
        if (!is_array($request->remark)) {
            $request->merge([
                'remark' => array_filter(array_map('trim', explode(',', $request->remark)))
            ]);
        }

        $validator = Validator::make($request->all(), [
            'project_id' => ['required', 'array'],
            'remark'     => ['required', 'array'],
            'status'     => ['nullable'],
        ]);

//        dd($validator);
        $validator->after(function ($validator) use ($request) {
            $projectIds = is_array($request->project_id) ? $request->project_id : [$request->project_id];

            foreach ($request->remark as $singleRemark) {
                $existing = DB::table('remark_masters')
                    ->whereIn('project_id', $projectIds)
                    ->where('remark', $singleRemark)
                    ->exists();

                if ($existing) {
                    $validator->errors()->add(
                        'remark',
                        "Remark '" . $singleRemark . "' already exists for selected projects."
                    );
                }
            }

//            // Count how many of the selected project IDs already have the remark
//            $existingCount = DB::table('remark_masters')
//                ->whereIn('project_id', $projectIds)
//                ->where('remark', $request->remark)
//                ->count();

//            // If remark exists for all selected project IDs, show error
//            if ($existingCount === count($projectIds)) {
//                $validator->errors()->add('remark', 'The remark has already been taken for all selected projects.');
//            }
        });

        $validator->validate();

        try {

            $formFields = [];
            $formFields['remark'] = $request->remark;

            if ($request->has('status')) {
                $formFields['status'] = 1;
            } else {
                $formFields['status'] = 0;
            }

            $projectIds = $request->project_id;
//            foreach ($projectIds as $project_id) {
//                $formFields['project_id'] = $project_id;
//
//                RemarkMaster::create($formFields);
//            }
            foreach ($request->remark as $singleRemark) {
                foreach ($request->project_id as $project_id) {

                    $exists = RemarkMaster::where('project_id', $project_id)
                        ->where('remark', $singleRemark)
                        ->exists();

                    if (!$exists) {
                        RemarkMaster::create([
                            'project_id' => $project_id,
                            'remark' => $singleRemark,
                            'status' => $request->has('status') ? 1 : 0,
                        ]);
                    }
                }
            }

            return redirect(route('remark.list'))->with('message', "New Remark created successfully");
        } catch (\Exception $e) {
            return redirect()->back()->withErrors('Something went wrong');
        }
    }

    public function update(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'project_id' => ['required'],
            'remark' => ['required'],
            'status' => ['nullable'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $projectIds = is_array($request->project_id) ? $request->project_id : [$request->project_id];

            // Count how many of the selected project IDs already have the remark
            $existingCount = DB::table('remark_masters')
                ->whereIn('project_id', $projectIds)
                ->where('remark', $request->remark)
                ->count();

            // If remark exists for all selected project IDs, show error
            if ($existingCount === count($projectIds)) {
                $validator->errors()->add('remark', 'The remark has already been taken for all selected projects.');
            }
        });

        $validator->validate();

        try {

            $formFields = [];
            $formFields['remark'] = $request->remark;

            if ($request->has('status')) {
                $formFields['status'] = 1;
            } else {
                $formFields['status'] = 0;
            }

            // $remark = RemarkMaster::find($id);
            $projectIds = $request->project_id;
            foreach ($projectIds as $project_id) {
                $formFields['project_id'] = $project_id;

                $remark = RemarkMaster::find($id);
                if ($remark->project_id == $project_id) {
                    $remark->update($formFields);
                } else {
                    $ProjectWithRemarkExist = RemarkMaster::where('remark', $request->remark)->where('project_id', $project_id)->exists();
                    if (!$ProjectWithRemarkExist) {
                        $formFields['project_id'] = $project_id;
                        RemarkMaster::create($formFields);
                    }
                }
            }

            return redirect(route('remark.list'))->with('message', 'Remark Updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors('Something went wrong');
        }
    }

    public function edit($id)
    {
        $remark = RemarkMaster::find($id);
        //khushboo 15-05-25
        $projects = Project::all();
        //khushboo 15-05-25
        return view('masters.remarks.edit', compact('remark', 'projects'));
    }

    public function destroy(Request $request)
    {
        $remark = RemarkMaster::findOrFail($request->id);
        $remark->delete();
        return "Success";
    }

    public function export()
    {
        return Excel::download(new RemarkExport(), 'remark_export.xlsx');
    }
}
