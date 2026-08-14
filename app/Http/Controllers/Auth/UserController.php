<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityGroupPivot;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\User;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use App\Models\Verifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use App\Exports\UsersExport;

class UserController extends Controller
{
    public function index()
    {
        // $users = User::all();
//        dd('ghgh');
        //khushboo 31-03-2025
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        //Super Admin
        if ($currentUserRole == 'Super Admin') {
            $users = User::all();
        }
        //Verifier
        if ($currentUserRole == 'Verifier') {
            $users = User::where('verifier_user_id', Auth::user()->id)->orWhere('id', Auth::user()->id)->get();
        }
        //Agency
        if ($currentUserRole == 'Agency') {
            $users = User::where('agency_user_id', Auth::user()->id)->orWhere('id', Auth::user()->id)->get();
        }
        //khushboo 31-03-2025
        return view('role-permission.user.index', compact('users', 'currentUserRole'));
    }

    public function create()
    {
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();
        $agencies = User::role('agency')->get();

        // Verifier cannot create Super Admin users — exclude that role from the dropdown
        if ($currentUserRole == 'Verifier') {
            $roles = Role::where('name', '!=', 'Super Admin')->get();
        } else {
            $roles = Role::all();
        }

        return view('role-permission.user.create', compact('roles', 'currentUserRole', 'agencies'));
    }

    public function store(Request $request)
    {
        // dd('fhg');
        $validated = $request->validate([
            "name" => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'email',
                'unique:users,email'
            ],
            'mobile' => 'required|unique:users|digits:10',
            'password' => 'required|min:8|max:20',
            'address1' => 'nullable|string',
            'address2' => 'nullable|string',
            'city' => "nullable|string",
            'district' => "nullable|string",
            'state' => "nullable|string",
            'pincode' => ['nullable', 'digits:6', 'numeric'],
            'roles' => 'required',
            //            'roles' => 'required|array'

        ]);

        //khushboo 31-03-2025
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if ($currentUserRole == 'Verifier' && $request->roles == 'Super Admin') {
            return redirect()->back()->withErrors(['roles' => 'You are not allowed to create a Super Admin user.'])->withInput();
        }

