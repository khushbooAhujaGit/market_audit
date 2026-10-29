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
use App\Services\DataAssignmentService;
use App\Services\VerifierAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
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
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email'
            ],
            'mobile' => 'required|unique:users|digits:10',
            'password' => 'required|min:8|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => "nullable|string|max:255",
            'district' => "nullable|string|max:255",
            'state' => "nullable|string|max:255",
            'pincode' => ['nullable', 'digits:6', 'numeric'],
            'roles' => 'required|exists:roles,name',
            'agency_select' => 'nullable|exists:users,id',
            //            'roles' => 'required|array'

        ], [
            'roles.required' => 'Please select a role.',
            'roles.exists'   => 'Please select a valid role.',
            'agency_select.exists' => 'Please select a valid agency.',
        ]);

        //khushboo 31-03-2025
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();

        if ($currentUserRole == 'Verifier' && $request->roles == 'Super Admin') {
            return redirect()->back()->withErrors(['roles' => 'You are not allowed to create a Super Admin user.'])->withInput();
        }

        $data = [
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
        ];

        if ($currentUserRole == 'Agency') {
            $data['is_agency_user'] = 1;
            $data['agency_user_id'] = Auth::user()->id;
        } elseif ($currentUserRole == 'Verifier') {
            $data['verifier_user_id'] = Auth::user()->id;
        } else {
            $agency_user_id = null;
            $is_agency_user = 0;
            if (isset($request->agency_select) && !empty($request->agency_select)) {
                $agency_user_id = $request->agency_select;
                $is_agency_user = 1;
            }
            $data['is_agency_user'] = $request->roles == 'Agency' ? 1 : $is_agency_user;
            $data['agency_user_id'] = $agency_user_id;
        }
        //khushboo 31-03-2025

        // Any failure while saving goes back to the form with the entered values and a message,
        // instead of an error page.
        try {
            DB::transaction(function () use ($data, $request) {
                $user = User::create($data);
                $user->syncRoles($request->roles);
            });
        } catch (\Throwable $e) {
            report($e);
            return redirect()->back()
                ->withErrors(['general' => 'The user could not be created. Please check the details and try again.'])
                ->withInput($request->except('password'));
        }

        return redirect("/users")->with('message', "User created successfully with roles");
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $currentUser = User::find(Auth::user()->id);
        $currentUserRole = $currentUser->getRoleNames()->first();
        $agencies = User::role('agency')->get();
        // Admin-side users can map an Auditor to a Verifier (shows on that Verifier's user list)
        $canMapVerifier = self::canMapVerifier($currentUserRole);
        $verifiers = $canMapVerifier ? User::role('Verifier')->orderBy('name')->get(['id', 'name', 'email']) : collect();
        return view('role-permission.user.edit', compact('agencies', 'user', 'roles', 'currentUserRole', 'canMapVerifier', 'verifiers'));
    }

    /** Verifiers and agencies can't reassign users to a verifier; admin-side roles can. */
    private static function canMapVerifier(?string $currentUserRole): bool
    {
        return !in_array($currentUserRole, ['Verifier', 'Agency'], true);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            "name" => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile,' . $user->id,],
            'password' => 'required|min:8|max:20',
            'address1' => 'nullable|string|max:255',
            'address2' => 'nullable|string|max:255',
            'city' => "nullable|string|max:255",
            'district' => "nullable|string|max:255",
            'state' => "nullable|string|max:255",
            'pincode' => ['nullable', 'digits:6', 'numeric'],
            'roles' => 'required',
            'verifier_select' => 'nullable|integer|exists:users,id',
        ]);

        $currentUserRole = User::find(Auth::id())->getRoleNames()->first();
        if ($request->filled('verifier_select') && !User::role('Verifier')->whereKey($request->verifier_select)->exists()) {
            return redirect()->back()->withErrors(['verifier_select' => 'Please select a valid verifier.'])->withInput();
        }
        // Same role limits as Add User: a Verifier can't make a Super Admin, an Agency only Auditor/Verifier
        if (($currentUserRole == 'Verifier' && $request->roles == 'Super Admin')
            || ($currentUserRole == 'Agency' && !in_array($request->roles, ['Auditor', 'Verifier'], true))) {
            return redirect()->back()->withErrors(['roles' => 'You are not allowed to give this role.'])->withInput();
        }

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
        // Verifier mapping (Auditors only, set from the admin side). Clearing the dropdown removes
        // the mapping; for other roles, or when edited by a Verifier/Agency, it is left unchanged.
        if (self::canMapVerifier($currentUserRole) && $request->roles == 'Auditor' && $request->has('verifier_select')) {
            $data['verifier_user_id'] = $request->filled('verifier_select') ? (int) $request->verifier_select : null;
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

    /** Roles a user with $creatorRole may give to new users — same as the Add User form. */
    public static function assignableRoleNames(?string $creatorRole): \Illuminate\Support\Collection
    {
        return Role::pluck('name')->filter(fn($r) => match ($creatorRole) {
            'Agency'   => in_array($r, ['Auditor', 'Verifier'], true),
            'Verifier' => $r !== 'Super Admin',
            default    => true,
        })->values();
    }

    /**
     * Bulk user upload. Each row is checked with the same rules as the Add User form
     * (plus duplicates within the sheet); valid rows are created, invalid rows are
     * skipped and listed with the reason in a downloadable error file.
     */
    public function userUpload(Request $request)
    {
        $request->validate([
            'user_excel' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ]);

        $columns = ['Name', 'Mobile No', 'Email ID', 'Address 1', 'Address 2', 'City', 'District', 'State', 'Pincode', 'Password', 'Role', 'Agency'];

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('user_excel')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);
        array_shift($rows); // header

        // Excel keeps numbers (mobile, pincode, numeric passwords) as numbers: read them as text
        $text = function ($v) {
            if ($v === null) return '';
            if (is_float($v) && floor($v) == $v && abs($v) < 1e15) $v = (int) $v;
            return trim((string) $v);
        };

        // Roles this admin may assign — same as the Add User form's role list
        $currentUser = User::find(Auth::id());
        $currentUserRole = $currentUser->getRoleNames()->first();
        $allowedRoles = self::assignableRoleNames($currentUserRole)
            ->mapWithKeys(fn($r) => [mb_strtolower($r) => $r]);

        // Agency users an Auditor/Verifier can be linked to (by email), as on the Add User form.
        // Agency users' own uploads are always linked to themselves instead.
        $agencyIdByEmail = User::role('Agency')->whereNotNull('email')->get(['id', 'email'])
            ->mapWithKeys(fn($u) => [mb_strtolower(trim($u->email)) => $u->id]);

        // First sheet row for each email / mobile, to catch duplicates within the sheet
        $firstRowByEmail = [];
        $firstRowByMobile = [];

        $created = 0;
        $errorRows = [];

        foreach ($rows as $index => $raw) {
            $sheetRow = $index + 2;
            // 12 columns; older templates without the Agency column read it as blank
            $values = array_map($text, array_pad(array_slice($raw, 0, 12), 12, null));
            if (implode('', $values) === '') continue; // blank row

            [$name, $mobile, $email, $address1, $address2, $city, $district, $state, $pincode, $password, $roleRaw, $agencyRaw] = $values;

            // Repeated within this sheet: report that instead of "already taken"
            // (the first occurrence may have just been created by this upload)
            $emailKey  = mb_strtolower($email);
            $dupEmail  = $email !== '' && isset($firstRowByEmail[$emailKey]);
            $dupMobile = $mobile !== '' && isset($firstRowByMobile[$mobile]);

            $validator = Validator::make(
                compact('name', 'email', 'mobile', 'password', 'address1', 'address2', 'city', 'district', 'state', 'pincode') + ['roles' => $roleRaw],
                [
                    'name'     => ['required', 'string', 'max:255'],
                    'email'    => array_merge(['required', 'email', 'max:255'], $dupEmail ? [] : ['unique:users,email']),
                    'mobile'   => array_merge(['required', 'digits:10'], $dupMobile ? [] : ['unique:users,mobile']),
                    'password' => ['required', 'min:8', 'max:20'],
                    'address1' => ['nullable', 'string', 'max:255'],
                    'address2' => ['nullable', 'string', 'max:255'],
                    'city'     => ['nullable', 'string', 'max:255'],
                    'district' => ['nullable', 'string', 'max:255'],
                    'state'    => ['nullable', 'string', 'max:255'],
                    'pincode'  => ['nullable', 'digits:6', 'numeric'],
                    'roles'    => ['required'],
                ],
                [],
                ['roles' => 'role', 'address1' => 'address 1', 'address2' => 'address 2']
            );
            $errors = $validator->errors()->all();

            $role = $allowedRoles->get(mb_strtolower($roleRaw));
            if ($roleRaw !== '' && !$role) {
                $errors[] = "The role \"{$roleRaw}\" is not a role you can assign.";
            }

            $agencyId = null;
            if ($agencyRaw !== '' && $currentUserRole !== 'Agency') {
                if ($role && !in_array($role, ['Auditor', 'Verifier'], true)) {
                    $errors[] = 'An agency can only be set for an Auditor or Verifier.';
                } elseif (!($agencyId = $agencyIdByEmail->get(mb_strtolower($agencyRaw)))) {
                    $errors[] = "\"{$agencyRaw}\" is not the email of an agency user.";
                }
            }

            if ($dupEmail) {
                $errors[] = "The email is repeated in this sheet (first used on row {$firstRowByEmail[$emailKey]}).";
            }
            if ($dupMobile) {
                $errors[] = "The mobile number is repeated in this sheet (first used on row {$firstRowByMobile[$mobile]}).";
            }
            if ($email !== '') $firstRowByEmail[$emailKey] ??= $sheetRow;
            if ($mobile !== '') $firstRowByMobile[$mobile] ??= $sheetRow;

            if ($errors) {
                $errorRows[] = array_merge($values, [implode(' ', $errors), $sheetRow]);
                continue;
            }

            // Same account links the Add User form sets for the person creating the user
            $userData = [
                'name'          => $name,
                'email'         => $email,
                'mobile'        => $mobile,
                'password'      => Hash::make($password),
                'password_info' => $password,
                'address1'      => $address1 ?: null,
                'address2'      => $address2 ?: null,
                'city'          => $city ?: null,
                'district'      => $district ?: null,
                'state'         => $state ?: null,
                'pincode'       => $pincode ?: null,
            ];
            if ($currentUserRole === 'Agency') {
                $userData += ['is_agency_user' => 1, 'agency_user_id' => $currentUser->id];
            } elseif ($currentUserRole === 'Verifier') {
                $userData += ['verifier_user_id' => $currentUser->id];
            } else {
                $userData += [
                    'is_agency_user' => ($role === 'Agency' || $agencyId) ? 1 : 0,
                    'agency_user_id' => $agencyId,
                ];
            }

            try {
                $user = User::create($userData);
                $user->syncRoles($role);
                $created++;
            } catch (\Throwable $e) {
                if (isset($user) && $user->exists && !$user->roles()->exists()) $user->delete();
                $errorRows[] = array_merge($values, ['Could not be saved: ' . $e->getMessage(), $sheetRow]);
            }
            unset($user);
        }

        if (!$errorRows) {
            return redirect("/users")->with('message', "{$created} user(s) uploaded successfully");
        }

        // Error file: same columns as the template (so it can be corrected and uploaded
        // again as is) plus the reason and the original sheet row.
        $token = (string) \Illuminate\Support\Str::uuid();
        $w = new \App\Services\XlsxStream();
        $w->beginSheet('Errors', [22, 14, 30, 24, 24, 14, 14, 14, 10, 14, 12, 30, 70, 10], 1);
        $w->row(array_merge($columns, ['Error', 'Sheet Row']), \App\Services\XlsxStream::STYLE_HEADER);
        foreach ($errorRows as $row) $w->row($row);
        $w->endSheet();
        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('user_upload_errors');
        $w->save(storage_path("app/user_upload_errors/{$token}.xlsx"));

        session(['user_upload_error_file' => $token]);
        return redirect()->route('users.upload.view')->with('upload_result', [
            'created' => $created,
            'skipped' => count($errorRows),
            'token'   => $token,
        ]);
    }

    public function downloadUserUploadErrors(string $token)
    {
        abort_unless(session('user_upload_error_file') === $token && preg_match('/^[0-9a-f-]{36}$/', $token), 404);
        $path = storage_path("app/user_upload_errors/{$token}.xlsx");
        abort_unless(is_file($path), 404);
        return response()->download($path, 'user_upload_errors_' . date('Ymd_His') . '.xlsx')->deleteFileAfterSend(true);
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

        // Assigning on an outlet (child) template directly: every selected outlet must belong to a
        // parent row (own template head value matching a master template head value), otherwise
        // nothing is assigned.
        if ($projectTemplate_info->is_master == 0 && !empty($request->template_name_head_values)) {
            $selectedRowIds = [];
            foreach ((array) $request->template_name_head_values as $template_name_head_v) {
                $selectedRowIds = array_merge($selectedRowIds, DB::table('project_template_name_values_new')
                    ->where('project_template_id', $projectTemplate_info->id)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($template_name_head_v)])
                    ->pluck('id')->all());
            }
            $parentCheck = \App\Services\OutletDistributorMatch::rowsWithoutParent($projectTemplate_info, array_unique($selectedRowIds));
            if (!empty($parentCheck['missing'])) {
                return redirect()->back()
                    ->withErrors(['assign' => \App\Services\OutletDistributorMatch::missingParentMessage($parentCheck)])
                    ->withInput();
            }
        }

        // Verifiers get the same rows as the auditors assigned in this submission, and are
        // linked to those auditors for rows the auditors create later.
        $verifierIds = array_values(array_filter(array_map('intval', (array) $request->verify_users_names)));
        $isVerifier = Auth::user()->hasRole('Verifier');
        // A Verifier assigning data is always its only verifier, and can assign only the auditors it created.
        if ($isVerifier) {
            $verifierIds = [Auth::id()];
            $ownAuditorIds = User::where('verifier_user_id', Auth::id())->role('Auditor')->pluck('id')->all();
            $request->merge(['auditor_names' => array_values(array_intersect(array_map('intval', (array) $request->auditor_names), $ownAuditorIds))]);
        }
        $auditorIds = array_values(array_filter(array_map('intval', (array) $request->auditor_names)));

        // Without auditors (admin only) the data goes to the selected verifiers; they assign their auditors later.
        $isAgency = Auth::user()->hasRole('Agency');
        if (empty($auditorIds) && ($isVerifier || $isAgency || empty($verifierIds))) {
            return redirect()->back()->withErrors(['auditor_names' => $isVerifier || $isAgency
                ? 'Select at least one auditor.'
                : 'Select at least one auditor, or only a verifier to assign the data to the verifier.'])->withInput();
        }

        $project_info = Project::findOrFail($request->project_id);
        $act_group_id = $request->activity_group_name_id ?? null;
        $project_template_id = $projectTemplate_info;
        $companyUserId = $request->has('company_user') ? $request->company_user : null;
        $agencyId = $request->has('agency_list') ? $request->agency_list : null;
        $activityIds = array_values(array_unique(array_map('intval', (array) $request->activity_id)));

        $rowIdsOfValue = fn($value) => DB::table('project_template_name_values_new')
            ->where('project_template_id', $project_template_id->id)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$request->template_name_head_id\"')) = ?", [trim($value)])
            ->pluck('id')
            ->toArray();

        // A Verifier assigns only inside what an admin gave them: that template, its activities and rows.
        if ($isVerifier) {
            $given = \App\Services\VerifierAssignment::assignedDataAssigns(Auth::id())->where('project_template_id', $project_template_id->id);
            $activityIds = array_values(array_intersect($activityIds, $given->pluck('activity_id')->map(fn($id) => (int) $id)->all()));
            if ($given->isEmpty() || empty($activityIds)) {
                return redirect()->back()->withErrors(['assign' => 'This project, template or activity has not been assigned to you.'])->withInput();
            }
            if (!empty($request->template_name_head_values)) {
                $selectedRowIds = collect($request->template_name_head_values)->flatMap($rowIdsOfValue)->unique()->values()->all();
                $ownRows = collect($activityIds)->flatMap(fn($activityId) => \App\Services\VerifierAssignment::ownRowIds(Auth::id(), $project_template_id->id, $activityId, $selectedRowIds));
                if ($ownRows->isEmpty()) {
                    return redirect()->back()->withErrors(['assign' => 'None of the selected values are assigned to you.'])->withInput();
                }
            }
            // Same agency / company user as the admin's assignment
            $agencyId = $agencyId ?: $given->first()->agency_id;
            $companyUserId = $companyUserId ?: $given->first()->company_user_id;
        }

        DataAssignmentService::ensureTemplateVerifiers($verifierIds, $projectTemplate_info, $is_outlet_assinged == 1);

        foreach ($activityIds as $selected_activities) {
            $dataAssign = DataAssignmentService::findOrCreateDataAssign(
                $project_info, (int) $selected_activities, $act_group_id ? (int) $act_group_id : null,
                (int) $request->template_name_id, $project_template_id->id, $request->template_name_head_id,
                $is_outlet_assinged, $companyUserId, $agencyId
            );
            \App\Services\VerifierAssignment::recordDataAssign($verifierIds, $dataAssign->id, Auth::id());

            if (isset($request->template_name_head_values) && !empty($request->template_name_head_values)) {
                foreach ($request->template_name_head_values as $template_name_head_v) {
                    $rowIds = $rowIdsOfValue($template_name_head_v);
                    if ($isVerifier) {
                        $rowIds = \App\Services\VerifierAssignment::ownRowIds(Auth::id(), $project_template_id->id, (int) $selected_activities, $rowIds);
                    }

                    if (empty($auditorIds)) {
                        DataAssignmentService::assignToVerifiersOnly($verifierIds, $project_template_id, (int) $selected_activities, $rowIds, $is_outlet_assinged == 1);
                        continue;
                    }

                    foreach ($auditorIds as $auditor_id) {
                        DataAssignmentService::assignRows($dataAssign->id, $auditor_id, $project_template_id->id, $selected_activities, $act_group_id, $rowIds, $verifierIds);

                        // If outlet assigned → also the child (outlet) template rows
                        if ($is_outlet_assinged == 1) {
                            DataAssignmentService::assignOutletChildren($dataAssign->id, $auditor_id, $project_template_id, $rowIds, $act_group_id, $verifierIds, $isVerifier ? Auth::id() : null);
                        }
                    }
                }
            }
            else if ($projectTemplate_info->with_data == 0) {
                //if template data not uploaded
                if (!empty($auditorIds)) {
                    foreach ($auditorIds as $auditor_id) {
                        DataAssignmentService::assignWithoutData($dataAssign->id, $auditor_id, $project_template_id->id, $selected_activities, $act_group_id, null, $verifierIds);
                        if ($is_outlet_assinged == 1) {
                            $childProjectTemplate = DB::table('project_templates')
                                ->where('project_id', $project_template_id->project_id)
                                ->where('is_master', 0)
                                ->get();
                            foreach ($childProjectTemplate as $childProject) {
                                if ($childProject->activityType == 0) {
                                    DataAssignmentService::assignWithoutData($dataAssign->id, $auditor_id, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $childProject->id, $act_group_id, $verifierIds);
                                } else if ($childProject->activityType == 1) {
                                    $getActivityIds = ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)
                                        ->pluck('activity_id')->toArray();
                                    foreach ($getActivityIds as $childActivityId) {
                                        DataAssignmentService::assignWithoutData($dataAssign->id, $auditor_id, $childProject->id, $childActivityId, $childProject->activity_group_name_id_or_activity_id, null, $verifierIds);
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



    private function assignUserActivityAndAuditRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds, array $verifierIds = [])
    {
        return DataAssignmentService::assignRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds, $verifierIds);
    }


    private function AssignDataWithoutTemplateData($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds=null, array $verifierIds = [])
    {
        return DataAssignmentService::assignWithoutData($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds, $verifierIds);
    }



    public function export()
    {
        $currentUserRole = auth()->user()->getRoleNames()->first();
        return Excel::download(new UsersExport($currentUserRole), 'users.xlsx');
    }
}
