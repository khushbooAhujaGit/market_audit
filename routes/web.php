<?php

use App\Http\Controllers\Auth\CustomLoginController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Masters\ReportController;
use App\Http\Controllers\Masters\InfiltrationReportController;
use App\Http\Controllers\Masters\ActivityController;
use App\Http\Controllers\Masters\ActivityGroupController;
use App\Http\Controllers\Masters\AuditorAssignedDataController;
use App\Http\Controllers\Masters\CompanyController;
use App\Http\Controllers\Masters\CompanyTypeController;
use App\Http\Controllers\Masters\DropdownController;
use App\Http\Controllers\Masters\ExcelFormatsController;
use App\Http\Controllers\Masters\ProjectController;
use App\Http\Controllers\Masters\ProjectTemplateController;
use App\Http\Controllers\Masters\ProjectTypeController;
use App\Http\Controllers\Masters\QuestionController;
use App\Http\Controllers\Masters\RemarkController;
use App\Http\Controllers\Masters\SubjectDropdownController;
use App\Http\Controllers\Masters\SubjectiveQuestionController;
use App\Http\Controllers\Masters\TaskHandlerController;
use App\Http\Controllers\Masters\TemplateNameController;
use App\Http\Controllers\Masters\UnitController;
use App\Http\Controllers\Masters\VerifierController;
use App\Http\Controllers\Masters\ZoneController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PrivacyPolicyController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Auth;

// ── Public routes (no auth required) ─────────────────────────────────────────

Route::get('privacy_policy', [PrivacyPolicyController::class, 'index']);

// Laravel built-in auth scaffold (login page, password reset, etc.)
Auth::routes(['login' => true, 'register' => false]);

