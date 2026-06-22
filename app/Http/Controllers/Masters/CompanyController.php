<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityGroupPivot;
use App\Models\AuditorAssignedData;
use App\Models\Company;
use App\Models\CompanyType;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\Question;
use App\Models\TemplateName;
use App\Models\TemplateNameHead;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function index()
    {
        $companies = Company::latest()->paginate(10);
        return view('masters.companies.index', compact('companies'));
    }

    public function create()
    {
        $company_types = CompanyType::all();
        return view('masters.companies.add', compact('company_types'));
    }

    public function store(Request $request)
    {
        $formFields = $request->validate([
            'company_name' => [
                'required',
                function ($attribute, $value, $fail) use ($request) {
                    $exists = Company::where('company_name', $value)
                        ->where('company_type', $request->input('company_type'))->first();
                    if ($exists) {
                        $fail('The combination of company name and type already exists.');
                    }
                }
            ],
            'company_type' => ['required'],
            'company_code' => ['required', 'string', 'max:6', 'unique:companies'],
            'address' => 'required',
            'city' => ['required'],
            'district' => ['nullable'],
            'state' => ['nullable'],
            'image' => 'nullable|file|mimes:jpeg,png,pdf,jpg|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $company_image = $request->file('image');
            // Generate a unique filename
            $company_image_name = uniqid() . '.' . $company_image->getClientOriginalExtension();
            // Move the uploaded file to the public directory under 'assets/images'
            $company_image->move(public_path('assets/images/company_images'), $company_image_name);
            $formFields['image'] = $company_image_name;
        }
        Company::create($formFields);
        return redirect(route('companies.list'))->with('message', "New Company created successfully");
    }

    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'company_name' => ['required'],
            'company_type' => ['required'],
            'company_code' => ['required', 'string', 'max:6', Rule::unique('companies')->ignore($id)],
            'address' => 'required',
            'city' => ['required'],
            'district' => ['nullable'],
            'state' => ['nullable'],
            'image' => 'nullable|file|mimes:jpeg,png,pdf,jpg|max:2048',
        ]);
        $company = Company::find($id);
        if ($request->hasFile('image')) {
            if ($company->image) {
                $previousImagePath = public_path('assets/images/company_images/' . $company->image);
                if (File::exists($previousImagePath)) {
                    File::delete($previousImagePath);
                }
            }
            if ($request->hasFile('image')) {
                $company_image = $request->file('image');
                // Generate a unique filename
                $company_image_name = uniqid() . '.' . $company_image->getClientOriginalExtension();
                // Move the uploaded file to the public directory under 'assets/images'
                $company_image->move(public_path('assets/images/company_images'), $company_image_name);
                $formFields['image'] = $company_image_name;
            }
        }
        $company->update($formFields);
        return redirect(route('companies.list'))->with('message', 'Company Updated successfully');
    }

    public function edit($id)
    {
        $company = Company::find($id);
        $company_types = CompanyType::all();
        return view('masters.companies.edit_company', compact('company', 'company_types'));
    }

    public function destroy(Request $request)
    {
        $company = Company::findOrFail($request->id);
        if ($company->image) {
            $previousImagePath = public_path('assets/images/company_images/' . $company->image);
            if (File::exists($previousImagePath)) {
                File::delete($previousImagePath);
            }
        }
        $company->delete();
        return "Success";
    }

    //khushboo 18-04-25

    public function companyVerificationPage()
    {
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if ($currentUser->getRoleNames()->first() == 'Super Admin') {
            $companies = Company::all();
            $projects = [];

            return view('masters.companies.company_verification_page', compact('companies', 'currentUser', 'projects', 'currentUserRole'));
        }
        if ($currentUser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentUser) {
                    $query->where('company_user_id', $currentUser->id);
                })
                ->get();

            return view('masters.companies.company_verification_page', compact('companies', 'currentUser', 'projects', 'currentUserRole'));
        }

    }

    public function getCompanyZoneData(Request $request)
    {
        try {
            $companyId = $request->companyId;
            // dd($companyId);
            // $zoneData = Zone::where('company_id', $companyId)->get();
            $projectData = Project::where('company_id', $companyId)->get();
            return response()->json(['status' => true, 'data' => $projectData, 'message' => 'Zone Data Fetch Successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Zone Data Fetch Error']);
        }
    }


    public function projectTemplateDataOld(Request $request)
    {
        try {
            $id = $request->projectId;
            $projectData = Project::with(['getProjectTemplates.getTemplate', 'getCompanyInfo'])
                ->where('id', $id)->first();

            $templateRoutes = [];
            $userAssignedActivities = [];
            $project_master_template = ProjectTemplate::where('project_id', $projectData->id)
                ->where('is_master', 1)
                ->first();
            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')->where('project_id', $projectData->id)
                ->where('id', '!=', $project_master_template->id)
                ->get();

            $distinct_data_assignIds = AuditorAssignedData::where('project_id', $projectData->id)
                ->distinct('data_assign_id')
                ->pluck('data_assign_id');
            $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')->whereIn("id", $distinct_data_assignIds)
                ->get();
            foreach ($data_assign_info as $assigned_data) {
                $sequence = null;
                // dd($assigned_data);
                if (isset($assigned_data->activity_group_id)) {
                    $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                        ->where('activity_group_id', $assigned_data->activity_group_id)
                        ->first();
                    $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : '';
                }
                $group_name = $assigned_data->getActivityGroup ? $assigned_data->getActivityGroup->activity_group_name : null;
                $is_master_temp = $assigned_data->getProjectTemplate->is_master ? $assigned_data->getProjectTemplate->is_master : 0;
                $current_template_name = $assigned_data->templateName->template_name;
                $current_activity_name = $assigned_data->activityName->activity_name;
                $exists = false;
                foreach ($userAssignedActivities as $activity) {
                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                        $exists = true;
                        break; // Exit loop if a match is found
                    }
                }
                if (!$exists) {
                    $userAssignedActivities[] = [
                        'template_name_id' => $assigned_data->template_name_id,
                        'template_name' => $assigned_data->templateName->template_name,
                        'is_master' => $is_master_temp,
                        'activity_id' => $assigned_data->activity_id,
                        'activity_name' => $assigned_data->activityName->activity_name,
                        'group_id' => $assigned_data->activity_group_id,
                        'group_name' => $group_name,
                        'sequence' => $sequence,
                    ];
                }
                if ($assigned_data->is_outlet_assigned) {
                    foreach ($project_other_templates as $other_project_template_info) {
                        if ($other_project_template_info->activityType) {
                            $group_info = ActivityGroup::with('get_group_activities.getActivityInfo')->find($other_project_template_info->activity_group_name_id_or_activity_id);
                            foreach ($group_info->get_group_activities as $group_activity_info) {
                                $current_template_name = $other_project_template_info->getTemplate->template_name;
                                $current_activity_name = $group_activity_info->getActivityInfo->activity_name;
                                $exists = false;
                                foreach ($userAssignedActivities as $activity) {
                                    if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                                        $exists = true;
                                        break; // Exit loop if a match is found
                                    }
                                }
                                if (!$exists) {
                                    $userAssignedActivities[] = [
                                        'template_name_id' => $other_project_template_info->getTemplate->id,
                                        'template_name' => $other_project_template_info->getTemplate->template_name,
                                        'is_master' => 0,
                                        'activity_id' => $group_activity_info->activity_id,
                                        'activity_name' => $group_activity_info->getActivityInfo->activity_name,
                                        'group_id' => $group_info->id,
                                        'group_name' => $group_info->activity_group_name,
                                        'sequence' => $group_activity_info->sequence
                                    ];
                                }
                            }
                        } else {
                            $current_template_name = $other_project_template_info->getTemplate->template_name;
                            $current_activity_name = $other_project_template_info->activity->activity_name;
                            $exists = false;
                            foreach ($userAssignedActivities as $activity) {
                                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
                                    $exists = true;
                                    break; // Exit loop if a match is found
                                }
                            }
                            if (!$exists) {
                                $userAssignedActivities[] = [
                                    'template_name_id' => $other_project_template_info->getTemplate->id,
                                    'template_name' => $other_project_template_info->getTemplate->template_name,
                                    'is_master' => 0,
                                    'activity_id' => $other_project_template_info->activity->id,
                                    'activity_name' => $other_project_template_info->activity->activity_name,
                                    'group_id' => null,
                                    'group_name' => null,
                                    'sequence' => null
                                ];
                            }
                        }
                    }
                }
            }
            // dd($userAssignedActivities);
            foreach ($userAssignedActivities as $projectTemplateAssignData) {
                $templateId = $projectTemplateAssignData['template_name_id'];
                $projectTemplateData = ProjectTemplate::where('project_id', $id)->where('template_name_id', $templateId)->first();
                $activitData = Activity::find($projectTemplateAssignData['activity_id']);

                if (!isset($templateRoutes[$templateId])) {
                    $templateRoutes[$templateId] = [
                        'template_id' => $projectTemplateData->id,
                        'is_master' => $projectTemplateAssignData['is_master'],
                        'activity_name' => $activitData->activity_name,
                        'template_name' => $projectTemplateAssignData['template_name'], // optional
                        'url' => route('getDistributorOutletData', [
                            'id' => $id,
                            'templateid' => $templateId
                        ])

                    ];
                }
            }

            // If you want a numeric indexed array as result
            $templateRoutes = array_values($templateRoutes);
            $templateALlRoutes = array_values($templateRoutes);
            // // Sort to move is_master = 1 to top
            usort($templateRoutes, function ($a, $b) {
                return $b['is_master'] <=> $a['is_master'];
            });

            return response()->json(['status' => true, 'data' => [$projectData, $templateRoutes, $templateALlRoutes], 'message' => 'Project Template Data Fetch Successfully']);
        } catch (\Exception $e) {
            //            dd($e->getMessage());
            return response()->json(['status' => false, 'message' => 'Project Template Data Fetch Error']);
        }
    }


    public function projectTemplateData(Request $request)
    {
        try {

            $id = $request->projectId;
            $projectData = Project::with(['getProjectTemplates.getTemplate', 'getCompanyInfo'])
                ->where('id', $id)->first();

            $templateRoutes = [];
            $userAssignedActivities = [];
            $project_master_template = ProjectTemplate::where('project_id', $projectData->id)
                ->where('is_master', 1)
                ->first();
            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')->where('project_id', $projectData->id)
                ->where('id', '!=', $project_master_template->id)
                ->get();

            $getDataAssignedIds = DataAssign::where('project_id', $projectData->id)->pluck('id')->toArray();

            $userAssignedActivities = UserActivityDataAssign::with('dataAssign')
                ->whereIn('data_assign_id', $getDataAssignedIds)
                ->distinct('activity_id', 'project_template_id')
                ->get();

            //             dd($userAssignedActivities);
            foreach ($userAssignedActivities as $projectTemplateAssignData) {
                //                dd($projectTemplateAssignData);
                $projecttemplateId = $projectTemplateAssignData->dataAssign->project_template_id;
                $projectTemplateData = ProjectTemplate::find($projecttemplateId);
                $activitData = Activity::find($projectTemplateAssignData['activity_id']);
                $templateId = $projectTemplateData->template_name_id;
                $templateName = TemplateName::find($templateId);

                if (!isset($templateRoutes[$templateId])) {
                    $templateRoutes[$templateId] = [
                        'template_id' => $projectTemplateData->id,
                        'is_master' => $projectTemplateData->is_master,
                        'activity_name' => $activitData->activity_name,
                        'template_name' => $templateName->template_name, // optional
                        'url' => route('getDistributorOutletData', [
                            'id' => $id,
                            'templateid' => $templateId
                        ])

                    ];
                }
            }

            // If you want a numeric indexed array as result
            $templateRoutes = array_values($templateRoutes);
            $templateALlRoutes = array_values($templateRoutes);
            // // Sort to move is_master = 1 to top
            usort($templateRoutes, function ($a, $b) {
                return $b['is_master'] <=> $a['is_master'];
            });

            return response()->json(['status' => true, 'data' => [$projectData, $templateRoutes, $templateALlRoutes], 'message' => 'Project Template Data Fetch Successfully']);
        } catch (\Exception $e) {
            //            dd($e->getMessage());
            return response()->json(['status' => false, 'message' => 'Project Template Data Fetch Error']);
        }
    }


    public function getProjectTemplateActivity(Request $request)
    {
        try {

            $projectTempInfo = ProjectTemplate::where('id', $request->template_id)->first();

            //            $checkDataAssign = DataAssign::where('project_id', $request->project_id)
//                ->where('template_name_id', $projectTempInfo->template_name_id)->first();

            $checkDataAssign = UserActivityDataAssign::where('project_template_id', $projectTempInfo->id)->get();

            $activityIds = [];
            $activityType = '';


            if (!empty($checkDataAssign)) {

                foreach ($checkDataAssign as $checkDataAssignData) {

                    if ($checkDataAssignData->activity_id) {
                        $activityIds[] = $checkDataAssignData->activity_id;
                    } else if ($checkDataAssignData->activity_group_id) {

                        $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $checkDataAssignData->activity_group_id)
                            ->pluck('activity_id')
                            ->toArray();

                        foreach ($groupActivityIds as $id) {
                            if (!in_array($id, $activityIds)) {
                                $activityIds[] = $id;
                            }
                        }
                    }

                }

            } else {

                if ($projectTempInfo->is_master == 0) {
                    $masterProjectTemp = ProjectTemplate::where('project_id', $request->project_id)->where('is_master', 1)->first();
                    $checkDataAssign = DataAssign::where('project_template_id', $masterProjectTemp->id)
                        ->where('is_outlet_assigned', 1)->first();

                    if ($checkDataAssign->activity_id) {
                        $activityIds[] = $checkDataAssign->activity_id;
                    } else if ($checkDataAssign->activity_group_id) {

                        $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $checkDataAssign->activity_group_id)
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

            //            dd($activityIds);
            $activities = [];
            if (!empty($activityIds)) {
                $activities = Activity::whereIn('id', $activityIds)->get();

                return response()->json([
                    'status' => true,
                    'data' => ['activityData' => $activities],
                    'message' => 'Project Template Activity Data Fetch Successfully'
                ]);

            } else {

                return response()->json([
                    'status' => false,
                    'data' => [],
                    'message' => 'Data Not Assigned'
                ]);

            }


        } catch (\Exception $e) {
            //             dd($e->getMessage());
            return response()->json(['status' => false, 'message' => 'Project Template Activity Data Fetch Error']);
        }
    }

    public function getDistributorData(Request $request)
    {
        $projectTemplate = ProjectTemplate::with('getTemplate.getTemplateHeads')
            ->where('id', $request->template_id)->first();
        // dd($request);
        $activityIds = [];
        $activityType = '';

        $project_templates_count = ProjectTemplate::where('project_id', $request->project_id)
            ->count();

        $mainHeader = $projectTemplate->main_header;

        $project_template_main_headers = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
            ->select(
                'id',
                'verified_by_company',
                'company_user_id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$mainHeader}\"')) as value")
            )
            ->orderBy('id')
            ->get()
            ->map(function ($item) use ($mainHeader) {
                return [
                    'id' => $item->id,
                    'value' => $item->value,
                    'template_name_head_id' => $mainHeader, // added manually
                    'verified_by_company' => $item->verified_by_company,
                    'company_user_id' => $item->company_user_id
                ];
            });
        // Get all their row_ids
        $rowIds = $project_template_main_headers->pluck('id');
        $project = Project::find($request->project_id);
        //to check if company can verify the data or not
        $companyCanVerify = false;
        if ($project->companyVerificationRequired == 1) {
            $companyCanVerify = true;
        }

        $projectTemplateNameHeads = TemplateNameHead::where('template_name_id', $projectTemplate->template_name_id)->pluck('id')->toArray();
        $projectAssignedData = [];

        if (isset($request->activity_id) && !empty($request->activity_id)) {
            if (isset($request->from_date) && !empty($request->from_date) && isset($request->to_date) && !empty($request->to_date)) {

                $projectAssignedData = UserActivityDataAssign::with('dataAssign')->where('project_template_id', $projectTemplate->id)
                    ->where('activity_id', $request->activity_id)
                    ->whereBetween('created_at', [
                        Carbon::parse($request->from_date)->startOfDay(),
                        Carbon::parse($request->to_date)->endOfDay()
                    ])
                    ->get();
            } else {
                $projectAssignedData = UserActivityDataAssign::with('dataAssign')->where('project_template_id', $projectTemplate->id)
                    ->where('activity_id', $request->activity_id)
                    ->get();
            }
        } else {
            $projectAssignedData = UserActivityDataAssign::with('dataAssign')
                ->where('project_template_id', $projectTemplate->id)
                ->get();
        }

        $projectDataAssignIds = [];
        foreach ($projectAssignedData as $projectAssignedDataData) {
            if (!empty($projectAssignedDataData->dataAssign)) {
                $projectDataAssignIds[] = $projectAssignedDataData->dataAssign->id;
            }
        }

        $projectDataAssignIds = array_unique($projectDataAssignIds);
        //        dd($projectDataAssignIds);

        $userAssignActivityIds = UserActivityDataAssign::whereIn('data_assign_id', $projectDataAssignIds)
            ->where('project_template_id', $projectTemplate->id)
            ->distinct('activity_id')->pluck('activity_id')->toArray();

        $activities = Activity::whereIn('id', $userAssignActivityIds)->select('id', 'activity_name')->get();

        $userAssignCommonIds = UserActivityDataAssign::whereIn('data_assign_id', $projectDataAssignIds)
            ->where('project_template_id', $projectTemplate->id)
            ->distinct('common_id')->pluck('common_id')->toArray();

        $userAssignIds = UserActivityDataAssign::whereIn('data_assign_id', $projectDataAssignIds)
            ->where('project_template_id', $projectTemplate->id)
            ->distinct('user_id')->pluck('user_id')->toArray();

        //        dd($userAssignIds, $userAssignActivityIds, $rowIds);
        $getAssignedRows = UserAuditAssigns::whereIn('common_id', $userAssignCommonIds)->pluck('row_id')->toArray();

        $existingRowIdsInAnswers = TempUserActivityAnswersData::whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $userAssignActivityIds)
            ->whereNotNull('verified_by')
            ->where('verified_by', '!=', 0)
            ->whereNotNull('remark')
            ->whereIn('user_id', $userAssignIds)
            ->pluck('row_id')
            ->toArray();

        //        dd($existingRowIdsInAnswers);

        // Get row_id => [user_id, created_at] mapping
        $rowIdDataMap = TempUserActivityAnswersData::whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $userAssignActivityIds)
            ->whereNotNull('verified_by')
            ->where('verified_by', '!=', 0)
            ->whereNotNull('remark')
            ->get(['row_id', 'user_id', 'created_at'])
            ->mapWithKeys(function ($item) {
                return [
                    $item->row_id => [
                        'user_id' => $item->user_id,
                        'created_at' => $item->created_at
                    ]
                ];
            })
            ->toArray();


        //        // Add `exist` key to each item
        $project_template_main_headers = $project_template_main_headers->map(function ($item) use ($existingRowIdsInAnswers, $rowIdDataMap) {
            $item['answer_exist'] = in_array($item['id'], $existingRowIdsInAnswers);
            $item['answer_row_id'] = in_array($item['id'], $existingRowIdsInAnswers) ? $item['id'] : '';
            $item['verified_by_company'] = $item['verified_by_company'] ?? null;
            $item['company_user_id'] = $item['company_user_id'] ?? null;

            // Get auditor data if exists
            $auditorDataId = $rowIdDataMap[$item['id']] ?? null;
            $auditorData = $auditorDataId ? User::find($auditorDataId['user_id']) : '';
            $item['auditorData'] = !empty($auditorData) ? $auditorData->name : '';
            $item['agencyName'] = !empty($auditorData) ? (User::find($auditorData->agency_user_id))->name : '';
            $item['answer_created_at'] = $auditorData ? $auditorDataId['created_at']->format('Y-m-d H:i:s') : '';
            return $item;
        });

        $project_template_sub_headers = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
            ->select(
                'id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplate->sub_header}\"')) as value")
            )
            ->orderBy("id")
            ->get();

        $checkChildExist = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)->exists();


        return response()->json([
            'message' => 'success',
            'projectTemplate' => $projectTemplate,
            'project_templates_count' => $project_templates_count,
            'sub_headers' => $project_template_sub_headers,
            'main_headers' => $project_template_main_headers,
            'activityType' => $activityType,
            'activities' => $activities,
            'companyCanVerify' => $companyCanVerify,
            'masterExist' => $checkChildExist ? 1 : 0,
        ]);
    }


    public function getDistributorDataOld(Request $request)
    {
        $projectTemplate = ProjectTemplate::with('getTemplate.getTemplateHeads')
            ->where('id', $request->template_id)->first();
        // dd($request);
        $activityIds = [];
        $activityType = '';

        $project_templates_count = ProjectTemplate::where('project_id', $request->project_id)
            ->count();

        $project_template_main_headers = ProjectTemplateNameValuesNew::where('project_template_id', $projectTemplate->id)
            ->select(
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$projectTemplate->main_header}\"')) as value")
            )
            ->orderBy("id")
            ->get();

        // Get all their row_ids
        $rowIds = $project_template_main_headers->pluck('id');
        //        dd($rowIds);

        $project = Project::find($request->project_id);
        // dd($project);

        //to check if company can verify the data or not
        $companyCanVerify = false;
        if ($project->companyVerificationRequired == 1) {
            $companyCanVerify = true;
        }

        $projectTemplateNameHeads = TemplateNameHead::where('template_name_id', $projectTemplate->template_name_id)->pluck('id')->toArray();

        if (isset($request->activity_id) && !empty($request->activity_id)) {
            if (isset($request->from_date) && !empty($request->from_date) && isset($request->to_date) && !empty($request->to_date)) {

                $projectDataAssignQuery = DataAssign::where('project_id', $project->id)
                    ->where('company_id', $project->company_id)
                    ->where('zone_id', $project->zone_id)
                    ->where('unit_id', $project->unit_id)
                    ->where('template_name_id', $projectTemplate->template_name_id)
                    ->whereIn('template_name_head_id', $projectTemplateNameHeads) //$projectTemplate->sub_header
                    ->where('project_template_id', $projectTemplate->id)
                    ->where('activity_id', $request->activity_id)
                    ->whereBetween('created_at', [
                        Carbon::parse($request->from_date)->startOfDay(),
                        Carbon::parse($request->to_date)->endOfDay()
                    ]);
            } else {

                $projectDataAssignQuery = DataAssign::where('project_id', $project->id)
                    ->where('company_id', $project->company_id)
                    ->where('zone_id', $project->zone_id)
                    ->where('unit_id', $project->unit_id)
                    ->where('template_name_id', $projectTemplate->template_name_id)
                    ->whereIn('template_name_head_id', $projectTemplateNameHeads) //$projectTemplate->sub_header
                    ->where('project_template_id', $projectTemplate->id)
                    ->where('activity_id', $request->activity_id);
            }


        } else {

            $projectDataAssignQuery = DataAssign::where('project_id', $project->id)
                ->where('company_id', $project->company_id)
                ->where('zone_id', $project->zone_id)
                ->where('unit_id', $project->unit_id)
                ->where('template_name_id', $projectTemplate->template_name_id)
                ->whereIn('template_name_head_id', $projectTemplateNameHeads) //$projectTemplate->sub_header
                ->where('project_template_id', $projectTemplate->id);
        }


        $projectAssignedData = $projectDataAssignQuery->get();
        //         dd($projectAssignedData);
        if ($projectAssignedData->IsEmpty() && $projectTemplate->is_master == 0) {


            $project_master_template = ProjectTemplate::where('project_id', $projectTemplate->project_id)
                ->where('is_master', 1)
                ->first();

            $project_other_templates = ProjectTemplate::with('activity', 'activityGroup', 'getTemplate')->where('project_id', $projectTemplate->project_id)
                ->where('id', '!=', $project_master_template->id)
                ->get();

            $distinct_data_assignIds = AuditorAssignedData::where('project_id', $projectTemplate->project_id)
                ->distinct('data_assign_id')
                ->where('template_name_id', $project_master_template->template_name_id)
                ->pluck('data_assign_id');

            $projectAssignedData = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')->whereIn("id", $distinct_data_assignIds)
                ->get();

            //            dd($projectTemplate);
            if ($projectTemplate->activityType == 0) {

                $activityIds[] = $projectTemplate->activity_group_name_id_or_activity_id;
                // $projectDataAssignQuery->where('activity_id', $projectTemplate->activity_group_name_id_or_activity_id);
            } elseif ($projectTemplate->activityType == 1) {
                $groupActivityIds = ActivityGroupPivot::where('activity_group_id', $projectTemplate->activity_group_name_id_or_activity_id)
                    ->pluck('activity_id')
                    ->toArray();

                foreach ($groupActivityIds as $id) {
                    if (!in_array($id, $activityIds)) {
                        $activityIds[] = $id;
                    }
                }
            }

            //            dd($activityIds);
            $activities = Activity::whereIn('id', $activityIds)->select('id', 'activity_name')->get();
        } else {

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
        }

        $activities = Activity::whereIn('id', $activityIds)->select('id', 'activity_name')->get();

        $projectDataAssignDataIds = $projectDataAssignQuery->pluck('id')->toArray();
        // dd($projectDataAssignDataIds, $project->id, $projectTemplate->template_name_id);

        $auditorAssignDataIds = AuditorAssignedData::whereIn('data_assign_id', $projectDataAssignDataIds)
            ->where('project_id', $project->id)->where('template_name_id', $projectTemplate->template_name_id)->pluck('user_id')->toArray();

        // dd($activityIds, $auditorAssignDataIds, $rowIds);
        // Get the row_ids that exist in the answers table