        if ($currentUserRole == 'Agency') {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'district' => $request->district,
                'state' => $request->state,
                'password_info' => $request->password,
                'pincode' => $request->pincode,
                'is_agency_user' => 1,
                'agency_user_id' => Auth::user()->id,
            ]);

            $user->syncRoles($request->roles);

            return redirect("/users")->with('message', "User created successfully with roles");
        }

        if ($currentUserRole == 'Verifier') {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'district' => $request->district,
                'state' => $request->state,
                'password_info' => $request->password,
                'pincode' => $request->pincode,
                'verifier_user_id' => Auth::user()->id,
            ]);

            $user->syncRoles($request->roles);

            return redirect("/users")->with('message', "User created successfully with roles");
        }

        $agency_user_id = null;
        $is_agency_user = 0;
        if (isset($request->agency_select) && !empty($request->agency_select)) {
            $agency_user_id = $request->agency_select;
            $is_agency_user = 1;
        }
        //khushboo 31-03-2025

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'district' => $request->district,
            'state' => $request->state,
            'password_info' => $request->password,
            'pincode' => $request->pincode,
            'is_agency_user' => $request->roles == 'Agency' ? 1 : $is_agency_user,
            'agency_user_id' => $agency_user_id
        ]);

        $user->syncRoles($request->roles);

        return redirect("/users")->with('message', "User created successfully with roles");
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();
        $agencies = User::role('agency')->get();
        return view('role-permission.user.edit', compact('agencies', 'user', 'roles', 'currentUserRole'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            "name" => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'email',
                'unique:users,email,' . $user->id,
            ],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile,' . $user->id,],
            'password' => 'required|min:8|max:20',
            'address1' => 'nullable|string',
            'address2' => 'nullable|string',
            'city' => "nullable|string",
            'district' => "nullable|string",
            'state' => "nullable|string",
            'pincode' => ['nullable', 'digits:6', 'numeric'],
            'roles' => 'required',
        ]);

        //khushboo 29-05-25
        $agency_user_id = null;
        $is_agency_user = 0;
        if (isset($request->agency_select) && !empty($request->agency_select)) {
            $agency_user_id = $request->agency_select;
            $is_agency_user = 1;
        }
        //khushboo 29-05-25

        $data = [
            'name' => $request->name,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'city' => $request->city,
            'district' => $request->district,
            'state' => $request->state,
            'pincode' => $request->pincode,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'is_agency_user' => $request->roles == 'Agency' ? 1 : $is_agency_user,
            'agency_user_id' => !empty($user->agency_user_id) && ($user->agency_user_id == $request->agency_select) ? $user->agency_user_id : $agency_user_id
        ];

        if ($request->password !== $user->password_info) {
            $data += [
                'password' => Hash::make($request->password),
                'password_info' => $request->password,
            ];
        }
        $user->update($data);
        $user->syncRoles($request->roles);
        return redirect("/users")->with('message', "User Updated successfully with roles");
    }

    public function destroy(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->delete();
        return "Success";
    }

    public function view_user_upload()
    {
        return view('role-permission.user.user_upload');
    }

    public function userUpload(Request $request)
    {
        $validated = $request->validate([
            'user_excel' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ]);

        if ($request->hasFile('user_excel')) {
            $file = $request->file('user_excel');
            $data = Excel::toCollection(Excel::class, $file)->first();
            $dataArray = $data->toArray();
            // Remove the first element from the array
            array_shift($dataArray);
            foreach ($dataArray as $userData) {
                $user = User::create([
                    'name' => $userData[0],
                    'mobile' => $userData[1],
                    'email' => $userData[2],
                    'password' => Hash::make($userData[9]),
                    'password_info' => $userData[9],
                    'address1' => $userData[3],
                    'address2' => $userData[4],
                    'city' => $userData[5],
                    'district' => $userData[6],
                    'state' => $userData[7],
                    'pincode' => $userData[8],
                    // 'is_agency_user' =>  $userData[10] == 'Agency' ? 1 : null,
                ]);

                $user->syncRoles($userData[10]);
            }
        }
        return redirect("/users")->with('message', "User Uploaded successfully");
    }

    public function assignDataToUsers(Request $request)
    {
//        dd($request->all());
        $formFields = $request->validate([
            'project_id' => ['required'],
            'activity_id' => ['required'],
            'activity_group_name_id' => ['nullable'],
            'template_name_id' => ['required'],
            'template_name_head_id' => ['required'],
//            'template_name_head_values' => ['required'],
//            'auditor_names' => ['required'],
//            'verify_users_names' => ['nullable'],
        ]);

        $is_outlet_assinged = 0;
        if ($request->has('is_outlet_assigned')) {
            $is_outlet_assinged = 1;
        }
        // dd($request->activity_id);

        $projectTemplate_info = ProjectTemplate::where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)->first();

        if ($projectTemplate_info->with_data == 1) {
            $request->validate([
                'template_name_head_values' => ['required']
            ]);
        }

        if ($request->verify_users_names != null) {
            foreach ($request->verify_users_names as $verify_user_name) {
                $check_if_allocated = Verifier::where('user_id', $verify_user_name)
                    ->where('project_template_name_id', $projectTemplate_info->id)->first();
                if (!$check_if_allocated) {
                    Verifier::create([
                        'user_id' => $verify_user_name,
                        'project_template_name_id' => $projectTemplate_info->id,
                    ]);
                }

                if ($request->has('is_outlet_assigned')) {
                    $project_outlets = ProjectTemplate::where('project_id', $request->project_id)
                        ->where('is_master', 0)
                        ->get();
                    foreach ($project_outlets as $project_outlet) {
                        $check_if_outlet_allocated = Verifier::where('user_id', $verify_user_name)
                            ->where('project_template_name_id', $project_outlet->id)->first();
                        if (!$check_if_outlet_allocated) {
                            Verifier::create([
                                'user_id' => $verify_user_name,
                                'project_template_name_id' => $project_outlet->id,
                            ]);
                        }
                    }
                }
            }
        }

        $project_info = Project::findOrFail($request->project_id);
        $act_group_id = $request->activity_group_name_id ?? null;
        $project_template_id = ProjectTemplate::where('project_id', $request->project_id)->where('template_name_id', $request->template_name_id)->first();
        $companyUserId = $request->has('company_user') ? $request->company_user : null;
        $agencyId = $request->has('agency_list') ? $request->agency_list : null;

        foreach ($request->activity_id as $selected_activities) {
            // Use a transaction + pessimistic lock to prevent duplicate data_assigns
            // when the form is submitted concurrently (e.g. double-click).
            $dataAssign = DB::transaction(function () use (
                $project_info, $selected_activities, $act_group_id, $request,
                $project_template_id, $is_outlet_assinged, $companyUserId, $agencyId
            ) {
                $searchFields = [
                    'company_id'           => $project_info->company_id,
                    'zone_id'              => $project_info->zone_id,
                    'unit_id'              => $project_info->unit_id,
                    'project_id'           => $request->project_id,
                    'activity_id'          => $selected_activities,
                    'activity_group_id'    => $act_group_id,
                    'template_name_id'     => $request->template_name_id,
                    'project_template_id'  => $project_template_id->id,
                    'template_name_head_id'=> $request->template_name_head_id,
                    'is_outlet_assigned'   => $is_outlet_assinged,
                ];
                $query = DataAssign::query();
                foreach ($searchFields as $col => $val) {
                    $query = is_null($val) ? $query->whereNull($col) : $query->where($col, $val);
                }
                $existing = $query->lockForUpdate()->first();
                if ($existing) return $existing;

                return DataAssign::create(array_merge($searchFields, [
                    'company_user_id' => $companyUserId,
                    'agency_id'       => $agencyId,
                ]));
            });

            if (isset($request->template_name_head_values) && !empty($request->template_name_head_values)) {
                foreach ($request->template_name_head_values as $template_name_head_v) {
                    if (!empty($request->auditor_names)) {
                        foreach ($request->auditor_names as $auditor_id) {
                            // If outlet assigned → handle master first, then child
                            if ($is_outlet_assinged == 1) {
                                // MASTER TEMPLATE ROWS
                                $masterRowIds = DB::table('project_template_name_values_new')
                                    ->where('project_template_id', $project_template_id->id)
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($template_name_head_v)])
                                    ->pluck('id')
                                    ->toArray();
                                // Assign master template rows
                                $existingCommonId = $this->assignUserActivityAndAuditRows(
                                    $dataAssign->id,
                                    $auditor_id,
                                    $project_template_id->id,
                                    $selected_activities,
                                    $act_group_id,
                                    $masterRowIds
                                );
                                // Now get child template id
                                $childProjectTemplate = DB::table('project_templates')
                                    ->where('project_id', $project_template_id->project_id)
                                    ->where('is_master', 0)
                                    ->get();

                                if (!empty($childProjectTemplate)) {
                                    foreach ($childProjectTemplate as $childProject) {
                                        //get master head id value

                                        $getHeadValues = DB::table('project_template_name_values_new')
                                            ->whereIn('id', $masterRowIds)
                                            ->pluck(
                                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$childProject->master_head_id}\"')) as head_value")
                                            )
                                            ->toArray();


                                        if ($childProject->activityType == 0) {

                                            $childdatacheck = DB::table('project_template_name_values_new')
                                                ->where('project_template_id', $childProject->id)
                                                ->where(function ($query) use ($getHeadValues) {
                                                    foreach ($getHeadValues as $value) {
                                                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                    }
                                                })->get();

                                            $childRowIds = DB::table('project_template_name_values_new')
                                                ->where('project_template_id', $childProject->id)
                                                ->where(function ($query) use ($getHeadValues) {
                                                    foreach ($getHeadValues as $value) {
                                                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                    }
                                                })
                                                ->pluck('id')
                                                ->toArray();

                                            //   dd($childProject->activityType, $childProject->with_data, !$childdatacheck->isEmpty());
                                            // Assign child template rows (same logic, different template)
                                            if (!$childdatacheck->isEmpty()) {
                                                $this->assignUserActivityAndAuditRows(
                                                    $dataAssign->id,
                                                    $auditor_id,
                                                    $childProject->id,
                                                    $childProject->activity_group_name_id_or_activity_id,
                                                    $act_group_id,
                                                    $childRowIds
                                                );
                                            }
                                            else if($childProject->with_data == 0){
                                                // dd('dgdf');
                                                $this->AssignDataWithoutTemplateData($dataAssign->id, $auditor_id, $childProject->id, $childProject->activity_group_name_id_or_activity_id,  $act_group_id);
                                            }

                                        } else if ($childProject->activityType == 1) {


                                            $childdatacheck = DB::table('project_template_name_values_new')
                                                ->where('project_template_id', $childProject->id)
                                                ->where(function ($query) use ($getHeadValues) {
                                                    foreach ($getHeadValues as $value) {
                                                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                    }
                                                })->get();

                                            $childRowIds = DB::table('project_template_name_values_new')
                                                ->where('project_template_id', $childProject->id)
                                                ->where(function ($query) use ($getHeadValues) {
                                                    foreach ($getHeadValues as $value) {
                                                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                    }
                                                })
                                                ->pluck('id')
                                                ->toArray();

                                            $getActivityIds = ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)
                                                ->pluck('activity_id')->toArray();

                                            foreach ($getActivityIds as $childActivityId) {
                                                // Assign child template rows (same logic, different template)
                                                if (!$childdatacheck->isEmpty()) {
                                                    $this->assignUserActivityAndAuditRows(
                                                        $dataAssign->id,
                                                        $auditor_id,
                                                        $childProject->id,
                                                        $childActivityId,
                                                        $childProject->activity_group_name_id_or_activity_id,
                                                        $childRowIds
                                                    );
                                                }
                                                else if($childProject->with_data == 0){
                                                    $this->AssignDataWithoutTemplateData($dataAssign->id, $auditor_id, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $act_group_id);
                                                }
                                            }
                                        }
                                    }
                                }

                            } else {

                                // is_outlet_assigned == 0 → Only master template
                                $rowIds = DB::table('project_template_name_values_new')
                                    ->where('project_template_id', $project_template_id->id)
                                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($template_name_head_v)])
                                    ->pluck('id')
                                    ->toArray();

                                $this->assignUserActivityAndAuditRows(
                                    $dataAssign->id,
                                    $auditor_id,
                                    $project_template_id->id,
                                    $selected_activities,
                                    $act_group_id,
                                    $rowIds
                                );
                            }
                        }
                    }
                }
            }
            else if ($projectTemplate_info->with_data == 0) {
                //if template data not uploaded
                if (!empty($request->auditor_names)) {
                    foreach ($request->auditor_names as $auditor_id) {
                        $this->AssignDataWithoutTemplateData($dataAssign->id, $auditor_id,$project_template_id->id, $selected_activities,  $act_group_id);
                        if ($is_outlet_assinged == 1) {
                            $childProjectTemplate = DB::table('project_templates')
                                ->where('project_id', $project_template_id->project_id)
                                ->where('is_master', 0)
                                ->get();
                            if (!empty($childProjectTemplate)) {
                                foreach ($childProjectTemplate as $childProject) {
                                    if ($childProject->activityType == 0) {
                                        $this->AssignDataWithoutTemplateData($dataAssign->id, $auditor_id, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $childProject->id, $act_group_id);
                                    } else if ($childProject->activityType == 1) {
                                        $getActivityIds = ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)
                                            ->pluck('activity_id')->toArray();
                                        foreach ($getActivityIds as $childActivityId) {
                                            // Assign child template rows (same logic, different template)
                                            $this->AssignDataWithoutTemplateData($dataAssign->id, $auditor_id, $childProject->id, $childActivityId,  $childProject->activity_group_name_id_or_activity_id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return redirect(route('auditorAssigned.list'))->with('message', "Data Assigned Successfully");
    }


    public function assignDataToUsersOLDOct(Request $request)
    {
//        dd($request->all());
        $formFields = $request->validate([
            'project_id' => ['required'],
            'activity_id' => ['required'],
            'activity_group_name_id' => ['nullable'],
            'template_name_id' => ['required'],
            'template_name_head_id' => ['required'],
            'template_name_head_values' => ['required'],
//            'auditor_names' => ['required'],
//            'verify_users_names' => ['nullable'],
        ]);

        $is_outlet_assinged = 0;
        if ($request->has('is_outlet_assigned')) {
            $is_outlet_assinged = 1;
        }
        // dd($request->activity_id);


        $projectTemplate_info = ProjectTemplate::where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)->first();

        if ($request->verify_users_names != null) {
            foreach ($request->verify_users_names as $verify_user_name) {
                $check_if_allocated = Verifier::where('user_id', $verify_user_name)
                    ->where('project_template_name_id', $projectTemplate_info->id)->first();
                if (!$check_if_allocated) {
                    Verifier::create([
                        'user_id' => $verify_user_name,
                        'project_template_name_id' => $projectTemplate_info->id,
                    ]);
                }

                if ($request->has('is_outlet_assigned')) {
                    $project_outlets = ProjectTemplate::where('project_id', $request->project_id)
                        ->where('is_master', 0)
                        ->get();
                    foreach ($project_outlets as $project_outlet) {
                        $check_if_outlet_allocated = Verifier::where('user_id', $verify_user_name)
                            ->where('project_template_name_id', $project_outlet->id)->first();
                        if (!$check_if_outlet_allocated) {
                            Verifier::create([
                                'user_id' => $verify_user_name,
                                'project_template_name_id' => $project_outlet->id,
                            ]);
                        }
                    }
                }
            }
        }

        $project_info = Project::findOrFail($request->project_id);
        $act_group_id = $request->activity_group_name_id ?? null;
        $project_template_id = ProjectTemplate::where('project_id', $request->project_id)->where('template_name_id', $request->template_name_id)->first();

        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        $commonId = UserActivityDataAssign::max('common_id') + 1;

        foreach ($request->activity_id as $selected_activities) {
            // dd($selected_activities);

            // this was to insert the select activity of the group in the data assign

            $check_data_assign = DataAssign::where('company_id', $project_info->company_id)
                ->where('zone_id', $project_info->zone_id)
                ->where('unit_id', $project_info->unit_id)
                ->where('project_id', $request->project_id)
                ->where('activity_id', $selected_activities)
                ->when($act_group_id === null, fn($q) => $q->whereNull('activity_group_id'), fn($q) => $q->where('activity_group_id', $act_group_id))
                ->where('template_name_id', $request->template_name_id)
                ->where('project_template_id', $project_template_id->id)
                ->where('template_name_head_id', $request->template_name_head_id)
                ->where('is_outlet_assigned', $is_outlet_assinged)
                ->first();

//            dd($check_data_assign);
            if ($check_data_assign) {
                $dataAssign = $check_data_assign;
            } else {
                $companyUserId = null;
                $agencyId = null;
                if ($request->has('company_user')) {
                    $companyUserId = $request->company_user;
                }

                //give agency admin permission to view project if its selected
                if ($request->has('agency_list')) {
                    $agencyId = $request->agency_list;
                }

                $dataAssign = DataAssign::create([
                    'company_id' => $project_info->company_id,
                    'zone_id' => $project_info->zone_id,
                    'unit_id' => $project_info->unit_id,
                    'project_id' => $request->project_id,
                    'activity_id' => $selected_activities,
                    'activity_group_id' => $act_group_id,
                    'template_name_id' => $request->template_name_id,
                    'project_template_id' => $project_template_id->id,
                    'template_name_head_id' => $request->template_name_head_id,
                    'is_outlet_assigned' => $is_outlet_assinged,
                    'company_user_id' => $companyUserId,
                    'agency_id' => $agencyId
                ]);

            }

            foreach ($request->template_name_head_values as $template_name_head_v) {
                if (!empty($request->auditor_names)) {
                    foreach ($request->auditor_names as $auditor_id) {

                        // If outlet assigned → handle master first, then child
                        if ($is_outlet_assinged == 1) {
                            // MASTER TEMPLATE ROWS
                            $masterRowIds = DB::table('project_template_name_values_new')
                                ->where('project_template_id', $project_template_id->id)
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($template_name_head_v)])
                                ->pluck('id')
                                ->toArray();

                            // Assign master template rows
                            $existingCommonId = $this->assignUserActivityAndAuditRows(
                                $dataAssign->id,
                                $auditor_id,
                                $project_template_id->id,
                                $selected_activities,
                                $act_group_id,
                                $masterRowIds
                            );

                            // Now get child template id
                            $childProjectTemplate = DB::table('project_templates')
                                ->where('project_id', $project_template_id->project_id)
                                ->where('is_master', 0)
                                ->get();

                            if (!empty($childProjectTemplate)) {

                                foreach ($childProjectTemplate as $childProject) {

                                    //get master head id value
                                    $getHeadValues = DB::table('project_template_name_values_new')
                                        ->whereIn('id', $masterRowIds)
                                        ->pluck(
                                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$childProject->master_head_id}\"')) as head_value")
                                        )
                                        ->toArray();

                                    if ($childProject->activityType == 0) {

//                                        if ($childProject->master_head_id == $request->template_name_head_id) {

//                                            $childRowIds = DB::table('project_template_name_values_new')
//                                                ->where('project_template_id', $childProject->id)
//                                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$childProject->own_reference_head_id\"')) = ?", [trim($template_name_head_v)])
//                                                ->pluck('id')
//                                                ->toArray();

                                        $childRowIds = DB::table('project_template_name_values_new')
                                            ->where('project_template_id', $childProject->id)
                                            ->where(function ($query) use ($getHeadValues) {
                                                foreach ($getHeadValues as $value) {
                                                    $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                }
                                            })
                                            ->pluck('id')
                                            ->toArray();


                                        // Assign child template rows (same logic, different template)
                                        if (!empty($childRowIds)) {
                                            $this->assignUserActivityAndAuditRows(
                                                $dataAssign->id,
                                                $auditor_id,
                                                $childProject->id,
                                                $childProject->activity_group_name_id_or_activity_id,
                                                $act_group_id,
                                                $childRowIds
                                            );
                                        }

//                                        }

                                    } else if ($childProject->activityType == 1) {

                                        //for group activities of child template
//                                        if ($childProject->master_head_id == $request->template_name_head_id) {

//                                            $childRowIds = DB::table('project_template_name_values_new')
//                                                ->where('project_template_id', $childProject->id)
//                                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$childProject->own_reference_head_id\"')) = ?", [trim($template_name_head_v)])
//                                                ->pluck('id')
//                                                ->toArray();

                                        $childRowIds = DB::table('project_template_name_values_new')
                                            ->where('project_template_id', $childProject->id)
                                            ->where(function ($query) use ($getHeadValues) {
                                                foreach ($getHeadValues as $value) {
                                                    $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                                                }
                                            })
                                            ->pluck('id')
                                            ->toArray();

                                        $getActivityIds = ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)
                                            ->pluck('activity_id')->toArray();

                                        foreach ($getActivityIds as $childActivityId) {

                                            // Assign child template rows (same logic, different template)
                                            if (!empty($childRowIds)) {
                                                $this->assignUserActivityAndAuditRows(
                                                    $dataAssign->id,
                                                    $auditor_id,
                                                    $childProject->id,
                                                    $childActivityId,
                                                    $childProject->activity_group_name_id_or_activity_id,
                                                    $childRowIds
                                                );
                                            }

                                        }

//                                        }

                                    }


                                }
                            }

                        } else {

                            // is_outlet_assigned == 0 → Only master template
                            $rowIds = DB::table('project_template_name_values_new')
                                ->where('project_template_id', $project_template_id->id)
                                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($template_name_head_v)])
                                ->pluck('id')
                                ->toArray();

                            $this->assignUserActivityAndAuditRows(
                                $dataAssign->id,
                                $auditor_id,
                                $project_template_id->id,
                                $selected_activities,
                                $act_group_id,
                                $rowIds
                            );
                        }
                    }
                }
            }

        }

        return redirect(route('auditorAssigned.list'))->with('message', "Data Assigned Successfully");


        return redirect(route('auditorAssigned.list'))->with('message', "Data Assigned Successfully");
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


    private function AssignDataWithoutTemplateData($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds=null)
    {
        // dd('dgfdg');
        // if (empty($rowIds)) return null;

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



        return $commonId;
    }



    public function export()
    {
        $currentUserRole = auth()->user()->getRoleNames()->first();
        return Excel::download(new UsersExport($currentUserRole), 'users.xlsx');
    }
}