Route::post('login', [CustomLoginController::class, 'login'])->name('custom_login');
Route::get('/log-out', [\App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout-route');

// Misc / utility (keep public)
Route::get('/send-test-mail', function () {
    $userInfo = (object)['email' => 'rajsrajput1010@gmail.com'];
    Mail::raw('This is the testing mail', function ($message) use ($userInfo) {
        $message->to($userInfo->email)->subject('Test Email');
    });
    return 'Test email sent!';
});

Route::get('clear', function () {
    return 'clear';
});

Route::get('/backup/files', [BackupController::class, 'listFiles']);

// Public — no login required (embedded in Excel reports shared with external users)
Route::get('/report/download-images-zip/{answer_id}',
    [\App\Http\Controllers\Masters\ReportController::class, 'downloadImagesZip'])
    ->name('report.download.images.zip');

// ── All protected routes — session expired → redirect to login ────────────────
// auth.session re-validates the user on every request: if the DB record was deleted
// while the session cookie still exists, the session is invalidated and the user is
// redirected to login instead of crashing with a 500 error.
Route::middleware(['auth', 'auth.session'])->group(function () {

    Route::get('/', function () {
        if (Auth::user()->hasRole('Auditor')) {
            return redirect()->route('user.projects');
        }
        return view('dashboard');
    });

    Route::get('/home',      [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

    // ── Permissions ───────────────────────────────────────────────────────────
    Route::resource('permissions', PermissionController::class);

    // ── Roles ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:Role-Permission')->group(function () {
        Route::resource('roles', RoleController::class);
        Route::post('roles/delete',                           [RoleController::class, 'destroy'])->name('role.destroy');
        Route::get('roles/{roleId}/give-permissions',         [RoleController::class, 'view_addPermissionToRole']);
        Route::put('role/give-permissions/{roleId}',          [RoleController::class, 'addPermissionToRole'])->name('role.givePermissions');
        Route::post('permission/delete',                      [PermissionController::class, 'destroy'])->name('permission.destroy');
    });

    // ── Users ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:Users')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('user/delete',                            [UserController::class, 'destroy'])->name('user.destroy');
        Route::get('user/upload_user',                        [UserController::class, 'view_user_upload'])->name('users.upload.view');
        Route::post('user/bluk_data_upload',                  [UserController::class, 'userUpload'])->name('user.blukUpload');
        Route::get('/user_export',                            [UserController::class, 'export'])->name('users.export');
    });
    Route::post('user/assign_data', [UserController::class, 'assignDataToUsers'])->name('assignToUsers.data');

    // ── Auditor Assigned Data ─────────────────────────────────────────────────
    Route::prefix('auditorAssigned')->controller(AuditorAssignedDataController::class)->group(function () {
        Route::get('/',                        'auditor_assigned_data')->name('auditorAssigned.list');
        Route::post('/assigned/users',         'get_assigned_auditors')->name('auditors.show');
        Route::post('/destroy',                'assignedDestroy')->name('assigned_data.destroy');
        Route::get('/assigned_data_export',    'auditorAssignDataExport')->name('assigned_data.export');
        Route::post('/destroy_assignment',     'destroy_assignment')->name('assigned_auditor.delete');
    });

    // ── Activities ────────────────────────────────────────────────────────────
    Route::prefix('activities')->controller(ActivityController::class)->group(function () {
        Route::get('/',                                            'index')->name('activities.list');
        Route::get('/create',                                      'create')->name('activities.create');
        Route::post('/store',                                      'store')->name('activities.store');
        Route::post('/destroy',                                    'destroy')->name('activities.destroy');
        Route::get('edit/{id}',                                    'edit')->name('activities.edit');
        Route::put('update/{id}',                                  'update')->name('activity.update');
        Route::get('/questions/{activity_id}',                     'view_question')->name('activities.question');
        Route::get('/questions/{activity_id}/export',              'exportActivityQuestions')->name('activities.questions.export');
        Route::get('/questions/{activity_id}/bulk-add',            'bulkAddQuestions')->name('activities.questions.bulk_add');
        Route::post('/questions/{activity_id}/bulk-store',         'bulkStoreQuestions')->name('activities.questions.bulk_store');
        Route::post('/questions/{activity_id}/import-excel',       'importQuestionsFromExcel')->name('activities.questions.import_excel');
        Route::post('/questions/{activity_id}/reorder',            'reorderQuestions')->name('activities.questions.reorder');
        Route::post('/info',                                       'get_activity_info')->name('activity.info');
        Route::get('questions/options/{id}',                       'dropdown_options')->name('activity.question.dropdown');
        Route::post('question/option/destroy',                     'destroy_question_option')->name('activity.question.option.destroy');
        Route::get('export_activity',                              'exportActivityData')->name('activities.export');
        Route::post('get_parent_question_dropdown',                'getParentQuestionDropdown')->name('get_parent_question_dropdown');
        Route::get('questions/sub-questions/{question_id}',        'subQuestions')->name('activity.question.sub_questions');
        Route::post('question/sub-question/store',                 'storeSubQuestion')->name('activity.question.sub_question.store');
        Route::post('question/sub-question/destroy',               'destroySubQuestion')->name('activity.question.sub_question.destroy');
        Route::post('question/sub-question/update-sequence',       'updateSubQuestionSequence')->name('activity.question.sub_question.update_sequence');

        Route::prefix('subjective')->controller(SubjectiveQuestionController::class)->group(function () {
            Route::get('/',                             'index')->name('subjectiveQuestion.list');
            Route::get('questions/subjects/{id}',       'subjects')->name('activity.question.subjective');
            Route::post('/questions/subject',           'subject_editOrUpdate')->name('subject.UpdateOrCreate');
            Route::post('question/destroy',             'subject_question_destroy')->name('subjectQuestion.destroy');

            Route::prefix('subject')->controller(SubjectDropdownController::class)->group(function () {
                Route::get('options/{id}',                      'view_subject_options')->name('subject.options');
                Route::post('/questions/subject/option',        'subject_option_editOrUpdate')->name('subject_option.UpdateOrCreate');
                Route::post('option/destroy',                   'destroy')->name('subjectOptionDropdown.destroy');
                Route::post('subject/options',                  'get_subject_options')->name('getQuestion.subject.options');
                Route::get('dropdowns/{id}',                    'get_subject_dropdowns')->name('getQuestion.subject.dropdown');
                Route::post('subject/dropdown/store',           'store_subject_dropdowns')->name('getQuestion.subject.dropdown.UpdateOrCreate');
            });
        });
    });

    // ── Dropdown ──────────────────────────────────────────────────────────────
    Route::controller(DropdownController::class)->group(function () {
        Route::get('/dropdown',                          'index')->name('dropdown.list');
        Route::get('/dropdown/create',                   'create')->name('dropdown.create');
        Route::post('/dropdown/store',                   'store')->name('dropdown.store');
        Route::get('/dropdown/excel/import',             'excel_option')->name('import.options_dropdown');
        Route::post('/dropdown/excel/import_store',      'excel_option_store')->name('import.options_dropdown.store');
        Route::post('/activity/dropdowns',               'get_activity_dropdown_questions')->name('get_activity_dropdown_questions');
        Route::post('activity/questions/dropdownOption', 'dropdown_options_editOrUpdate')->name('dropDown.UpdateOrCreate');
    });

    // ── Questions ─────────────────────────────────────────────────────────────
    Route::prefix('questions')->controller(QuestionController::class)->group(function () {
        Route::post('/store',                         'store')->name('question.store');
        Route::put('/update',                         'update')->name('question.update');
        Route::post('/destroy',                       'destroy')->name('activity.question.destroy');
        Route::get('question/{id}/parent-links',      'getParentLinks')->name('question.parent_links');
        Route::get('question/{id}/sub-questions-json','getSubQuestionsJson')->name('question.sub_questions_json');
    });

    // ── Activity Groups ───────────────────────────────────────────────────────
    Route::prefix('activity_group')->controller(ActivityGroupController::class)->group(function () {
        Route::get('/',                      'index')->name('activityGroup.list');
        Route::get('/create',                'create_group')->name('activityGroup.create');
        Route::get('/group/edit/{id}',       'activity_group_edit')->name('activity.group.edit');
        Route::post('/store_activity_group', 'store_activity_group')->name('create_activity_group');
        Route::post('/destroy',              'activityGroupDestroy')->name('activity_group.destroy');
        Route::post('/activity/destroy',     'groupActivityDestroy')->name('group_activity.destroy');
        Route::post('/update_group/{id}',    'update_activity_group')->name('update_activity_group');
        Route::post('activities',            'group_activites')->name('group_activities');
    });

    // ── Template Names ────────────────────────────────────────────────────────
    Route::prefix('templatenames')->controller(TemplateNameController::class)->group(function () {
        Route::get('/',                            'index')->name('templateName.list');
        Route::get('/create',                      'create')->name('templateName.create');
        Route::post('/store',                      'store')->name('templateName.store');
        Route::post('templateName_data/store',     'upload_template_data')->name('templateNameData.store');
        Route::get('templatenames/edit/{id}',      'edit')->name('templateName.edit');
        Route::get('templatenames/download/{id}',  'downloadExcel')->name('templateName.download');
        Route::put('templatenames/update/{id}',    'update')->name('templateName.update');
        Route::post('/templateName/destroy',       'destroy')->name('templateName.destroy');
        Route::post('/templateName/get_heads',     'getTemplateHeads')->name('template_name.getHeads');
        Route::get('template_export',              'exportTemplateData')->name('template.export');
    });

    // ── Excel Format Downloads ────────────────────────────────────────────────
    Route::prefix('download_excel_format')->controller(ExcelFormatsController::class)->group(function () {
        Route::get('user_bulk_upload_template',          'userUploadTemplateDownload')->name('userUploadTemplate.download');
        Route::get('activity_template',                  'activityTemplateDownload')->name('activityTemplate.download');
        Route::get('/download-heads-template',           'downloadTemplateHeadsTemplate')->name('download.TemplateName.Template');
        Route::get('/download-dropdown_option_templates','downloadDropdownOptionsFormat')->name('dropdownOptionTemplate.download');
    });

    // ── Company Types ─────────────────────────────────────────────────────────
    Route::middleware('permission:Company Types')->prefix('company_type')->controller(CompanyTypeController::class)->group(function () {
        Route::get('/',           'index')->name('company_type.list');
        Route::get('/create',     'create')->name('company_type.create');
        Route::post('/store',     'store')->name('company_type.store');
        Route::get('/edit/{id}',  'edit')->name('company_type.edit');
        Route::put('/update/{id}','update')->name('company_type.update');
        Route::post('/destroy',   'destroy')->name('company_type.destroy');
    });

    // ── Companies ─────────────────────────────────────────────────────────────
    Route::prefix('company')->controller(CompanyController::class)->group(function () {
        Route::middleware('permission:Companies')->group(function () {
            Route::get('/',           'index')->name('companies.list');
            Route::get('/create',     'create')->name('company.create');
            Route::post('/store',     'store')->name('company.store');
            Route::get('/edit/{id}',  'edit')->name('company.edit');
            Route::put('/update/{id}','update')->name('company.update');
            Route::post('/destroy',   'destroy')->name('company.destroy');
        });
        Route::get('/company_verification',                      'companyVerificationPage')->name('company.verifyPage');
        Route::get('/pdf_report',                                'pdfReportPage')->name('company.pdfReport');
        Route::post('/get_zone_data',                            'getCompanyZoneData')->name('company.zoneData');
        Route::post('/get_unit_data',                            'getCompanyUnitData')->name('company.unitData');
        Route::post('/get_project_data',                         'getCompanyProjectData')->name('company.projectData');
        Route::post('/get_project_master_questions',             'getProjectTemplateActivityQuestion')->name('company.getQuestionData');
        Route::get('/test_page/{data}',                          'testPage')->name('company.testPage');
        Route::post('/template_header_view_data',                'TemplateHeaderViewData')->name('company.getTemplateHeaderViewData');
        Route::get('/get_distributor_child_outlets/{id}/{templateid}', 'getDistributorOutletData')->name('getDistributorOutletData');
        Route::post('/get_distributor_child_outlets_answer_data','getQuestionAnswersofChildTemplate')->name('getQuestionAnswersofChildTemplate');
    });

    Route::post('/get_project_master_activity', [CompanyController::class, 'getProjectTemplateActivity'])->name('company.projectActivity');
    Route::post('/get_distributor_data',        [CompanyController::class, 'getDistributorData'])->name('company.getDistributors');
    Route::post('/project_template_data',       [CompanyController::class, 'projectTemplateData'])->name('company.projectTemplateData');

    // ── Zones ─────────────────────────────────────────────────────────────────
    Route::prefix('zone')->controller(ZoneController::class)->group(function () {
        Route::get('/',           'index')->name('zone.list');
        Route::get('/create',     'create')->name('zone.create');
        Route::post('/store',     'store')->name('zone.store');
        Route::get('/edit/{id}',  'edit')->name('zone.edit');
        Route::put('/update/{id}','update')->name('zone.update');
        Route::post('/destroy',   'destroy')->name('zone.destroy');
    });

    // ── Units ─────────────────────────────────────────────────────────────────
    Route::prefix('unit')->controller(UnitController::class)->group(function () {
        Route::get('/',           'index')->name('unit.list');
        Route::get('/create',     'create')->name('unit.create');
        Route::post('/store',     'store')->name('unit.store');
        Route::get('/edit/{id}',  'edit')->name('unit.edit');
        Route::put('/update/{id}','update')->name('unit.update');
        Route::post('/destroy',   'destroy')->name('unit.destroy');
        Route::post('/company_zones', 'get_company_zones')->name('get_company_zones');
    });

    // ── Project Types ─────────────────────────────────────────────────────────
    Route::prefix('project_type')->controller(ProjectTypeController::class)->group(function () {
        Route::get('/list',       'index')->name('project_type.list');
        Route::get('/create',     'create')->name('project_type.create');
        Route::post('/store',     'store')->name('project_type.store');
        Route::get('/edit/{id}',  'edit')->name('project_type.edit');
        Route::put('/update/{id}','update')->name('project_type.update');
        Route::post('/destroy',   'destroy')->name('project_type.destroy');
    });

    // ── Projects ──────────────────────────────────────────────────────────────
    Route::controller(ProjectController::class)->group(function () {
        Route::get('/projects',                              'index')->name('project.list');
        Route::get('search',                                 'searchProjects')->name('project.search');
        Route::get('/projects/create',                       'create')->name('project.create');
        Route::post('/projects/store',                       'store')->name('project.store');
        Route::get('projects/edit/{id}',                     'edit')->name('project.edit');
        Route::put('/projects/update/{id}',                  'update')->name('project.update');
        Route::post('/projects/destroy',                     'destroy')->name('project.destroy');
        Route::get('/upload/data',                           'upload_data_view')->name('projectData.upload');
        Route::post('projectData/store',                     'upload_project_template_data')->name('projectData.store');
        Route::post('projectData/add',                       'add_project_template_data')->name('projectData.add');
        Route::get('project_view/data',                      'project_upload_data_view')->name('project_template_uploaded_data.view');
        Route::match(['get','post'], 'project_template_render_uploaded/data', 'render_project_upload_data_view')->name('project_template_data.view');
        Route::get('project_data_edit/{id}',                 'project_data_item')->name('project_data.edit');
        Route::post('project_data_edit/{id}',                'project_data_item_update')->name('project_data.update');
        Route::get('project_data_activity_mapping',          'project_data_activity_mapping')->name('project.data_activity_mapping');
        Route::get('distributor_setting',                    'distributor_setting')->name('distributor_setting');
        Route::post('distributor_set',                       'distributor_set')->name('distributor.set');
        Route::post('project_data_activity_mapping',         'project_temp_activity_map')->name('project.data.activityGroup.map');
        Route::post('project_master_template_make',          'make_master_template')->name('project.master_template.make');
        Route::post('get_project_master_template_row_info',  'master_template_row_info')->name('get_project_master_templates.rowInfo');
        Route::post('/zone_units',                           'get_zones_units')->name('get_zones_units');
        Route::post('/project/info',                         'get_project_info')->name('get_project_info');
        Route::get('/group_activities/{group_id}',           'getGroupActivities')->name('get_group_activities');
        Route::post('/unit_projects',                        'get_unit_projects')->name('get_unit_projects');
        Route::get('assign_data/create',                     'assign_create')->name('assign_data.create');
        Route::post('/agency_user',                          'get_agency_users')->name('get_agency_users');
        Route::get('/set_compliance',                        'set_compliance')->name('set_compliance');
        Route::get('/set_compliance_new',                    'set_compliance_new')->name('set_compliance_new');
        Route::post('/store_compliance_data',                'storeComplianceData')->name('compliance.store');
        Route::post('/get-project-templates',                'getProjectTemplate')->name('getProjectTemplate');
        Route::post('/get-questions',                        'getQuestions')->name('get-questions');
        Route::post('/get-question-details',                 'getQuestionsTypeData')->name('get-question-details');
        Route::post('/get-subjective-dropdown',              'getSUbjectQuestionsDropdown')->name('get-subjective-dropdown');
        Route::post('/get_project_activities',               'getProjectActivities')->name('get_project_activities');
        Route::get('/non_compliance_data',                   'nonComplaincePage')->name('company.nonCompliancePage');
        Route::post('/non_compliance_data',                  'nonComplainceData')->name('company.nonComplainceData');
        Route::get('/compliance_action_taken',               'complianceActionTakenPage')->name('compliance_action_taken');
        Route::get('/getProjectTemplateData',                'getProjectTemplateData')->name('getProjectTemplateData');
        Route::post('/add_action_taken',                     'addActionTaken')->name('add_action_taken');
        Route::get('/export_data',                           'exportProjectData')->name('project.export');
        Route::get('/export_template_data/{projectTemplateID}', 'exportProjectTemplateData')->name('project_template_data.export');
        Route::get('/get_all_template_data',                 'getNewProjectTemplateValues')->name('get_all_template_data');
    });

    // ── Project Templates ─────────────────────────────────────────────────────
    Route::prefix('projectTemplate')->controller(ProjectTemplateController::class)->group(function () {
        Route::post('/info',                          'projectTemplateInfo')->name('projectTemplate.info');
        Route::post('/project_report/info',           'projectTemplateInfoProjectReport')->name('projectTemplateProjectReport.info');
        Route::post('/get_projectTemplate_HeadValues','projectTemplateHeadValues')->name('project.templateHead_values');
    });

    // ── Verifiers ─────────────────────────────────────────────────────────────
    Route::prefix('Verifier')->controller(VerifierController::class)->group(function () {
        Route::get('/my_verifications',                                     'user_verification')->name('user.verification.list');
        Route::get('/verifiy/group_activity/{pt}/{g}/{s}/{a}',              'group_verification_data')->name('activityGroup.verification.view');
        Route::get('/verifiy/activity/{pt}/{a}',                            'activity_verification_data')->name('activity.verification.view');
        Route::get('/data_to_verify/{r}/{a}/{g?}/{seq?}',                   'data_to_verify')->name('data_to_verify');
        Route::post('auditor_activity_question_verification',               'activity_answer_verify')->name('verifier.activityQuestionAnswers');
        Route::get('verify_template/{r}/{a}/{g?}',                          'viewTemplateFile')->name('verify_template');
        Route::get('report_page',                                           'reportPage')->name('report_page');
        Route::post('download_pdf',                                         'downloadPdfTemplate')->name('pdf_template');
        Route::post('/delete-temp-pdf',                                     'deleteTempPdf')->name('delete_temp_pdf');
        Route::post('download_pdf_zip',                                     'downloadPdfZip')->name('pdf_template_zip');
        Route::post('download_pdf_zip_group',                               'downloadPdfZipGroup')->name('pdf_template_zip_group');
        Route::post('export_answer_pdfs',                                   'exportAnswerPdfs')->name('export_answer_pdfs');
        Route::get('download_all_images',                                   'downloadAllImages')->name('verifier.download_all_images');
    });

    // ── Remarks ───────────────────────────────────────────────────────────────
    Route::prefix('remark')->controller(RemarkController::class)->group(function () {
        Route::get('/',           'index')->name('remark.list');
        Route::get('/create',     'create')->name('remark.create');
        Route::post('/store',     'store')->name('remark.store');
        Route::get('/edit/{id}',  'edit')->name('remark.edit');
        Route::post('/destroy',   'destroy')->name('remark.destroy');
        Route::put('/update/{id}','update')->name('remark.update');
        Route::get('/export',     'export')->name('remark.export');
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::prefix('report')->controller(ReportController::class)->group(function () {
        Route::get('/',                                'index')->name('view-report');
        Route::get('/project_report',                  'project_report')->name('project-report');
        Route::post('/project_report',                 'project_distributor_report')->name('project-distributor-report');
        Route::post('/download',                       'get_report')->name('get_report.download');
        Route::get('get_report_mail/{project}/{template}/{activity?}', 'getReportMail')->name('getReportMail');
    });

    // ── Infiltration Audit Report (external Python tool integration) ───────────
    Route::prefix('infiltration-report')->controller(InfiltrationReportController::class)->group(function () {
        Route::get('/',                          'index')->name('infiltration.index');
        Route::post('/generate',                 'generate')->name('infiltration.generate');
        Route::post('/download',                 'download')->name('infiltration.download');
        Route::post('/upload-onedrive-personal',  'uploadToPersonalOneDrive')->name('infiltration.upload_onedrive_personal');
        Route::post('/upload-onedrive-shared',    'uploadToSharedOneDrive')->name('infiltration.upload_onedrive_shared');
    });

    // ── OneDrive (per-user delegated Graph API access, for the Infiltration Report's
    //    "Upload to My OneDrive" option) ────────────────────────────────────────
    Route::prefix('onedrive')->controller(\App\Http\Controllers\Masters\OneDriveController::class)->group(function () {
        Route::get('/connect',    'connect')->name('onedrive.connect');
        Route::get('/callback',   'callback')->name('onedrive.callback');
        Route::post('/disconnect','disconnect')->name('onedrive.disconnect');
    });

    // ── Auditor / Task Handler (mobile app web routes) ────────────────────────
    Route::controller(TaskHandlerController::class)->group(function () {
        Route::get('my_projects',                                                         'userProjects')->name('user.projects');
        Route::get('my_project_distributor_data/{project}',                               'userProjectMasterData')->name('user.project_master.data');
        Route::get('my_project_outlet_data/{type}/{project}/{template?}/{activity?}/{group_info?}', 'userProjectChildData')->name('user.project_child.data');
        Route::get('my_project_activities/{row_id}/{status?}',                            'userProjectActivities')->name('user.project.assigned_activities');
        Route::get('my_project_activity_instances/{row_id}/{activity}/{group_info?}',     'userActivityInstancesList')->name('user.activity.instances_list');
        Route::get('my_project_row_activity/{row_id}/{activity}/{group_info?}',           'row_data_activity')->name('user.project.row_id.activity');
        Route::post('my_project_row_activity_answer/{activity_sequence?}',                'row_activity_answers')->name('user.rowId.activity.answers');
        Route::post('my_project_autosave_answer',                                         'autoSaveAnswer')->name('user.activity.autosave');
        Route::post('upload-activity-image-temp',                                         'upload_activity_image_temp')->name('upload.activity.image.temp');
        Route::post('my_project_activity_add_instance',                                   'add_activity_instance')->name('user.activity.add_instance');
        Route::post('my_project_activity_close_instances',                                'closeActivityInstances')->name('user.activity.close_instances');
        Route::post('my_project_activity_delete_instance',                                'delete_activity_instance')->name('user.activity.delete_instance');
        Route::get('my_projects_distributor_outlets_data/{row_id}/{distributor_value}',   'userProjectActivityDistributorOutletData')->name('user.project.distributor.outlets');
        Route::post('close_audit',                                                        'closeAuditData')->name('closeAuditData');
        Route::post('getTemplateHeadData',                                                'getTemplateHeadData')->name('getTemplateHeadData');
        Route::post('edit_project_data_template',                                         'edit_project_data_template')->name('edit_project_data_template');
        Route::get('otp_verification_page/{row_id}/{activity}/{project_id}',              'otp_verification_page')->name('otp_verification_page');
        Route::post('verify_otp',                                                         'verify_otp')->name('verify_otp');
        Route::post('resend_otp',                                                         'resend_otp')->name('resend_otp');
        Route::post('send_otp',                                                           'send_otp')->name('send_otp');
    });

}); // end middleware('auth')