//        dd($rowIds, $activityIds);
        $existingRowIdsInAnswers = TempUserActivityAnswersData::whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('verified_by')
            ->where('verified_by', '!=', 0)
            ->whereNotNull('remark')
            // ->whereIn('user_id', $auditorAssignDataIds)
            ->pluck('row_id')
            ->toArray();

        //        dd($existingRowIdsInAnswers);

        // Get row_id => [user_id, created_at] mapping
        $rowIdDataMap = TempUserActivityAnswersData::whereIn('row_id', $rowIds)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('verified_by')
            ->where('verified_by', '!=', 0)
            ->whereNotNull('remark')
            ->get(['row_id', 'user_id', 'created_at'])
            ->mapWithKeys(function ($item) {
                return [
                    $item->row_id => [
                        'user_id' => $item->user_id,
                        'created_at' => $item->created_at
                    ]
                ];
            })
            ->toArray();


        //        // Add `exist` key to each item
        $project_template_main_headers = $project_template_main_headers->map(function ($item) use ($existingRowIdsInAnswers, $rowIdDataMap) {
            $item->answer_exist = in_array($item->row_id, $existingRowIdsInAnswers);
            $item->answer_row_id = in_array($item->row_id, $existingRowIdsInAnswers) ? $item->row_id : '';

            // Get auditor data if exists
            $auditorDataId = $rowIdDataMap[$item->row_id] ?? null;
            $auditorData = $auditorDataId ? User::find($auditorDataId['user_id']) : '';
            $item->auditorData = !empty($auditorData) ? $auditorData->name : '';
            $item->agencyName = !empty($auditorData) ? (User::find($auditorData->agency_user_id))->name : '';
            $item->answer_created_at = $auditorData ? $auditorDataId['created_at']->format('Y-m-d H:i:s') : '';
            return $item;
        });

        $project_template_sub_headers = ProjectTemplateNameValue::where('project_template_id', $projectTemplate->id)
            ->where('template_name_head_id', $projectTemplate->sub_header)
            ->orderBy("id")
            ->get();

        $checkChildExist = ProjectTemplate::where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)->exists();


        return response()->json([
            'message' => 'success',
            'projectTemplate' => $projectTemplate,
            'project_templates_count' => $project_templates_count,
            'sub_headers' => $project_template_sub_headers,
            'main_headers' => $project_template_main_headers,
            'activityType' => $activityType,
            'activities' => $activities,
            'companyCanVerify' => $companyCanVerify,
            'masterExist' => $checkChildExist ? 1 : 0,
        ]);
    }

    public function getProjectTemplateActivityQuestion(Request $request)
    {
        // dd($request);
        try {

            $projectTemplateData = ProjectTemplate::find($request->template_id);

            if (isset($request->activity_id)) {
                $activityAnswers = TempUserActivityAnswersData::with([
                    'getUser.agencyUser',
                    'getQuestionInfo',
                    'projectTemplateNameValue',
                    'get_activity_info',
                    'get_remark_info',
                    'getVerifier.agencyUser'
                ])
                    ->where('row_id', $request->distributor_id)
                    ->where('activity_id', $request->activity_id)
                    // ->where('remark', 1)
                    ->get();
            } else {

                $activityAnswers = TempUserActivityAnswersData::with([
                    'getUser.agencyUser',
                    'getQuestionInfo',
                    'projectTemplateNameValue',
                    'get_activity_info',
                    'get_remark_info',
                    'getVerifier.agencyUser'
                ])
                    ->where('row_id', $request->distributor_id)
                    // ->where('remark', 1)
                    ->get();
            }

            // dd($questionData, $request->distributor_id);

            if ($request->activity_id) {

                return response()->json(['status' => true, 'data' => $activityAnswers, 'message' => 'Question Data Fetch Successfully']);
            } else {
                if (!empty($activityAnswers)) {
                    ProjectTemplateNameValuesNew::where('id', $request->distributor_id)
                        ->where('project_template_id', $projectTemplateData->id)
                        ->update([
                            'verified_by_company' => 1,
                            'company_user_id' => auth()->id()
                        ]);

                    return response()->json(['status' => true, 'message' => 'Approved Successfully']);
                }
            }
        } catch (\Exception $e) {
            //            dd($e->getMessage());
            return response()->json(['status' => false, 'message' => 'Question Data Fetch Error']);
        }
    }


    public function TemplateHeaderViewData(Request $request)
    {
        try {

            // dd($request);
            $projectTemplate = ProjectTemplate::find($request->template_id);
            if (!$projectTemplate) {
                $response = "No data found";
                return view('masters.data_templates.view_project_data', compact('response'));
            }
            $checkTemplatename = TemplateName::find($projectTemplate->template_name_id);
            $templateHeadsCount = count($checkTemplatename->getTemplateHeads); // Dynamic number of heads
            $perPage = 100 * $templateHeadsCount;

            $templateNameHeadIds = TemplateNameHead::where('template_name_id', $projectTemplate->template_name_id)
                ->pluck('id')->toArray();

            $templateNameHeads = TemplateNameHead::where('template_name_id', $projectTemplate->template_name_id)
                ->get();

            $projectTemplateDataGet = ProjectTemplateNameValuesNew::find($request->row_id);

            $templateDataArray = json_decode($projectTemplateDataGet->template_data_json, true);

            $headIds = array_keys($templateDataArray);

            $headNames = TemplateNameHead::whereIn('id', $headIds)
                ->pluck('template_head_name', 'id');

            // Step 4: Prepare final output
            $projectTemplateData = [];

            foreach ($templateDataArray as $headId => $value) {
                $headName = $headNames[$headId] ?? null;

                if ($headName !== null) {
                    $projectTemplateData[] = [
                        'head_id' => (int) $headId,
                        'template_head_name' => $headName,
                        'value' => $value,
                    ];
                }
            }

            return response()->json(['status' => true, 'data' => $projectTemplateData, 'templateHead' => $templateNameHeads, 'message' => 'TemplateHeader Data Approved Successfully']);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function testPage($data)
    {
        dd($data);
    }

    public function getDistributorOutletData($id, $templateId)
    {
        // dd($id, $templateId);
        $project = Project::find($id);
        $userAssignedActivities = [];

        $project_other_templates = ProjectTemplate::where('project_id', $project->id)
            ->where('is_master', 0)
            ->where('template_name_id', $templateId)->get();

        $distinct_data_assignIds = DataAssign::where('project_id', $project->id)
            ->pluck('id')->toArray();

        //        $distinct_data_assignIds = AuditorAssignedData::where('project_id', $project->id)
//            ->distinct('data_assign_id')
//            // ->where('user_id', $user->id)
//            ->pluck('data_assign_id');

        $data_assign_info = DataAssign::with('getProjectTemplate', 'templateName', 'activityName', 'getActivityGroup')
            ->where('project_id', $project->id)
            ->get();

        $project_other_templatesIds = $project_other_templates->pluck('id')->toArray();

        $userAssignedActivityIds = UserActivityDataAssign::with('dataAssign')
            ->whereIn('data_assign_id', $distinct_data_assignIds)
            ->whereIn('project_template_id', $project_other_templatesIds)
            ->get()
            ->unique('activity_id');

        $getActivityGroupData = null;
        foreach ($userAssignedActivityIds as $assigned_data) {
            $sequence = null;
            // dd($assigned_data);
            $getActivityGroupName = null;
            if (isset($assigned_data->activity_sequence_id)) {
                $group_activity_info = ActivityGroupPivot::where('activity_id', $assigned_data->activity_id)
                    ->where('sequence', $assigned_data->activity_sequence_id)
                    ->first();

                $sequence = !empty($group_activity_info) ? $group_activity_info->sequence : '';
                $getActivityGroupData = ActivityGroup::find($group_activity_info->activity_group_id);
            }

            $group_name = !empty($getActivityGroupData) ? $getActivityGroupData->activity_group_name : null;
            $getProjectTemplateInfo = ProjectTemplate::find($assigned_data->project_template_id);
            $is_master_temp = $getProjectTemplateInfo->is_master ?? 0;
            $templateData = TemplateName::find($getProjectTemplateInfo->template_name_id);
            $current_template_name = $templateData->template_name;
            $current_activity_name = $assigned_data->activityInfo->activity_name;
            $exists = false;

            //            dd($userAssignedActivityIds);
//            if(!empty($userAssignedActivityIds)){
//                $exists = true;
//            }
//            foreach ($userAssignedActivities as $activity) {
//                if ($activity['template_name'] == $current_template_name && $activity['activity_name'] == $current_activity_name) {
//                    $exists = true;
//                    break; // Exit loop if a match is found
//                }
//            }

            if (!$exists) {
                $userAssignedActivities[] = [
                    'template_name_id' => $getProjectTemplateInfo->template_name_id,
                    'template_name' => $templateData->template_name,
                    'is_master' => $is_master_temp,
                    'activity_id' => $assigned_data->activity_id,
                    'activity_name' => $assigned_data->activityInfo->activity_name,
                    'group_id' => !empty($group_activity_info) ? $group_activity_info->id : null,
                    'group_name' => $group_name,
                    'sequence' => $sequence,
                ];
            }

        }
        $userAssignedActivities = collect($userAssignedActivities)->sortBy([['is_master', 'desc'], ['sequence', 'asc']])->values()->toArray();
        return view('masters.companies.viewOutletData', compact('userAssignedActivities', 'project'));

    }

    public function getQuestionAnswersofChildTemplate(Request $request)
    {

        $related_questions_ids = Question::with(['getOptions', 'getSubjects'])->where('activity_id', $request->activity_id)
            ->pluck('id')->toArray();

        $activityAnswerData = TempUserActivityAnswersData::with(['getQuestionInfo', 'getUser', 'getVerifier', 'get_activity_info'])->where('row_id', $request->row_id)
            ->whereIn('question_id', $related_questions_ids)->get();

        return response()->json(['status' => true, 'data' => ['activityAnswerData' => $activityAnswerData]]);
    }
    //khushboo 18-04-25
}
