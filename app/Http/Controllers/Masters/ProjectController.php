<?php

namespace App\Http\Controllers\Masters;

use App\Exports\DynamicTableExport;
use App\Exports\NonComplianceExport;
use App\Exports\ProjectsExport;
use App\Http\Controllers\Controller;
use App\Imports\ProjectDataImport2;
use App\Jobs\ProcessExcelImportJob;
use App\Jobs\ProjectDataUploadWithJob;
use App\Jobs\FilterTemplateValuesData;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityGroupName;
use App\Models\ActivityGroupPivot;
use App\Models\AuditorAssignedData;
use App\Models\Company;
use App\Models\ComplianceAnswerData;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectMasterTemplates;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\ProjectTemplatesCommonHeads;
use App\Models\ProjectType;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\SubjectDropdown;
use App\Models\SubjectQuestion;
use App\Models\Template;
use App\Models\TemplateName;
use App\Models\TemplateNameHead;
use App\Models\TempUserActivityAnswersData;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class ProjectController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function index(Request $request)
    {
        $projects = Project::with('getUnit.getZone.getCompany')->orderBy('id', 'DESC')->paginate(10);
        return view('masters.projects.index', compact('projects'));
    }

    public function searchProjects(Request $request)
    {
        $search = $request->search;
        $page = $request->page ?? 1;

        $query = Project::with('getUnit.getZone.getCompany', 'getProjectType');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('project_name', 'like', "%$search%")
                    ->orWhereHas('getUnit', fn($q2) => $q2->where('unit_name', 'like', "%$search%"))
                    ->orWhereHas('getUnit.getZone', fn($q2) => $q2->where('zone_name', 'like', "%$search%"))
                    ->orWhereHas('getUnit.getZone.getCompany', fn($q2) => $q2->where('company_name', 'like', "%$search%"));
            });
        }

        $projects = $query->orderBy('id', 'DESC')->paginate(10, ['*'], 'page', $page);

        // ðŸ”¥ Generate HTML here (same as your sales page)
        $html = '';

        foreach ($projects as $index => $project) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . e($project->getUnit->getZone->getCompany->company_name ?? '') . '</td>';
            $html .= '<td>' . e($project->getUnit->getZone->zone_name ?? '') . '</td>';
            $html .= '<td>' . e($project->getUnit->unit_name ?? '') . '</td>';
            $html .= '<td>' . e($project->project_name) . '</td>';
            $html .= '<td>' . e(optional($project->getProjectType)->project_type_name) . '</td>';
            $html .= '<td>
            <ul class="action">
                <li class="edit">
                    <a href="' . route('project.edit', $project->id) . '">
                        <i class="icon-pencil-alt"></i>
                    </a>
                </li>
                <li class="delete" data-id="' . $project->id . '">
                    <i class="icon-trash"></i>
                </li>
            </ul>
        </td>';
            $html .= '</tr>';
        }

        return response()->json([
            'html' => $html,
            'pagination' => $projects->links()->toHtml()
        ]);
    }


    public function create()
    {
        $companies = Company::all();
        $template_names = TemplateName::all();
        $activites = Activity::all();
        $activity_groups = ActivityGroup::all();
        $project_types = ProjectType::all();
        return view('masters.projects.add', compact('companies', 'template_names', 'activites', 'activity_groups', 'project_types'));
    }

    public function store(Request $request)
    {

        $formFields = $request->validate([
            'project_name' => ['required', 'unique:projects'],
            'company_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'project_type_id' => ['required', 'integer'],
            // 'activity_id' => ['required_without:activity_group_name_id'],
            // 'activity_group_name_id' => ['required_without:activity_id'],
            'template_name_id' => ['required', 'array'],
        ]);
        if ($request->has('is_application_applicable')) {
            $formFields['is_application_applicable'] = 1;
        }
        if ($request->has('recurring')) {
            $formFields['recurring'] = 1;
        }
        if ($request->has('with_data')) {
            $formFields['with_data'] = 1;
        }
        if ($request->has('data_add_on')) {
            $formFields['data_add_on'] = 1;
        }

        //khushboo 01-04-2025
        // if ($request->has('is_agency_required')) {
        $formFields['is_agency_required'] = 1;
        // }
        if ($request->has('is_otp_required')) {
            $formFields['is_otp_required'] = 1;
        }

        if (count($request->template_name_id) == 1) {
            if ($request->has('isComplianceApplicable')) {
                return redirect()->back()->with('error', 'Cannot Assign Compliance with One Template');
            }
        } else {
            if ($request->has('isComplianceApplicable')) {
                $formFields['isComplianceApplicable'] = 1;
                //if compliance Applicable

                if ($request->has('complianceRepeationStartDate') && $request->has('complianceRepeationEndDate') && !empty($request->complianceRepeationStartDate) && !empty($request->complianceRepeationEndDate)) {
                    $formFields['complianceRepeationStartDate'] = $request->complianceRepeationStartDate;
                    $formFields['complianceRepeationEndDate'] = $request->complianceRepeationEndDate;
                } else {
                    return redirect()->back()->with('error', 'Please Select Non Compliance Period');
                }
            }
        }


        if ($request->has('isCompanyApplicable')) {
            $formFields['isCompanyApplicable'] = 1;
        }
        if ($request->has('companyVerificationRequired')) {
            $formFields['companyVerificationRequired'] = 1;
        }
        if ($request->has('is_otp_duplication_allowed')) {
            $formFields['is_otp_duplication_allowed'] = 1;
        }
        //khushboo 01-04-2025

        $project = Project::create($formFields);
        $is_master = 0;
        if (count($request->template_name_id) == 1) {
            $is_master = 1;
        }
        foreach ($request->template_name_id as $templateId) {
            $projectTemplate = ProjectTemplate::firstOrCreate([
                'project_id' => $project->id,
                'template_name_id' => $templateId,
                'is_master' => $is_master
            ]);
        }
        return redirect(route('project.list'))->with('message', "Project Created Successfully");
    }


    public function update(Request $request, $id)
    {
        // dd($request);
        $formFields = $request->validate([
            'project_name' => ['required'],
            'company_id' => ['required', 'integer'],
            'zone_id' => ['required', 'integer'],
            'unit_id' => ['required', 'integer'],
            'project_type_id' => ['required', 'integer'],
            //            'activity_id' => ['required_without:activity_group_name_id'],
            //            'activity_group_name_id' => ['required_without:activity_id'],
            'template_name_id' => ['required', 'array'],
        ]);

        if ($request->has('is_application_applicable')) {
            $formFields['is_application_applicable'] = 1;
        } else {
            $formFields['is_application_applicable'] = 0;
        }
        if ($request->has('recurring')) {
            $formFields['recurring'] = 1;
        } else {
            $formFields['recurring'] = 0;
        }
        if ($request->has('with_data')) {
            $formFields['with_data'] = 1;
        } else {
            $formFields['with_data'] = 0;
        }

        //khushboo 01-04-2025

        if ($request->has('is_otp_required')) {
            $formFields['is_otp_required'] = 1;
        } else {
            $formFields['is_otp_required'] = 0;
        }
        if ($request->has('isComplianceApplicable')) {
            $formFields['isComplianceApplicable'] = 1;
        } else {
            $formFields['isComplianceApplicable'] = 0;
        }
        if ($request->has('isCompanyApplicable')) {
            $formFields['isCompanyApplicable'] = 1;
        } else {
            $formFields['isCompanyApplicable'] = 0;
        }
        if ($request->has('companyVerificationRequired')) {
            $formFields['companyVerificationRequired'] = 1;
        } else {
            $formFields['companyVerificationRequired'] = 0;
        }
        if ($request->has('is_otp_duplication_allowed')) {
            $formFields['is_otp_duplication_allowed'] = 1;
        } else {
            $formFields['is_otp_duplication_allowed'] = 0;
        }
        //khushboo 01-04-2025

        $project = Project::find($id);
        $project->update($formFields);
        foreach ($request->template_name_id as $templateId) {
            $projectTemplate = ProjectTemplate::firstOrCreate([
                'project_id' => $project->id,
                'template_name_id' => $templateId,
            ]);
        }
        $existingTemplateIds = $project->getProjectTemplates->pluck('template_name_id')->toArray();
        $selectedTemplateIds = $request->input('template_name_id', []);
        $unselectedTemplateIds = array_diff($existingTemplateIds, $selectedTemplateIds);
        foreach ($unselectedTemplateIds as $deleted_template_name) {
            $project_template_del = ProjectTemplate::where('project_id', $project->id)
                ->where('template_name_id', $deleted_template_name)->delete();
        }
        return redirect(route('project.list'))->with('message', 'Project Updated');
    }


    public function edit($id)
    {
        // dd($id);
        $project = Project::with('getUnit.getZone.getCompany', 'common_heads')->find($id);
        $companies = Company::all();
        $zones = Zone::all();
        $units = Unit::all();
        $template_names = TemplateName::all();
        $activites = Activity::all();
        $project_types = ProjectType::all();
        $activity_groups = ActivityGroup::all();
        return view('masters.projects.edit', compact('project', 'companies', 'zones', 'units', 'template_names', 'activites', 'activity_groups', 'project_types'));
    }

    public function destroy(Request $request)
    {
        try {
            $project = Project::findOrFail($request->id);

            ProjectTemplatesCommonHeads::where('project_id', $project->id)->delete();
            DataAssign::where('project_id', $project->id)->delete();
            AuditorAssignedData::where('project_id', $project->id)->delete();

            $projectTemplatesIds = ProjectTemplate::where('project_id', $project->id)->pluck('id')->toArray();
            if (!empty($projectTemplatesIds)) {
                $projectTemplateRowIds = ProjectTemplateNameValuesNew::whereIn('project_template_id', $projectTemplatesIds)->pluck('id')->toArray();
                if (!empty($projectTemplateRowIds)) {
                    TempUserActivityAnswersData::whereIn('row_id', $projectTemplateRowIds)->delete();
                }
                ProjectTemplateNameValuesNew::whereIn('project_template_id', $projectTemplatesIds)->delete();
                ProjectTemplate::where('project_id', $project->id)->delete();
            }

            $project->delete();
            return "Success";
        } catch (\Exception $e) {
            \Log::info('Project Deletion Error with id - ' . $request->id . $e->getMessage());
            return "Error";
        }
    }

    public function get_zones_units(Request $request)
    {
        $units = Unit::where('zone_id', $request->id)->get();
        return response()->json(['message' => "Success", 'related_units' => $units]);
    }

    public function getGroupActivities($group_id)
    {
        $activities = ActivityGroupPivot::where('activity_group_id', $group_id)
            ->with('getActivityInfo')
            ->orderBy('sequence')
            ->get()
            ->map(fn($p) => ['id' => $p->activity_id, 'name' => $p->getActivityInfo->activity_name]);
        return response()->json($activities);
    }

    public function get_project_info(Request $request)
    {
        $projectInfo = Project::findOrFail($request->id);
        //khushboo 01-04-2025
        $is_agency_required = false;
        $agency_list = [];
        if ($projectInfo && $projectInfo->is_agency_required == 1) {
            $is_agency_required = true;
            $agency_list = User::whereHas('roles', function ($query) {
                $query->where('name', 'Agency');
            })->get();

            $verifier_user_list = User::whereHas('roles', function ($query) {
                $query->where('name', 'Verifier');
            })->get();
            $auditor_user_list = User::whereHas('roles', function ($query) {
                $query->where('name', 'Auditor');
            })->get();
        } else {
            $verifier_user_list = User::whereHas('roles', function ($query) {
                $query->where('name', 'Verifier');
            })->get();
            $auditor_user_list = User::whereHas('roles', function ($query) {
                $query->where('name', 'Auditor');
            })->get();
        }

        //company user list
        $companyUsers = [];
        $isCompanyApplicable = false;
        if ($projectInfo->isCompanyApplicable) {
            $isCompanyApplicable = true;
            $companyUsers = User::whereHas('roles', function ($query) {
                $query->where('name', 'Company User');
            })->get();
        }

        $companyhtml = '';
        if (!empty($companyUsers)) {
            $companyhtml = '<option selected="" disabled="" value=""> Choose...</option>';
            foreach ($companyUsers as $companyData) {
                $companyhtml .= "<option value=" . $companyData->id . ">" . $companyData->name . "</option>";
            }
        }

        //agency list
        $html = '';
        if (!empty($agency_list)) {
            $html = '<option selected="" disabled="" value=""> Choose...</option>';
            foreach ($agency_list as $val1) {
                $html .= "<option value=" . $val1->id . ">" . $val1->name . "</option>";
            }
        }

        $currentUser = User::find(Auth::user()->id);

        $excludedAgencyIds = Project::where('is_agency_required', 1)
            ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                $query->where('agency_id', $currentUser->agency_user_id)
                    ->orWhere('agency_id', $currentUser->id);
            })
            ->with('dataAssigns')
            ->get()
            ->pluck('dataAssigns')
            ->flatten()
            ->pluck('agency_id')
            ->unique()
            ->toArray();

        // Filter verifier list
        $filtered_verifier_list = collect($verifier_user_list)->filter(function ($user) use ($excludedAgencyIds) {
            return !in_array($user->agency_user_id, $excludedAgencyIds);
        });

        // Filter auditor list
        $filtered_auditor_list = collect($auditor_user_list)->filter(function ($user) use ($excludedAgencyIds) {
            return !in_array($user->agency_user_id, $excludedAgencyIds);
        });

        $html1 = '';
        if ($filtered_verifier_list->isNotEmpty()) {
            $html1 = '<option selected disabled value="">Choose...</option>';
            foreach ($filtered_verifier_list as $val1) {
                $html1 .= "<option value={$val1->id}>{$val1->name}</option>";
            }
        }

        $html2 = '';
        if ($filtered_auditor_list->isNotEmpty()) {
            $html2 = '<option selected disabled value="">Choose...</option>';
            foreach ($filtered_auditor_list as $val1) {
                $html2 .= "<option value={$val1->id}>{$val1->name}</option>";
            }
        }

        //khushboo 01-04-2025

        $projectMasterInfo = ProjectTemplate::with('getTemplate.getTemplateHeads')->where('project_id', $request->id)
            ->where('is_master', 1)->first();
        $projectTemplateNames = ProjectTemplate::where('project_id', $request->id)->with('getTemplate.getTemplateHeads')->get();

        //khushboo 05-04-2025
        $otpProjectActivities = false;
        if ($projectInfo && $projectInfo->is_otp_required == 1) {
            $otpProjectActivities = true;
        }
        //khushboo 05-04-2025

        return response()->json([
            'message' => "Success",
            'projectInfo' => $projectInfo,
            'projectTemplateNames' => $projectTemplateNames,
            'projectMasterInfo' => $projectMasterInfo,
            'is_agency_required' => $is_agency_required,
            'isCompanyApplicable' => $isCompanyApplicable,
            'agency_list' => $html,
            'verifier_user_list' => $html1,
            'auditor_user_list' => $html2,
            'company_users' => $companyhtml,
            'otpProjectActivities' => $otpProjectActivities
        ]);
    }


    public function upload_data_view()
    {
        $data_templates = TemplateName::all();
        $companies = Company::all();
        $projects = Project::all();
        return view('masters.data_templates.upload_data', compact('data_templates', 'companies', 'projects'));
    }

    public function get_unit_projects(Request $request)
    {
        $projects = Project::where('unit_id', $request->id)->get();
        return response()->json(['message' => "Success", 'related_projects' => $projects]);
    }

    public function upload_project_template_data(Request $request)
    {
        $formFields = $request->validate([
            'project_id' => ['required'],
            'template_name_id' => ['required'],
            'data_excel' => [
                'required',
                'file',
                'mimetypes:text/plain,text/csv,application/csv',
                'max:512000', // 500MB = 512000 KB
            ],
        ]);

        $templateNameId = $formFields['template_name_id'];
        $projectId = $formFields['project_id'];
        $projectInfo = Project::findOrFail($request->project_id);
        $projectNameGet = $projectInfo->project_name;
        $getMasterTemplate = ProjectTemplate::where('project_id', $request->project_id)->where('is_master', 1)->first();
        // dd(empty($getMasterTemplate), $projectId);
        $dataFilter = 0;
        if (!empty($getMasterTemplate)) {
            $checkTemplateValues = DB::table('project_template_name_values_new')->where('project_template_id', $getMasterTemplate->id)->exists();

            $checkProjectTemplate = ProjectTemplate::where('project_id', $projectId)->where('template_name_id', $templateNameId)->first();
            if ($checkTemplateValues && $checkProjectTemplate->is_master == 0 && !empty($checkProjectTemplate->own_reference_head_id) && !empty($checkProjectTemplate->master_head_id)) {
                $dataFilter = 1;
            }
        }


        if ($request->has('upload_with_job')) {
            // Clear cache for the project and template before starting the job
            $cacheKey = 'headers_checked_' . $formFields['project_id'] . '_' . $formFields['template_name_id'];
            Cache::forget($cacheKey);

            $file = $request->file('data_excel');
            $filename = 'project_data_upload_' . $projectNameGet . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('projectsDataFiles'), $filename);
            $filePath = public_path('projectsDataFiles/' . $filename);

            $user = User::find(Auth::user()->id);

            dispatch(new ProjectDataUploadWithJob($filePath, $formFields['project_id'], $formFields['template_name_id'], $user, $dataFilter));
            //            $job = new ProjectDataUploadWithJob($filePath, $formFields['project_id'], $formFields['template_name_id'], $user, $dataFilter);
            //            $job->handle();

            $filePath = storage_path('app/' . $filePath);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            return redirect()->back()->with('success', "Data import has been queued and will be processed shortly.");
        }

        $insertProjectTemplateData = [];

        // this is to check the template name is selected properly or not
        $checkTemplatename = TemplateName::findOrFail($templateNameId);

        $projectTemplate_id = '';
        // this is to check the template name is selected properly or not
        $checkProjectname = Project::findOrFail($projectId);
        $checkProjectTemplate = ProjectTemplate::where('project_id', $projectId)->where('template_name_id', $templateNameId)->first();
        if ($checkProjectTemplate) {
            $projectTemplate_id = $checkProjectTemplate->id;
        } else {
            $checkProjectTemplate = ProjectTemplate::create([
                'project_id' => $formFields['project_id'],
                'template_name_id' => $formFields['template_name_id']
            ]);
            $projectTemplate_id = $checkProjectTemplate->id;
        }
        $template_heads_count = count($checkProjectTemplate->getTemplate->getTemplateHeads);
        //        dd($request->hasFile('data_excel'));
        if ($request->hasFile('data_excel')) {

            $file = $request->file('data_excel');
            $filename = 'project_data_upload_' . $projectNameGet . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('projectsDataFiles'), $filename);
            $filePath = public_path('projectsDataFiles/' . $filename);
            $formFields['template_name_id'];
            try {
                //                dd(DB::select("SHOW VARIABLES LIKE 'local_infile'"));
                //                dd($filePath);
                $getMasterTemplate = ProjectTemplate::where('project_id', $request->project_id)->where('is_master', 1)->first();
                $dataFilter = 0;
                if (!empty($getMasterTemplate)) {
                    $checkTemplateValues = DB::table('project_template_name_values_new')->where('project_template_id', $getMasterTemplate->id)->exists();

                    if ($checkTemplateValues && $checkProjectTemplate->is_master == 0 && !empty($checkProjectTemplate->own_reference_head_id) && !empty($checkProjectTemplate->master_head_id)) {
                        $dataFilter = 1;
                    }
                }

                $import = new ProjectDataImport2($filePath, $formFields['project_id'], $formFields['template_name_id'], $dataFilter);
                $result = $import->handle();
            } catch (\Exception $e) {
                // dd($e->getMessage());

            } finally {
                if (file_exists($filePath)) {
                    unlink($filePath); // Ensure file is deleted even if import fails
                }
            }
            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }
            return redirect()->back()->with('success', $result['message']);
        }
    }

    public function upload_project_template_data_old_sept(Request $request)
    {
        $formFields = $request->validate([
            'project_id' => ['required'],
            'template_name_id' => ['required'],
            'data_excel' => [
                'required',
                'file',
                'mimetypes:text/plain,text/csv,application/csv',
                'max:512000', // 500MB = 512000 KB
            ],
        ]);

        $projectInfo = Project::findOrFail($request->project_id);
        $projectNameGet = $projectInfo->project_name;

        $templateNameId = $formFields['template_name_id'];
        // this is to check the template name is selected properly or not
        $checkTemplatename = TemplateName::findOrFail($templateNameId);
        $projectId = $formFields['project_id'];

        if ($request->has('upload_with_job')) {
            // Clear cache for the project and template before starting the job
            $cacheKey = 'headers_checked_' . $formFields['project_id'] . '_' . $formFields['template_name_id'];
            Cache::forget($cacheKey);

            $file = $request->file('data_excel');
            $filename = 'project_data_upload_' . $projectNameGet . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('projectsDataFiles'), $filename);
            $filePath = public_path('projectsDataFiles/' . $filename);

            $user = User::find(Auth::user()->id);

            $job = new ProjectDataUploadWithJob($filePath, $formFields['project_id'], $formFields['template_name_id'], $user);
            $job->handle();

            $filePath = storage_path('app/' . $filePath);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            return redirect()->back()->with('success', "Data import has been queued and will be processed shortly.");
        }

        $insertProjectTemplateData = [];

        $projectTemplate_id = '';
        // this is to check the template name is selected properly or not
        $checkProjectname = Project::findOrFail($projectId);
        $checkProjectTemplate = ProjectTemplate::where('project_id', $projectId)->where('template_name_id', $templateNameId)->first();
        if ($checkProjectTemplate) {
            $projectTemplate_id = $checkProjectTemplate->id;
        } else {
            $checkProjectTemplate = ProjectTemplate::create([
                'project_id' => $formFields['project_id'],
                'template_name_id' => $formFields['template_name_id']
            ]);
            $projectTemplate_id = $checkProjectTemplate->id;
        }
        $template_heads_count = count($checkProjectTemplate->getTemplate->getTemplateHeads);
        //        dd($request->hasFile('data_excel'));
        if ($request->hasFile('data_excel')) {

            $file = $request->file('data_excel');
            $filename = 'project_data_upload_' . $projectNameGet . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('projectsDataFiles'), $filename);
            $filePath = public_path('projectsDataFiles/' . $filename);
            $formFields['template_name_id'];
            try {
                //                dd(DB::select("SHOW VARIABLES LIKE 'local_infile'"));
                //                dd($filePath);
                $import = new ProjectDataImport2($filePath, $formFields['project_id'], $formFields['template_name_id']);
                $result = $import->handle();
            } catch (\Exception $e) {
                dd($e->getMessage());
            } finally {
                if (file_exists($filePath)) {
                    unlink($filePath); // Ensure file is deleted even if import fails
                }
            }
            if (!$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }
            return redirect()->back()->with('success', $result['message']);
        }
    }

    public function upload_project_template_dataOld(Request $request)
    {
        $formFields = $request->validate([
            'project_id' => ['required'],
            'template_name_id' => ['required'],
            'data_excel' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'max:512000', // 50MB limit (size in KB)
            ],
        ]);

        if ($request->has('upload_with_job')) {
            // Clear cache for the project and template before starting the job
            $cacheKey = 'headers_checked_' . $formFields['project_id'] . '_' . $formFields['template_name_id'];
            Cache::forget($cacheKey);
            // Store the uploaded file in the `temp` directory
            $filePath = $request->file('data_excel')->store('temp');
            $requestData = $request->only(['project_id', 'template_name_id']);

            //            Excel::queueImport(new ProjectDataImport($formFields['project_id'], $formFields['template_name_id']), $filePath);
            // Store the uploaded file in the `temp` directory
            $filePath = $request->file('data_excel')->store('temp');
            $requestData = $request->only(['project_id', 'template_name_id']);

            $file = $request->file('data_excel');
            $folder = 'tempExcel/uploads'; // Folder under public/
            $filename = time() . '_' . $file->getClientOriginalName();

            $destination = public_path($folder);

            // Check if folder exists, if not create it
            if (!File::exists($destination)) {
                File::makeDirectory($destination, 0755, true); // recursive = true
            }

            // Move the file
            $file->move($destination, $filename);

            // Full path of stored file
            $absolutePath = $destination . '/' . $filename;

            $user = User::find(Auth::user()->id);

            // Dispatch the job to queue AFTER response
            ProcessExcelImportJob::dispatch($filePath, $formFields['project_id'], $formFields['template_name_id'], $user);
            //            ProcessExcelImportJob::dispatch($filePath, $formFields['project_id'], $formFields['template_name_id']);

            $filePath = storage_path('app/' . $filePath);
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
            return redirect()->back()->with('message', 'Data import initiated successfully.');
        }

        $insertProjectTemplateData = [];
        $templateNameId = $formFields['template_name_id'];
        // this is to check the template name is selected properly or not
        $checkTemplatename = TemplateName::findOrFail($templateNameId);
        $projectId = $formFields['project_id'];
        $projectTemplate_id = '';
        // this is to check the template name is selected properly or not
        $checkProjectname = Project::findOrFail($projectId);
        $checkProjectTemplate = ProjectTemplate::where('project_id', $projectId)->where('template_name_id', $templateNameId)->first();
        if ($checkProjectTemplate) {
            $projectTemplate_id = $checkProjectTemplate->id;
        } else {
            $checkProjectTemplate = ProjectTemplate::create([
                'project_id' => $formFields['project_id'],
                'template_name_id' => $formFields['template_name_id']
            ]);
            $projectTemplate_id = $checkProjectTemplate->id;
        }
        $template_heads_count = count($checkProjectTemplate->getTemplate->getTemplateHeads);
        if ($request->hasFile('data_excel')) {
            $file = $request->file('data_excel');
            $data = Excel::toCollection(Excel::class, $file)->first();
            $dataArray = $data->toArray();
            // Remove the first element from the array
            $headers_data = array_shift($dataArray);
            $headers = array_slice($headers_data, 0, $template_heads_count);
            $template_header_all = $checkTemplatename->getTemplateHeads->pluck('template_head_name')->toArray();
            $trimmedHeaders = collect($headers)->map(function ($item) {
                return trim($item);
            })->all();
            $trimmedTemplateHeaders = collect($template_header_all)->map(function ($item) {
                return trim($item);
            })->all();
            // Check if the two trimmed arrays have the same values
            if (empty(array_diff($trimmedHeaders, $trimmedTemplateHeaders)) && empty(array_diff($trimmedTemplateHeaders, $trimmedHeaders))) {
            } else {
                return redirect()->back()->withErrors(['data_excel' => 'The data format is not correct as per the data template.'])->withInput();
            }
            $row_id_counter = ProjectTemplateNameValue::max('row_id') + 1;
            foreach ($dataArray as $index => $template_data) {
                // Check if all values in the row are empty, ' ', or null
                if (empty(array_filter($template_data, fn($value) => !is_null($value) && $value !== ''))) {
                    continue; // Skip empty row
                }
                $template_data = array_slice($template_data, 0, $template_heads_count);
                $template_data = array_map('strval', $template_data);
                foreach ($template_data as $inside => $data) {
                    $length = count($template_data);
                    $let_in = 1;
                    if ($let_in == 1) {
                        $template_headInfo = TemplateNameHead::where('template_name_id', $templateNameId)->where('template_head_name', $headers[$inside])->first();
                        $insertProjectTemplateData[] = [
                            'row_id' => $row_id_counter,
                            'project_template_id' => $projectTemplate_id,
                            'template_name_head_id' => $template_headInfo->id,
                            'value' => $data
                        ];
                    }
                }
                $row_id_counter += 1;
                if (count($insertProjectTemplateData) >= 500) {
                    ProjectTemplateNameValue::insert($insertProjectTemplateData);
                    $insertProjectTemplateData = []; // Reset the batch
                }
            }
            // Insert any remaining data
            if (!empty($insertProjectTemplateData)) {
                ProjectTemplateNameValue::insert($insertProjectTemplateData);
                $insertProjectTemplateData = []; // Reset the batch
            }
        }
        return redirect(route('templateName.list'))->with('message', 'Data Uploaded Successfully');
    }


    public function project_upload_data_view()
    {

        $companies = Company::all();
        // $projects = Project::all();
        // $data_templates = TemplateName::all();

        //khushboo 02-04-2025
        $currentuser = User::find(Auth::user()->id);
        // dd($currentuser->id);
        if ($currentuser->is_agency_user == 1 || $currentuser->getRoleNames()->first() == 'Agency') {
            // $projects = Project::where('is_agency_required', 1)->where('agency_id', Auth::user()->id)->get();
            $projects = Project::where('is_agency_required', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('agency_id', $currentuser->id);
                })->get();

            $projectsIds = Project::where('is_agency_required', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('agency_id', $currentuser->id);
                })
                ->pluck('id')->toArray();

            $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)->pluck('template_name_id')->toArray();
            $data_templates = TemplateName::whereIn('id', $project_templates_ids)->get();
        } else {
            $data_templates = TemplateName::all();
            $projects = Project::all();
        }
        //khushboo 02-04-2025

        return view('masters.data_templates.view_upload_data', compact('data_templates', 'companies', 'projects'));
    }


    public function render_project_upload_data_viewbk(Request $request)
    {
        $formFields = $request->validate([
            'company_id' => ['nullable'],
            'zone_id' => ['nullable'],
            'unit_id' => ['nullable'],
            'project_id' => ['required'],
            'template_name_id' => ['required'],
        ]);
        $templateNameId = $formFields['template_name_id'];
        // this is to check the template name is selected properly or not
        $checkTemplatename = TemplateName::findOrFail($templateNameId);
        $projectId = $formFields['project_id'];
        // this is to check the template name is selected properly or not
        $checkProjectname = Project::findOrFail($projectId);
        $projectTemplate = ProjectTemplate::where('project_id', $projectId)->where('template_name_id', $templateNameId)->first();
        if (!$projectTemplate) {
            //            abort(404);
            $response = "No data found";
            return view('masters.data_templates.view_project_data', compact('response'));
        }

        $response = "yes";
        return view('masters.data_templates.view_project_data', compact('projectTemplate', 'response'));
    }

    public function render_project_upload_data_view(Request $request)
    {
        if (!$request->has('page') && !$request->has('template_name_id')) {
            return redirect()->back(); // This can cause a redirect loop!
        }
        $formFields = $request->validate([
            'company_id' => ['nullable'],
            'zone_id' => ['nullable'],
            'unit_id' => ['nullable'],
            'project_id' => ['required'],
            'template_name_id' => ['required'],
        ]);
        $templateNameId = $formFields['template_name_id'];
        // this is to check the template name is selected properly or not
        $checkTemplatename = TemplateName::findOrFail($templateNameId);
        $projectId = $formFields['project_id'];
        // this is to check the template name is selected properly or not
        $checkProjectname = Project::findOrFail($projectId);
        $projectTemplate = ProjectTemplate::where('project_id', $projectId)
            ->where('template_name_id', $templateNameId)
            ->first();

        if (!$projectTemplate) {
            $response = "No data found";
            return view('masters.data_templates.view_project_data', compact('response'));
        }
        $templateHeadsCount = count($checkTemplatename->getTemplateHeads); // Dynamic number of heads
        //        $perPage = 100 * $templateHeadsCount;
        $perPage = 100;
        //        $projectTemplateDataGet = DB::table('project_template_name_values')
        //            ->where("project_template_id", $projectTemplate->id)
        ////            ->with('getHeadName')
        //            ->join('template_name_heads', 'project_template_name_values.template_name_head_id', '=' , 'template_name_heads.id')
        //            ->select(
        //                'project_template_name_values.*',
        //                'template_name_heads.*'
        //            )
        //            ->orderBy('project_template_name_values.id')
        //            ->paginate($perPage)
        //            ->appends($request->all());

        $projectTemplateDataGet = DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplate->id)
            ->select('id', 'project_template_id', 'template_data_json')
            ->orderBy('id')
            ->paginate($perPage)
            ->appends($request->all());

        // Decode the JSON for each row
        $projectTemplateDataGet->getCollection()->transform(function ($item) {
            $item->decoded_json = json_decode($item->template_data_json, true);
            return $item;
        });

        $response = "yes";
        //         dd($projectTemplateDataGet);
        return view('masters.data_templates.view_project_data', compact('projectTemplate', 'response', 'projectTemplateDataGet'));
    }


    public function project_data_item($id)
    {
        //        dd($id);
        $related_values = ProjectTemplateNameValuesNew::where('id', $id)
            ->with('getProjectTemplateData')
            ->first();

        $tempalteDatas = json_decode($related_values->template_data_json, true);
        $templateHeadData = [];

        foreach ($tempalteDatas as $key => $value) {
            $templatehead = TemplateNameHead::find($key);
            $templateHeadData[] = [
                'id' => $key,
                'template_head_name' => $templatehead->template_head_name,
                'value' => $value
            ];
        }
        return view('masters.data_templates.edit_data', compact('related_values', 'templateHeadData'));
    }

    public function project_data_item_update(Request $request, $id)
    {
        // Validate the incoming request data
        $request->validate([
            'values' => 'required|array',
            //            'values.*.id' => 'required|integer|exists:project_template_name_values_new,id',
            'values.*.id' => 'required|integer|',
            'values.*.value' => 'nullable|string|max:255',
        ]);

        $data = ProjectTemplateNameValuesNew::find($id);
        $new_values = $request->input('values');
        $new_json_data = collect($new_values)          // make it a collection
            ->pluck('value', 'id') // pluck value keyed by id
            ->toArray();
        //        dd($new_json_data, $id);
        $data->update([
            'template_data_json' => json_encode($new_json_data)
        ]);

        // Loop through the values and update them
        //        foreach ($request->input('values') as $valueData) {
        //            $value = ProjectTemplateNameValuesNew::find($valueData['id']);
        //            if ($valueData['value'] == "") {
        //                $valueData['value'] = "";
        //            }
        //            $value->value = $valueData['value']; // This can now be null or an empty string
        //            $value->save();
        //        }

        return redirect()->route('project_data.edit', ['id' => $id])->with('success', 'Data updated successfully.');
    }

    public function project_data_activity_mapping()
    {
        $projects = Project::all();
        $activities = Activity::all();
        $group_activities = ActivityGroup::all();
        $templates = TemplateName::all();
        return view('masters.data_templates.data_activity_mapping', compact('projects', 'activities', 'group_activities', 'templates'));
    }

    public function distributor_setting()
    {
        $projects = Project::all();
        $templates = TemplateName::all();
        return view('masters.data_templates.distributor', compact('projects', 'templates'));
    }

    public function distributor_set(Request $request)
    {
        $validate = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'row_id' => 'required',
            'completion_type' => 'required',
            'min_completion' => 'required',
        ]);
        $project_template_info = ProjectTemplate::where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)
            ->first();
        if ($project_template_info->is_master) {
            $master_rows = ProjectMasterTemplates::updateOrCreate(
                [
                    'row_id' => $request->row_id,
                    'project_template_id' => $project_template_info->id
                ],
                [
                    'completion_type' => $request->completion_type,
                    'min_completion' => $request->min_completion,
                    'user_id' => auth()->id(), // Assuming you want to assign the current user
                ]
            );
            return back()->with(['message' => "Distributor Min Mapped Successfully"]);
        }
        return back()->with(['message' => "Only Distributor Can be Mapped"]);
    }


    public function project_temp_activity_map(Request $request)
    {
        // dd($request);
        $type_helper = 0;
        $activity_or_group_id = '';
        $otpActivityData = null;
        foreach ($request->mapper_data as $mapping_data) {
            //khushboo 05-04-2025
            if (isset($mapping_data['otpactivityId']) && !empty($mapping_data['otpactivityId'])) {
                $otpActivityData = $mapping_data['otpactivityId'];
            }

            // dd($mapping_data);
            //khushboo 05-04-2025
            if (isset($mapping_data['activityId']) && !empty($mapping_data['activityId'])) {
                $type_helper = 0;
                $activity_or_group_id = $mapping_data['activityId'];
            } else {
                $activity_or_group_id = $mapping_data['activity_groupId'];
                $type_helper = 1;
            }
            $projectTemplate = ProjectTemplate::where('project_id', $request->project_id)
                ->where('template_name_id', $mapping_data['templateNameId'])->first();
            $projectTemplate->update([
                'activity_group_name_id_or_activity_id' => $activity_or_group_id,
                'activityType' => $type_helper,
                'completion_type' => $mapping_data['completion_type'],
                'min_completion' => $mapping_data['min_completion'],
                'data_add_on' => $mapping_data['data_add_on'],
                'activity_add_on' => $mapping_data['activity_add_on'] ?? 0,
                'activity_add_on_activity_ids' => isset($mapping_data['activity_add_on_activity_ids']) && !empty($mapping_data['activity_add_on_activity_ids']) ? json_encode($mapping_data['activity_add_on_activity_ids']) : null,
                'with_data' => $mapping_data['with_data'],
                'can_edit_data' => $mapping_data['can_edit_data'],
                'master_head_id' => $mapping_data['master_head'],
                'own_reference_head_id' => $mapping_data['own_head'],
                'sub_header' => $mapping_data['sub_header'],
                'main_header' => $mapping_data['main_header'],
                'compliance_column_id' => $mapping_data['complianceColumnId'], //khushboo 02-05-2025
                'activity_otp_required_ids' => json_encode($otpActivityData), //khushboo 05-04-2025
            ]);

            if (isset($mapping_data['activityId']) && !empty($mapping_data['activityId'])) {
                // Update data_assigns table based on activityType
                DataAssign::where('project_id', $request->project_id)
                    ->where('template_name_id', $mapping_data['templateNameId'])
                    ->update([
                        'activity_id' => $type_helper == 0 ? $activity_or_group_id : null,
                        'activity_group_id' => $type_helper == 1 ? $activity_or_group_id : null,
                    ]);
            } else {

                $activity_ids = ActivityGroupPivot::where('activity_group_id', $activity_or_group_id)->pluck('activity_id')->toArray();
                foreach ($activity_ids as $actId) {
                    // Update data_assigns table based on activityType
                    DataAssign::where('project_id', $request->project_id)
                        ->where('template_name_id', $mapping_data['templateNameId'])
                        ->update([
                            'activity_id' => $actId,
                            'activity_group_id' => $type_helper == 1 ? $activity_or_group_id : null,
                        ]);
                }
            }
        }

        // for mapping distributors with outlet data
        dispatch(new FilterTemplateValuesData($request->project_id));

        return "success";
    }


    public function assign_create()
    {
        // $template_names = TemplateName::all();
        $companies = Company::all();
        $activities = Activity::all();

        $activity_groups = ActivityGroup::all();
        // $projects = Project::all();

        //khushboo 31-03-2025
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if ($currentUserRole == 'Agency') {

            $projects = Project::where('is_agency_required', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('agency_id', Auth::user()->agency_user_id)
                        ->orWhere('agency_id', Auth::user()->id);
                })
                ->orderBy('id', 'DESC')
                ->get();

            //            dd($projects);
            $projectsIds = Project::where('is_agency_required', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('agency_id', Auth::user()->agency_user_id)
                        ->orWhere('agency_id', Auth::user()->id);
                })->pluck('id')->toArray();

            $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)
                ->pluck('template_name_id')->toArray();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
            $users = User::where('agency_user_id', Auth::user()->id)->role('Auditor')->get();
            $verifier_users = User::where('agency_user_id', Auth::user()->id)->role('Verifier')->get();
        } else {
            $template_names = TemplateName::all();
            $users = User::all();
            $projects = Project::all();
            $verifier_users = [];
        }

        //khushboo 31-03-2025

        return view('masters.data_templates.assign_data', compact('verifier_users', 'currentUser', 'currentUserRole', 'template_names', 'companies', 'activities', 'users', 'activity_groups', 'projects'));
    }

    // this is to make the project template a master template
    public function make_master_template(Request $request)
    {
        $projectInfo = Project::findOrFail($request->p_id);
        //khushboo 12-05-25
        $isNonComplianceApplicable = false;
        if ($projectInfo->isComplianceApplicable == 1) {
            $isNonComplianceApplicable = true;
        }

        $otpProjectActivities = false;
        if ($projectInfo && $projectInfo->is_otp_required == 1) {
            $otpProjectActivities = true;
        }
        //khushboo 12-05-25
        $project_template_make_master = ProjectTemplate::where('project_id', $projectInfo->id)
            ->where('template_name_id', $request->mt_id)->first();
        if ($project_template_make_master) {
            $project_template_make_master->is_master = 1;
            $project_template_make_master->completion_type = "Percentage";
            $project_template_make_master->min_completion = 100;
            $project_template_make_master->save();
        }
        $other_project_templates = ProjectTemplate::where('project_id', $projectInfo->id)
            ->where('template_name_id', '!=', $request->mt_id)
            ->get();
        // foreach($other_project_templates as $other_project_template){
        //     $other_project_template->completion_type = 'Percentage';
        //     $other_project_template->min_completion = 100;
        //     $other_project_template->save();
        // }
        $projectMasterInfo = ProjectTemplate::with('getTemplate.getTemplateHeads')->where('project_id', $projectInfo->id)
            ->where('is_master', 1)->first();
        $projectTemplateNames = ProjectTemplate::where('project_id', $projectInfo->id)->with('getTemplate.getTemplateHeads')->get();
        return response()->json(['message' => "Success", 'projectInfo' => $projectInfo, 'projectTemplateNames' => $projectTemplateNames, 'projectMasterInfo' => $projectMasterInfo, 'isNonComplianceApplicable' => $isNonComplianceApplicable, 'otpProjectActivities' => $otpProjectActivities]);
    }

    public function master_template_row_info(Request $request)
    {
        $row_id_info = ProjectMasterTemplates::where('row_id', $request->row_id)
            ->first();
        if ($row_id_info) {
            return response()->json(['message' => true, 'row_info' => $row_id_info], 201);
        }
        return response()->json(['message' => false], 201);
    }

    public function add_project_template_data(Request $request)
    {
        $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
        if ($projectTemplateInfo) {
            $projectRowData = $request->except(['_token', 'project_template_id']);
            ksort($projectRowData);

            $is_template_master = $projectTemplateInfo->is_master;
            $get_header_id = "";
            $get_header_value = "";
            $json_Data = [];
            //for linking outlet with distributor
            $matchedMasterId = null;
            $masterTemplateValuesData = null;
            if ($projectTemplateInfo->is_master == 0) {
                $masterHeadId = $projectTemplateInfo->master_head_id;
                $masterData = ProjectTemplate::where('project_id', $projectTemplateInfo->project_id)->where('is_master', 1)->first();
                $masterTemplateValuesData = DB::table('project_template_name_values_new')
                    ->where('project_template_id', $masterData->id)
                    ->select('id', 'template_data_json')
                    ->get()
                    ->map(function ($row) use ($masterHeadId) {
                        $json = json_decode($row->template_data_json, true);
                        return [
                            'id' => $row->id,  // keep the row id
                            'value' => $json[$masterHeadId] ?? null // keep only matched key value
                        ];
                    });
            }
            //for linking outlet with distributor
            //            dd($projectRowData, $masterTemplateValuesData, $projectTemplateInfo->is_master);
            foreach ($projectRowData as $headId => $projectData) {

                if (empty($get_header_id) && !empty($projectData)) {
                    $get_header_id = $headId;
                    $get_header_value = $projectData;
                }
                $json_Data[$headId] = $projectData == null ? "" : $projectData;
                //link outlet with distributor
                $found = collect($masterTemplateValuesData)->firstWhere('value', $projectData);
                if ($found) {
                    $matchedMasterId = $found['id'];
                }
            }

            $projectTemplateNameValues = ProjectTemplateNameValuesNew::create([
                'project_template_id' => $projectTemplateInfo->id,
                'template_data_json' => json_encode($json_Data),
                'distributor_id' => $matchedMasterId ?? null
            ]);

            $max_row_id = $projectTemplateNameValues->id;
            $projectInfo = Project::find($projectTemplateInfo->project_id);
            $templateNameInfo = TemplateName::find($projectTemplateInfo->template_name_id);
            $companyId = $projectInfo->company_id;
            $zoneId = $projectInfo->zone_id;
            $unitId = $projectInfo->unit_id;
            $activityOrGroup = $projectTemplateInfo->activityType;
            $activity_group_name_id_or_activity_id = $projectTemplateInfo->activity_group_name_id_or_activity_id;
            $activityIdsArr = [];
            $activityGroupId = null;
            if ($activityOrGroup == 1) {
                $activityGroupId = $activity_group_name_id_or_activity_id;
                $group_activities = ActivityGroupPivot::where('activity_group_id', $activity_group_name_id_or_activity_id)->get();
                foreach ($group_activities as $group_activity) {
                    $activityIdsArr[] = $group_activity->activity_id;
                }
            } else {
                $activityGroupId = null;
                $activityIdsArr[] = $activity_group_name_id_or_activity_id;
            }

            foreach ($activityIdsArr as $activity) {
                if ($projectTemplateInfo->with_data == 1) {
                    $dataAssign = DataAssign::create([
                        'company_id' => $companyId,
                        'zone_id' => $zoneId,
                        'unit_id' => $unitId,
                        'project_id' => $projectInfo->id,
                        'activity_id' => $activity,
                        'template_name_id' => $templateNameInfo->id,
                        'project_template_id' => $projectTemplateInfo->id,
                        'activity_group_id' => $activityGroupId,
                        'template_name_head_id' => $get_header_id,
                        'is_outlet_assigned' => $is_template_master
                    ]);

                    $rowIds = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$get_header_id\"')) = ?", [trim($get_header_value)])
                        ->pluck('id')
                        ->toArray();

                    $this->assignUserActivityAndAuditRows(
                        $dataAssign->id,
                        auth()->user()->id,
                        $projectTemplateInfo->id,
                        $activity,
                        $activityGroupId,
                        $rowIds
                    );
                } else {
                    $getDataAssign = DB::table('data_assigns')->where('project_id', $projectInfo->id)
                        ->where('activity_id', $activity)
                        ->where('template_name_id', $templateNameInfo->id)
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->where('template_name_head_id', $get_header_id)
                        ->orderBy('id', 'desc')
                        ->first();

                    if (!empty($getDataAssign)) {

                        $getCommonId = DB::table('user_activity_data_assigns')->where('data_assign_id', $getDataAssign->id)
                            ->where('project_template_id', $projectTemplateInfo->id)
                            ->where('user_id', auth()->user()->id)
                            ->where('activity_id', $activity)
                            ->pluck('common_id')->toArray();

                        // add row ids on user auditor assign table
                        DB::table('user_audit_assigns')->insert([
                            'row_id' => $projectTemplateNameValues->id,
                            'common_id' => $getCommonId[0]
                        ]);
                    } else {

                        //if mapped with is outlet assigned
                        $master_project_template = ProjectTemplate::where('project_id', $projectInfo->id)
                            ->where('is_master', 1)->first();

                        $activityOrGroupMaster = $projectTemplateInfo->activityType;
                        $activity_group_name_id_or_activity_id_master = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                        $activityIdsArrMaster = [];
                        $activityGroupIdMaster = null;
                        if ($activityOrGroupMaster == 1) {
                            $activityGroupId = $activity_group_name_id_or_activity_id_master;
                            $group_activities = ActivityGroupPivot::where('activity_group_id', $activity_group_name_id_or_activity_id_master)->get();
                            foreach ($group_activities as $group_activity) {
                                $activityIdsArrMaster[] = $group_activity->activity_id;
                            }
                        } else {
                            $activityGroupId = null;
                            $activityIdsArrMaster[] = $activity_group_name_id_or_activity_id_master;
                        }

                        //get master data assign ids
                        $checkOutletAssignData = DB::table('data_assigns')->where('project_id', $projectInfo->id)
                            ->whereIn('activity_id', $activityIdsArrMaster)
                            ->where('template_name_id', $master_project_template->template_name_id)
                            ->where('project_template_id', $master_project_template->id)
                            //                            ->where('template_name_head_id', $get_header_id)
                            ->where('is_outlet_assigned', 1)
                            ->orderBy('id', 'desc')
                            ->get();

                        if (!$checkOutletAssignData->isEmpty()) {
                            $masterDataAssignIds = $checkOutletAssignData->pluck('id')->toArray();

                            //get child project template common ids linked with master data assign ids
                            $getCommonIds = DB::table('user_activity_data_assigns')
                                ->whereIn('data_assign_id', $masterDataAssignIds)
                                ->where('project_template_id', $projectTemplateInfo->id)
                                ->where('user_id', auth()->user()->id)
                                ->where('activity_id', $activity)
                                ->pluck('common_id')->toArray();

                            // add row ids on user auditor assign table
                            foreach ($getCommonIds as $commonId) {
                                DB::table('user_audit_assigns')->insert([
                                    'row_id' => $projectTemplateNameValues->id,
                                    'common_id' => $commonId
                                ]);
                            }
                        }
                    }
                }
            }

            $redirectUrl = route('user.project.row_id.activity', [
                'row_id' => $max_row_id,
                'activity' => $activityOrGroup == 0 ? $activity_group_name_id_or_activity_id : '',
                'group_info' => $activityOrGroup == 1 ? $activity_group_name_id_or_activity_id : '',
            ]);

            return response()->json(['message' => "success", 'row_id' => $max_row_id, 'redirectUrl' => $redirectUrl]);
        }
    }

    public function add_project_template_data_old_oct(Request $request)
    {
        $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
        if ($projectTemplateInfo) {
            $projectRowData = $request->except(['_token', 'project_template_id']);
            ksort($projectRowData);
            $max_row_id = null;
            //            $max_row_id = ProjectTemplateNameValue::max('id');
            //            $max_row_id += 1;
            $is_template_master = $projectTemplateInfo->is_master;
            $get_header_id = "";
            $get_header_value = "";
            $json_Data = [];
            foreach ($projectRowData as $headId => $projectData) {
                if (empty($get_header_id) && !empty($projectData)) {
                    $get_header_id = $headId;
                    $get_header_value = $projectData;
                }
                $json_Data[$headId] = $projectData;
                //                ProjectTemplateNameValue::create([
                //                    'row_id' => $max_row_id,
                //                    'project_template_id' => $projectTemplateInfo->id,
                //                    'template_name_head_id' => $headId,
                //                    'value' => $projectData
                //                ]);
            }

            $projectTemplateNameValues = ProjectTemplateNameValuesNew::create([
                'project_template_id' => $projectTemplateInfo->id,
                'template_data_json' => json_encode($json_Data)
            ]);

            $max_row_id = $projectTemplateNameValues->id;
            $projectInfo = Project::find($projectTemplateInfo->project_id);
            $templateNameInfo = TemplateName::find($projectTemplateInfo->template_name_id);
            $companyId = $projectInfo->company_id;
            $zoneId = $projectInfo->zone_id;
            $unitId = $projectInfo->unit_id;
            $activityOrGroup = $projectTemplateInfo->activityType;
            $activity_group_name_id_or_activity_id = $projectTemplateInfo->activity_group_name_id_or_activity_id;
            $activityIdsArr = [];
            $activityGroupId = null;
            if ($activityOrGroup == 1) {
                $activityGroupId = $activity_group_name_id_or_activity_id;
                $group_activities = ActivityGroupPivot::where('activity_group_id', $activity_group_name_id_or_activity_id)->get();
                foreach ($group_activities as $group_activity) {
                    $activityIdsArr[] = $group_activity->activity_id;
                }
            } else {
                $activityGroupId = null;
                $activityIdsArr[] = $activity_group_name_id_or_activity_id;
            }
            foreach ($activityIdsArr as $activity) {
                $dataAssign = DataAssign::create([
                    'company_id' => $companyId,
                    'zone_id' => $zoneId,
                    'unit_id' => $unitId,
                    'project_id' => $projectInfo->id,
                    'activity_id' => $activity,
                    'template_name_id' => $templateNameInfo->id,
                    'project_template_id' => $projectTemplateInfo->id,
                    'activity_group_id' => $activityGroupId,
                    'template_name_head_id' => $get_header_id,
                    'is_outlet_assigned' => $is_template_master
                ]);

                $rowIds = DB::table('project_template_name_values_new')
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$get_header_id\"')) = ?", [trim($get_header_value)])
                    ->pluck('id')
                    ->toArray();

                $this->assignUserActivityAndAuditRows(
                    $dataAssign->id,
                    auth()->user()->id,
                    $projectTemplateInfo->id,
                    $activity,
                    $activityGroupId,
                    $rowIds
                );

                //                $auditorAssigned = AuditorAssignedData::create([
                //                    'data_assign_id' => $dataAssign->id,
                //                    'project_id' => $projectInfo->id,
                //                    'template_name_id' => $templateNameInfo->id,
                //                    'user_id' => auth()->id(),
                //                    'template_name_head_value' => $get_header_value
                //                ]);
            }

            $redirectUrl = route('user.project.row_id.activity', [
                'row_id' => $max_row_id,
                'activity' => $activityOrGroup == 0 ? $activity_group_name_id_or_activity_id : '',
                'group_info' => $activityOrGroup == 1 ? $activity_group_name_id_or_activity_id : '',
            ]);

            return response()->json(['message' => "success", 'row_id' => $max_row_id, 'redirectUrl' => $redirectUrl]);
        }
    }

    private function assignUserActivityAndAuditRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds)
    {
        if (empty($rowIds)) return null;

        // 1. Check if common_id already exists for this user+template+activity
        $commonId = UserActivityDataAssign::where([
            'data_assign_id' => $dataAssignId,
            'user_id' => $userId,
            'activity_id' => $activityId,
            'project_template_id' => $templateId
        ])->value('common_id');

        if (!$commonId) {
            // 2. If not exists, create one
            $commonId = DB::table('user_activity_data_assigns')->max('common_id') + 1;

            $data = [
                'data_assign_id' => $dataAssignId,
                'user_id' => $userId,
                'project_template_id' => $templateId,
                'activity_id' => $activityId,
                'common_id' => $commonId
            ];

            if ($activityGroupId) {
                $data['activity_sequence_id'] = ActivityGroupPivot::where('activity_group_id', $activityGroupId)
                    ->where('activity_id', $activityId)
                    ->value('sequence');
            }

            UserActivityDataAssign::create($data);
        }

        // 3. Find already inserted row_ids
        $existingRowIds = UserAuditAssigns::where('common_id', $commonId)
            ->whereIn('row_id', $rowIds)
            ->pluck('row_id')
            ->toArray();

        // 4. Insert only missing rows under the SAME common_id
        $missingRowIds = array_diff($rowIds, $existingRowIds);

        foreach ($missingRowIds as $rowId) {
            UserAuditAssigns::create([
                'row_id' => $rowId,
                'common_id' => $commonId
            ]);
        }

        return $commonId;
    }


    //khushboo 31-03-2025
    public function get_agency_users(Request $request)
    {
        // dd(Auth::user()->id);
        try {

            $verifier_users = User::where('agency_user_id', $request->user_id)->whereHas('roles', function ($query) {
                $query->where('name', 'Verifier');
            })->get();

            $auditor_user_list = User::where('agency_user_id', $request->user_id)->whereHas('roles', function ($query) {
                $query->where('name', 'Auditor');
            })->get();

            // $html1 = '<option selected="" disabled="" value="" multiple="" > Choose...</option>';
            // if (!empty($verifier_users)) {

            //     foreach ($verifier_users as $val1) {
            //         $html1 .= "<option value=" . $val1->id . ">" . $val1->name . "</option>";
            //     }
            // }

            // $html2 = '<option selected="" disabled="" value="" multiple="" > Choose...</option>';
            // if (!empty($auditor_user_list)) {

            //     foreach ($auditor_user_list as $val1) {
            //         $html2 .= "<option value=" . $val1->id . ">" . $val1->name . "</option>";
            //     }
            // }

            // dd($request);
            // dd($request);
            $currentUser = User::find(Auth::user()->id);
            // dd('fgf');
            if ($currentUser->getRoleNames()->first() == 'Super Admin') {

                // // Step 1: Get all relevant agency_ids used in matching projects
                // $excludedAgencyIds = Project::where('is_agency_required', 1)
                //     ->whereHas('dataAssigns', function ($query) use ($currentUser, $request) {
                //         $query->where(function ($q) use ($request) {
                //             $q->where(function ($subQ) use ($request) {
                //                 $subQ->where('template_name_head_id', $request->template_name_head_id)
                //                     ->where('project_template_id', $request->project_template_id)
                //                     ->where('activity_group_id', $request->activity_group_name_id);
                //             })->orWhereIn('activity_id', $request->activity_id);
                //         })->where(function ($q2) use ($currentUser) {
                //             $q2->where('agency_id', $currentUser->agency_user_id)
                //                 ->orWhere('agency_id', $currentUser->id);
                //         });
                //     })
                //     ->with('dataAssigns')
                //     ->get()
                //     ->pluck('dataAssigns')
                //     ->filter() // ✅ this removes any null values
                //     ->flatten()
                //     ->pluck('agency_id')
                //     ->filter() // ✅ this removes null agency_id
                //     ->unique()
                //     ->values()
                //     ->toArray();
                $excludedAgencyIds = [];

                $filtered_verifier_list = collect($verifier_users)->filter(function ($user) use ($excludedAgencyIds) {
                    return !in_array($user->agency_user_id, $excludedAgencyIds) &&
                        !in_array($user->id, $excludedAgencyIds);
                });

                $filtered_auditor_list = collect($auditor_user_list)->filter(function ($user) use ($excludedAgencyIds) {
                    return !in_array($user->agency_user_id, $excludedAgencyIds) &&
                        !in_array($user->id, $excludedAgencyIds);
                });
            } else {

                $excludedAgencyIds = Project::where('is_agency_required', 1)
                    ->whereHas('dataAssigns', function ($query) use ($currentUser, $request) {
                        $query->where(function ($q) use ($currentUser, $request) {
                            $q->where(function ($subQ) use ($request) {
                                $subQ->where('template_name_head_id', $request->template_name_head_id)
                                    ->where('project_template_id', $request->project_template_id)
                                    ->where('activity_group_id', $request->activity_group_name_id);
                            })->orWhereIn('activity_id', $request->activity_id);
                        })->where(function ($q2) use ($currentUser) {
                            $q2->where('agency_id', $currentUser->agency_user_id)
                                ->orWhere('agency_id', $currentUser->id);
                        });
                    })
                    ->with('dataAssigns')
                    ->get()
                    ->pluck('dataAssigns')
                    ->flatten()
                    ->pluck('agency_id')
                    ->unique()
                    ->toArray();

                // Filter verifier list
                $filtered_verifier_list = collect($verifier_users)->filter(function ($user) use ($excludedAgencyIds) {
                    return !in_array($user->agency_user_id, $excludedAgencyIds);
                });

                // Filter auditor list
                $filtered_auditor_list = collect($auditor_user_list)->filter(function ($user) use ($excludedAgencyIds) {
                    return !in_array($user->agency_user_id, $excludedAgencyIds);
                });
            }


            // Filter verifier list
            $filtered_verifier_list = collect($verifier_users)->filter(function ($user) use ($excludedAgencyIds) {
                return !in_array($user->agency_user_id, $excludedAgencyIds);
            });

            // Filter auditor list
            $filtered_auditor_list = collect($auditor_user_list)->filter(function ($user) use ($excludedAgencyIds) {
                return !in_array($user->agency_user_id, $excludedAgencyIds);
            });

            $html1 = '';
            if ($filtered_verifier_list->isNotEmpty()) {
                $html1 = '<option selected disabled value="">Choose...</option>';
                foreach ($filtered_verifier_list as $val1) {
                    $html1 .= "<option value={$val1->id}>{$val1->name}</option>";
                }
            }

            $html2 = '';
            if ($filtered_auditor_list->isNotEmpty()) {
                $html2 = '<option selected disabled value="">Choose...</option>';
                foreach ($filtered_auditor_list as $val1) {
                    $html2 .= "<option value={$val1->id}>{$val1->name}</option>";
                }
            }

            return response()->json(['message' => true, 'verifier_users' => $html1, 'auditor_user_list' => $html2]);
        } catch (\Exception $e) {
            // echo $e->getMessage();
            return response()->json(['message' => false, 'error_message' => 'Somthing Went Wrong']);
        }
    }
    //khushboo 31-03-2025

    //khushboo 03-04-2025
    // public function verifier_exist_check(Request $request){
    //     $verifier_exist = false;
    //     $projectTemplate_info = ProjectTemplate::where('project_id', $request->project_id)
    //     ->where('template_name_id', $request->template_name_id)->first();
    //     $check_if_allocated = Verifier::where('project_template_name_id', $projectTemplate_info->id)->orderBy('id', 'desc')->get();
    //     if(!empty($check_if_allocated)){
    //         $verifier_exist = true;
    //     }
    //     return response()->json(['message' => true, 'verifier_exist' => $verifier_exist]);
    // }
    //khushboo 03-04-2025

    //khushboo 09-04-2025
    public function getProjectTemplate(Request $request)
    {
        $id = $request->projectId;
        //if project has atleast one master template
        $hasMaster = ProjectTemplate::where('project_id', $id)
            ->where('is_master', 1)
            ->exists();

        //if project has atleast one child template
        $hasNonMaster = ProjectTemplate::where('project_id', $id)
            ->where('is_master', 0)
            ->exists();

        $projectTemplateData = collect(); // empty collection by default

        if ($hasMaster && $hasNonMaster) {
            //project template data
            $projectTemplateData = ProjectTemplate::with('getTemplate')->where('project_id', $id)
                ->whereIn('is_master', [0])
                ->get();

            return response()->json(['status' => true, 'data' => $projectTemplateData]);
        } else {

            return response()->json(['status' => false, 'data' => [], 'message' => 'Data Does Not Exist for Given Project ID']);
        }
    }

    public function set_compliance()
    {
        $projects = Project::where('isComplianceApplicable', 1)->get();
        $activities = Activity::all();
        return view('masters.data_templates.set_compliance_new', compact('projects', 'activities'));
    }

    public function set_compliance_new()
    {
        $projects = Project::where('isComplianceApplicable', 1)->get();
        $activities = Activity::all();
        return view('masters.data_templates.set_compliance_new', compact('projects', 'activities'));
    }

    public function getQuestions(Request $request)
    {
        $id = $request->activity_id;
        $questionData = Question::where('activity_id', $id)
            ->where(function ($query) {
                $query->where('question_type', 'Yes / No')
                    ->orWhere('question_type', 'Dropdown')
                    ->orWhere('question_type', 'Subjective');
            })
            ->get();
        return response()->json(['status' => true, 'data' => $questionData]);
    }

    public function getQuestionsTypeData(Request $request)
    {
        $id = $request->questionId;
        $questionData = Question::find($id);
        $dropdowndata = [];
        if ($questionData->question_type == 'Yes / No') {
            $dropdowndata = ['Yes', 'No'];
        }
        if ($questionData->question_type == 'Dropdown') {
            $dropdowndata = QuestionDropdown::where('question_id', $id)->get();
        }
        if ($questionData->question_type == 'Subjective') {
            $dropdowndata = SubjectQuestion::where('question_id', $id)->get();
        }
        return response()->json(['status' => true, 'data' => $dropdowndata, 'question_type' => $questionData->question_type]);
    }

    public function getSUbjectQuestionsDropdown(Request $request)
    {
        $subjectId = $request->subjectId;
        $subjectDropdowns = SubjectDropdown::where('subject_id', $subjectId)->get();
        return response()->json(['status' => true, 'data' => $subjectDropdowns]);
    }

    public function storeComplianceData(Request $request)
    {
        try {

            $complianceData = $request->complianceData;
            // dd($complianceData);
            foreach ($complianceData as $data) {
                // dd($data);
                $templatevalue = null;
                $projectTemplate = ProjectTemplate::where('project_id', $data['project_id'])
                    ->where('template_name_id', $data['template_id'])->first();

                if (!empty($projectTemplate)) {
                    $projectTemplateNameValue = DB::table('project_template_name_values')
                        ->where('project_template_id', $projectTemplate->id)
                        ->where('template_name_head_id', $projectTemplate->own_reference_head_id)
                        ->first();

                    $templatevalue = !empty($projectTemplateNameValue) ? $projectTemplateNameValue->value : null;
                }

                // dd(ComplianceAnswerData::where('project_id', $data['project_id'])
                //     ->where('activity_id', $data['activity_id'])
                //     ->where('question_id', $data['question_id'])
                //     ->where('project_template_id', $data['template_id'])
                //     ->exists());

                if (ComplianceAnswerData::where('project_id', $data['project_id'])
                    ->where('activity_id', $data['activity_id'])
                    ->where('question_id', $data['question_id'])
                    ->where('project_template_id', $data['template_id'])
                    ->exists()
                ) {
                    ComplianceAnswerData::where('project_id', $data['project_id'])
                        ->where('activity_id', $data['activity_id'])
                        ->where('project_template_id', $data['template_id'])
                        ->where('question_id', $data['question_id'])
                        ->update([
                            'user_answer' => $data['compliance_value'],
                            'compliance_threshold' => $data['threshold_value'],
                            'threshold_type' => $data['threshold_type'],
                            'compliance_remark' => $data['remark'],
                            'template_name_value' => $templatevalue
                        ]);
                } else {

                    ComplianceAnswerData::create([
                        'project_id' => $data['project_id'],
                        'activity_id' => $data['activity_id'],
                        'question_id' => $data['question_id'],
                        'project_template_id' => $data['template_id'],
                        'user_answer' => $data['compliance_value'],
                        'compliance_threshold' => $data['threshold_value'],
                        'threshold_type' => $data['threshold_type'],
                        'compliance_remark' => $data['remark'],
                        'user_id' => Auth::user()->id,
                        'template_name_value' => $templatevalue
                    ]);
                }
            }

            return response()->json(['status' => true, 'message' => 'Data Added Successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => true, 'message' => $e->getMessage()]);
        }
    }

    public function getProjectActivities(Request $request)
    {
        try {
            $projectId = $request->id;
            $projectTemplateId = $request->template_id;

            //check if compliance data exist or not
            $dataCheck = [];
            $dataCheck = ComplianceAnswerData::with(['projectData', 'questionData', 'activityData', 'templateData'])
                ->where('project_id', $request->id)
                // ->where('project_template_id', $request->template_id)
                ->get();

            $projectTemplateData = ProjectTemplate::where('template_name_id', $projectTemplateId)
                ->where('project_id', $projectId)->get();

            // dd($projectTemplateData);
            $activities = [];
            $activityIds = [];

            if (empty($projectTemplateData)) {
                return response()->json(['status' => false, 'data' => [], 'message' => 'Project Template Data Does Not Exist']);
            }

            foreach ($projectTemplateData as $val) {
                // dd($val);
                if ($val->is_master == 0) {
                    if ($val->activityType == 0) {
                        if (!in_array($val->activity_group_name_id_or_activity_id, $activityIds)) {
                            $activityIds[] = $val->activity_group_name_id_or_activity_id;
                        }
                    }

                    if ($val->activityType == 1) {
                        $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $val->activity_group_name_id_or_activity_id)
                            ->pluck('activity_id')
                            ->toArray();

                        foreach ($groupActivityIds as $id) {
                            if (!in_array($id, $activityIds)) {
                                $activityIds[] = $id;
                            }
                        }
                    }
                }
            }

            $activities = Activity::whereIn('id', $activityIds)->get();
            // $activityCount = count($activities);

            if (!$activities->IsEmpty()) {
                return response()->json(['status' => true, 'data' => $activities, 'complianceData' => $dataCheck]);
            } else {
                return response()->json(
                    ['status' => false, 'data' => [], 'message' => 'No activities found for the selected project.']
                );
            }
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'data' => []]);
        }
    }

    public function nonComplaincePage()
    {
        $nonComplianceData = ComplianceAnswerData::all();
        $currentUser = User::find(Auth::user()->id);
        if ($currentUser->getRoleNames()->first() == 'Super Admin') {

            $projects = Project::where('isComplianceApplicable', 1)->get();;
        }
        if ($currentUser->getRoleNames()->first() == 'Company User') {

            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->where('isComplianceApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->get();
        }
        // $projects = Project::where('isComplianceApplicable', 1)->get();
        return view('masters.companies.non_compliance_data', compact('nonComplianceData', 'projects'));
    }

    public function nonComplainceData(Request $request)
    {

        $project = DB::table('projects')->where('id', $request->project_id)->first();

        $projectTemplate = DB::table('project_templates')->where('project_id', $request->project_id)
            ->where('is_master', 1)->first();

        $currentUser = User::find(Auth::user()->id);

        // dd($projectTemplate);
        $activityIds = [];
        $activityType = '';

        $project_templates_count = DB::table('project_templates')->where('project_id', $request->project_id)
            ->count();

        $project_template_main_headers = ProjectTemplateNameValuesNew::with([
            'getProjectTemplateData',
            'getProjectTemplateData.getProject.getUnit',
            'getProjectTemplateData.getProject.getZone',
            'getProjectTemplateData.getProject.getCompanyInfo'
        ])
            ->where('project_template_id', $projectTemplate->id)
            ->where('template_name_head_id', $projectTemplate->main_header)
            ->orderBy("id")
            // ->paginate(10);
            ->get();

        // dd($project);
        // Get all their row_ids
        // dd($project_template_main_headers);
        $rowIds = $project_template_main_headers->pluck('id')->toArray();

        //to check if company can verify the data or not
        $companyCanVerify = false;
        if ($project->companyVerificationRequired == 1) {
            $companyCanVerify = true;
        }

        //get child templates
        $childtemplateHeaders = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)->where('is_master', 0)->pluck('main_header')->toArray();

        $alltemplateHeaders = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)->pluck('main_header')->toArray();

        $TemplateNameIds = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)->pluck('template_name_id')->toArray();

        $childProjecttemplateNameIds = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)->where('is_master', 0)->pluck('id')->toArray();

        $ProjecttemplateNameIds = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)->pluck('id')->toArray();

        // // Get all their row_ids
        $templaterowIds = $project_template_main_headers->pluck('id');

        // dd($childtemplateHeaders);
        if ($currentUser->getRoleNames()->first() == 'Company User') {

            $projectDataAssignQuery = DB::table('data_assigns')->where('project_id', $project->id)
                ->where('company_user_id', $currentUser->id);
        } else {
            $projectDataAssignQuery = DB::table('data_assigns')->where('project_id', $project->id)
                ->where('company_id', $project->company_id)
                ->where('zone_id', $project->zone_id)
                ->where('unit_id', $project->unit_id)
                ->whereIn('template_name_id', $TemplateNameIds) //$childtemplateNameIds
                ->whereIn('template_name_head_id', $alltemplateHeaders) //$childtemplateHeaders
                ->whereIn('project_template_id', $ProjecttemplateNameIds); //$childProjecttemplateNameIds


            if ($projectTemplate->activityType == 0) {
                $projectDataAssignQuery->where('activity_id', $projectTemplate->activity_group_name_id_or_activity_id);
            } elseif ($projectTemplate->activityType == 1) {
                $projectDataAssignQuery->where('activity_group_id', $projectTemplate->activity_group_name_id_or_activity_id);
            }
        }


        $projectDataAssignDataIds = $projectDataAssignQuery->pluck('id')->toArray();
        // dd($projectDataAssignDataIds, $childtemplateHeaders);

        $childauditorAssignDataIds = AuditorAssignedData::with('get_user_info')
            ->whereIn('data_assign_id', $projectDataAssignDataIds)
            ->where('project_id', $projectTemplate->project_id)
            ->whereIn('template_name_id', $TemplateNameIds)
            ->distinct('user_id')
            ->pluck('user_id')->toArray();

        $childauditorAssignData = AuditorAssignedData::with(['get_user_info', 'dataAssign', 'templatedata'])
            ->whereIn('data_assign_id', $projectDataAssignDataIds)
            ->where('project_id', $projectTemplate->project_id)
            ->whereIn('template_name_id', $TemplateNameIds)
            ->distinct('user_id')
            ->get();

        // dd($childauditorAssignDataIds);
        $ParentprojectDataAssignQuery = DB::table('data_assigns')->where('project_id', $project->id)
            ->where('company_id', $project->company_id)
            ->where('zone_id', $project->zone_id)
            ->where('unit_id', $project->unit_id)
            ->where('template_name_id', $projectTemplate->template_name_id)
            ->where('template_name_head_id', $projectTemplate->main_header)
            ->where('project_template_id', $projectTemplate->id);

        $projectAssignedData = $projectDataAssignQuery->get();
        // dd($projectAssignedData);

        foreach ($projectAssignedData as $AssignedData) {
            if (!empty($AssignedData->activity_id)) {
                $activityIds[] = $AssignedData->activity_id;
                // $projectDataAssignQuery->where('activity_id', $projectTemplate->activity_group_name_id_or_activity_id);
            } elseif (!empty($AssignedData->activity_group_id)) {
                $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $AssignedData->activity_group_id)
                    ->pluck('activity_id')
                    ->toArray();

                foreach ($groupActivityIds as $id) {
                    if (!in_array($id, $activityIds)) {
                        $activityIds[] = $id;
                    }
                }
                // $projectDataAssignQuery->where('activity_group_id', $projectTemplate->activity_group_name_id_or_activity_id);
            }
        }

        $activities = Activity::whereIn('id', $activityIds)->select('id', 'activity_name')->get();

        $isOutletAssigned = $projectDataAssignQuery->where('is_outlet_assigned', 1)->first();
        // dd($isOutletAssigned);
        if (!empty($isOutletAssigned)) {

            $projectDataAssignDataAuditorIds = AuditorAssignedData::with('get_user_info')
                ->whereIn('data_assign_id', $projectDataAssignDataIds)
                ->where('project_id', $projectTemplate->project_id)
                ->whereIn('template_name_id', $TemplateNameIds)
                ->distinct('user_id')
                ->pluck('user_id')->toArray();

            // dd($projectDataAssignDataAuditorIds);

            //get Verifier Names
            $verifierNames = TempUserActivityAnswersData::with('getVerifier')
                ->whereIn('row_id', $templaterowIds)
                ->whereIn('activity_id', $activityIds)
                ->whereNotNull('verified_by')
                ->where('verified_by', '<>', 0)
                ->whereNotNull('remark')
                ->whereIn('user_id', $projectDataAssignDataAuditorIds)
                ->get()
                ->unique('user_id')  // Keep only one entry per user_id
                ->values();
        } else {

            //get Verifier Names
            $verifierNames = TempUserActivityAnswersData::with('getVerifier')
                ->whereIn('row_id', $templaterowIds)
                ->whereIn('activity_id', $activityIds)
                ->whereNotNull('verified_by')
                ->where('verified_by', '<>', 0)
                ->whereNotNull('remark')
                ->whereIn('user_id', $childauditorAssignDataIds)
                ->get()
                ->unique('user_id')  // Keep only one entry per user_id
                ->values();
        }

        $ParentprojectDataAssignDataIds = $ParentprojectDataAssignQuery->pluck('id')->toArray();

        $ParentauditorAssignData = AuditorAssignedData::with('get_user_info')
            ->whereIn('data_assign_id', $ParentprojectDataAssignDataIds)
            ->where('project_id', $projectTemplate->project_id)
            ->where('template_name_id', $projectTemplate->template_name_id)
            ->distinct('user_id')
            ->get();

        // dd($childauditorAssignDataIds, $childrowIds, $activityIds);


        // dd($existingRowIdsInAnswers);
        $complianceTemplateIds = ComplianceAnswerData::pluck('project_template_id')->toArray();

        // dd($activityIds, $auditorAssignDataIds, $rowIds);
        // Get the row_ids that exist in the answers table
        $existingRowIdsInAnswers = DB::table('temp_user_activity_answers_data')->whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('verified_by')
            ->whereNotNull('remark')
            // ->whereIn('user_id', $auditorAssignDataIds)
            ->pluck('row_id')
            ->toArray();


        // Step 1: Get audit start/end dates grouped by row_id
        $auditDates = DB::table('temp_user_activity_answers_data')->select(
            'row_id',
            DB::raw('MIN(created_at) as start_date'),
            DB::raw('MAX(created_at) as end_date')
        )
            ->whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('user_answer') // only submitted answers
            ->whereNotNull('remark')
            ->groupBy('row_id')
            ->get()
            ->keyBy('row_id');

        // dd($auditDates, $activityIds, $rowIds);

        $actionTakenType = $request->status;

        // dd($actionTakenType);
        $dataloadtype = $request->type;


        // dd($project_template_main_headers);
        $nonComplianceTemplateSUbHeaders = ProjectTemplate::whereIn('template_name_id', $complianceTemplateIds)
            ->where('is_master', 0)->pluck('sub_header')->toArray();

        $project_template_sub_headers = DB::table('project_template_name_values')
            ->where('project_template_id', $projectTemplate->id)
            ->where('template_name_head_id', $projectTemplate->sub_header)
            ->orderBy("row_id")
            ->get();

        // dd($project_template_sub_headers);
        $allComplianceRemarks = ComplianceAnswerData::all();
        // dd($project_template_sub_headers);

        // Attach remarks to each sub-header

        $childTemplateCount = DB::table('project_templates')->where('project_id', $request->project_id)->where('is_master', 0)->count();

        // dd($project_template_main_headers,$auditDates);
        $project_template_main_headers = $project_template_main_headers
            ->filter(function ($item) use ($auditDates, $actionTakenType) {
                // Ensure the item has a valid audit date
                if (!$auditDates->has($item->row_id)) {
                    return false;
                }

                // Action type filtering
                switch ($actionTakenType) {
                    case 'action':
                        return !empty($item->action_taken_type); // Keep items where action_taken_type is not empty
                    case 'pending':
                        return empty($item->action_taken_type); // Keep items where action_taken_type is empty
                    case 'all':
                        return true; // Keep all items regardless of action_taken_type
                    default:
                        return true;
                }
            })
            ->map(function ($item) use ($auditDates, $existingRowIdsInAnswers, $allComplianceRemarks, $childTemplateCount) {
                $rowId = $item->row_id;
                $startDate = $auditDates[$rowId]->start_date;
                $endDate = $auditDates[$rowId]->end_date;

                $item->start_date = $startDate;
                $item->end_date = $endDate;

                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                $item->duration_days = $start->diffInDays($end);
                $item->duration_human = $start->diffForHumans($end, true);

                $item->answer_exist = in_array($rowId, $existingRowIdsInAnswers);

                $template = DB::table('project_templates')->where('id', $item->project_template_id)->first();

                return $item;
            });


        // Add pagination manually (since it's a Collection, not a query builder)
        if ($dataloadtype == 'data') {
            $page = $request->page ?? 1;
            $perPage = 10; // Set your desired per-page limit
            $paginated = new LengthAwarePaginator(
                $project_template_main_headers->forPage($page, $perPage),
                $project_template_main_headers->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        // dd($isOutletAssigned->is_outlet_assigned);
        if (!empty($isOutletAssigned)) {
            // dd($project_template_sub_headers);
            $project_template_sub_headers = $project_template_sub_headers->map(function ($subHeader) use ($allComplianceRemarks, $childTemplateCount, $project, $activityIds) {
                // $template = $subHeader->getProjectTemplate;
                $template = DB::table('project_templates')->where('id', $subHeader->project_template_id)->first();
                // dd($subHeader);
                $associatedActivityIds = [];
                $childTemplatesData = DB::table('project_templates')->where('project_id', $template->project_id)->where('is_master', 0)->get();
                $childTempalteIds = DB::table('project_templates')->where('project_id', $template->project_id)->where('is_master', 0)->pluck('id')->toArray();

                foreach ($childTemplatesData as $child_Data) {
                    if ($child_Data->activityType == 0) {
                        $associatedActivityIds[] = $child_Data->activity_group_name_id_or_activity_id;
                    } elseif ($child_Data->activityType == 1) {
                        $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $child_Data->activity_group_name_id_or_activity_id)
                            ->pluck('activity_id')
                            ->toArray();

                        $associatedActivityIds = array_merge($associatedActivityIds, $groupActivityIds);
                    }
                }

                // dd($associatedActivityIds);
                // Get related remarks and join them
                $remarks = $allComplianceRemarks
                    ->whereIn('activity_id', $associatedActivityIds)
                    ->where('project_id', $project->id);

                //to check for each threshold for a given $remarks data
                $templateValues = collect();
                // dd($remarks);
                $validRemarks = $remarks->filter(function ($remark) use ($childTemplateCount, &$templateValues) {
                    $threshold = $remark->compliance_threshold;
                    $thresholdType = $remark->threshold_type;
                    // dd($remark->template_name_value);
                    // dd($remark,$thresholdType);

                    if ($thresholdType == 'number') {
                        if ($threshold == 0) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                        if ($threshold > $childTemplateCount || $threshold == $childTemplateCount) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                    } elseif ($thresholdType == 'percent') {
                        $thresholdValue = ($threshold / 100) * $childTemplateCount;
                        // dd($thresholdValue);
                        if ($thresholdValue == 0) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                        if ($thresholdValue > $childTemplateCount || $thresholdValue == $childTemplateCount) {
                            // dd($childTemplateCount);
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                    }

                    return false;
                });

                $remarksString = $validRemarks
                    ->pluck('compliance_remark')
                    ->filter()
                    ->unique()
                    ->implode(', ');


                // dd( $templateValues, $subHeader->value, $templateValues->contains($subHeader->value));

                // if ($templateValues->contains($subHeader->value)) {
                //     $subHeader->compliance_remarks = $remarksString;
                //     return $subHeader;
                // }
                if (!$validRemarks->IsEmpty()) {
                    $subHeader->compliance_remarks = $remarksString;
                    return $subHeader;
                }
            });
        } else {

            $project_template_sub_headers = $project_template_sub_headers->map(function ($subHeader) use ($allComplianceRemarks, $childTemplateCount, $project) {
                // $template = $subHeader->getProjectTemplate;
                $template = DB::table('project_templates')->where('id', $subHeader->project_template_id)->first();
                $childTemplatesData = DB::table('project_templates')->where('project_id', $template->project_id)->where('is_master', 0)->get();
                $associatedActivityIds = [];

                // if ($template->activityType == 0) {
                //     $associatedActivityIds[] = $template->activity_group_name_id_or_activity_id;
                // } elseif ($template->activityType == 1) {
                //     $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $template->activity_group_name_id_or_activity_id)
                //         ->pluck('activity_id')
                //         ->toArray();

                //     $associatedActivityIds = array_merge($associatedActivityIds, $groupActivityIds);
                // }
                foreach ($childTemplatesData as $child_Data) {
                    if ($child_Data->activityType == 0) {
                        $associatedActivityIds[] = $child_Data->activity_group_name_id_or_activity_id;
                    } elseif ($child_Data->activityType == 1) {
                        $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $child_Data->activity_group_name_id_or_activity_id)
                            ->pluck('activity_id')
                            ->toArray();

                        $associatedActivityIds = array_merge($associatedActivityIds, $groupActivityIds);
                    }
                }

                // Get related remarks and join them
                // dd($associatedActivityIds, $project->id);
                $remarks = $allComplianceRemarks
                    ->whereIn('activity_id', $associatedActivityIds)
                    ->where('project_id', $project->id);
                // dd($project->id, $template->activityType);
                //to check for each threshold for a given $remarks data
                $templateValues = collect();
                $validRemarks = $remarks->filter(function ($remark) use ($childTemplateCount, &$templateValues) {
                    $threshold = $remark->compliance_threshold;
                    $thresholdType = $remark->threshold_type;
                    // dd($remark->template_name_value);

                    if ($thresholdType == 'number') {
                        if ($threshold == 0) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                        if ($threshold > $childTemplateCount || $threshold == $childTemplateCount) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                    } elseif ($thresholdType == 'percent') {
                        $thresholdValue = ($threshold / 100) * $childTemplateCount;
                        // dd($thresholdValue);
                        if ($thresholdValue == 0) {
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                        if ($thresholdValue > $childTemplateCount || $thresholdValue == $childTemplateCount) {
                            // dd($childTemplateCount);
                            $templateValues->push($remark->template_name_value);
                            return true;
                        }
                    }

                    return false;
                });

                $remarksString = $validRemarks
                    ->pluck('compliance_remark')
                    ->filter()
                    ->unique()
                    ->implode(', ');

                // dd($subHeader);
                // dd($templateValues, $subHeader->value, $templateValues->contains($subHeader->value));
                // if ($templateValues->contains($subHeader->value)) {
                if (!$validRemarks->IsEmpty()) {
                    $subHeader->compliance_remarks = $remarksString;
                    return $subHeader;
                }
                // if ($templateValues->contains($subHeader->value)) {
                //     $subHeader->compliance_remarks = $remarksString;
                //     return $subHeader;
                // } else {
                //     $subHeader->compliance_remarks = '';
                // }
                // $subHeader->compliance_remarks = $remarksString;

            });
        }


        $agencyUserIds = User::role('agency')->get();
        // dd($nonComplianceTemplateSUbHeaders, $project_template_sub_headers);

        //action to be taken check
        $actiontobetakendata = [];

        $projectMatchedCount = 0;
        if ($currentUser->getRoleNames()->first() == 'Company User') {

            $projectsTypeIds = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isComplianceApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->pluck('project_type_id')->toArray();

            $projectsCompanyIds = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isComplianceApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->pluck('company_id')->toArray();

            // otherproject with same project_type_id exist or not
            $checkifSameTypeExist = Project::with('getProjectTemplates')
                ->whereIn('project_type_id', $projectsTypeIds)
                ->whereIn('company_id', $projectsCompanyIds)
                ->exists();

            $checkifSameTypeData = Project::with('getProjectTemplates')
                ->whereIn('project_type_id', $projectsTypeIds)
                ->get();

            // Check if start and end dates also match
            $matchedProjects = $checkifSameTypeData->filter(function ($projectdata) use ($project) {
                return $projectdata->complianceRepeationStartDate == $project->complianceRepeationStartDate &&
                    $projectdata->complianceRepeationEndDate == $project->complianceRepeationEndDate
                    && $project->id != $projectdata->id;
            });

            if ($checkifSameTypeExist) {

                $currentComplianceColumnId = $projectTemplate->compliance_column_id;
                $currentProjectId = $project->id;
                $currentCompanyId = $project->company_id;
                $currentStartDate = $project->complianceRepeationStartDate;
                $currentEndDate = $project->complianceRepeationEndDate;

                // Get current values
                $currentValues = DB::table('project_template_name_values')->where('project_template_id', $projectTemplate->id)
                    ->where('template_name_head_id', $currentComplianceColumnId)
                    ->pluck('value')
                    ->toArray();

                $hasAnyMatch = false;

                // Go through all same-type projects
                foreach ($checkifSameTypeData as $otherProject) {
                    if (
                        $otherProject->id != $currentProjectId &&
                        $otherProject->company_id == $currentCompanyId &&
                        $otherProject->complianceRepeationStartDate == $currentStartDate &&
                        $otherProject->complianceRepeationEndDate == $currentEndDate
                    ) {

                        // Step 2: Identify master template in other project
                        $otherMasterTemplate = $otherProject->getProjectTemplates->firstWhere('is_master', 1);
                        if (!$otherMasterTemplate || empty($otherMasterTemplate->compliance_column_id)) {
                            continue;
                        }

                        $otherValues = DB::table('project_template_name_values')
                            ->where('project_template_id', $otherMasterTemplate->id)
                            ->where('template_name_head_id', $otherMasterTemplate->compliance_column_id)
                            ->pluck('value')
                            ->toArray();

                        if (empty($otherValues)) continue;

                        // Step 3: Compare current and other master values
                        $intersect = array_intersect($currentValues, $otherValues);

                        if (!empty($intersect)) {
                            $projectMatchedCount++;

                            $hasAnyMatch = true;
                            $valuesFullyMatch = empty(array_diff($currentValues, $otherValues)) &&
                                empty(array_diff($otherValues, $currentValues));

                            $actiontobetakendata[] = [
                                'matched_project_id' => $otherProject->id,
                                'template_id' => $otherMasterTemplate->id,
                                'shared_values' => $intersect,
                                'fully_match' => $valuesFullyMatch
                            ];
                        }
                    }
                }


                if (!$hasAnyMatch) {
                    // dd('No match found across templates with same compliance_column_id in matched projects.');
                }
            }
        }
        $currentProjectCount = 1;
        $totalProjectCount = $currentProjectCount + $projectMatchedCount;

        if ($dataloadtype === 'data') {
            // dd($paginatedCombined);
            return response()->json([
                'message' => 'success',
                'projectTemplate' => $projectTemplate,
                'project_templates_count' => $project_templates_count,
                'sub_headers' => $project_template_sub_headers,
                // 'main_headers' => $project_template_main_headers,
                'activityType' => $activityType,
                'activities' => $activities,
                'companyCanVerify' => $companyCanVerify,
                'ParentauditorAssignDataIds' => $ParentauditorAssignData,
                'childauditorAssignDataIds' => $childauditorAssignData,
                'verifierNames' => $verifierNames,
                'agencyUserIds' => $agencyUserIds,
                'actiontobetakendata' => $actiontobetakendata,
                'projectMatchedCount' => $totalProjectCount,
                'paginated' => $paginated
            ]);
        } else {

            $rowData = $this->prepareNonComplianceExportData(
                $project_template_main_headers,
                $project_template_sub_headers,
                $childauditorAssignData,
                $verifierNames,
                $agencyUserIds,
                $totalProjectCount
            );

            // dd($rowData);
            $fileName = 'non_compliance_report_' . time() . '.xlsx';
            $publicPath = public_path('ComplianceExport/' . $fileName);

            // Ensure the assets directory exists
            if (!File::exists(public_path('ComplianceExport'))) {
                File::makeDirectory(public_path('ComplianceExport'), 0755, true);
            }

            // Store the Excel file in public/assets
            Excel::store(
                new NonComplianceExport($rowData->toArray()),
                'ComplianceExport/' . $fileName,
                'public'
            );

            return response()->json([
                'status' => true,
                'path' => asset('ComplianceExport/' . $fileName)
            ]);
        }
    }

    public function prepareNonComplianceExportData($mainHeaders, $subHeaders, $childAuditors, $verifiers, $agencyUserIds, $projectMatchedCount)
    {
        $rowData = [];
        $count = 0;
        // dd($projectMatchedCount);
        // Determine action to be taken text
        $actionToBeTakenText = '';
        if ($projectMatchedCount == 1) {
            $actionToBeTakenText = 'A Warning Letter with 25% of Previous One Month Claim to be Debited';
        } elseif ($projectMatchedCount == 2) {
            $actionToBeTakenText = '50% of Previous One Month Claim to be Debited';
        } elseif ($projectMatchedCount >= 3) {
            $actionToBeTakenText = 'No Further Scheme till Distributor Clear the Audit';
        }

        foreach ($mainHeaders as $index => $mainHeaderInfo) {

            $subHeaderInfo = $subHeaders[$index] ?? null;
            // dd($subHeaderInfo);
            $templateNameToCheck = $subHeaderInfo->value ?? '';

            // Auditor name for this row (single match)
            $matchedItem = collect($childAuditors)->firstWhere('templatedata.template_name', $templateNameToCheck);
            $auditorName = $matchedItem['get_user_info']['name'] ?? '';

            // All auditor names
            $auditorNames = collect($childAuditors)->unique('get_user_info.id')->pluck('get_user_info.name')->implode(', ');

            // Matched agency user (audit firm)
            $matchedUser = collect($agencyUserIds)->firstWhere('id', $matchedItem['get_user_info']['agency_user_id'] ?? null);
            $matchedUserName = $matchedUser['name'] ?? '';

            // Verifier names
            $verifierNames = collect($verifiers)->unique('getVerifier.id')->pluck('getVerifier.name')->implode(', ');

            // Audit period
            $durationDays = $mainHeaderInfo['duration_days'] ?? 0;
            $durationHuman = $mainHeaderInfo['duration_human'] ?? '';
            $auditPeriod = '';
            if ($durationDays) {
                $auditPeriod .= $durationDays . ' days';
            }
            if ($durationHuman) {
                $auditPeriod .= ($durationDays ? ' and ' : '') . $durationHuman;
            }

            // Document info
            $documentLink = $mainHeaderInfo['complianceDocument'] ?? '';

            $rowIndex = ++$count;
            // dd($mainHeaderInfo->get_project_template);

            $rowData[] = [
                $rowIndex,
                $mainHeaderInfo->getProjectTemplate->getProject->getCompanyInfo->company_name ?? '',
                $mainHeaderInfo->getProjectTemplate->getProject->getZone->zone_name ?? '',
                $mainHeaderInfo->getProjectTemplate->getProject->getUnit->unit_name ?? '',
                ($mainHeaderInfo->value ?? '') . ' - ' . ($subHeaderInfo->value ?? ''),
                $matchedUserName,
                $auditorNames,
                $verifierNames,
                $auditPeriod,
                $subHeaderInfo->compliance_remarks ?? '',
                ($mainHeaderInfo->action_taken_type ?? '') == "Action Taken" ? 'yes' : ($mainHeaderInfo->action_taken_type ?? ''),
                $actionToBeTakenText,
                $mainHeaderInfo->action_remark ?? '',
                $mainHeaderInfo->action_taken_amount ?? '',
                $mainHeaderInfo->compliance_document_number ?? '',
                $documentLink ? 'View Document' : '',
                $mainHeaderInfo->action_taken_type ? 'Action Taken' : 'Pending',
            ];
        }

        return collect($rowData);
    }


    public function complianceActionTakenPage()
    {

        $nonComplianceData = ComplianceAnswerData::all();

        $currentUser = User::find(Auth::user()->id);
        if ($currentUser->getRoleNames()->first() == 'Super Admin') {
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])->where('isComplianceApplicable', 1)->get();
        }
        if ($currentUser->getRoleNames()->first() == 'Company User') {
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isComplianceApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->get();
        }
        // dd($projects);

        return view('masters.data_templates.compliance_action_taken', compact('projects'));
    }

    public function checkAndCreateDirectory($directory)
    {
        $publicPath = public_path($directory);
        // Check if the directory doesn't exist
        if (!File::exists($publicPath)) {
            // Create the directory
            File::makeDirectory($publicPath, 0755, true, true);
        }
    }

    public function addActionTaken(Request $request)
    {

        try {

            // dd($request);
            $findTemplateROwDataExist = ProjectTemplateNameValuesNew::where('project_template_id', $request->project_template_id)
                ->where('id', $request->row_id)
                ->where('template_name_head_id', $request->template_name_head_id)
                ->where('value', $request->template_value)
                ->exists();

            $findTemplateROwData = ProjectTemplateNameValuesNew::where('project_template_id', $request->project_template_id)
                ->where('id', $request->row_id)
                ->where('template_name_head_id', $request->template_name_head_id)
                ->where('value', $request->template_value)
                ->first();

            if ($findTemplateROwDataExist) {
                $complianceDocument = '';
                // dd($findTemplateROwData);
                if ($request->hasFile('complianceDocument')) {
                    $file = $request->file('complianceDocument');
                    // dd($file->getClientOriginalExtension());
                    if ($file->getClientOriginalExtension() == 'pdf') {

                        $file_name = uniqid() . '.' . $file->getClientOriginalExtension();
                        $baseDirectory = 'complianceImages/';
                        $this->checkAndCreateDirectory($baseDirectory);
                        $full_file_path = $baseDirectory . $file_name;
                        $file->move(public_path($baseDirectory), $file_name);
                        $complianceDocument = $full_file_path;

                        ProjectTemplateNameValuesNew::where('id', $findTemplateROwData->id)->update([
                            'action_taken_type' => $request->actionTaken,
                            'complianceDocument' => $complianceDocument,
                            'action_taken_amount' => $request->action_taken_amount,
                            'compliance_document_number' => $request->compliance_document_number,
                            'action_remark' => $request->action_remark,
                        ]);

                        return response()->json(["status" => true, "message" => "Action Data Added Successfully"]);
                    } else {
                        return response()->json(["status" => false, "message" => "Invalid Document"]);
                    }
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            return response()->json(["status" => false, "message" => "Something Went Wrong!"]);
        }
    }

    //khushboo 09-04-2025

    //khushboo 13-05-2025
    public function exportProjectData()
    {
        return Excel::download(new ProjectsExport(), 'projects.xlsx');
    }


    public function exportProjectTemplateData($projectTemplateID)
    {
        $projectTemplate = ProjectTemplate::with([
            'getProject',
            'getTemplate.getTemplateHeads',
        ])->findOrFail($projectTemplateID);

        // Get all template head names (dynamic headings)
        $templateHeads = $projectTemplate->getTemplate->getTemplateHeads;
        $templateHeadsCount = $templateHeads->count();

        // $headings = ['S No'];
        foreach ($templateHeads as $head) {
            $headings[] = $head->template_head_name;
        }

        // Fetch all template values
        $templateValues = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplateID)
            ->orderBy('id')
            ->get();

        // Group values by row_id
        $grouped = $templateValues->groupBy('row_id');

        $data = [];
        $counter = 0;

        foreach ($grouped as $rowId => $values) {
            $row = [];
            // $row[] = ++$counter; // SNo

            foreach ($values as $val) {
                $row[] = $val->value;
            }

            $data[] = $row;
        }

        foreach ($templateValues as $ind => $tempdata) {
            $json_data = json_decode($tempdata->template_data_json);
            $row = [];
            foreach ($json_data as $key => $value) {
                $row[] = $value;
                //                foreach ($values as $val) {
                //                    $row[] = $val->value;
                //                }
            }
            $data[] = $row;
        }

        $fileName = 'project-template-data-' . now()->format('Y-m-d_H-i') . '.xlsx';

        return Excel::download(new DynamicTableExport($data, $headings), 'project_template_data.xlsx');
    }


    public function getNewProjectTemplateValues(Request $request)

    {
        $projectTemplateId = 2;

        // Get meta: main and sub header IDs
        $projectTemplateMeta = DB::table('project_templates')
            ->where('id', $projectTemplateId)
            ->select('main_header', 'sub_header')
            ->first();

        $mainHeaderName = null;
        $subHeaderName = null;
        $mainHeaderId = null;
        $subHeaderId = null;

        if ($projectTemplateMeta) {
            $mainHeaderId = $projectTemplateMeta->main_header;
            $subHeaderId = $projectTemplateMeta->sub_header;

            $mainHeaderName = DB::table('template_name_heads')->where('id', $mainHeaderId)->value('template_head_name');
            $subHeaderName = DB::table('template_name_heads')->where('id', $subHeaderId)->value('template_head_name');
        }

        // Get sample row to extract dynamic head IDs
        $firstRow = DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplateId)
            ->select('template_data_json')
            ->first();

        if (!$firstRow) {
            return view('project_template_values_new.index', [
                'data' => collect(),
                'headMappings' => [],
                'mainHeaderName' => $mainHeaderName,
                'subHeaderName' => $subHeaderName,
            ]);
        }

        $headIds = array_keys(json_decode($firstRow->template_data_json, true));

        // Head name mappings
        $headMappings = DB::table('template_name_heads')
            ->whereIn('id', $headIds)
            ->pluck('template_head_name', 'id')
            ->toArray();

        // Prepare SELECT clause
        $selects = ['row_id'];
        foreach ($headIds as $headId) {
            $selects[] = DB::raw('JSON_UNQUOTE(JSON_EXTRACT(template_data_json, "$.\"' . $headId . '\"")) as head_' . $headId);
        }

        // Also select main/sub header values
        if ($mainHeaderId && in_array($mainHeaderId, $headIds)) {
            $selects[] = DB::raw('JSON_UNQUOTE(JSON_EXTRACT(template_data_json, "$.\"' . $mainHeaderId . '\"")) as main_header_value');
        }

        if ($subHeaderId && in_array($subHeaderId, $headIds)) {
            $selects[] = DB::raw('JSON_UNQUOTE(JSON_EXTRACT(template_data_json, "$.\"' . $subHeaderId . '\"")) as sub_header_value');
        }

        // Base query
        $query = DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplateId)
            ->select($selects);

        // Search by value
        $values = null;

        if ($request->filled('search_value')) {
            $value = $request->input('search_value');
            $query->whereRaw('JSON_SEARCH(template_data_json, "one", ?) IS NOT NULL', [$value]);

            //            // Split by comma if multiple values
            $values = array_filter(array_map('trim', explode(',', $value)));
            //
            //            if(count($values) > 0){
            //                $query->where(function ($q) use ($values) {
            //                    foreach ($values as $value) {
            //                        $q->orWhereRaw('JSON_SEARCH(template_data_json, "one", ?) IS NOT NULL', [$value]);
            //                    }
            //                });
            //            }else{
            //                $query->whereRaw('JSON_SEARCH(template_data_json, "one", ?) IS NOT NULL', [$value]);
            //            }

        }

        // Sorting
        //        if ($request->filled('sort_by') && in_array($request->input('sort_by'), $headIds)) {
        //            $query->orderBy(
        //                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(template_data_json, "$.\"' . $request->input('sort_by') . '\""))'),
        //                $request->input('sort_order', 'asc')
        //            );
        //        }

        $data = $query->paginate(100);

        //        dd($mainHeaderName, $subHeaderName);
        // AJAX response
        if ($request->ajax()) {
            return response()->json([
                'data' => $data->items(),
                'headIds' => $headIds,
                'headMappings' => $headMappings,
                'mainHeaderName' => $mainHeaderName,
                'subHeaderName' => $subHeaderName,
                'mainHeaderId' => $mainHeaderId,
                'subHeaderId' => $subHeaderId,
                'values' => $values,
                'pagination' => (string)$data->appends($request->query())->links()
            ]);
        }

        return view('masters.data_templates.project_template_new', compact(
            'data',
            'headIds',
            'headMappings',
            'mainHeaderName',
            'subHeaderName',
            'mainHeaderId',
            'subHeaderId'
        ));
    }


    //khushboo 13-05-2025

}
