<?php

use App\Http\Controllers\API\AuthenticationController;
use App\Http\Controllers\API\DashboardController;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\QuestionAnsController;
use App\Http\Controllers\API\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('command', [DashboardController::class, 'command']);
Route::post('authentication', [AuthenticationController::class, 'authentication']);
// Route::middleware(['auth:sanctum'])->group(function () {
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
// });

//  Route::middleware(['auth:sanctum'])->group(function () {
Route::get('getAllProject', [TaskController::class, 'getAllProject']);
Route::get('getAllActivity/{id}/{user_id}', [TaskController::class, 'getAllActivity']);
Route::get('projectDistributorData', [TaskController::class, 'projectDistributorData']);
Route::get('projectOutletData', [TaskController::class, 'projectOutletData']);
Route::get('getAllQuestion', [TaskController::class, 'getAllQuestion']);
Route::post('answerSubmit', [TaskController::class, 'row_activity_answers']);
Route::post('questionAnswer', [TaskController::class, 'questionAnswer']);
Route::get('getSubjectiveDropdown', [TaskController::class, 'getSubjectiveDropdown']);
//  });
Route::get('myProjectsDistributorOutletsData/{row_id}/{distributor_value}/{user} ', [TaskController::class, 'myProjectsDistributorOutletsData']);

//khushboo 05-04-2025
Route::post('sendActivityOtp', [TaskController::class, 'sendActivityOtp']);
Route::post('verifyOtp', [TaskController::class, 'verifyOtp']);
Route::post('close_audit_data', [TaskController::class, 'auditCloseSubmit']);
//khushboo 05-04-2025

//khushboo 16-05-2025
//to add more distributors
Route::get('getTemplateData/{project}/{template}', [TaskController::class, 'getTemplateHeaders']);
Route::get('getEditTemplateData/{row_id}', [TaskController::class, 'getEditTemplateHeadersData']);
Route::get('getOutletTemplatesAvailable/{row_id}/{user_id}', [TaskController::class, 'getOutletTemplatesAvailable']);
Route::post('storeTemplateData', [TaskController::class, 'storeTemplateHeaderValues']);
Route::post('editTemplateData', [TaskController::class, 'editTemplateHeaderValues']);
//khushboo 16-05-2025

