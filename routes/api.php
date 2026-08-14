<?php

use App\Http\Controllers\API\ApiErrorLogController;
use App\Http\Controllers\API\AuthenticationController;
use App\Http\Controllers\API\DashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\QuestionAnsController;
use App\Http\Controllers\API\TaskController;
use Illuminate\Support\Facades\Route;
 
// Public routes
Route::get('command', [DashboardController::class, 'command']);
Route::post('login', [AuthenticationController::class, 'authentication']);         // primary login
Route::post('authentication', [AuthenticationController::class, 'authentication']); // kept for backward compatibility

// Forgot password — all three steps are public (user has no token when they've forgotten their password)
Route::post('forgotPassword',          [AuthenticationController::class, 'forgotPassword']);
Route::post('verifyForgotPasswordOtp', [AuthenticationController::class, 'verifyForgotPasswordOtp']);
Route::post('resetForgotPassword',     [AuthenticationController::class, 'resetForgotPassword']);

// Protected routes — require Bearer token (Sanctum)
Route::middleware(['auth:sanctum'])->group(function () {

    // Legacy dashboard routes (kept for backward compatibility)
    Route::get('fetchcompany', [DashboardController::class, 'fetchcompany']);
    Route::get('dashboard', [DashboardController::class, 'dashboard']);
    Route::get('fetchzone/{company_id}', [DashboardController::class, 'fetchzone']);
    Route::get('fetchUnit/{zone_id}', [DashboardController::class, 'fetchUnit']);
    Route::get('fetchProject', [DashboardController::class, 'fetchProject']);
    Route::get('assignProject', [DashboardController::class, 'assignProject']);
    Route::get('fetchActivities', [DashboardController::class, 'fetchActivities']);
    Route::get('fetchQuestion', [AuthenticationController::class, 'fetchQuestion']);
    Route::post('submitAnswer', [DashboardController::class, 'submitAnswer']);
    Route::get('fetechDistributer', [DashboardController::class, 'fetechDistributer']);
    Route::get('newfetechDistributer', [DashboardController::class, 'newfetechDistributer']);
    Route::post('updateStatus', [AuthenticationController::class, 'updateStatus']);

    // Auditor task routes (mirrors web TaskHandlerController flow)
    // 1. Projects list
    Route::get('getAllProject', [TaskController::class, 'getAllProject']);

    // 2. Distributor / master data for a project
    Route::get('projectDistributorData', [TaskController::class, 'projectDistributorData']);

    // 3. Outlet data (child rows)
    Route::get('projectOutletData', [TaskController::class, 'projectOutletData']);

    // 4. Activities for a row — user_id now comes from auth token
    Route::get('getAllActivity/{id}/{status?}', [TaskController::class, 'getAllActivity']);

    // 5. Questions for an activity row
    Route::get('getAllQuestion', [TaskController::class, 'getAllQuestion']);

    // 6. Answer submission
    Route::post('answerSubmit', [TaskController::class, 'row_activity_answers']);   // bulk submit
    Route::post('saveAnswer', [TaskController::class, 'saveAnswer']);               // one answer at a time
    Route::post('finalizeAnswers', [TaskController::class, 'finalizeAnswers']);     // validate + finalize
    Route::post('uploadActivityImage', [TaskController::class, 'uploadActivityImage']); // pre-upload for multi-image
    Route::post('uploadSignature', [TaskController::class, 'uploadSignature']);         // one signature per row_id+activity_id+user_id

    // 7. Question answer (alternative endpoint)
    Route::post('questionAnswer', [TaskController::class, 'questionAnswer']);

    // 8. Subjective dropdown options
    Route::get('getSubjectiveDropdown', [TaskController::class, 'getSubjectiveDropdown']);

    // 9. Distributor outlets for a row
    Route::get('myProjectsDistributorOutletsData/{row_id}/{distributor_value}', [TaskController::class, 'myProjectsDistributorOutletsData'])->where('distributor_value', '[^/]+');

    // 10. OTP flow
    Route::post('sendActivityOtp', [TaskController::class, 'sendActivityOtp']);
    Route::post('verifyOtp', [TaskController::class, 'verifyOtp']);

    // 11. Audit close
    Route::post('close_audit_data', [TaskController::class, 'auditCloseSubmit']);

    // 12. Add / get / update distributor / template data
    Route::get('getTemplateData/{project}/{template}', [TaskController::class, 'getTemplateHeaders']);
    Route::post('storeTemplateData', [TaskController::class, 'storeTemplateHeaderValues']);
    Route::get('getTemplateRowData/{row_id}', [TaskController::class, 'getTemplateRowData']);
    Route::post('updateTemplateRowData', [TaskController::class, 'updateTemplateRowData']);

    // 13. Repeat activity instances
    Route::post('activityInstances', [TaskController::class, 'activityInstances']);
    Route::post('addInstance', [TaskController::class, 'addInstance']);
    Route::post('deleteInstance', [TaskController::class, 'deleteInstance']);
    Route::post('closeActivity', [TaskController::class, 'closeActivity']);

    // 14. Password update
    Route::post('updatePassword', [TaskController::class, 'updatePassword']);

    // 15. Logout (invalidates current Bearer token)
    Route::post('logout', [AuthenticationController::class, 'logout']);

    // 16. API error logs (admin use)
    Route::get('errorLogs', [ApiErrorLogController::class, 'index']);
});
