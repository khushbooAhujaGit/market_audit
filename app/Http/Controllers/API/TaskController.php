<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityGroupPivot;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\User;
use App\Models\SubjectQuestion;
use App\Models\ProjectMasterTemplates;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\SubjectDropdown;
use App\Models\Question;
use App\Models\TemplateName;
use App\Models\ClosedAudits;
use App\Models\TemplateNameHead;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use Carbon\Carbon;
use App\Models\TempUserActivityAnswersData;
use Dflydev\DotAccessData\Data;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Jobs\ConvertAuditorSelfiesToGeoSelfies;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\ActivityRepeatInstance;
use App\Models\ActivityInstanceClose;
use App\Models\ActivitySignature;
use App\Models\QuestionSubQuestion;

class TaskController extends Controller
{
    //

    public function getAllProject(Request $request)
    {
        try {
            $user_id = $request->user()->id;

            // Single join instead of 3 separate Eloquent queries
            $projectIds = DB::table('user_activity_data_assigns as uada')
                ->join('data_assigns as da', 'da.id', '=', 'uada.data_assign_id')
                ->where('uada.user_id', $user_id)
                ->distinct()
                ->pluck('da.project_id');

            $userProjects = DB::table('projects')
                ->whereIn('id', $projectIds)
                ->select('id', 'project_name')
                ->when($request->keyword, fn($q) => $q->where('project_name', 'like', "%{$request->keyword}%"))
                ->paginate(10);

            $pageProjectIds = collect($userProjects->items())->pluck('id')->toArray();

            // Count distinct distributor rows that are assigned to at least one activity
            // (any user). Chain: master project_template → data_assigns → user_activity_data_assigns
            // → user_audit_assigns (row_id). Unassigned rows are excluded.
            // Get the user's common_ids per project, then count distinct assigned row_ids
            
            $userCommonIds = DB::table('user_activity_data_assigns as uada')
                ->join('data_assigns as da', 'da.id', '=', 'uada.data_assign_id')
                ->join('project_templates as pt', function ($join) {
                    $join->on('pt.project_id', '=', 'da.project_id')
                         ->on('pt.template_name_id', '=', 'da.template_name_id');
                })
                ->where('uada.user_id', $user_id)
                ->where('pt.is_master', 1)
                ->whereIn('da.project_id', $pageProjectIds)
                ->select('da.project_id', 'uada.common_id', 'pt.id as project_template_id')
                ->distinct()
                ->get()
                ->groupBy('project_id');

                // dd($userCommonIds);

            $distributorCounts = [];
            foreach ($userCommonIds as $projectId => $rows) {
                $commonIds        = $rows->pluck('common_id')->toArray();
                $masterTemplateId = $rows->first()->project_template_id;

                $rowIds = DB::table('user_audit_assigns')
                    ->whereIn('common_id', $commonIds)
                    ->distinct()
                    ->pluck('row_id');

                // Verify against project_template_name_values_new (same check projectDistributorData
                // uses) so stale/orphaned user_audit_assigns rows don't inflate this count.
                $distributorCounts[$projectId] = DB::table('project_template_name_values_new')
                    ->whereIn('id', $rowIds)
                    ->where('project_template_id', $masterTemplateId)
                    ->count();
            }

            $data = collect($userProjects->items())->map(function ($project) use ($distributorCounts) {
                return [
                    'id'                => $project->id,
                    'project_name'      => $project->project_name,
                    'total_distributor' => (int) ($distributorCounts[$project->id] ?? 0),
                ];
            });

            return response([
                'status'       => 200,
                'message'      => 'success',
                'total'        => $userProjects->total(),
                'per_page'     => $userProjects->perPage(),
                'current_page' => $userProjects->currentPage(),
                'next_page'    => $userProjects->currentPage() < $userProjects->lastPage() ? $userProjects->currentPage() + 1 : null,
                'total_pages'    => $userProjects->lastPage(),
                'data'         => $data,
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status'  => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    

    /**
     * Mirrors TaskHandlerController::userProjectActivities() exactly.
     * GET /api/getAllActivity/{id}/{status?}
     */
    public function getAllActivity(Request $request, $row_id, $status = null)
    {
        try {
            $user           = $request->user();
            $userAssignedActivities = [];

            if (empty($status)) $status = 0;

            // Single JOIN instead of 3 separate model find() calls
            $rowInfo = DB::table('project_template_name_values_new as ptnv')
                ->join('project_templates as pt', 'pt.id', '=', 'ptnv.project_template_id')
                ->join('projects as p', 'p.id', '=', 'pt.project_id')
                ->where('ptnv.id', $row_id)
                ->select(
                    'ptnv.id as row_id', 'ptnv.template_data_json',
                    'pt.id as template_id', 'pt.project_id', 'pt.is_master',
                    'pt.main_header', 'pt.sub_header', 'pt.template_name_id',
                    'pt.activity_add_on', 'pt.activity_add_on_activity_ids',
                    'pt.activityType', 'pt.activity_group_name_id_or_activity_id',
                    'pt.data_add_on', 'pt.own_reference_head_id', 'pt.master_head_id',
                    'pt.add_signature',
                    'p.id as project_id_val', 'p.is_otp_required'
                )
                ->first();

            if (!$rowInfo) {
                return response(['status' => 404, 'message' => 'Row not found.'], 404);
            }

            // Wrap into objects that existing code can reference by ->property
            $projectTemplateNameValue = (object)[
                'id'                   => $rowInfo->row_id,
                'template_data_json'   => $rowInfo->template_data_json,
                'project_template_id'  => $rowInfo->template_id,
            ];
            $projectTemplateData = (object)[
                'id'                                    => $rowInfo->template_id,
                'project_id'                            => $rowInfo->project_id,
                'is_master'                             => $rowInfo->is_master,
                'main_header'                           => $rowInfo->main_header,
                'sub_header'                            => $rowInfo->sub_header,
                'template_name_id'                      => $rowInfo->template_name_id,
                'activity_add_on'                       => $rowInfo->activity_add_on,
                'activity_add_on_activity_ids'          => $rowInfo->activity_add_on_activity_ids,
                'activityType'                          => $rowInfo->activityType,
                'activity_group_name_id_or_activity_id' => $rowInfo->activity_group_name_id_or_activity_id,
                'data_add_on'                           => $rowInfo->data_add_on,
                'own_reference_head_id'                 => $rowInfo->own_reference_head_id,
                'master_head_id'                        => $rowInfo->master_head_id,
                'add_signature'                         => $rowInfo->add_signature,
            ];
            $project = (object)[
                'id'              => $rowInfo->project_id_val,
                'is_otp_required' => $rowInfo->is_otp_required,
            ];

            $distributor_value = json_decode($rowInfo->template_data_json, true)[$rowInfo->main_header] ?? null;

            // ── OUTLET template (is_master == 0): build from UserActivityDataAssign ──
            if ($projectTemplateData->is_master == 0) {
                $outletAssigns = DB::table('user_activity_data_assigns')
                    ->where('user_id', $user->id)
                    ->where('project_template_id', $projectTemplateData->id)
                    ->get()
                    ->unique('activity_id');

                // Pre-load to avoid N+1 queries inside the loop
                $outletActivityIds = $outletAssigns->pluck('activity_id')->unique()->toArray();
                $activitiesMap = empty($outletActivityIds) ? collect() :
                    DB::table('activities')->whereIn('id', $outletActivityIds)->get()->keyBy('id');
                $outletPivotsMap = empty($outletActivityIds) ? collect() :
                    DB::table('activity_group_pivots')
                        ->whereIn('activity_id', $outletActivityIds)
                        ->get()->groupBy('activity_id')->map(fn($g) => $g->first());
                $repeatInstancesByActivityOutlet = empty($outletActivityIds) ? collect() :
                    DB::table('activity_repeat_instances')
                        ->where('row_id', $row_id)
                        ->whereIn('activity_id', $outletActivityIds)
                        ->orderBy('activity_sequence')
                        ->get()
                        ->groupBy('activity_id');
                // Decode once — reused for every iteration
                $outletAddOnIds    = json_decode($projectTemplateData->activity_add_on_activity_ids ?? '[]', true);
                $outletTemplateName = DB::table('template_names')->where('id', $projectTemplateData->template_name_id)->value('template_name');

                // ── Batch pre-load outlet: eliminates 7 DB queries per activity ──
                $outletReqQsByActivity = empty($outletActivityIds) ? collect() :
                    DB::table('questions')
                        ->whereIn('activity_id', $outletActivityIds)
                        ->where('answer_type', 1)
                        ->where('question_type', '!=', 'Multi Response')
                        ->get(['id', 'activity_id', 'parent_question_id', 'parent_value'])
                        ->groupBy('activity_id');

                $outletAllQIdsByActivity = empty($outletActivityIds) ? collect() :
                    DB::table('questions')
                        ->whereIn('activity_id', $outletActivityIds)
                        ->get(['id', 'activity_id'])
                        ->groupBy('activity_id')
                        ->map(fn($g) => $g->pluck('id')->toArray());

                $outletAnswersByActivity = empty($outletActivityIds) ? collect() :
                    DB::table('temp_user_activity_answers_data')
                        ->where('row_id', $row_id)
                        ->where('user_id', $user->id)
                        ->whereIn('activity_id', $outletActivityIds)
                        ->get()->groupBy('activity_id');

                $outletClosedSet = empty($outletActivityIds) ? [] :
                    DB::table('activity_instance_closes')
                        ->where('row_id', $row_id)
                        ->whereIn('activity_id', $outletActivityIds)
                        ->where('user_id', $user->id)
                        ->pluck('activity_id')->flip()->toArray();

                $outletSignedSet = empty($outletActivityIds) ? [] :
                    DB::table('activity_signatures')
                        ->where('row_id', $row_id)
                        ->whereIn('activity_id', $outletActivityIds)
                        ->where('user_id', $user->id)
                        ->pluck('activity_id')->flip()->toArray();

                foreach ($outletAssigns as $outletAssign) {
                    $outActId = $outletAssign->activity_id;
                    $activity = $activitiesMap->get($outActId);
                    if (!$activity) continue;

                    $allRequiredQs       = $outletReqQsByActivity->get($outActId, collect());
                    $standaloneRequired  = $allRequiredQs->whereNull('parent_question_id');
                    $conditionalRequired = $allRequiredQs->whereNotNull('parent_question_id');

                    $outActAnswers  = $outletAnswersByActivity->get($outActId, collect());
                    $outBaseAnswers = $outActAnswers->filter(fn($a) => is_null($a->activity_sequence) || $a->activity_sequence == 0);

                    $activeConditionalIds = collect();
                    if ($conditionalRequired->isNotEmpty()) {
                        $parentIds    = $conditionalRequired->pluck('parent_question_id')->unique()->values()->toArray();
                        $parentAnswers = $outBaseAnswers->whereIn('question_id', $parentIds)
                            ->pluck('user_answer', 'question_id');
                        $activeConditionalIds = $conditionalRequired->filter(function ($q) use ($parentAnswers) {
                            $parentAnswer = trim((string) ($parentAnswers->get($q->parent_question_id) ?? ''));
                            if ($parentAnswer === '') return false;
                            if ($q->parent_value === '__non_empty__') return true;
                            return strcasecmp($parentAnswer, (string) $q->parent_value) === 0;
                        })->pluck('id');
                    }

                    $requiredQuestionIds = $standaloneRequired->pluck('id')
                        ->merge($activeConditionalIds)->unique()->values()->toArray();
                    $requiredCount = count($requiredQuestionIds);

                    if ($requiredCount === 0) {
                        $allQIds           = $outletAllQIdsByActivity->get($outActId, []);
                        $submittedcount    = $outBaseAnswers->whereIn('question_id', $allQIds)
                            ->pluck('question_id')->unique()->count();
                        $check_if_answered = $submittedcount > 0;
                    } else {
                        $submittedcount    = $this->countSubmittedAgainstRequired($outBaseAnswers, $requiredQuestionIds);
                        $check_if_answered = $submittedcount >= $requiredCount;
                    }

                    $sentBackExists = $outBaseAnswers->where('status', 3)->isNotEmpty();
                    $isSentBack     = $sentBackExists;
                    if ($isSentBack) { $check_if_answered = false; }

                    $hasAnySentBack = $isSentBack || $outActAnswers
                        ->filter(fn($a) => $a->status == 3 && !is_null($a->activity_sequence) && $a->activity_sequence > 0)
                        ->isNotEmpty();

                    $allowRepeat = false;
                    if ($projectTemplateData->activity_add_on == 1) {
                        $allowRepeat = empty($outletAddOnIds) || in_array((int)$outActId, array_map('intval', $outletAddOnIds));
                    }

                    $isFullyClosed = false;
                    if ($allowRepeat) {
                        if (isset($outletClosedSet[$outActId])) {
                            $isFullyClosed = $outActAnswers->where('status', 3)->isEmpty();
                        }
                        if ($isFullyClosed) {
                            $check_if_answered = true;
                        }
                    } else {
                        $isFullyClosed = $check_if_answered && !$isSentBack;
                    }

                    // Signature still owed: template requires one and this user hasn't
                    // uploaded it yet for this row+activity — blocks is_fully_closed
                    // even if answers/instances are otherwise complete.
                    $signatureAllowed = (bool) $projectTemplateData->add_signature && !isset($outletSignedSet[$outActId]);
                    if ($signatureAllowed) {
                        $isFullyClosed = false;
                    }

                    $otpVerificationDone = false;
                    if ($project->is_otp_required == 1) {
                        $lastAns = $outActAnswers->sortByDesc('id')->first();
                        if ($lastAns && !empty($lastAns->mobile_otp) && $lastAns->otp_verified_status == 1) {
                            $otpVerificationDone = true;
                        }
                    }

                    $outletPivot    = $outletPivotsMap->get($outActId);
                    $outletSequence = $outletPivot ? $outletPivot->sequence : null;

                    $userAssignedActivities[] = [
                        'template_name_id'      => $projectTemplateData->template_name_id,
                        'template_name'         => $outletTemplateName,
                        'is_master'             => 0,
                        'activity_id'           => $outActId,
                        'activity_name'         => $activity->activity_name,
                        'sequence'              => $outletSequence,
                        'answer_submitted'      => $check_if_answered,
                        'otp_verification_done' => $otpVerificationDone,
                        'is_sent_back'          => $isSentBack,
                        'has_any_sent_back'     => $hasAnySentBack,
                        'is_fully_closed'       => $isFullyClosed,
                        'repeat_instances'      => $repeatInstancesByActivityOutlet->get($outActId, collect()),
                        'allow_repeat'          => $allowRepeat,
                        'otp_required'          => (bool)$project->is_otp_required,
                        'signature_allowed'     => $signatureAllowed,
                        'is_locked'             => false,
                    ];
                }

                // Compute is_locked for outlet activities by sequence
                $outletSorted = collect($userAssignedActivities)->sortBy('sequence')->values()->toArray();
                $outletBlock  = null;
                foreach ($outletSorted as $idx => $act) {
                    $seq = $act['sequence'];
                    if ($seq === null || $seq === '') continue;
                    if ($outletBlock !== null && $seq > $outletBlock) {
                        // $outletSorted[$idx]['is_locked'] = true;
                        $outletSorted[$idx]['is_locked'] = false;
                        continue;
                    }
                    $hasRepeat  = count($act['repeat_instances']) > 0;
                    $incomplete = !$act['answer_submitted'] || ($hasRepeat && $act['allow_repeat'] && !$act['is_fully_closed']);
                    if ($incomplete && $outletBlock === null) {
                        $outletBlock = $seq;
                    }
                }

                $collection = collect($outletSorted);
                $collection = $this->filterAndPaginateActivities($collection, $request);

                return response([
                    'status'           => 200,
                    'message'          => 'success',
                    'row_id'           => (int) $row_id,
                    'project_id'       => $project->id,
                    'distributor_value'=> $distributor_value,
                    'is_outlet'        => true,
                    'total'            => $collection['total'],
                    'per_page'         => $collection['per_page'],
                    'current_page'     => $collection['current_page'],
                    'next_page'        => $collection['next_page'],
                    'total_pages'        => $collection['total_pages'],
                    'data'             => $collection['data'],
                ], 200);
            }

            // ── MASTER template: mirror projectDistributorData's lookup strategy ──
            // Find data_assigns by template_name_id (not project_template_id) so that
            // assignments pointing to data_assigns on a different project_template record
            // are still found — matching exactly how projectDistributorData counts activities.
            $templateNameId = $projectTemplateData->template_name_id;

            $templateDataAssignIds = DB::table('data_assigns')
                ->where('project_id', $project->id)
                ->where('template_name_id', $templateNameId)
                ->pluck('id');

            $distinct_data_assignIds = DB::table('user_activity_data_assigns')
                ->where('user_id', $user->id)
                ->whereIn('data_assign_id', $templateDataAssignIds)
                ->distinct()->pluck('data_assign_id');

            $data_assign_info = DB::table('data_assigns as da')
                ->join('project_templates as pt', 'pt.id', '=', 'da.project_template_id')
                ->join('template_names as tn', 'tn.id', '=', 'da.template_name_id')
                ->join('activities as a', 'a.id', '=', 'da.activity_id')
                ->whereIn('da.id', $distinct_data_assignIds)
                ->where('da.project_id', $project->id)
                ->select('da.*', 'pt.is_master as pt_is_master', 'tn.template_name', 'a.activity_name')
                ->get()
                ->unique('activity_id'); // guard against duplicate data_assigns for the same activity

            // Fallback: retry using the master template's template_name_id
            if ($data_assign_info->isEmpty()) {
                $masterTemplateNameId = DB::table('project_templates')
                    ->where('project_id', $projectTemplateData->project_id)
                    ->where('is_master', 1)->value('template_name_id') ?? 0;
                $masterDataAssignIds = DB::table('data_assigns')
                    ->where('project_id', $project->id)
                    ->where('template_name_id', $masterTemplateNameId)
                    ->pluck('id');
                $freshMasterIds = DB::table('user_activity_data_assigns')
                    ->where('user_id', $user->id)
                    ->whereIn('data_assign_id', $masterDataAssignIds)
                    ->distinct()->pluck('data_assign_id');
                $data_assign_info = DB::table('data_assigns as da')
                    ->join('project_templates as pt', 'pt.id', '=', 'da.project_template_id')
                    ->join('template_names as tn', 'tn.id', '=', 'da.template_name_id')
                    ->join('activities as a', 'a.id', '=', 'da.activity_id')
                    ->whereIn('da.id', $freshMasterIds)
                    ->where('da.project_id', $project->id)
                    ->select('da.*', 'pt.is_master as pt_is_master', 'tn.template_name', 'a.activity_name')
                    ->get()
                    ->unique('activity_id');
            }

            // Outlet-link status
            $with_data_check = 1;
            if ($projectTemplateData->is_master == 1) {
                $childTemplateIds = DB::table('project_templates')
                    ->where('project_id', $projectTemplateData->project_id)
                    ->where('is_master', 0)->pluck('id')->toArray();
                if (!empty($childTemplateIds) && DB::table('project_template_name_values_new')
                    ->whereIn('project_template_id', $childTemplateIds)->exists()) {
                    $status = 1;
                }
            }

            // ── Pre-load all data for the master loop in bulk queries ──────────
            $masterActivityIds = $data_assign_info->pluck('activity_id')->unique()->toArray();

            $repeatInstancesByActivity = DB::table('activity_repeat_instances')
                ->where('row_id', $row_id)
                ->whereIn('activity_id', $masterActivityIds)
                ->orderBy('activity_sequence')
                ->get()->groupBy('activity_id');

            $masterPivotsAll = DB::table('activity_group_pivots')
                ->whereIn('activity_id', $masterActivityIds)->get();
            $masterPivotsKey = $masterPivotsAll->groupBy(fn($p) => $p->activity_id . '_' . $p->activity_group_id);
            $masterAddOnIds  = json_decode($projectTemplateData->activity_add_on_activity_ids ?? '[]', true);

            // Required questions per activity
            $masterReqQsByActivity = empty($masterActivityIds) ? collect() :
                DB::table('questions')
                    ->whereIn('activity_id', $masterActivityIds)
                    ->where('answer_type', 1)
                    ->where('question_type', '!=', 'Multi Response')
                    ->get(['id', 'activity_id', 'parent_question_id', 'parent_value'])
                    ->groupBy('activity_id');

            // All question IDs per activity (for fallback when required count = 0)
            $masterAllQIdsByActivity = empty($masterActivityIds) ? collect() :
                DB::table('questions')
                    ->whereIn('activity_id', $masterActivityIds)
                    ->get(['id', 'activity_id'])
                    ->groupBy('activity_id')
                    ->map(fn($g) => $g->pluck('id')->toArray());

            // All answers for this row+user across all activities at once
            $masterAnswersByActivity = empty($masterActivityIds) ? collect() :
                DB::table('temp_user_activity_answers_data')
                    ->where('row_id', $row_id)
                    ->where('user_id', $user->id)
                    ->whereIn('activity_id', $masterActivityIds)
                    ->get()->groupBy('activity_id');

            // Closed activity IDs — set for O(1) lookup
            $masterClosedSet = empty($masterActivityIds) ? [] :
                DB::table('activity_instance_closes')
                    ->where('row_id', $row_id)
                    ->whereIn('activity_id', $masterActivityIds)
                    ->where('user_id', $user->id)
                    ->pluck('activity_id')->flip()->toArray();

            $masterSignedSet = empty($masterActivityIds) ? [] :
                DB::table('activity_signatures')
                    ->where('row_id', $row_id)
                    ->whereIn('activity_id', $masterActivityIds)
                    ->where('user_id', $user->id)
                    ->pluck('activity_id')->flip()->toArray();

            foreach ($data_assign_info as $assigned_data) {
                $actId    = $assigned_data->activity_id;
                $sequence = null;
                if (isset($assigned_data->activity_group_id)) {
                    $pivotKey = $actId . '_' . $assigned_data->activity_group_id;
                    $pivot    = $masterPivotsKey->get($pivotKey)?->first();
                    $sequence = $pivot ? $pivot->sequence : '';
                }

                // Fields now come directly from the JOIN query (no relation calls)
                $is_master_temp   = $assigned_data->pt_is_master ?? 0;
                $current_template = $assigned_data->template_name;
                $current_activity = $assigned_data->activity_name;

                $exists = false;
                foreach ($userAssignedActivities as $a) {
                    if ($a['template_name'] == $current_template && $a['activity_name'] == $current_activity) { $exists = true; break; }
                }
                if ($exists) continue;

                // ── All lookups from pre-loaded collections (zero extra DB queries) ──
                $allRequiredQs2 = $masterReqQsByActivity->get($actId, collect());
                $standaloneReq2 = $allRequiredQs2->whereNull('parent_question_id');
                $conditionalReq2 = $allRequiredQs2->whereNotNull('parent_question_id');

                $actAnswers  = $masterAnswersByActivity->get($actId, collect());
                $baseAnswers = $actAnswers->filter(fn($a) => is_null($a->activity_sequence) || $a->activity_sequence == 0);

                $activeCondIds2 = collect();
                if ($conditionalReq2->isNotEmpty()) {
                    $parentIds2     = $conditionalReq2->pluck('parent_question_id')->unique()->values()->toArray();
                    $parentAnswers2 = $baseAnswers->whereIn('question_id', $parentIds2)
                        ->pluck('user_answer', 'question_id');
                    $activeCondIds2 = $conditionalReq2->filter(function ($q) use ($parentAnswers2) {
                        $parentAnswer = trim((string) ($parentAnswers2->get($q->parent_question_id) ?? ''));
                        if ($parentAnswer === '') return false;
                        if ($q->parent_value === '__non_empty__') return true;
                        return strcasecmp($parentAnswer, (string) $q->parent_value) === 0;
                    })->pluck('id');
                }

                $requiredQuestionIds = $standaloneReq2->pluck('id')
                    ->merge($activeCondIds2)->unique()->values()->toArray();
                $requiredCount = count($requiredQuestionIds);

                if ($requiredCount === 0) {
                    $allQIds        = $masterAllQIdsByActivity->get($actId, []);
                    $submittedcount = $baseAnswers->whereIn('question_id', $allQIds)
                        ->pluck('question_id')->unique()->count();
                    $check_if_answered = $submittedcount > 0;
                } else {
                    $submittedcount = $this->countSubmittedAgainstRequired($baseAnswers, $requiredQuestionIds);
                    $check_if_answered = $submittedcount >= $requiredCount;
                }

                $activityAllQIds = $requiredCount > 0
                    ? $requiredQuestionIds
                    : ($masterAllQIdsByActivity->get($actId, []));

                $sentBackExists = $baseAnswers->where('status', 3)->isNotEmpty();
                $isSentBack     = $sentBackExists;
                if ($isSentBack) { $check_if_answered = false; }

                $hasAnySentBack = $isSentBack || $actAnswers
                    ->filter(fn($a) => $a->status == 3 && !is_null($a->activity_sequence) && $a->activity_sequence > 0)
                    ->isNotEmpty();

                $otpVerificationDone = false;
                if ($project->is_otp_required == 1) {
                    $lastAns = $actAnswers->whereIn('question_id', $activityAllQIds)->sortByDesc('id')->first();
                    if ($lastAns && !empty($lastAns->mobile_otp) && $lastAns->otp_verified_status == 1) {
                        $otpVerificationDone = true;
                    }
                }

                $allowRepeat  = false;
                if ($projectTemplateData->activity_add_on == 1) {
                    $allowRepeat = empty($masterAddOnIds) || in_array((int)$actId, array_map('intval', $masterAddOnIds));
                }
                $isFullyClosed = false;
                if ($allowRepeat) {
                    if (isset($masterClosedSet[$actId])) {
                        $isFullyClosed = $actAnswers->where('status', 3)->isEmpty();
                    }
                    // When repeat activity is fully closed, treat it as answered
                    if ($isFullyClosed) {
                        $check_if_answered = true;
                    }
                } else {
                    $isFullyClosed = $check_if_answered && !$isSentBack;
                }

                // Signature still owed: template requires one and this user hasn't
                // uploaded it yet for this row+activity — blocks is_fully_closed even
                // if answers/instances are otherwise complete.
                $signatureAllowed = (bool) $projectTemplateData->add_signature && !isset($masterSignedSet[$actId]);
                if ($signatureAllowed) {
                    $isFullyClosed = false;
                }

                $userAssignedActivities[] = [
                    'template_name_id'      => $assigned_data->template_name_id,
                    'template_name'         => $current_template,
                    'is_master'             => $is_master_temp,
                    'activity_id'           => $actId,
                    'activity_name'         => $current_activity,
                    'sequence'              => $sequence,
                    'answer_submitted'      => $check_if_answered,
                    'otp_verification_done' => $otpVerificationDone,
                    'is_sent_back'          => $isSentBack,
                    'has_any_sent_back'     => $hasAnySentBack,
                    'is_fully_closed'       => $isFullyClosed,
                    'repeat_instances'      => $repeatInstancesByActivity->get($actId, collect()),
                    'allow_repeat'          => $allowRepeat,
                    'otp_required'          => (bool)$project->is_otp_required,
                    'signature_allowed'     => $signatureAllowed,
                    'is_locked'             => false, // placeholder; computed below after all activities are collected
                ];
            }

            // ── Compute is_locked based on activity submission sequence ───────
            // Sort by sequence first, then walk through: once we find an activity
            // that is not yet complete (not submitted, or allow_repeat and not fully
            // closed), every subsequent activity (higher sequence) is locked.
            $sorted = collect($userAssignedActivities)
                ->sortBy([['is_master', 'desc'], ['sequence', 'asc']])
                ->values()
                ->toArray();

            $blockFromSequence = null;
            foreach ($sorted as $idx => $act) {
                $seq = $act['sequence'];
                if ($seq === null || $seq === '') continue;
                if ($blockFromSequence !== null && $seq > $blockFromSequence) {
                    // $sorted[$idx]['is_locked'] = true;
                    $sorted[$idx]['is_locked'] = false;
                    continue;
                }
                // An activity blocks the next one only if:
                // - it has no answer submitted yet, OR
                // - it has repeat instances and is explicitly not fully closed
                $hasRepeat  = count($act['repeat_instances']) > 0;
                $incomplete = !$act['answer_submitted'] || ($hasRepeat && $act['allow_repeat'] && !$act['is_fully_closed']);
                if ($incomplete && $blockFromSequence === null) {
                    $blockFromSequence = $seq;
                }
            }

            $collection = collect($sorted);
            $collection = $this->filterAndPaginateActivities($collection, $request);

            return response([
                'status'            => 200,
                'message'           => 'success',
                'row_id'            => (int) $row_id,
                'project_id'        => $project->id,
                'distributor_value' => $distributor_value,
                'is_outlet'         => $status == 1,
                'total'             => $collection['total'],
                'per_page'          => $collection['per_page'],
                'current_page'      => $collection['current_page'],
                'next_page'         => $collection['next_page'],
                'total_pages'       => $collection['total_pages'],
                'data'              => $collection['data'],
            ], 200);

        } catch (\Throwable $th) {
            return response([
                'status'  => 500,
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function projectDistributorData(Request $request)
    {
        $request->validate(['project_id' => 'required|integer']);

        $user      = $request->user()->id;
        $projectId = (int) $request->project_id;

        // ── Project metadata (lightweight, no Eloquent) ───────────────────────
        $allProjectTemplates = DB::table('project_templates')
            ->where('project_id', $projectId)
            ->get();

        $project_temp_info = $allProjectTemplates->firstWhere('is_master', 1);
        if (!$project_temp_info) {
            return response(['status' => 404, 'message' => 'No master template found for this project.'], 404);
        }

        $projectInfo = DB::table('projects')->where('id', $projectId)->first();
        if (!$projectInfo) {
            return response(['status' => 404, 'message' => 'Project not found.'], 404);
        }

        $add_outlet     = $project_temp_info->data_add_on == 1;
        $main_header_id = $project_temp_info->main_header;
        $sub_header_id  = $project_temp_info->sub_header;

        // ── Cache the expensive row-building (5 min per user+project) ─────────
        $cacheKey = "proj_dist_data_{$user}_{$projectId}";
        Cache::forget($cacheKey); // always recompute fresh; too many write paths can change this data without busting the cache
        [$project_data_arr, $totalcompletedproject, $totalproject] = Cache::remember(
            $cacheKey, 300,
            function () use ($allProjectTemplates, $project_temp_info, $projectId, $user, $main_header_id, $sub_header_id) {

            $project_data_arr      = [];
            $totalcompletedproject = 0;
            $totalproject          = 0;
            $isOutletAssigned      = 0;
            $row_rendered_arr      = [];

            $project_template_details    = $allProjectTemplates->sortByDesc('is_master')->values();
            $project_temp_child_info_Ids = $allProjectTemplates->where('is_master', 0)->pluck('id')->toArray();

            // ── Child rows for outlet counting ────────────────────────────────
            $valueToChildRowIds = [];
            if (!empty($project_temp_child_info_Ids)) {
                $assignedChildRowIds = DB::table('user_audit_assigns as ua')
                    ->join('user_activity_data_assigns as uada', 'uada.common_id', '=', 'ua.common_id')
                    ->whereIn('uada.project_template_id', $project_temp_child_info_Ids)
                    ->distinct()->pluck('ua.row_id')->toArray();

                if (!empty($assignedChildRowIds)) {
                    DB::table('project_template_name_values_new')
                        ->select('id', 'template_data_json')
                        ->whereIn('id', $assignedChildRowIds)
                        ->get()
                        ->each(function ($row) use (&$valueToChildRowIds) {
                            $decoded = json_decode($row->template_data_json, true) ?? [];
                            foreach (array_values($decoded) as $v) {
                                if ($v !== null && $v !== '') {
                                    $valueToChildRowIds[(string)$v][] = $row->id;
                                }
                            }
                        });
                }
            }

            // ── Batch: template_name_heads keyed by id ────────────────────────
            $templateHeads = DB::table('template_name_heads')
                ->select('id', 'template_head_name')
                ->get()
                ->keyBy('id');

            // ── Batch: data_assigns for all templates in one query ────────────
            $allTemplateNameIds = $project_template_details->pluck('template_name_id')->unique()->toArray();
            $allDataAssigns = DB::table('data_assigns')
                ->where('project_id', $projectId)
                ->whereIn('template_name_id', $allTemplateNameIds)
                ->get()
                ->groupBy('template_name_id');

            // ── Batch: user_activity_data_assigns for all data_assign ids ─────
            $allDataAssignIds = $allDataAssigns->flatten()->pluck('id')->unique()->toArray();
            $allUserAssigns   = empty($allDataAssignIds) ? collect() :
                DB::table('user_activity_data_assigns')
                    ->where('user_id', $user)
                    ->whereIn('data_assign_id', $allDataAssignIds)
                    ->select('data_assign_id', 'common_id')
                    ->get()
                    ->groupBy('data_assign_id');

            // ── Batch: user_audit_assigns → all row_ids per common_id ─────────
            // BUG FIX: use groupBy so multiple row_ids per common_id are preserved
            $allCommonIds = $allUserAssigns->flatten()->pluck('common_id')->filter()->unique()->toArray();
            $rowIdsByCommonId = [];
            if (!empty($allCommonIds)) {
                DB::table('user_audit_assigns')
                    ->whereIn('common_id', $allCommonIds)
                    ->select('common_id', 'row_id')
                    ->distinct()
                    ->get()
                    ->each(function ($r) use (&$rowIdsByCommonId) {
                        $rowIdsByCommonId[$r->common_id][] = $r->row_id;
                    });
            }

            // ── Batch: activity_group_pivots for group-type templates ─────────
            $allGroupIds = $project_template_details
                ->where('activityType', 1)
                ->pluck('activity_group_name_id_or_activity_id')->unique()->toArray();
            $pivotsByGroup = empty($allGroupIds) ? collect() :
                DB::table('activity_group_pivots')
                    ->whereIn('activity_group_id', $allGroupIds)
                    ->select('activity_group_id', 'activity_id')
                    ->get()
                    ->groupBy('activity_group_id');

            // ── Batch: required question IDs per activity ─────────────────────
            // BUG FIX: groupBy to get ALL question IDs per activity (not just last one)
            $allActivityIds = $project_template_details->flatMap(function ($t) use ($pivotsByGroup) {
                if ($t->activityType == 0) return [$t->activity_group_name_id_or_activity_id];
                return $pivotsByGroup->get($t->activity_group_name_id_or_activity_id, collect())
                    ->pluck('activity_id')->toArray();
            })->unique()->toArray();

            $questionIdsByActivity = [];
            if (!empty($allActivityIds)) {
                DB::table('questions')
                    ->whereIn('activity_id', $allActivityIds)
                    ->where('answer_type', 1)
                    ->select('activity_id', 'id')
                    ->get()
                    ->each(function ($q) use (&$questionIdsByActivity) {
                        $questionIdsByActivity[$q->activity_id][] = $q->id;
                    });
            }

            $latitudeHeaders  = ['Latitude', 'latitude', 'lat', 'Lat'];
            $longitudeHeaders = ['Longitude', 'longitude', 'long', 'Long'];

            // ── Per-template loop — no DB queries for assignments/questions ────
            foreach ($project_template_details as $tempData) {

                $dataAssigns = $allDataAssigns->get($tempData->template_name_id, collect());
                if ($dataAssigns->isEmpty()) continue;

                $getDataAssignIds             = $dataAssigns->pluck('id');
                $OutletAssignedExists         = $dataAssigns->contains('is_outlet_assigned', 1);
                $getDataAssignTemplateHeadIds = $dataAssigns->pluck('template_name_head_id')->unique()->filter();

                // Collect user assigns that belong to this template's data_assign IDs
                $userAssignsForTemplate = collect();
                foreach ($getDataAssignIds as $daId) {
                    foreach ($allUserAssigns->get($daId, collect()) as $ua) {
                        $userAssignsForTemplate->push($ua);
                    }
                }
                if ($userAssignsForTemplate->isEmpty()) continue;

                $dataAssignCommonIds = $userAssignsForTemplate->pluck('common_id')->unique()->filter()->toArray();
                $userDataAssignIds   = $userAssignsForTemplate->pluck('data_assign_id')->unique()->toArray();
                $activitiesids       = $dataAssigns->whereIn('id', $userDataAssignIds)
                    ->pluck('activity_id')->unique()->toArray();

                // BUG FIX: flatMap so all row_ids per common_id are included
                $getRowIds = collect($dataAssignCommonIds)
                    ->flatMap(fn($cid) => $rowIdsByCommonId[$cid] ?? [])
                    ->unique()->values()->toArray();

                // BUG FIX: collect ALL question IDs across all activities for this template
                $getAllQuestionIds = collect($activitiesids)
                    ->flatMap(fn($actId) => $questionIdsByActivity[$actId] ?? [])
                    ->unique()->values()->toArray();
                $totalQuestions = count($getAllQuestionIds);

                $projectTemplateHeadsAssigned = [];
                $templateMainHeaderId         = $tempData->master_head_id;
                $allTemplateValues            = [];

                if ($tempData->is_master == 1) {
                    if ($OutletAssignedExists) $isOutletAssigned = 1;

                    $allTemplates = empty($getRowIds) ? collect() :
                        DB::table('project_template_name_values_new')
                            ->select('id as row_id', 'project_template_id', 'template_data_json')
                            ->whereIn('id', $getRowIds)
                            ->where('project_template_id', $project_temp_info->id)
                            ->get();

                    $headIdSet = array_flip(array_map('strval', $getDataAssignTemplateHeadIds->toArray()));
                    $seen      = [];
                    foreach ($allTemplates as $row) {
                        $jsonData = json_decode($row->template_data_json, true) ?? [];
                        foreach ($jsonData as $headKeyId => $value) {
                            if (!isset($templateHeads[$headKeyId])) continue;
                            $allTemplateValues[$row->project_template_id][] = [
                                'row_id'                => $row->row_id,
                                'project_template_id'   => $row->project_template_id,
                                'template_name_head_id' => $headKeyId,
                                'template_data_json'    => $row->template_data_json,
                                'value'                 => $value,
                                'template_head_name'    => $templateHeads[$headKeyId]->template_head_name,
                            ];
                            if ($value && isset($headIdSet[(string)$headKeyId])) {
                                $seenKey = $headKeyId . '|' . $value;
                                if (!isset($seen[$seenKey])) {
                                    $seen[$seenKey] = true;
                                    $projectTemplateHeadsAssigned[] = [
                                        'project_template_id' => $project_temp_info->id,
                                        'head_id'             => $headKeyId,
                                        'head_value'          => $value,
                                    ];
                                }
                            }
                        }
                    }
                } else {
                    // Outlet template path.
                    // This query doesn't depend on $headId — it only varies by $templateMainHeaderId
                    // and $project_temp_info->id, both constant for this template — so it's run once
                    // here instead of once per entry in $getDataAssignTemplateHeadIds (was re-running
                    // the same unindexed JSON scan over the whole table N times for no reason).
                    $getHeadValues = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $project_temp_info->id)
                        ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$templateMainHeaderId\"')) as head_value")
                        ->distinct()->get();
                    if ($getHeadValues->isNotEmpty()) $isOutletAssigned = 1;
                    foreach ($getHeadValues as $headValue) {
                        if ($headValue->head_value) {
                            $projectTemplateHeadsAssigned[] = [
                                'project_template_id' => $project_temp_info->id,
                                'head_id'             => $templateMainHeaderId,
                                'head_value'          => $headValue->head_value,
                            ];
                        }
                    }

                    $projectTemplateIDs = array_column($projectTemplateHeadsAssigned, 'project_template_id');
                    $templateHeadValues = array_column($projectTemplateHeadsAssigned, 'head_value');
                    // Scoped to this project's master template only — was previously unscoped and
                    // running JSON_SEARCH across every row in the whole table for every project,
                    // which is what made this endpoint slow regardless of how few rows this
                    // specific project actually has.
                    $mainHeaderValues   = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $project_temp_info->id)
                        ->where(function ($q) use ($templateHeadValues) {
                            foreach ($templateHeadValues as $v) {
                                $q->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$v]);
                            }
                        })
                        ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$templateMainHeaderId\"')) as main_header_value"));

                    $allTemplates = DB::table('project_template_name_values_new')
                        ->select('id as row_id', 'project_template_id', 'template_data_json')
                        ->whereIn('id', $getRowIds)
                        ->whereIn('project_template_id', $projectTemplateIDs)
                        ->where(function ($q) use ($mainHeaderValues) {
                            foreach ($mainHeaderValues as $v) {
                                $q->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$v]);
                            }
                        })
                        ->get();

                    foreach ($allTemplates as $row) {
                        $jsonData = json_decode($row->template_data_json, true) ?? [];
                        foreach ($jsonData as $headKeyId => $value) {
                            if (!isset($templateHeads[$headKeyId])) continue;
                            $allTemplateValues[$row->project_template_id][] = [
                                'row_id'                => $row->row_id,
                                'project_template_id'   => $row->project_template_id,
                                'template_name_head_id' => $headKeyId,
                                'template_data_json'    => $row->template_data_json,
                                'value'                 => $value,
                                'template_head_name'    => $templateHeads[$headKeyId]->template_head_name,
                            ];
                        }
                    }
                }

                // Flatten all template values and group by row_id for fast lookup
                $allTemplateValuesFlat = array_merge(...array_values($allTemplateValues));
                $rowGroupedValues      = [];
                foreach ($allTemplateValuesFlat as $item) {
                    $rowGroupedValues[$item['row_id']][] = $item;
                }

                $allRowIds          = array_unique(array_column($allTemplateValuesFlat, 'row_id'));
                $totalActivityCount = count($activitiesids);

                // ── Aggregate answered counts per row (single query) ──────────
                $answeredCountByRow     = [];
                $completedActivityByRow = [];
                $otpVerifiedRows        = [];
                if (!empty($allRowIds) && !empty($activitiesids)) {
                    $rows = DB::table('temp_user_activity_answers_data')
                        ->whereIn('activity_id', $activitiesids)
                        ->whereIn('row_id', $allRowIds)
                        ->select(
                            'row_id',
                            DB::raw('COUNT(DISTINCT activity_id) as completed_activities'),
                            DB::raw('COUNT(DISTINCT CASE WHEN otp_verified_status = 1 AND mobile_otp IS NOT NULL THEN 1 END) as otp_done')
                        )
                        ->groupBy('row_id')
                        ->get();
                    foreach ($rows as $r) {
                        $completedActivityByRow[$r->row_id] = (int) $r->completed_activities;
                        if ($r->otp_done > 0) $otpVerifiedRows[$r->row_id] = true;
                    }
                    if (!empty($getAllQuestionIds)) {
                        $answeredCountByRow = DB::table('temp_user_activity_answers_data')
                            ->whereIn('activity_id', $activitiesids)
                            ->whereIn('row_id', $allRowIds)
                            ->whereIn('question_id', $getAllQuestionIds)
                            ->select('row_id', DB::raw('COUNT(DISTINCT question_id) as answered'))
                            ->groupBy('row_id')
                            ->pluck('answered', 'row_id')
                            ->toArray();
                    }
                }

                // ── Build result rows ─────────────────────────────────────────
                foreach ($projectTemplateHeadsAssigned as $assigned) {
                    $tid        = $assigned['project_template_id'];
                    $filterHead = $assigned['head_id'];
                    $filterVal  = $assigned['head_value'];

                    foreach ($allTemplateValues[$tid] ?? [] as $item) {
                        if ($item['template_name_head_id'] != $filterHead || $item['value'] != $filterVal) continue;
                        $rowId = $item['row_id'];
                        if (isset($row_rendered_arr[$rowId])) continue;
                        $row_rendered_arr[$rowId] = true;

                        $answeredCount = $answeredCountByRow[$rowId] ?? 0;
                        $allAnswered   = $totalQuestions > 0 ? ($answeredCount >= $totalQuestions) : false;
                        if (!$allAnswered && isset($otpVerifiedRows[$rowId])) $allAnswered = true;

                        $rowItems = $rowGroupedValues[$rowId] ?? [];
                        $main_header    = null;
                        $sub_header     = null;
                        $latitudeValue  = null;
                        $longitudeValue = null;
                        foreach ($rowItems as $ri) {
                            if ($ri['template_name_head_id'] == $main_header_id) $main_header    = $ri['value'];
                            if ($ri['template_name_head_id'] == $sub_header_id)  $sub_header     = $ri['value'];
                            if (in_array($ri['template_head_name'], $latitudeHeaders))  $latitudeValue  = $ri['value'];
                            if (in_array($ri['template_head_name'], $longitudeHeaders)) $longitudeValue = $ri['value'];
                        }

                        $projectStatus = $allAnswered ? 'completed' : 'pending';
                        $totalproject++;
                        if ($allAnswered) $totalcompletedproject++;

                        // Outlet count via pre-built lookup
                        $masterVals = array_filter(
                            json_decode($item['template_data_json'], true) ?? [],
                            fn($v) => $v !== null && $v !== ''
                        );
                        $matchingChildIds = [];
                        foreach ($masterVals as $mv) {
                            foreach ($valueToChildRowIds[(string)$mv] ?? [] as $cid) {
                                $matchingChildIds[$cid] = true;
                            }
                        }
                        $outletCount            = count($matchingChildIds);
                        $completedActivityCount = $completedActivityByRow[$rowId] ?? 0;

                        $project_data_arr[] = [
                            'row_id'               => (int) $rowId,
                            'project_template_id'  => (int) $tid,
                            'status'               => $projectStatus,
                            'main_header'          => $main_header,
                            'sub_header'           => $sub_header,
                            'latitude'             => $latitudeValue,
                            'longitude'            => $longitudeValue,
                            'is_outlet_assigned'   => $isOutletAssigned && $outletCount > 0 ? 1 : 0,
                            'total_activities'     => $totalActivityCount,
                            'completed_activities' => $completedActivityCount,
                            'pending_activities'   => $totalActivityCount - $completedActivityCount,
                            'total_outlets'        => $outletCount,
                        ];
                    }
                }
            }

            return [$project_data_arr, $totalcompletedproject, $totalproject];
        }); // end Cache::remember

        if ($totalproject > 0 && $totalcompletedproject == $totalproject) {
            $add_outlet = false;
        }

        Session::put('project_dist_activity', ['project' => $projectId]);
        Session::forget('project_outlet_activity');

        // ── Filter + paginate on the cached array (no DB, fast) ──────────────
        // (falls through cleanly when $project_data_arr is empty — total ends up 0,
        // data ends up [], but project_id/template_id/pagination fields still populate)
        $collection = collect($project_data_arr);

        if ($request->status) {
            $collection = $collection->where('status', $request->status);
        }

        if ($request->keyword) {
            $kw = strtolower($request->keyword);
            $collection = $collection->filter(
                fn($item) => str_contains(strtolower($item['main_header'] ?? ''), $kw)
                          || str_contains(strtolower($item['sub_header']  ?? ''), $kw)
            );
        }

        $currentPage      = (int) $request->get('page', 1);
        $perPage          = 10;
        $total            = $collection->count();
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginated = new LengthAwarePaginator(
            $currentPageItems, $total, $perPage, $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response([
            'status'              => 200,
            'message'             => 'success',
            'project_id'          => $projectId,
            'project_template_id' => (int) $project_temp_info->id,
            'template_id'         => (int) $project_temp_info->template_name_id,
            'total'               => $paginated->total(),
            'per_page'            => $paginated->perPage(),
            'current_page'        => $paginated->currentPage(),
            'next_page'           => $paginated->currentPage() < $paginated->lastPage()
                                         ? $paginated->currentPage() + 1 : null,
            'total_pages'         => $paginated->lastPage(),
            'add_distributor'     => $add_outlet,
            'add_signature'       => (bool) $project_temp_info->add_signature,
            'data'                => $paginated->items(),
        ], 200);
    }



    public function projectOutletData(Request $request)
    {
        $request->validate([
            'project_id'  => 'required|integer',
            'activity_id' => 'required|integer',
            'template_id' => 'required|integer',
        ]);

        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $startIndex = (($currentPage - 1) * $perPage + 1) - 1;
        $endIndex = ($currentPage * $perPage) - 1;
        $group_session_id = null;
        if (!isset($request->group_info_id)) {
            $group_info = null;
        } else {
            $group_session_id = $request->group_info_id;
        }
        $user = $request->user();

        //khushboo 16-04-25
        $isAuditClosed = false;
        //khushboo 16-04-25

        $totalNumberOfOutlets = 0;
        $projectCompletedCount = 0;

        $activity = Activity::find($request->activity_id);
        if (!$activity) {
            return response(['status' => 404, 'message' => 'Activity not found.'], 404);
        }

        $projectTemplateInfo = DB::table('project_templates')->where('project_id', $request->project_id)->get();

        $project_temp_info = $projectTemplateInfo->where('template_name_id', $request->template_id)->first();
        if (!$project_temp_info) {
            return response(['status' => 404, 'message' => 'Project template not found for this template_id.'], 404);
        }

        $outlet_master_info = $projectTemplateInfo->where('is_master', 1)->first();
        if (!$outlet_master_info) {
            return response(['status' => 404, 'message' => 'No master template found for this project.'], 404);
        }

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        $projectInfo = Project::find($request->project_id);
        if (!$projectInfo) {
            return response(['status' => 404, 'message' => 'Project not found.'], 404);
        }

        if (
            $projectInfo->is_otp_required == 1 &&
            !empty($project_temp_info->activity_otp_required_ids)
        ) {
            $otpRequiredIds = json_decode($project_temp_info->activity_otp_required_ids, true);

            if (!empty($otpRequiredIds) && in_array($request->activity_id, $otpRequiredIds)) {
                $otpRequiredStatus = 1;
            }
        }
        //khushboo 07-04-2025

        $distributors_arr = [];
        // $outlet_master_info already validated above
        $activity_group_name_id_or_activity_id = $outlet_master_info->activity_group_name_id_or_activity_id;

        $master_head_id = $project_temp_info->master_head_id;
        $outlet_master_sub_header_id = $outlet_master_info->sub_header;
        $outlet_master_main_header_id = $outlet_master_info->main_header;
        $outlet_master_completion_type = $outlet_master_info->completion_type;
        $outlet_master_min_completion = $outlet_master_info->min_completion;
        // Single query — reuse as both a filter collection and a keyed map
        $templateHeadName = DB::table('template_name_heads')->get();
        $templateHeads    = $templateHeadName->keyBy('id');
        $master_head_info = $templateHeadName->where('id', $master_head_id)->first();
        //        dd($master_head_info);
        $master_head_name = !empty($master_head_info) ? $master_head_info->template_head_name : '';
        $own_head_id = $project_temp_info->own_reference_head_id;
        $own_head_info = $templateHeadName->where('id', $own_head_id)->first();
        $cur_sequence_helper = 0;
        $activity_sequence = null;
        // This is to get the heads and key from outlet to the master template
        if (isset($request->group_info_id)) {
            $activity_sequence = DB::table('activity_group_pivots')->where('activity_id', $request->activity_id)
                ->where('activity_group_id', $request->group_info_id)
                ->first();
            if ($activity_sequence) {
                $cur_sequence_helper = $activity_sequence->sequence;
            }
        }

        // Load only assigned outlet rows — loading all rows is catastrophic for large datasets
        $allTemplates = empty($getRowIds) ? collect() :
            DB::table('project_template_name_values_new')
                ->select('id as row_id', 'project_template_id', 'template_data_json')
                ->whereIn('id', $getRowIds)
                ->where('project_template_id', $project_temp_info->id)
                ->get();

        $allTemplateNamesValues = [];
        foreach ($allTemplates as $row) {
            $jsonData = json_decode($row->template_data_json, true);
            foreach ($jsonData as $headId => $value) {
                if (isset($templateHeads[$headId])) {
                    $head = $templateHeads[$headId];
                    $allTemplateNamesValues[] = (object)[
                        'row_id' => $row->row_id,
                        'project_template_id' => $row->project_template_id,
                        'template_name_head_id' => $headId,
                        'value' => $value,
                        'head_id' => $headId,
                        'head_template_name_id' => $head->template_name_id,
                        'template_head_name' => $head->template_head_name,
                        'head_created_at' => $head->created_at,
                        'head_updated_at' => $head->updated_at,
                        'head_deleted_at' => $head->deleted_at,
                    ];
                }
            }
        }

        $allTemplateNamesValues = collect($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

        //        dd($cur_sequence_helper);
        //        if ($cur_sequence_helper == 0 || $cur_sequence_helper == 1) {

        // Single query — derive common_ids and data_assign_ids from the collection
        $get_project_template_assigned_data = UserActivityDataAssign::where('user_id', $user->id)
            ->where('project_template_id', $project_temp_info->id)
            ->where('activity_id', $request->activity_id)
            ->get();

        $dataAssignCommonIds = $get_project_template_assigned_data->pluck('common_id')->unique()->filter();

        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

        //            dd($get_distinct_data_assigned_ids);
        $getDataAssignTemplateHeadIds = DataAssign::whereIn('id', $get_distinct_data_assigned_ids)
            ->pluck('template_name_head_id')
            ->unique();

        $getRowIds = UserAuditAssigns::whereIn('common_id', $dataAssignCommonIds)
            ->pluck('row_id');

        // Pre-fetch data that is constant across all nested loop iterations
        $questionIds = Question::where('activity_id', $activity->id)->pluck('id')->toArray();

        // Pre-fetch all answers for all assigned rows at once (avoids N+1 per-row queries)
        $allAnswersByRow = DB::table('temp_user_activity_answers_data')
            ->where('activity_id', $activity->id)
            ->whereIn('row_id', $getRowIds)
            ->whereIn('question_id', $questionIds)
            ->get()
            ->groupBy('row_id');

        // Row IDs that have at least one answer (used in filter() instead of per-row EXISTS query)
        $answeredRowIdSet = $allAnswersByRow->keys()->toArray();

        // Pre-fetch ProjectMasterTemplates for all row IDs (avoids N+1 per-row queries)
        $masterTemplatesMap = \App\Models\ProjectMasterTemplates::whereIn('row_id', $getRowIds->toArray())
            ->get()
            ->keyBy('row_id');

        //            dd($getRowIds);
        foreach ($get_project_template_assigned_data as $assigned) {

            if ($assigned->audit_closed == 1) {
                $isAuditClosed = true;
            }

            foreach ($getDataAssignTemplateHeadIds as $headId) {

                //                    dd($assigned->dataAssign->is_outlet_assigned);
                if ($assigned->dataAssign->is_outlet_assigned == 1) {

                    $getHeadValues = DB::table('project_template_name_values_new')
                        ->whereIn('id', $getRowIds)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$project_temp_info->own_reference_head_id}\"')) as head_value")
                        )
                        ->pluck('head_value');
                } else {

                    $getHeadValues = DB::table('project_template_name_values_new')
                        ->whereIn('id', $getRowIds)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$headId}\"')) as head_value")
                        )
                        ->pluck('head_value');
                }

                //                dd($getHeadValues);
                foreach ($getHeadValues as $headValue) {
                    $get_rows_of_outlet = $getRowIds;

                    foreach ($get_rows_of_outlet as $outletRowId) {

                        $row_id = $outletRowId;
                        $row_data_group = $allTemplateGroupedByRow[$row_id] ?? collect();

                        $row_data = $row_data_group->firstWhere('template_name_head_id', $own_head_id);

                        if ($row_data && !in_array($row_data->value, array_column($distributors_arr, 'value'))) {
                            //                            echo 'count';
                            $get_master_row = $allTemplateNamesValues
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data->value)
                                ->where('project_template_id', $project_temp_info->id)
                                ->first();

                            //                                dd($get_master_row);

                            $dist_main_header = optional($allTemplateNamesValues
                                ->where('row_id', $get_master_row->row_id)
                                ->where('template_name_head_id', $own_head_id)
                                ->first())->value;

                            $dist_info = count($distributors_arr);
                            //                                dd($endIndex, $startIndex, $dist_info);
                            //                                if ($dist_info >= $startIndex && $dist_info <= $endIndex) {
                            //                                    continue;
                            //                                }

                            $get_sub_header_val = $allTemplateNamesValues->where('row_id', $get_master_row->row_id)
                                ->whereIn('template_name_head_id', [$project_temp_info->sub_header, $outlet_master_sub_header_id])
                                ->first();

                            $dist_sub_header = optional($get_sub_header_val)->value;
                            //                                dd($get_sub_header_val);

                            $get_count_of_outlets = $allTemplateNamesValues
                                ->where('project_template_id', $project_temp_info->id)
                                ->where('template_name_head_id', $own_head_id)
                                ->where('value', $row_data->value);

                            //                                dd($get_count_of_outlets);

                            // Use pre-fetched answered row IDs instead of N+1 DB queries inside filter()
                            $completed_count = $get_count_of_outlets->filter(function ($outlet) use ($answeredRowIdSet) {
                                return in_array($outlet->row_id, $answeredRowIdSet);
                            })->count();

                            // Use pre-loaded ProjectMasterTemplates map instead of per-row query
                            $masterTemplateRow = $masterTemplatesMap->get($get_master_row->row_id);
                            $min_value = $masterTemplateRow->min_completion ?? $outlet_master_min_completion;
                            $completion_type_value = $masterTemplateRow->completion_type ?? $outlet_master_completion_type;

                            // Use pre-fetched answers by row instead of per-row queries
                            $rowAnswers  = $allAnswersByRow->get($get_master_row->row_id, collect());
                            $answeredCount = $rowAnswers->pluck('question_id')->unique()->count();

                            $allAnswered = $answeredCount === count($questionIds);
                            $otpVerificationDone = false;

                            if ($allAnswered) {
                                $lastAnswer = $rowAnswers->whereNotNull('mobile_otp')->sortByDesc('id')->first();
                                $otpVerificationDone = $lastAnswer && $lastAnswer->otp_verified_status == 1;
                            }

                            $projectStatus = match (true) {
                                $allAnswered && $otpVerificationDone && $otpRequiredStatus == 1 => "completed",
                                $allAnswered && !$otpVerificationDone && $otpRequiredStatus == 1 => "Awaiting OTP Verify",
                                $allAnswered => "completed",
                                default => "pending",
                            };

                            if ($projectStatus === "completed") {
                                $projectCompletedCount++;
                            }

                            $totalNumberOfOutlets++;

                            $distributors_arr[] = [
                                'value' => $row_data->value,
                                'head_name' => $master_head_name,
                                'getDataOfRows' => $allTemplateGroupedByRow[$get_master_row->row_id] ?? collect(),
                                'activity_id' => $request->activity_id,
                                'activity_name' => $activity->activity_name,
                                'template_id' => $request->template_id,
                                'project_id' => $request->project_id,
                                'project_template_id' => $project_temp_info->id,
                                'total_outlets' => count($get_count_of_outlets),
                                'total_outlet_answered' => $completed_count,
                                'main_header' => $dist_main_header,
                                'sub_header' => $dist_sub_header,
                                'min_completion' => $min_value,
                                'completion_type' => $completion_type_value,
                                'status' => $projectStatus
                            ];
                        }
                    }
                }
            }
        }

        //    }

        $distributors_collection = collect($distributors_arr);

        if ($request->keyword) {
            $kw = strtolower($request->keyword);
            $distributors_collection = $distributors_collection->filter(function ($item) use ($kw) {
                return str_contains(strtolower($item['main_header'] ?? ''), $kw) ||
                       str_contains(strtolower($item['sub_header'] ?? ''), $kw);
            })->values();
        }

        if ($request->status) {
            $distributors_collection = $distributors_collection->where('status', $request->status)->values();
        }

        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $currentPageItems = $distributors_collection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedDistributors = new LengthAwarePaginator(
            $currentPageItems,
            $distributors_collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        //khushboo 17-05-25
        $add_outlet = false;
        if ($project_temp_info->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-25

        if ($totalNumberOfOutlets > 0 && $projectCompletedCount > 0 && $totalNumberOfOutlets == $projectCompletedCount) {
            $add_outlet = false;
        }

        return response([
            'status' => 200,
            'message' => 'success',
            // 'data'=>$distributors_arr,
            'data' => $paginatedDistributors,
            'group_session_id' => $group_session_id,
            'otp_required_status' => (string)$otpRequiredStatus, // khushboo 07-04-2025
            'show_audit_close_button' => false, // khushboo 17-04-2025
            'is_audit_closed' => false, // khushboo 17-04-2025
            'add_outlet' => $add_outlet
        ]);
    }


    public function auditCloseSubmit(Request $request)
    {
        $request->validate([
            'project_id'        => 'required|integer',
            'template_id'       => 'required|integer',
            'activity_id'       => 'required|integer',
            'distributor_value' => 'required|string',
        ]);

        try {

            $projectData = Project::find($request->project_id);
            if (!$projectData) {
                return response()->json(['status' => 404, 'message' => 'Project not found.'], 404);
            }
            $projectTemplateInfo = ProjectTemplate::where('project_id', $request->project_id)
                ->where('template_name_id', $request->template_id)
                ->first();
            if (!$projectTemplateInfo) {
                return response()->json(['status' => 404, 'message' => 'Project template not found.'], 404);
            }
            $activity = Activity::find($request->activity_id);
            if (!$activity) {
                return response()->json(['status' => 404, 'message' => 'Activity not found.'], 404);
            }
            $user = $request->user();
            $distributor_value = $request->distributor_value;

            $checkIfDataAssignExist = UserActivityDataAssign::where('user_id', $user->id)
                ->where('activity_id', $activity->id)
                ->where('project_template_id', $projectTemplateInfo->id)
                ->exists();

            if ($checkIfDataAssignExist) {

                $getDataAssignedCommonId = UserActivityDataAssign::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->distinct('common_id')
                    ->pluck('common_id');

                $getDataAssignedIds = UserActivityDataAssign::where('user_id', $user->id)
                    ->where('activity_id', $activity->id)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->distinct('data_assign_id')
                    ->pluck('data_assign_id');

                $getAuditorRowIds = UserAuditAssigns::whereIn('common_id', $getDataAssignedCommonId)
                    ->distinct('row_id')->pluck('row_id');

                $getAssignedData = DataAssign::whereIn('id', $getDataAssignedIds)->get();
                $getAssignedTemplateHeadIds = [];
                foreach ($getAssignedData as $assignedData) {
                    if ($assignedData->is_outlet_assigned == 1) {
                        $getAssignedTemplateHeadIds[] = $projectTemplateInfo->own_reference_head_id;
                    } else {
                        $getAssignedTemplateHeadIds[] = $assignedData->template_name_head_id;
                    }
                }
                // Convert to array if it's a collection
                $templateHeadIds = is_array($getAssignedTemplateHeadIds) ? $getAssignedTemplateHeadIds : $getAssignedTemplateHeadIds->toArray();
                //                dd($templateHeadIds, $distributor_value);
                $fetchRowIdWithValue = DB::table('project_template_name_values_new')
                    ->whereIn('id', $getAuditorRowIds)
                    ->where('project_template_id', $projectTemplateInfo->id)
                    ->where(function ($query) use ($templateHeadIds, $distributor_value) {
                        foreach ($templateHeadIds as $headId) {
                            $query->orWhereRaw(
                                "JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$headId\"')) = ?",
                                [trim($distributor_value)]
                            );
                        }
                    })
                    ->pluck('id')
                    ->toArray();
                //                dd($fetchRowIdWithValue);
                $main_header = TemplateNameHead::find($projectTemplateInfo->main_header);
                $sub_header = TemplateNameHead::find($projectTemplateInfo->sub_header);

                $allAnswers = DB::table('temp_user_activity_answers_data')
                    ->whereIn('row_id', $fetchRowIdWithValue)
                    ->get();

                foreach ($fetchRowIdWithValue as $rowId) {

                    $checkIfAnswerAlreadySubmitted = $allAnswers->where('row_id', $rowId)->isNotEmpty();
                    //                    dd($checkIfAnswerAlreadySubmitted);

                    if (!$checkIfAnswerAlreadySubmitted) {
                        $checkIfExists = ClosedAudits::where('project_template_id', $projectTemplateInfo->id)
                            ->where('main_header', $main_header->template_head_name)
                            ->where('sub_header', $sub_header->template_head_name)
                            ->where('activity_id', $activity->id)
                            ->where('row_id', $rowId)
                            ->where('user_id', $user->id)
                            ->where('distributor_value', $distributor_value)
                            ->exists();
                        if (!$checkIfExists) {
                            ClosedAudits::insert([
                                'project_template_id' => $projectTemplateInfo->id,
                                'main_header' => $main_header->template_head_name,
                                'sub_header' => $sub_header->template_head_name,
                                'activity_id' => $activity->id,
                                'row_id' => $rowId,
                                'user_id' => $user->id,
                                'distributor_value' => $distributor_value,
                                'audit_closed_status' => 1,
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
                            ]);
                        }
                    }
                }
            }

            return response()->json(['status' => 200, 'message' => 'Audit Closed Successfully']);
        } catch (\Exception $e) {
            //            dd($e->getMessage());
            return response()->json(['status' => 201, 'message' => 'Something went wrong']);
        }
    }


    public function getAllQuestion(Request $request)
    {
        $request->validate([
            'row_id'            => 'required|integer',
            'activity_id'       => 'required|integer',
            'activity_sequence' => 'nullable|integer',
        ]);

        $user              = $request->user();
        $row_id            = (int) $request->row_id;
        $activity_id       = (int) $request->activity_id;
        $activity_sequence = (int) ($request->activity_sequence ?? 0);
        $group_info        = $request->group_info;

        // Load all questions with full sub-question + option tree (mirrors row_data_activity)
        $allQuestions = Question::with([
            'getOptions',
            'getSubjects.getOptions',
            // Level 1 sub-questions (via QuestionSubQuestion pivot)
            'subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.getSubjects.getOptions',
            // Level 2
            'subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
            'subQuestions.childQuestion.subQuestions.childQuestion.getSubjects.getOptions',
            // Level 3
            'subQuestions.childQuestion.subQuestions.childQuestion.subQuestions.childQuestion.getOptions',
        ])->where('activity_id', $activity_id)->orderBy('question_sequence')->get();

        // Build a map of sub-question children from the pivot table:
        // child_question_id => parent_question_id (the Multi Response parent)
        $subQParentMap = DB::table('question_sub_questions')
            ->whereIn('child_question_id', $allQuestions->pluck('id')->toArray())
            ->pluck('parent_question_id', 'child_question_id');

        // Separate root questions from conditional children (parent_question_id-based)
        $rootQuestions  = $allQuestions->whereNull('parent_question_id')->values();
        $childQuestions = $allQuestions->whereNotNull('parent_question_id');

        // Build map: parent_id => [children grouped by parent_value]
        $childrenByParent = $childQuestions->groupBy('parent_question_id');

        // Build id => question name map for parent_question_name lookups
        $questionNameMap = $allQuestions->pluck('question', 'id');

        // Attach conditional_questions and parent_question_name
        $related_questions = $rootQuestions->map(function ($question) use ($childrenByParent, $subQParentMap, $questionNameMap) {
            // Conditional children only — questions linked via questions.parent_question_id column.
            // These are triggered by a specific parent answer (yes/no value, dropdown value, or __non_empty__).
            // MR sub-questions are NOT included here; they already appear via the sub_questions relation.
            $children = $childrenByParent->get($question->id, collect());

            // true when any conditional child uses __non_empty__ logic (show as soon as parent has any answer)
            $question->on_click = $children->contains('parent_value', '__non_empty__');

            $conditionalQuestions = [];
            foreach ($children as $child) {
                $childArr = $child->toArray();
                $childArr['trigger_value']        = $child->parent_value;   // specific value, or '__non_empty__'
                $childArr['parent_question_name'] = $questionNameMap->get($child->parent_question_id);
                $conditionalQuestions[] = $childArr;
            }
            $question->conditional_questions = $conditionalQuestions;


            // Fill parent_question_id + parent_question_name for MR sub-question children
            if (is_null($question->parent_question_id) && $subQParentMap->has($question->id)) {
                $question->parent_question_id   = $subQParentMap->get($question->id);
                $question->parent_question_name = $questionNameMap->get($question->parent_question_id);
            } elseif (!is_null($question->parent_question_id)) {
                $question->parent_question_name = $questionNameMap->get($question->parent_question_id);
            } else {
                $question->parent_question_name = null;
            }

            return $question;
        });

        // IDs of all conditional children (so app knows these are not standalone root questions)
        $sub_question_child_ids = $childQuestions->pluck('id')->toArray();

        // ── Build question codes; only root questions go into data ────────────
        // Collect sub-question links per MR parent (ordered by pivot sequence)
        $subLinksByParent = [];
        foreach ($related_questions as $_rq) {
            foreach ($_rq->subQuestions ?? [] as $_sl) {
                $subLinksByParent[$_rq->id][] = $_sl;
            }
        }

        $questionCodeMap = [];
        $displaySeq      = 0;
        $rootData        = [];   // only top-level questions; children stay nested

        foreach ($related_questions->sortBy('question_sequence') as $_rq) {
            // Skip any question that is a child of something else:
            // - MR sub-question child (parent_question_id was filled from pivot in the map above)
            // - Conditional child (parent_question_id set in the questions table)
            if (!is_null($_rq->parent_question_id)) continue;

            $displaySeq++;
            $code = 'Q' . str_pad($displaySeq, 3, '0', STR_PAD_LEFT);
            $questionCodeMap[$_rq->id] = $code;
            $_rq->question_code    = $code;
            $_rq->display_sequence = $displaySeq;

            // Generate sub-Q codes and set them directly on the child model objects
            // so they appear inside sub_questions.child_question when serialised
            if ($_rq->question_type === 'Multi Response') {
                $_rq->answer_type = 0; // MR parent is a section header, never directly answered
                $alpha = 0;
                foreach (collect($subLinksByParent[$_rq->id] ?? [])->sortBy('sequence') as $_sl) {
                    $child = $_sl->childQuestion ?? null;
                    if (!$child) continue;
                    $alpha++;
                    $childCode = $code . chr(96 + $alpha);
                    $questionCodeMap[$child->id] = $childCode;
                    $child->question_code        = $childCode;
                    $child->display_sequence     = $displaySeq + $alpha;
                    $child->parent_question_id   = $_rq->id;
                    $child->parent_question_name = $_rq->question;
                }
            }

            $rootData[] = $_rq;
        }
        // ─────────────────────────────────────────────────────────────────────

        // Load ALL answers for this row+activity in ONE query; derive current_answers from it
        $allAnswers = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activity_id)
            ->get();

        if ($activity_sequence > 0) {
            $current_answers = $allAnswers->filter(fn($a) => $a->activity_sequence == $activity_sequence)->values();
        } else {
            $current_answers = $allAnswers->filter(fn($a) => ($a->activity_sequence ?? 0) == 0)->values();
        }

        // Load ALL repeat instances in ONE query; derive instanceMap and all_instances_raw from it
        $all_instances_raw = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activity_id)
            ->orderBy('activity_sequence')
            ->get();

        $instanceMap = $all_instances_raw->keyBy('activity_sequence');

        // Status label helper
        $statusLabel = fn(int $s): string => match($s) {
            1       => 'approved',
            3       => 'sent_back',
            default => 'pending',
        };

        // Derive the overall status of a set of answers:
        // sent_back beats pending; approved only when ALL answers are approved
        $instanceStatus = function ($answers) use ($statusLabel): array {
            if ($answers->isEmpty()) {
                return ['status' => 0, 'status_label' => 'pending'];
            }
            if ($answers->contains('status', 3)) {
                return ['status' => 3, 'status_label' => 'sent_back'];
            }
            $statuses = $answers->pluck('status')->unique();
            $code = ($statuses->count() === 1 && $statuses->first() == 1) ? 1 : 0;
            return ['status' => $code, 'status_label' => $statusLabel($code)];
        };

        // Map question_id → question_type for answer formatting
        $questionTypeMap = $allQuestions->pluck('question_type', 'id');
        $fileQuestionTypes = ['Image', 'Audio', 'Video', 'File Upload'];

        // Convert a stored file path to an absolute URL.
        // - Already absolute (http/https/file://) → returned as-is
        // - Relative server path (e.g. activityAnswerImages/...) → prepend base URL
        $toAbsoluteUrl = function (string $path): string {
            if (preg_match('#^(https?://|file://)#i', $path)) {
                return $path; // already absolute
            }
            return url($path);
        };

        // Resolve a stored user_answer into the value the app expects:
        // - File/image answers stored as JSON array → decoded array of URLs
        // - Single file path → array with one URL
        // - All other answers → returned as-is
        $resolveAnswer = function ($a) use ($questionTypeMap, $fileQuestionTypes, $toAbsoluteUrl) {
            $qtype = $questionTypeMap->get($a->question_id);
            $raw   = $a->user_answer;

            if ($raw === null || $raw === '') {
                return $raw;
            }

            // Always decode JSON array answers regardless of question type
            // (covers multi-image and any other array-stored answers)
            if (is_string($raw) && str_starts_with(trim($raw), '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return array_map(fn($p) => $toAbsoluteUrl((string) $p), $decoded);
                }
            }

            // For file-type questions with a single stored path → wrap in array + full URL
            // Also catch by path prefix in case question type lookup missed (e.g. MR sub-question
            // whose activity_id differs from the current activity in $questionTypeMap)
            if (in_array($qtype, $fileQuestionTypes)
                || str_starts_with($raw, 'activityAnswerImages/')
            ) {
                return [$toAbsoluteUrl($raw)];
            }

            return $raw;
        };

        // Only the fields the app needs to prefill / resubmit answers
        $pluckAnswer = fn($a) => [
            'question_id'       => (int) $a->question_id,
            'parent_context_id' => $a->parent_context_id !== null ? (int) $a->parent_context_id : null,
            'same_answer_id'    => $a->same_answer_id !== null ? (int) $a->same_answer_id : null,
            'user_answer'       => $resolveAnswer($a),
            'status'            => (int) $a->status
        ];

        // Build answer_data as instance-grouped array
        $originalAnswers = $allAnswers
            ->filter(fn($a) => ($a->activity_sequence ?? 0) == 0)
            ->map($pluckAnswer)
            ->values();

        $origStatus = $instanceStatus($allAnswers->filter(fn($a) => ($a->activity_sequence ?? 0) == 0));

        $answer_data = [
            'original' => [
                'instance_id'       => null,
                'activity_sequence' => 0,
                'instance_label'    => 'Original',
                'status'            => $origStatus['status'],
                'status_label'      => $origStatus['status_label'],
                'answers'           => $originalAnswers,
            ],
            'instances' => $instanceMap->map(function ($instance) use ($allAnswers, $pluckAnswer, $instanceStatus) {
                $raw = $allAnswers->filter(fn($a) => $a->activity_sequence == $instance->activity_sequence);
                $instanceSt = $instanceStatus($raw);
                return [
                    'instance_id'       => (int) $instance->id,
                    'activity_sequence' => (int) $instance->activity_sequence,
                    'instance_label'    => $instance->instance_label,
                    'status'            => $instanceSt['status'],
                    'status_label'      => $instanceSt['status_label'],
                    'answers'           => $raw->map($pluckAnswer)->values(),
                ];
            })->values(),
        ];

        $answer_data_flat = $current_answers;

        // Sent-back flag for this specific instance (derived from already-loaded collection)
        $is_sent_back = $answer_data_flat->contains('status', 3);

        // Any instance sent back — derived from $allAnswers (no extra query)
        $has_any_sent_back = $allAnswers->contains('status', 3);

        // Repeat instance lookup — use $instanceMap already built above (no extra query)
        $repeat_instance = $activity_sequence > 0 ? $instanceMap->get($activity_sequence) : null;

        // Saved sequences — derived from $allAnswers (no extra query)
        $savedSequences = $allAnswers
            ->filter(fn($a) => ($a->activity_sequence ?? 0) > 0)
            ->pluck('activity_sequence')
            ->unique()
            ->toArray();

        $all_instances = $all_instances_raw->map(function ($instance) use ($savedSequences) {
            $instance->is_saved = in_array($instance->activity_sequence, $savedSequences);
            return $instance;
        });

        // Original submitted check — derived from $allAnswers (no extra query)
        $original_submitted = $allAnswers
            ->filter(fn($a) => ($a->activity_sequence ?? 0) == 0 && $a->user_id == $user->id)
            ->isNotEmpty();

        $current_instance_saved = $activity_sequence > 0
            ? $allAnswers->filter(fn($a) => $a->activity_sequence == $activity_sequence)->isNotEmpty()
            : $original_submitted;

        $is_closed = ActivityInstanceClose::where('row_id', $row_id)
            ->where('activity_id', $activity_id)
            ->where('user_id', $user->id)
            ->exists();

        // Determine allow_repeat and otp_required from project template + project
        $allow_repeat      = false;
        $is_otp_required   = false;
        $signature_allowed = false;
        $rowData         = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplate = $rowData ? ProjectTemplate::find($rowData->project_template_id) : null;
        if ($projectTemplate) {
            $addOnActivityIds = json_decode($projectTemplate->activity_add_on_activity_ids ?? '[]', true);
            $allow_repeat     = $projectTemplate->activity_add_on == 1 &&
                (empty($addOnActivityIds) || in_array($activity_id, array_map('intval', $addOnActivityIds)));
            $is_otp_required  = (bool) DB::table('projects')->where('id', $projectTemplate->project_id)->value('is_otp_required');

            // true = signature still needs to be captured; false once one is already
            // stored for this row+activity+user, or if the template doesn't require
            // a signature at all.
            $signature_allowed = (bool) $projectTemplate->add_signature
                && !ActivitySignature::where('row_id', $row_id)
                    ->where('activity_id', $activity_id)
                    ->where('user_id', $user->id)
                    ->exists();
        }

        // next_sequence: the activity_sequence the next new instance will receive.
        // Derived from already-loaded $all_instances_raw — no extra query needed.
        // null when allow_repeat is false (no instances possible).
        $next_sequence = $allow_repeat
            ? (int) ($all_instances_raw->max('activity_sequence') ?? 0) + 1
            : null;

        return response()->json([
            'status'                 => 200,
            'message'                => 'success',
            'data'                   => $rootData,
            'sub_question_child_ids' => $sub_question_child_ids,
            'answer_data'            => $answer_data,
            'activity_sequence'      => $activity_sequence,
            'next_sequence'          => $next_sequence,
            'is_sent_back'           => $is_sent_back,
            'has_any_sent_back'      => $has_any_sent_back,
            'allow_repeat'           => $allow_repeat,
            'is_otp_required'        => $is_otp_required,
            'signature_allowed'      => $signature_allowed,
            'is_closed'              => $is_closed,
            'original_submitted'     => $original_submitted,
            'current_instance_saved' => $current_instance_saved,
            'all_instances'          => $all_instances,
            'repeat_instance'        => $repeat_instance,
            'group_session_id'       => $group_info,
        ]);
    }

    public function myProjectsDistributorOutletsData(Request $request, $rowId, $distributor_value = null)
    {
        // Route param may be URL-encoded (%20 → space); also allow passing via query string
        $distributor_value = urldecode($distributor_value ?? $request->query('distributor_value', ''));
        $rowInfo = DB::table('project_template_name_values_new as ptnv')
            ->join('project_templates as pt', 'pt.id', '=', 'ptnv.project_template_id')
            ->join('projects as p', 'p.id', '=', 'pt.project_id')
            ->where('ptnv.id', $rowId)
            ->select('ptnv.id as row_id', 'ptnv.template_data_json',
                'pt.id as template_id', 'pt.project_id', 'pt.is_master',
                'pt.main_header', 'pt.sub_header', 'pt.template_name_id',
                'pt.activity_add_on', 'pt.activity_add_on_activity_ids',
                'pt.activityType', 'pt.activity_group_name_id_or_activity_id',
                'pt.data_add_on', 'pt.own_reference_head_id', 'pt.master_head_id',
                'pt.min_completion', 'pt.activity_otp_required_ids',
                'p.id as project_id_val', 'p.is_otp_required')
            ->first();
        if (!$rowInfo) {
            return response()->json(['status' => 404, 'message' => 'Row not found.'], 404);
        }
        $projectTemplateNameValue = (object)['id' => $rowInfo->row_id, 'template_data_json' => $rowInfo->template_data_json, 'project_template_id' => $rowInfo->template_id];
        $projectTemplate = (object)[
            'id' => $rowInfo->template_id, 'project_id' => $rowInfo->project_id,
            'is_master' => $rowInfo->is_master, 'main_header' => $rowInfo->main_header,
            'sub_header' => $rowInfo->sub_header, 'template_name_id' => $rowInfo->template_name_id,
            'activity_add_on' => $rowInfo->activity_add_on,
            'activity_add_on_activity_ids' => $rowInfo->activity_add_on_activity_ids,
            'activityType' => $rowInfo->activityType,
            'activity_group_name_id_or_activity_id' => $rowInfo->activity_group_name_id_or_activity_id,
            'data_add_on' => $rowInfo->data_add_on,
            'own_reference_head_id' => $rowInfo->own_reference_head_id,
            'master_head_id' => $rowInfo->master_head_id,
            'min_completion' => $rowInfo->min_completion,
            'activity_otp_required_ids' => $rowInfo->activity_otp_required_ids,
        ];
        $user   = $request->user();
        $userId = $user->id;

        $project = (object)['id' => $rowInfo->project_id_val, 'is_otp_required' => $rowInfo->is_otp_required];
        $outlet_main_header_id = $projectTemplate->main_header;
        $outlet_sub_header_id = $projectTemplate->sub_header;

        // Single query — derive ids and own_reference_head_ids from the collection
        $childTemplatesData = DB::table('project_templates')
            ->where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $childTemplatesIds    = $childTemplatesData->pluck('id')->toArray();
        $childTempOwnRefIds   = $childTemplatesData->pluck('own_reference_head_id')->toArray();

        //khushboo 17-05-2025
        $add_outlet = false;
        if ($projectTemplate->data_add_on == 1) {
            $add_outlet = true;
        }
        //khushboo 17-05-2025

        //khushboo 07-04-2025
        $otpRequiredStatus = 0;
        // $projectInfo = Project::findOrFail($project->id);
        // dd($project->is_otp_required, $projectTemplate->activity_otp_required_ids);
        if (($project->is_otp_required == 1) && !empty($projectTemplate->activity_otp_required_ids) && !empty(json_decode($projectTemplate->activity_otp_required_ids))) {
            $otpRequiredStatus = 1;
        }

        //khushboo 07-04-2025

        $row_renderred_arr = [];
        $project_data_arr = [];
        $previous_sequence_answered_rows = [];
        $is_to_check_previous_submitted = 0;
        $group_session_id = null;

        $activities = [];
        foreach ($childTemplatesData as $childTemp) {

            if ($childTemp->activityType == 0) {
                $activities[] = $childTemp->activity_group_name_id_or_activity_id;
            } elseif ($childTemp->activityType == 1) {
                $activities = array_merge(
                    $activities,
                    DB::table('activity_group_pivots')->where('activity_group_id', $childTemp->activity_group_name_id_or_activity_id)
                        ->pluck('activity_id')
                        ->toArray()
                );
            }
        }

        $rows_in_templates = DB::table('project_template_name_values_new')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->pluck('id')
            ->toArray();

        // $childTemp is the last iterated child template (or null if $childTemplatesData is empty)
        $childMainHeaderId = isset($childTemp) ? $childTemp->main_header : null;

        $allTemplates = DB::table('project_template_name_values_new')
            ->select('id as row_id', 'project_template_id', 'template_data_json')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();


        $templateHeads = DB::table('template_name_heads')
            ->get()
            ->keyBy('id');

        $allTemplateNamesValues = [];
        foreach ($allTemplates as $row) {
            $jsonData = json_decode($row->template_data_json, true);
            foreach ($jsonData as $headId => $value) {
                if (isset($templateHeads[$headId])) {
                    $head = $templateHeads[$headId];
                    $allTemplateNamesValues[] = (object)[
                        'row_id' => $row->row_id,
                        'project_template_id' => $row->project_template_id,
                        'template_name_head_id' => $headId,
                        'template_data_json' => $row->template_data_json,
                        'value' => $value,
                        'head_id' => $headId,
                        'head_template_name_id' => $head->template_name_id,
                        'template_head_name' => $head->template_head_name,
                        'head_created_at' => $head->created_at,
                        'head_updated_at' => $head->updated_at,
                        'head_deleted_at' => $head->deleted_at,
                    ];
                }
            }
        }

        $allTemplateNamesValues = collect($allTemplateNamesValues);
        $allTemplateGroupedByRow = $allTemplateNamesValues->groupBy('row_id');

        $childDataAssignIds = DB::table('data_assigns')->whereIn('project_template_id', $childTemplatesIds)
            ->where('is_outlet_assigned', 1)
            ->pluck('id')->toArray();

        $get_project_template_assigned_data = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('data_assign_id', $childDataAssignIds)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->get();

        $dataAssignCommonIds = DB::table('user_activity_data_assigns')->where('user_id', $user->id)
            ->whereIn('project_template_id', $childTemplatesIds)
            ->whereIn('activity_id', $activities)
            ->distinct('common_id')
            ->pluck('common_id');


        $get_distinct_data_assigned_ids = $get_project_template_assigned_data->pluck('data_assign_id')->unique();

        $getRowIds = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIds)
            ->distinct('row_id')
            ->pluck('row_id');

        $is_to_check_previous_submitted = 1;

        // Single query replaces N per-activity queries — find rows answered for ALL activities
        if (!empty($activities) && !empty($rows_in_templates)) {
            $answeredByActivity = DB::table('temp_user_activity_answers_data')
                ->whereIn('row_id', $rows_in_templates)
                ->whereIn('activity_id', $activities)
                ->distinct()
                ->select('activity_id', 'row_id')
                ->get()
                ->groupBy('activity_id');

            foreach ($activities as $previous_sequence_activity) {
                $get_previous_sequence_answered = $answeredByActivity->get($previous_sequence_activity, collect())
                    ->pluck('row_id')->toArray();
                if (empty($previous_sequence_answered_rows)) {
                    $previous_sequence_answered_rows = $get_previous_sequence_answered;
                } else {
                    $previous_sequence_answered_rows = array_intersect($previous_sequence_answered_rows, $get_previous_sequence_answered);
                }
            }
        }

        //        $outlet_items = $allTemplateNamesValues
        //            ->whereIn('project_template_id', $childTemplatesIds)
        //            ->where('template_name_head_id', $childTempOwnRefIds)
        //            ->where('value', $distributor_value);

        $checkPrentOutletAssign = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
            ->where('is_outlet_assigned', 1)
            ->exists();

        if ($checkPrentOutletAssign) {

            $parentDataAssignIds = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)
                ->where('is_outlet_assigned', 1)
                ->pluck('id')->toArray();

            //            dd($parentDataAssignIds, $projectTemplate->id);
            $dataAssignCommonIdsnew = DB::table('user_activity_data_assigns')->where('user_id', $userId)
                ->whereIn('data_assign_id', $parentDataAssignIds)
                ->distinct('common_id')
                ->pluck('common_id');
            //

            $getRowIdsnew = DB::table('user_audit_assigns')->whereIn('common_id', $dataAssignCommonIdsnew)
                ->distinct('row_id')
                ->pluck('row_id');

            //            dd($getRowIds);

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIdsnew)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

            //            dd($outlet_items, $childTemplatesIds, $getRowIds, $distributor_value, $allTemplateNamesValues);

        } else {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });
        }

        // dd($outlet_items->IsEmpty());
        if ($checkPrentOutletAssign && $outlet_items->IsEmpty()) {

            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($distributor_value) {
                    $json = json_decode($item->template_data_json, true);
                    // check if distributor_value exists anywhere in JSON values
                    return in_array($distributor_value, $json, true);
                });

            // dd($outlet_items, $getRowIds);

        }

        //outlet assign data with different head value
        if ($outlet_items->IsEmpty()) {


            $tempjsondata = json_decode($projectTemplateNameValue->template_data_json, true);

            // get only values, ignore keys
            $allValues = array_values($tempjsondata);


            $outlet_items = $allTemplateNamesValues
                ->whereIn('row_id', $getRowIds)
                ->whereIn('project_template_id', $childTemplatesIds)
                ->whereIn('template_name_head_id', $childTempOwnRefIds)
                ->filter(function ($item) use ($allValues) {
                    $json = json_decode($item->template_data_json, true);

                    // check if any value from $allValues exists in this row's JSON
                    return count(array_intersect($allValues, $json)) > 0;
                });
        }

        //  dd($outlet_items->IsEmpty());
        // if ($is_to_check_previous_submitted && !empty($previous_sequence_answered_rows)) {
        //     $outlet_items = $outlet_items->whereIn('row_id', $previous_sequence_answered_rows);
        // }

        $outlet_items = $outlet_items->values(); // Re-index the collection

        // dd($outlet_items);

        $projectCompletedStatus = 0;
        $auditCompleteCounts = 0;

        $allAnswers = collect(DB::table('temp_user_activity_answers_data')
            ->whereIn('row_id', $getRowIds)
            ->get());

        // Pre-load data that is constant across all loop iterations
        $childTemplateInfoById = $childTemplatesData->keyBy('id');

        //khushboo 02-05-2025
        $getAllQuestionIds = DB::table('questions')
            ->whereIn('activity_id', $activities)
            ->pluck('id')
            ->toArray();

        $getAllRequiredQuestionIds = DB::table('questions')
            ->whereIn('activity_id', $activities)
            ->where('answer_type', 1)
            ->pluck('id')
            ->toArray();

        $totalQuestions = count($getAllRequiredQuestionIds);

        // Pre-load closed audits for this distributor to avoid N+1 ClosedAudits queries
        $closedAuditRowIds = DB::table('closed_audits')
            ->whereIn('project_template_id', $childTemplatesIds)
            ->whereIn('activity_id', $activities)
            ->where('user_id', $user->id)
            ->where('distributor_value', $distributor_value)
            ->pluck('row_id')
            ->toArray();

        //        dd($outlet_items);

        foreach ($outlet_items as $outlet_item) {
            if (!in_array($outlet_item->row_id, $row_renderred_arr)) {
                $row_renderred_arr[] = $outlet_item->row_id;

                // Attach get_head_name to the data_item
                $outlet_item->get_head_name = $templateHeads[$outlet_item->template_name_head_id] ?? null;

                $headRows = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();
                $outlet_item->get_data_of_rows = $headRows->map(function ($row) use ($templateHeads) {
                    $rowCopy = clone $row; // prevent recursive reference
                    $rowCopy->get_head_name = $templateHeads[$row->template_name_head_id] ?? null;
                    return $rowCopy;
                })->values();

                $check_if_answered = $allAnswers->where('row_id', $outlet_item->row_id)
                    //                    ->where('activity_id', $activity->id)
                    ->isNotEmpty();

                $row_group = $allTemplateGroupedByRow[$outlet_item->row_id] ?? collect();

                // Use pre-loaded collection instead of per-row DB query
                $templateNameData = $childTemplateInfoById->get($outlet_item->project_template_id);

                $answeredCount = $allAnswers->where('row_id', $outlet_item->row_id)
                    ->whereIn('activity_id', $activities)
                    ->whereIn('question_id', $getAllRequiredQuestionIds)
                    ->unique('question_id')
                    ->count('question_id');

                // $allAnswered = $answeredCount === $totalQuestions;
                $allAnswered = false;
                // dd($answeredCount, $totalQuestions);
                //                dd($totalQuestions, $answeredCount);
                if ($answeredCount === $totalQuestions) {
                    $allAnswered = true;
                }

                // dd($totalQuestions,);
                // $allAnswered = true;
                $otpVerificationDone = false;
                // if ($allAnswered) {
                $lastQuestionAnswered = $allAnswers->where('row_id', $outlet_item->row_id)
                    //                        ->where('activity_id', $activity->id)
                    ->whereIn('question_id', $getAllQuestionIds)
                    ->whereNotNull('mobile_otp')
                    ->sortByDesc('id')
                    ->first();
                //                     dd($lastQuestionAnswered);
                if (!empty($lastQuestionAnswered) && !empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                    $otpVerificationDone = true;
                    $allAnswered = true;
                }
                // }


                $projectStatus = "pending";
                if ($allAnswered && $otpVerificationDone && ($otpRequiredStatus == 1)) {
                    $projectStatus = "completed";
                    // } else if ($allAnswered && !$otpVerificationDone && ($otpRequiredStatus == 1)) {
                    // $projectStatus = "Awaiting OTP Verify";
                } else if ($allAnswered) {
                    $projectStatus = "completed";
                }
                //khushboo 02-05-2025

                if ($projectStatus == "completed") {
                    $projectCompletedStatus++;
                }



                $main_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->main_header))->value;
                $sub_header = optional($row_group->firstWhere('template_name_head_id', $templateNameData->sub_header))->value;

                // Use pre-loaded closed audit row IDs (bulk-fetched before the loop)
                $checkIfExists = in_array($outlet_item->row_id, $closedAuditRowIds);

                if ($checkIfExists) {
                    $projectStatus = "completed";
                    $auditCompleteCounts++;
                }

                $totalActivityCount     = count($activities);
                $completedActivityCount = $allAnswers
                    ->where('row_id', $outlet_item->row_id)
                    ->pluck('activity_id')->unique()->intersect($activities)->count();
                $pendingActivityCount   = $totalActivityCount - $completedActivityCount;

                $project_data_arr[] = [
                    'row_id'               => (int) $outlet_item->row_id,
                    'project_template_id'  => (int) $outlet_item->project_template_id,
                    'project_id'           => (int) $project->id,
                    'template_id'          => (int) $templateNameData->template_name_id,
                    'status'               => $projectStatus,
                    'main_header'          => $main_header,
                    'sub_header'           => $sub_header,
                    'add_outlet'           => $templateNameData->data_add_on == 1 ? true : false,
                    'total_activities'     => $totalActivityCount,
                    'completed_activities' => $completedActivityCount,
                    'pending_activities'   => $pendingActivityCount,
                ];
            }
        }

        //        }

        //        Session::put('project_outlet_activity', ['projectTemplate' => $projectTemplate->id, 'distributor_value' => $distributor_value]);
        if (Session::has('project_activity')) {
            Session::forget('project_activity');
        }

        $masterPorjectTemplate = DB::table('project_templates')
            ->where('project_id', $projectTemplate->project_id)
            ->where('is_master', 1)->first();

        $minimumCount = $masterPorjectTemplate->min_completion ?? 0;
        // dd($projectTemplate);
        $showCLoseAuditOption = false;
        $IsAuditClosed = false;

        //         dd($projectCompletedStatus, (int) $minimumCount);
        if ($minimumCount && $projectCompletedStatus > 0 && $projectCompletedStatus >= (int)$minimumCount) {
            $showCLoseAuditOption = true;
        }

        if ($auditCompleteCounts > 0) {
            $showCLoseAuditOption = false;
        }

        $project_data_arr = collect($project_data_arr);

        // Now you can use the where method
        if ($request->status) {
            $project_data_arr = $project_data_arr->where('status', $request->status);
        }
        if ($request->keyword) {
            $project_data_arr = $project_data_arr->filter(function ($item) use ($request) {
                return strpos(strtolower($item['main_header']), strtolower($request->keyword)) !== false ||
                    strpos(strtolower($item['sub_header']), strtolower($request->keyword)) !== false;
            });
        }

        $distributors_collection = collect($project_data_arr);
        $currentPage = request()->get('page', 1);
        $perPage = 10;
        $currentPageItems = $distributors_collection->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $paginatedDistributors = new LengthAwarePaginator(
            $currentPageItems,
            $distributors_collection->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );


        $childTemplatesNameIds = DB::table('project_templates')
            ->where('project_id', $projectTemplate->project_id)
            ->where('is_master', 0)
            ->pluck('template_name_id')->toArray();

        return response([
            'status'               => 200,
            'message'              => 'success',
            'project_id'           => (int) $project->id,
            'template_id'          => isset($childTemplatesNameIds[0]) ? (int) $childTemplatesNameIds[0] : null,
            'distributor_value'    => $distributor_value,
            'add_outlet'           => $add_outlet,
            'show_audit_close_button' => $showCLoseAuditOption,
            'total'                => $paginatedDistributors->total(),
            'per_page'             => $paginatedDistributors->perPage(),
            'current_page'         => $paginatedDistributors->currentPage(),
            'next_page'            => $paginatedDistributors->currentPage() < $paginatedDistributors->lastPage() ? $paginatedDistributors->currentPage() + 1 : null,
            'total_pages'          => $paginatedDistributors->lastPage(),
            'data'                 => $paginatedDistributors->items(),
        ], 200);
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


    public function QuestionAnswer(Request $request)
    {

        $validatedData = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,svg,pdf,docx|max:2048',
        ]);

        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');

            // Store the file in the 'public' folder and get the file path
            $path = $file->store('uploads', 'public'); // This saves in storage/app/public/uploads

            // If you need the full URL, use asset() function to get the public URL
            $url = asset('storage/' . $path); // URL to access the file from the public folder


            return response()->json([
                'message' => 'File uploaded successfully',
                'url' => $url
            ]);
        }
    }

    


    public function row_activity_answers(Request $request)
    {
        $request->validate([
            'row_id'      => 'required|integer',
            'activity_id' => 'required|integer',
            'latitude'    => 'required|string',
            'longitude'   => 'required|string',
        ]);

        try {
            $userId = $request->user()->id;

            // --- 0. Block submission if activity instance is already closed ---
            if (ActivityInstanceClose::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->where('user_id', $userId)
                ->exists()) {
                return response()->json([
                    'status'  => 400,
                    'message' => 'This activity is already closed. Answers cannot be modified.',
                ], 400);
            }

            // --- 1. Resolve project directory ONCE ---
            $baseDirectory = 'activityAnswerImages/';
            $projectTemp   = ProjectTemplateNameValuesNew::find($request->row_id);
            $projectName   = $projectTemp->getProjectTemplateData->getProject->project_name;
            $projectMonthYearDirectory = $baseDirectory . $projectName . '/' . Carbon::now()->format('FY');

            $this->checkAndCreateDirectory($baseDirectory . $projectName . '/');
            $this->checkAndCreateDirectory($projectMonthYearDirectory);

            $latitude  = $request->latitude  ?? null;
            $longitude = $request->longitude ?? null;

            $userDetails = User::find($userId);

            $activitySequence = (int) ($request->activity_sequence ?? 0);

            // --- 2. Build question answers map ---
            // same_answer_id is auto-generated below from DB max — never read from the request.
            // group_id, _token, save, _method are web-only; ignore if accidentally sent.
            $excludedKeys    = ['user_id', 'row_id', 'activity_id', 'group_id', 'latitude', 'longitude', 'activity_sequence', 'same_answer_id', '_token', 'save', '_method'];
            $questionAnswers = collect($request->all())
                ->except($excludedKeys)
                ->reject(fn($v, $k) => str_starts_with((string)$k, 'multi_images_'));

            // For _pctx_ keys the format is {mr_parent_qid}_pctx_{child_qid}.
            // Extract the child question ID (after _pctx_) so validation sees the real answer key.
            $submittedQuestionIds = $questionAnswers->keys()->map(function ($k) {
                if (preg_match('/_pctx_(\d+)$/', (string)$k, $m)) {
                    return (int)$m[1]; // sub-question / child ID
                }
                return (int)preg_replace('/\D.*/', '', (string)$k);
            })->filter()->unique()->values()->toArray();

            // --- 3. Load ALL questions for this activity in ONE query ---
            $allQuestions = Question::where('activity_id', $request->activity_id)
                ->get()
                ->keyBy('id'); // keyed by id for O(1) lookup below

            // --- 4. Validate required questions ---
            // Load sub-question child IDs (MR children answered via _pctx_ keys, never as standalone)
            $subQChildIds = DB::table('question_sub_questions')
                ->whereIn('parent_question_id', $allQuestions->pluck('id')->toArray())
                ->pluck('child_question_id')->toArray();

            // Load parent answers to determine which conditional children are active
            $parentAnswersForValidation = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->where('user_id', $request->user()->id)
                ->where(fn($q) => $q->whereNull('activity_sequence')->orWhere('activity_sequence', (int)($request->activity_sequence ?? 0)))
                ->pluck('user_answer', 'question_id');

            $requiredMissing = [];
            foreach ($allQuestions->where('answer_type', 1) as $q) {
                // Skip Multi Response parent — it's a section header, never submitted
                if ($q->question_type === 'Multi Response') continue;

                // Sub-question (MR child) — answered via _pctx_ key; check by child ID
                if (in_array($q->id, $subQChildIds)) {
                    if (!in_array($q->id, $submittedQuestionIds)) {
                        $requiredMissing[] = ['id' => $q->id, 'question' => $q->question, 'question_type' => $q->question_type, 'reason' => 'sub_question'];
                    }
                    continue;
                }

                // Conditional child — only required when parent condition is met
                if (!is_null($q->parent_question_id)) {
                    $parentAnswer = trim((string)($parentAnswersForValidation->get($q->parent_question_id) ?? ''));
                    if ($parentAnswer === '') continue; // parent not answered → condition not met
                    if ($q->parent_value !== '__non_empty__' && strcasecmp($parentAnswer, (string)$q->parent_value) !== 0) continue;
                    // Condition IS met — fall through to check submission
                }

                if (!in_array($q->id, $submittedQuestionIds)) {
                    $requiredMissing[] = ['id' => $q->id, 'question' => $q->question, 'question_type' => $q->question_type, 'reason' => is_null($q->parent_question_id) ? 'standalone' : 'conditional'];
                }
            }

            if (!empty($requiredMissing)) {
                return response()->json([
                    'status'            => 422,
                    'message'           => 'Some required questions are missing from the submission.',
                    'missing_questions' => $requiredMissing,
                ], 422);
            }

            // --- 5. Load existing answers for THIS activity_sequence only ---
            // Must filter by sequence so repeat-instance answers don't get matched to
            // existing sequence-0 records (which would turn INSERT into UPDATE that hits 0 rows).
            $existingAnswers = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where(function ($query) use ($request) {
                    $query->where('activity_id', $request->activity_id)
                        ->orWhere('activity_group_name_id', $request->group_id);
                })
                ->where(function ($q) use ($activitySequence) {
                    $activitySequence > 0
                        ? $q->where('activity_sequence', $activitySequence)
                        : $q->where(fn($q2) => $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0));
                })
                ->get()
                ->groupBy('question_id'); // group for fast lookup

            // --- 6. Get last sequence ONCE ---
            $last_sequence = TempUserActivityAnswersData::max('same_answer_id') + 1;

            $imagesToProcess = [];
            $toCreate        = [];
            $toUpdate        = []; // [ question_id => update_payload ]
            $now             = now()->toDateTimeString();

            foreach ($questionAnswers as $key => $value) {
                preg_match('/^(\d+)([a-zA-Z]*)$/', $key, $matches);
                $numberPart = $matches[1] ?? null;
                $stringPart = $matches[2] ?? '';
                $questionId = (int)($stringPart === '' ? $key : $numberPart);

                // --- O(1) lookup from pre-loaded collection ---
                $question = $allQuestions->get($questionId);
                if (!$question) {
                    continue;
                }

                $user_answer = null;
                $file_path   = null;

                if ($request->hasFile($key)) {
                    $uploaded = $request->file($key);

                    // Normalise: a single file or an array of files (e.g. key "967[]" or
                    // multiple Postman parts under the same key name).
                    $files = is_array($uploaded) ? $uploaded : [$uploaded];

                    $rules = [];
                    $customMessages = [];
                    if ($question->question_type === 'Image') {
                        $rules['file_input'] = 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120';
                        $customMessages['file_input.image'] = 'Only image files are allowed.';
                        $customMessages['file_input.mimes'] = 'Invalid image format.';
                    } elseif ($question->question_type === 'File Upload') {
                        $rules['file_input'] = 'required|file|mimetypes:application/pdf,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/jpg,image/png,image/webp|max:51200';
                        $customMessages['file_input.mimetypes'] = 'Invalid File Type.';
                    }

                    $savedPaths = [];
                    foreach ($files as $file) {
                        $validator = Validator::make(['file_input' => $file], $rules, $customMessages);
                        if ($validator->fails()) {
                            return response()->json([
                                'status'        => 422,
                                'message'       => "Invalid file for question ID: $questionId",
                                'question_type' => $question->question_type,
                                'errors'        => $validator->errors()->all(),
                            ], 422);
                        }
                        $file_name      = uniqid() . '.' . $file->getClientOriginalExtension();
                        $full_file_path = $projectMonthYearDirectory . '/' . $file_name;
                        $file->move(public_path($projectMonthYearDirectory), $file_name);
                        $savedPaths[] = $full_file_path;
                    }

                    // Single file → store path as string; multiple files → store as JSON array.
                    if (count($savedPaths) === 1) {
                        $user_answer = $savedPaths[0];
                        $file_path   = $savedPaths[0];
                    } else {
                        $user_answer = json_encode($savedPaths);
                        $file_path   = $savedPaths[0]; // primary path for geo-tagging job
                    }
                } else {
                    // Normalize array values.
                    // Accepts: PHP array (repeated keys), JSON array string ["A","B"], or plain string.
                    if (is_array($value)) {
                        $user_answer = implode(',', array_filter($value, fn($v) => trim((string)$v) !== ''));
                    } else {
                        $str = (string)$value;
                        if (str_starts_with(trim($str), '[')) {
                            $decoded = json_decode($str, true);
                            $user_answer = is_array($decoded)
                                ? implode(',', array_filter($decoded, fn($v) => trim((string)$v) !== ''))
                                : $str;
                        } else {
                            $user_answer = $str;
                        }
                    }

                    if ($question->question_type === 'Date' && !empty($user_answer)) {
                        try {
                            $user_answer = Carbon::parse($user_answer)->format('d/m/Y');
                        } catch (\Exception $e) {
                            $user_answer = null;
                        }
                    } elseif ($question->question_type === 'Yes / No' && !empty($user_answer)) {
                        $norm = strtolower(trim((string)$user_answer));
                        $user_answer = in_array($norm, ['yes','1','true','y']) ? 'Yes'
                                     : (in_array($norm, ['no','0','false','n']) ? 'No' : $user_answer);
                    }
                }

                // --- 7. Decide create vs update using pre-loaded collection ---
                $existing = $existingAnswers->get($questionId);

                // Parse _pctx_ suffix from key (e.g. "123_pctx_45")
                $parentContextId = null;
                if (preg_match('/_pctx_(\d+)/', (string)$key, $pm)) {
                    $parentContextId = (int)$pm[1];
                }

                $payload = [
                    'user_id'                => $userId,
                    'row_id'                 => $request->row_id,
                    'activity_id'            => $request->activity_id,
                    'activity_sequence'      => $activitySequence ?: null,
                    'activity_group_name_id' => $request->group_id ?? 0,
                    'question_id'            => $questionId,
                    'parent_context_id'      => $parentContextId,
                    'user_answer'            => $user_answer,
                    'same_answer_id'         => $last_sequence,
                    'latitude'               => $latitude,
                    'longitude'              => $longitude,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];

                if ($question->question_type === 'Subjective') {
                    if (!$existing || $existing->isEmpty()) {
                        $toCreate[] = $payload;
                        $answer = null; // will be set after bulk insert for image tracking
                    } else {
                        $existingRecord = $existing->first();
                        $subjectiveAnswer = $existingRecord->user_answer;
                        $cleanedAnswer    = preg_replace('/\s+/', ' ', trim($subjectiveAnswer));
                        preg_match_all('/\(([^)]+)\)/', $cleanedAnswer, $bMatches);

                        if (count($bMatches[0]) === 1) {
                            $toCreate[] = $payload;
                        }
                        $answer = $existingRecord;
                    }
                } else {
                    if (!$existing || $existing->isEmpty()) {
                        $toCreate[] = $payload;
                    } else {
                        $toUpdate[$questionId] = [
                            'user_answer'    => $user_answer,
                            'same_answer_id' => $last_sequence,
                            'latitude'       => $latitude,
                            'longitude'      => $longitude,
                        ];
                        $answer = $existing->first();
                    }
                }

                // --- 8. Queue images for processing ---
                if ($question->question_type === 'Image' && $file_path) {
                    $imagesToProcess[] = [
                        'file_path'  => public_path($file_path),
                        'latitude'   => $latitude,
                        'longitude'  => $longitude,
                        'answer'     => $answer ?? null,
                        'directory'  => $projectMonthYearDirectory,
                        'user_id'    => $userDetails->id,
                    ];
                }
            }

            // --- 9. Handle pre-uploaded multi-image paths (multi_images_{qid}[] hidden fields) ---
            // Mobile: pre-upload each image via POST /api/uploadActivityImage, get back a path,
            // then send all paths as multi_images_{qid}[]  in this request.
            $multiImageKeys = array_filter(array_keys($request->all()), fn($k) => str_starts_with((string)$k, 'multi_images_'));
            foreach ($multiImageKeys as $multiKey) {
                $suffix     = str_replace('multi_images_', '', $multiKey);
                $qid        = (int)$suffix;
                if (!$qid) continue;
                $question = $allQuestions->get($qid);
                if (!$question || $question->question_type !== 'Image') continue;

                $multiPctx = null;
                if (preg_match('/_pctx_(\d+)/', $suffix, $pm)) {
                    $multiPctx = (int)$pm[1];
                }

                // Accept JSON array string or repeated keys ([]): both decode to the same paths array
                $rawPaths = $request->input($multiKey);
                if (is_string($rawPaths) && str_starts_with(trim($rawPaths), '[')) {
                    $paths = json_decode($rawPaths, true) ?? [];
                } else {
                    $paths = (array)$rawPaths;
                }
                $paths = array_filter($paths, fn($p) => !empty(trim((string)$p)));
                if (empty($paths)) continue;

                $jsonAnswer = json_encode(array_values($paths));

                $existingMI = TempUserActivityAnswersData::where('row_id', $request->row_id)
                    ->where('activity_id', $request->activity_id)
                    ->where('question_id', $qid)
                    ->where(fn($q) => $activitySequence > 0
                        ? $q->where('activity_sequence', $activitySequence)
                        : $q->where(fn($q2) => $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0)))
                    ->where(fn($q) => $multiPctx !== null
                        ? $q->where('parent_context_id', $multiPctx)
                        : $q->whereNull('parent_context_id'))
                    ->first();

                if ($existingMI) {
                    $existingMI->update(['user_answer' => $jsonAnswer, 'same_answer_id' => $last_sequence,
                        'latitude' => $latitude, 'longitude' => $longitude, 'updated_at' => $now]);
                } else {
                    TempUserActivityAnswersData::create([
                        'user_id'                => $userId,
                        'row_id'                 => $request->row_id,
                        'activity_id'            => $request->activity_id,
                        'activity_sequence'      => $activitySequence ?: null,
                        'activity_group_name_id' => $request->group_id ?? 0,
                        'question_id'            => $qid,
                        'parent_context_id'      => $multiPctx,
                        'user_answer'            => $jsonAnswer,
                        'same_answer_id'         => $last_sequence,
                        'latitude'               => $latitude,
                        'longitude'              => $longitude,
                    ]);
                }
            }

            // --- 10. Bulk insert + bulk update inside a single transaction ---
            DB::transaction(function () use ($toCreate, $toUpdate, $request, $activitySequence) {
                if (!empty($toCreate)) {
                    TempUserActivityAnswersData::insert($toCreate);
                }

                foreach ($toUpdate as $questionId => $updatePayload) {
                    TempUserActivityAnswersData::where('row_id', $request->row_id)
                        ->where('activity_id', $request->activity_id)
                        ->where('question_id', $questionId)
                        ->where(function ($q) use ($activitySequence) {
                            $activitySequence > 0
                                ? $q->where('activity_sequence', $activitySequence)
                                : $q->where(fn($q2) => $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0));
                        })
                        ->update($updatePayload);
                }
            });

            // --- 11. Dispatch image job ---
            if (!empty($imagesToProcess)) {
                dispatch(new ConvertAuditorSelfiesToGeoSelfies($imagesToProcess));
            }

            return response([
                'status'  => 200,
                'message' => 'Activity Answers submitted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 500, 'message' => 'Server error.'], 500);
        }
    }



    public function getSubjectiveDropdown(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        try {

            //            $data = SubjectDropdown::with('subjectiveQuestionDropdowns')->where('subject_id', $request->id)->get();
            //            $data = SubjectDropdown::with('SubjectiveQuestion')->where('subject_id', $request->id)->get();
            $paginated = DB::table('subject_dropdowns as sd')
                ->leftJoin('questions as q', 'q.id', '=', 'sd.subject_id')
                ->where('sd.subject_id', $request->id)
                ->when($request->keyword, fn($q, $kw) => $q->where('sd.option', 'like', "%$kw%"))
                ->select('sd.id', 'sd.subject_id', 'sd.option', 'sd.deleted_at', 'sd.created_at', 'sd.updated_at', 'q.answer_type')
                ->paginate(10);

            $data = collect($paginated->items())->map(function ($item) {
                return [
                    'id'          => $item->id,
                    'subject_id'  => $item->subject_id,
                    'option'      => $item->option,
                    'answer_type' => $item->answer_type ?? null,
                    'deleted_at'  => $item->deleted_at,
                    'created_at'  => $item->created_at,
                    'updated_at'  => $item->updated_at,
                ];
            });

            return response([
                'status'       => 200,
                'message'      => 'sucesss',
                'data'         => $data,
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'next_page'    => $paginated->currentPage() < $paginated->lastPage() ? $paginated->currentPage() + 1 : null,
                'total_pages'    => $paginated->lastPage(),
            ], 200);
        } catch (\Exception $e) {
            return response([
                'status' => 401,
                'message' => $e->getMessage(),
            ], 401);
        }
    }


    // khushboo 05-04-2025
    public function sendActivityOtp(Request $request)
    {

        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'row_id' => 'required|numeric'
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = $request->user();
            if (empty($userdata)) {
                return response([
                    'status' => 401,
                    'message' => 'User Not Found',
                ], 401);
            }
            //  dd($userdata);

            //khushboo 02-05-2025
            $getAllQuestionIds = Question::where('activity_id', $request->activity_id)
                ->pluck('id')
                ->toArray();

            $lastQuestionAnswered = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->whereIn('question_id', $getAllQuestionIds)
                ->orderBy('id', 'DESC')->first();

            if (!$lastQuestionAnswered) {
                return response([
                    'status' => 422,
                    'message' => 'No answers found for this activity. Please submit answers before requesting an OTP.',
                ], 422);
            }

            if (!empty($lastQuestionAnswered->mobile_otp) && ($lastQuestionAnswered->otp_verified_status == 1)) {
                return response([
                    'status' => 409,
                    'message' => 'OTP has already been verified.',
                ], 409);
            }

            if (!empty($lastQuestionAnswered->mobile_otp)) {
                return response([
                    'status' => 409,
                    'message' => 'OTP has already been generated. Please check your phone.',
                ], 409);
            }

            $phone = $request->mobile_no;
            $otpCode = rand(100000, 999999); // 6-digit OTP

            // Send SMS via SMS Gateway Center API
            $response = Http::asForm()->withoutVerifying()->post('https://unify.smsgateway.center/SMSApi/send', [
                'userid'        => 'tnbttech',
                'password'      => '79aJfwhX',
                'mobile'        => $phone,
                'senderid'      => 'TNBTTH',
                'dltEntityId'   => '1701173978747429878',
                'msg'           => 'Hi, Please find below OTP for your Document Verification: ' . $otpCode . '-TNBT Tech',
                'sendMethod'    => 'quick',
                'msgType'       => 'text',
                'dltTemplateId' => '1707173997125309765',
                'output'        => 'json',
                'duplicatecheck'=> 'false',
            ]);

            $responseJson = $response->json();
            $statusCode   = $responseJson['status'] ?? $responseJson['Status'] ?? null;
            $smsFailed    = !$response->successful()
                || (isset($statusCode) && !in_array(strtolower((string)$statusCode), ['success', '200', 'ok', '1']));

            if ($smsFailed) {
                $gatewayMsg = $responseJson['message'] ?? $responseJson['Message'] ?? $responseJson['reason'] ?? 'Failed To Send OTP';
                return response([
                    'status'  => 401,
                    'message' => $gatewayMsg,
                    'gateway' => $responseJson,
                ], 401);
            }

            TempUserActivityAnswersData::where('id', $lastQuestionAnswered->id)->update([
                'mobile_no'  => $request->mobile_no,
                'mobile_otp' => $otpCode,
            ]);

            return response([
                'status'  => 200,
                'message' => 'OTP Send Successfully',
            ], 200);
        } catch (\Exception $e) {

            return response([
                'status' => 401,
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    // khushboo 05-04-2025

    public function verifyOtp(Request $request)
    {
        $request->validate(
            [
                'mobile_no' => 'required|numeric|regex:/^[6-9]\d{9}$/',
                'activity_id' => 'required|numeric',
                'row_id' => 'required|numeric',
                'mobile_otp' => 'required|numeric',
            ],
            [
                'mobile_no.regex' => 'Please Enter 10 digit mobile number'
            ]
        );

        try {

            $userdata = $request->user();
            if (empty($userdata)) {
                return response([
                    'status' => 401,
                    'message' => 'User Not Found',
                ], 401);
            }

            $getAllQuestionIds = Question::where('activity_id', $request->activity_id)
                ->pluck('id')
                ->toArray();

            // Single query replaces the previous exists() + first() pair
            $lastQuestionAnsweredData = TempUserActivityAnswersData::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->whereIn('question_id', $getAllQuestionIds)
                ->where('mobile_no', $request->mobile_no)
                ->where('mobile_otp', $request->mobile_otp)
                ->orderBy('id', 'DESC')
                ->first();

            if ($lastQuestionAnsweredData) {
                TempUserActivityAnswersData::where('id', $lastQuestionAnsweredData->id)->update([
                    'otp_verified_status' => 1
                ]);

                return response([
                    'status' => 200,
                    'message' => 'OTP Verified Successfully',
                ], 200);
            } else {

                return response([
                    'status' => 401,
                    'message' => 'OTP Not Verified',
                ], 401);
            }
        } catch (\Exception $e) {
            return response([
                'status' => 401,
                'message' => 'OTP Not Verified',
            ], 401);
        }
    }


    //khushboo 16-05-2025

    public function getTemplateHeaders($projectId, $templateId)
    {
        $project = DB::table('projects')->where('id', $projectId)->first();
        if (!$project) {
            return response()->json(['status' => 404, 'message' => 'Project not found.'], 404);
        }

        $template = DB::table('template_names')->where('id', $templateId)->first();
        if (!$template) {
            return response()->json(['status' => 404, 'message' => 'Template not found.'], 404);
        }

        $project_temp_info = DB::table('project_templates')
            ->where('project_id', $project->id)
            ->where('template_name_id', $template->id)
            ->first();

        if (!$project_temp_info) {
            return response()->json(['status' => 404, 'message' => 'No project template found for this project and template combination.'], 404);
        }

        $mainHeaderId = (int) $project_temp_info->main_header;
        $subHeaderId  = (int) $project_temp_info->sub_header;

        $headRows = DB::table('template_name_heads')
            ->where('template_name_id', $template->id)
            ->orderBy('id')
            ->get(['id', 'template_head_name', 'value_type', 'is_required']);

        // Batch-load options for every dropdown head in one query instead of one per head.
        $dropdownHeadIds = $headRows->where('value_type', 'dropdown')->pluck('id')->toArray();
        $optionsByHeadId = empty($dropdownHeadIds) ? collect() :
            DB::table('template_name_head_options')
                ->whereIn('template_name_head_id', $dropdownHeadIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['template_name_head_id', 'option'])
                ->groupBy('template_name_head_id')
                ->map(fn($g) => $g->pluck('option')->values()->toArray());

        $headeNames = $headRows->map(function ($h) use ($mainHeaderId, $subHeaderId, $optionsByHeadId) {
            // Main/sub header fields are always effectively required for the app to function;
            // an admin can additionally mark any other head as required via the heads-upload
            // Excel's "Required" column.
            $entry = [
                'id'          => (int) $h->id,
                'name'        => $h->template_head_name,
                'is_required' => ((int)$h->id === $mainHeaderId || (int)$h->id === $subHeaderId || (bool) $h->is_required),
                'value_type'  => $h->value_type ?: 'text',
            ];
            if ($h->value_type === 'dropdown') {
                $entry['options'] = $optionsByHeadId->get($h->id, []);
            } elseif ($h->value_type === 'yes_no') {
                $entry['options'] = ['Yes', 'No'];
            }
            return $entry;
        })->values()->toArray();

        return response()->json([
            'status'              => 200,
            'project_id'          => $project->id,
            'template_id'         => $template->id,
            'project_template_id' => $project_temp_info->id,
            'data'                => $headeNames,
        ]);
    }


    //khushboo 16-05-2025

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


    public function storeTemplateHeaderValues(Request $request)
    {

        $request->validate(
            [
                'project_template_id' => 'required|numeric',
            ]
        );

        try {
            $projectTemplateInfo = ProjectTemplate::find($request->project_template_id);
            if (!empty($projectTemplateInfo)) {
                $projectTemplateInfoDetails = $projectTemplateInfo->getTemplate;
                $projectTemplateHeaders = $projectTemplateInfoDetails->getTemplateHeads;

                $projectRowData = $request->except(['project_template_id']);

                // Build lookup maps from template headers
                // headerIdMap: id => header object
                // headerNameMap: normalized_name => header object
                $headerIdMap   = [];
                $headerNameMap = [];
                foreach ($projectTemplateHeaders as $projectData) {
                    $headerIdMap[$projectData->id] = $projectData;
                    $normalizedName = strtolower(str_replace([' ', '-'], '_', trim($projectData->template_head_name)));
                    $headerNameMap[$normalizedName] = $projectData;
                }

                // Detect mode: if ALL non-system keys are numeric → ID mode, else name mode
                $nonSystemKeys = array_keys($projectRowData);
                $useIdMode = count($nonSystemKeys) > 0 && count(array_filter($nonSystemKeys, 'is_numeric')) === count($nonSystemKeys);

                $templateDataJson = [];
                $unknownKeys      = [];

                if ($useIdMode) {
                    // ── ID mode: keys are header IDs ─────────────────────────────
                    foreach ($projectRowData as $headerId => $value) {
                        if (!isset($headerIdMap[(int)$headerId])) {
                            $unknownKeys[] = $headerId;
                        } else {
                            $templateDataJson[(int)$headerId] = $value;
                        }
                    }
                } else {
                    // ── Name mode: keys are header names (normalized) ─────────────
                    $normalizedRowData = [];
                    foreach ($projectRowData as $key => $val) {
                        $normalizedKey = strtolower(str_replace([' ', '-'], '_', trim($key)));
                        $normalizedRowData[$normalizedKey] = $val;
                    }
                    foreach ($normalizedRowData as $key => $value) {
                        if (!isset($headerNameMap[$key])) {
                            $unknownKeys[] = $key;
                        } else {
                            $templateDataJson[$headerNameMap[$key]->id] = $value;
                        }
                    }
                }

                // Reject completely unknown keys
                if (!empty($unknownKeys)) {
                    return response()->json([
                        'status'       => 422,
                        'message'      => 'Unknown header keys provided.',
                        'unknown_keys' => $unknownKeys,
                    ], 422);
                }

                // Validate that main_header and sub_header fields are present and non-empty
                $mainHeaderId = (int) $projectTemplateInfo->main_header;
                $subHeaderId  = (int) $projectTemplateInfo->sub_header;
                $requiredErrors = [];
                if ($mainHeaderId && empty($templateDataJson[$mainHeaderId])) {
                    $requiredErrors[] = [
                        'id'   => $mainHeaderId,
                        'name' => $headerIdMap[$mainHeaderId]->template_head_name ?? 'main_header',
                    ];
                }
                if ($subHeaderId && empty($templateDataJson[$subHeaderId])) {
                    $requiredErrors[] = [
                        'id'   => $subHeaderId,
                        'name' => $headerIdMap[$subHeaderId]->template_head_name ?? 'sub_header',
                    ];
                }
                if (!empty($requiredErrors)) {
                    return response()->json([
                        'status'  => 422,
                        'message' => 'Required fields cannot be empty.',
                        'errors'  => $requiredErrors,
                    ], 422);
                }

                // Pick first non-empty entry as the distributor reference header
                $get_header_id    = "";
                $get_header_value = "";
                foreach ($templateDataJson as $hId => $hVal) {
                    if (!empty($hVal)) {
                        $get_header_id    = $hId;
                        $get_header_value = $hVal;
                        break;
                    }
                }

                // Insert single row with JSON data
                DB::table('project_template_name_values_new')->insert([
                    'project_template_id' => $projectTemplateInfo->id,
                    'template_data_json'  => json_encode($templateDataJson),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $projectInfo = Project::find($projectTemplateInfo->project_id);
                $templateNameInfo = TemplateName::find($projectTemplateInfo->template_name_id);
                $companyId = $projectInfo->company_id;
                $zoneId = $projectInfo->zone_id;
                $unitId = $projectInfo->unit_id;

                $activityGroupId = null;
                $activityIdsArr  = [];

                // Always assign all activities configured on this project template
                if ($projectTemplateInfo->activityType == 1) {
                    $group_activities = ActivityGroupPivot::where('activity_group_id', $projectTemplateInfo->activity_group_name_id_or_activity_id)->get();
                    foreach ($group_activities as $group_activity) {
                        $activityIdsArr[] = $group_activity->activity_id;
                    }
                    $activityGroupId = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                } else {
                    $activityIdsArr[] = $projectTemplateInfo->activity_group_name_id_or_activity_id;
                }

                if (empty($activityIdsArr)) {
                    return response()->json(['status' => 422, 'message' => 'No activities found for this project template.'], 422);
                }

                $checkOutletAssign = null;
                if ($projectTemplateInfo->is_master == 1) {

                    $activity_group_name_id_or_activity_id = $projectTemplateInfo->activity_group_name_id_or_activity_id;

                    $checkOutletAssign = DataAssign::where('project_id', $projectTemplateInfo->project_id)
                        ->where('template_name_id', $projectTemplateInfo->template_name_id)
                        ->where('template_name_head_id', $projectTemplateInfo->main_header)
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->where('is_outlet_assigned', 1)
                        ->where(function ($query) use ($activity_group_name_id_or_activity_id) {
                            $query->where('activity_id', $activity_group_name_id_or_activity_id)
                                ->orWhere('activity_group_id', $activity_group_name_id_or_activity_id);
                        })
                        ->exists();
                }


                foreach ($activityIdsArr as $activity) {

                    //                if ($checkOutletAssign) {

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
                        'is_outlet_assigned' => 0
                    ]);

                    $rowIds = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $projectTemplateInfo->id)
                        ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$get_header_id\"')) = ?", [trim($get_header_value)])
                        ->pluck('id')
                        ->toArray();

                    $this->assignUserActivityAndAuditRows(
                        $dataAssign->id,
                        $request->user()->id,
                        $projectTemplateInfo->id,
                        $activity,
                        $activityGroupId,
                        $rowIds
                    );
                }
            }

            return response()->json(['status' => 200, 'message' => 'Distributor Added Successfully']);
        } catch (\Exception $e) {
            Log::info('Distributor not added' . $e->getMessage());
            Log::info('Distributor not added' . $e->getMessage());
            return response()->json(['status' => 401, 'message' => 'Something went wrong']);
        }
    }

    // -------------------------------------------------------------------------
    // Single-answer save + finalize (new mobile flow)
    // -------------------------------------------------------------------------

    /**
     * Closure: scope a TempUserActivityAnswersData query to the correct repeat instance.
     * Mirrors TaskHandlerController::activitySequenceFilter().
     */
    private function apiActivitySequenceFilter(int $seq): \Closure
    {
        return function ($q) use ($seq) {
            if ($seq > 0) {
                $q->where('activity_sequence', $seq);
            } else {
                $q->where(function ($q2) {
                    $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                });
            }
        };
    }

    /**
     * Validate a text/select answer value against the question's stored rules.
     * Returns an error string on failure, or null on success.
     */
    private function validateAnswerValue(string $value, Question $question): ?string
    {
        $rule = strtolower(trim((string)($question->validation_rule ?? 'none')));
        $min  = $question->validation_min  !== null ? (string)$question->validation_min  : null;
        $max  = $question->validation_max  !== null ? (string)$question->validation_max  : null;
        $regex = $question->validation_regex ? trim($question->validation_regex) : null;
        $qtype = $question->question_type;

        // ── Required check ──────────────────────────────────────────────────
        if ($question->answer_type == 1 && trim($value) === '') {
            return 'This field is required.';
        }

        // Skip further validation if value is empty and field is optional
        if (trim($value) === '') {
            return null;
        }

        // ── Numeric / integer rules ─────────────────────────────────────────
        if (in_array($rule, ['numeric', 'number', 'integer', 'int']) ||
            in_array($qtype, ['Rating', 'Number'])) {
            if (!is_numeric($value)) {
                return 'Answer must be a number.';
            }
            $num = (float)$value;
            if ($min !== null && $num < (float)$min) {
                return "Value must be at least {$min}.";
            }
            if ($max !== null && $num > (float)$max) {
                return "Value must be at most {$max}.";
            }
        }

        // ── Text length rules ───────────────────────────────────────────────
        if (in_array($qtype, ['Free Text', 'Remark']) || $rule === 'text') {
            $len = mb_strlen($value);
            if ($min !== null && $len < (int)$min) {
                return "Answer must be at least {$min} characters.";
            }
            if ($max !== null && $len > (int)$max) {
                return "Answer must be at most {$max} characters.";
            }
        }

        // ── Date range rules ────────────────────────────────────────────────
        if (in_array($qtype, ['Date', 'Date & Time'])) {
            $dateMin = ($question->date_min && $question->date_min !== '0000-00-00') ? $question->date_min : null;
            $dateMax = ($question->date_max && $question->date_max !== '0000-00-00') ? $question->date_max : null;
            if ($dateMin || $dateMax) {
                try {
                    $date = null;
                    foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s', 'd-m-Y H:i:s'] as $fmt) {
                        try {
                            $date = Carbon::createFromFormat($fmt, $value);
                            break;
                        } catch (\Exception $e) {}
                    }
                    if (!$date) $date = Carbon::parse($value);
                    if ($dateMin && $date->lt(Carbon::parse($dateMin))) {
                        return 'Date must be on or after ' . Carbon::parse($dateMin)->format('d/m/Y') . '.';
                    }
                    if ($dateMax && $date->gt(Carbon::parse($dateMax))) {
                        return 'Date must be on or before ' . Carbon::parse($dateMax)->format('d/m/Y') . '.';
                    }
                } catch (\Exception $e) {
                    return 'Invalid date format.';
                }
            }
        }

        // ── Yes / No ────────────────────────────────────────────────────────
        if ($qtype === 'Yes / No') {
            if (!in_array($value, ['Yes', 'No'])) {
                return 'Answer must be "Yes" or "No".';
            }
        }

        // ── Regex rule ──────────────────────────────────────────────────────
        if ($regex && $regex !== 'none' && $regex !== '') {
            if (!preg_match($regex, $value)) {
                return 'Answer does not match the required format.';
            }
        }

        return null;
    }

    /**
     * Validate an uploaded file against the question's stored file rules.
     * Returns an error string on failure, or null on success.
     */
    private function validateAnswerFile(\Illuminate\Http\UploadedFile $file, Question $question): ?string
    {
        $qtype = $question->question_type;

        // ── Image question ──────────────────────────────────────────────────
        if ($qtype === 'Image') {
            $allowed = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'svg'];
            if ($question->file_types) {
                $allowed = array_map('trim', explode(',', strtolower($question->file_types)));
            }
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowed)) {
                return 'Invalid image format. Allowed: ' . implode(', ', $allowed) . '.';
            }
        }

        // ── File Upload question ────────────────────────────────────────────
        if ($qtype === 'File Upload' && $question->file_types) {
            $allowed = array_map('trim', explode(',', strtolower($question->file_types)));
            $ext     = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowed)) {
                return 'Invalid file type. Allowed: ' . implode(', ', $allowed) . '.';
            }
        }

        // ── Max file size ───────────────────────────────────────────────────
        if ($question->max_file_size_mb) {
            $maxBytes = (float)$question->max_file_size_mb * 1024 * 1024;
            if ($file->getSize() > $maxBytes) {
                return 'File size must not exceed ' . $question->max_file_size_mb . ' MB.';
            }
        }

        return null;
    }

    /**
     * POST /api/saveAnswer
     *
     * Save ONE answer at a time (call on blur / focus-out — same as web auto-save).
     * Supports every question type. Use multipart/form-data when sending a file.
     *
     * Required fields (always):
     *   row_id            integer   – template row id
     *   activity_id       integer
     *   question_id       integer
     *   latitude          string
     *   longitude         string
     *
     * Optional fields:
     *   activity_sequence integer   – 0 or omit = original, 1/2/3… = repeat instance
     *   group_id          integer   – activity group id (if applicable)
     *   parent_question_id integer  – for conditional sub-questions triggered by a parent
     *
     * Answer payload — send ONE of:
     *   value             string/array  – for Text, Number, Date, DateTime, Yes/No,
     *                                     Dropdown, Multi-select (comma-sep or JSON array),
     *                                     Outlet-type, Location string
     *   file              binary file   – for Image, Audio, Video, File Upload
     *                                     (use multipart/form-data)
     *
     * For Subjective questions — two calls needed:
     *   Call 1: question_id=X, value="chosen subject"        (main subject selection)
     *   Call 2: question_id=X, subjective_attribute="image"  (or audio/video/dropdown/etc.)
     *           + file=[binary] or value="path or base64"
     *
     * For Multi Response groups — submit each visible sub-question individually,
     *   setting parent_question_id to the Multi Response parent's question_id.
     */
    /**
     * POST /api/uploadActivityImage
     *
     * Pre-upload a single image for a multi-image question.
     * Returns the saved file path to be passed back as multi_images_{qid}[] in answerSubmit.
     *
     * Body (form-data):
     *   image     file     (required) jpeg/png/jpg/gif/webp ≤ 50MB
     *   row_id    integer  (required)
     *   latitude  string   (optional)
     *   longitude string   (optional)
     */
    public function uploadActivityImage(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'image'       => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:51200',
                'row_id'      => 'required|integer',
                'activity_id' => 'sometimes|integer',
                'question_id' => 'sometimes|integer',
            ]);

            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                return response()->json(['error' => 'Row not found.'], 404);
            }

            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $dir = 'activityAnswerImages/' . $projectName . '/' . Carbon::now()->format('FY');
            $this->checkAndCreateDirectory('activityAnswerImages/' . $projectName . '/');
            $this->checkAndCreateDirectory($dir);

            $file    = $request->file('image');
            $ext     = $file->getClientOriginalExtension();

            // Build a descriptive filename: r{row}_a{activity}_q{question}_{uniqid}.ext
            // activity_id and question_id are optional but strongly recommended so images
            // from different activities / questions never collide in the same folder.
            $prefix = 'r' . (int) $request->row_id;
            if ($request->filled('activity_id')) $prefix .= '_a' . (int) $request->activity_id;
            if ($request->filled('question_id')) $prefix .= '_q' . (int) $request->question_id;
            $fileName = $prefix . '_' . uniqid() . '.' . $ext;

            $file->move(public_path($dir), $fileName);
            @chmod(public_path("$dir/$fileName"), 0777);

            return response()->json(['path' => "$dir/$fileName"]);
        } catch (\Throwable $e) {
            Log::error('uploadActivityImage API error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Stores a signature image for the authenticated user against a specific
     * (row_id, activity_id) — one signature per person per row/activity, mirroring
     * activity_instance_closes' unique-triple shape. Re-signing replaces the
     * previous file and record rather than accumulating duplicates.
     */
    public function uploadSignature(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'signature'   => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:51200',
                'row_id'      => 'required|integer',
                'activity_id' => 'required|integer',
            ]);

            $projectTemp = ProjectTemplateNameValuesNew::find($request->row_id);
            if (!$projectTemp) {
                return response()->json(['error' => 'Row not found.'], 404);
            }

            $userId = $request->user()->id;

            // Signature can only be added once the activity is fully closed — this
            // covers both single-instance activities (closeActivity() requires an
            // answer to exist before closing) and repeat/multi-instance activities
            // (closeActivity() additionally requires every instance to be submitted
            // before the close record is created), so checking ActivityInstanceClose
            // alone is sufficient regardless of whether repeats are allowed.
            $isActivityClosed = ActivityInstanceClose::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isActivityClosed) {
                return response()->json([
                    'error' => 'This activity must be fully closed before a signature can be added.',
                ], 400);
            }

            $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
            $dir = 'activitySignatures/' . $projectName . '/' . Carbon::now()->format('FY');
            $this->checkAndCreateDirectory('activitySignatures/' . $projectName . '/');
            $this->checkAndCreateDirectory($dir);

            $file = $request->file('signature');
            $ext  = $file->getClientOriginalExtension();
            $fileName = 'sig_r' . (int) $request->row_id . '_a' . (int) $request->activity_id
                . '_u' . $userId . '_' . uniqid() . '.' . $ext;

            $file->move(public_path($dir), $fileName);
            @chmod(public_path("$dir/$fileName"), 0777);
            $path = "$dir/$fileName";

            // Replace the previous file on re-sign, so old signature images don't pile up.
            $existing = ActivitySignature::where('row_id', $request->row_id)
                ->where('activity_id', $request->activity_id)
                ->where('user_id', $userId)
                ->first();
            if ($existing && $existing->signature_path && file_exists(public_path($existing->signature_path))) {
                @unlink(public_path($existing->signature_path));
            }

            $signature = ActivitySignature::updateOrCreate(
                ['row_id' => $request->row_id, 'activity_id' => $request->activity_id, 'user_id' => $userId],
                ['signature_path' => $path]
            );

            return response()->json([
                'path'         => $path,
                'url'          => asset($path),
                'signature_id' => $signature->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('uploadSignature API error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function saveAnswer(Request $request)
    {
        $request->validate([
            'row_id'            => 'required|integer',
            'activity_id'       => 'required|integer',
            'activity_sequence' => 'required|integer',
            'latitude'          => 'nullable|string',
            'longitude'         => 'nullable|string',
        ]);

        $user    = $request->user();
        $rowId   = (int) $request->row_id;
        $actId   = (int) $request->activity_id;
        $seq     = (int) ($request->activity_sequence ?? 0);

        // Latitude and longitude are required only for the very first answer submission
        // for this row + activity + sequence combination.
        $hasExistingAnswer = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($this->apiActivitySequenceFilter($seq))
            ->exists();

        if (!$hasExistingAnswer) {
            if (empty($request->latitude) || empty($request->longitude)) {
                return response()->json([
                    'status'  => 422,
                    'saved'   => false,
                    'message' => 'latitude and longitude are required for the first answer submission of this activity instance.',
                ], 422);
            }
        }
        $groupId = $request->group_id ? (int) $request->group_id : 0;
        $lat     = $request->latitude;
        $lng     = $request->longitude;
        $subAttr = strtolower(trim((string) ($request->subjective_attribute ?? '')));

        // ── Resolve question ID and parent context ─────────────────────────
        // Answer key format: {question_id}="answer"  or  {question_id}_pctx_{sub_question_id}="answer"
        $qid       = null;
        $pctxId    = null;
        $answerKey = null;

        $systemKeys = ['row_id', 'activity_id', 'latitude', 'longitude', 'activity_sequence',
                       'group_id', 'subjective_attribute'];

        // question_id may be sent as an explicit field (app pattern: question_id=734 + 0_pctx_734=value)
        $explicitQid = $request->filled('question_id') ? (int) $request->input('question_id') : null;

        $allKeys = array_merge(
            array_keys($request->except(array_merge($systemKeys, ['question_id']))),
            array_keys($request->allFiles())
        );
        foreach ($allKeys as $key) {
            // Accept both "746" and "746[]" (multi-file array notation)
            $normalizedKey = rtrim((string)$key, '[]');
            if (preg_match('/^(\d+)(?:_pctx_(\d+))?$/', $normalizedKey, $m)) {
                $first  = (int) $m[1];
                $second = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;

                // App sends {sequence}_pctx_{question_id}: first part is sequence (often 0),
                // second part is the real question_id. Detect this when first==0 or when
                // explicit question_id matches the second number.
                if ($second !== null && ($first === 0 || ($explicitQid && $second === $explicitQid))) {
                    $answerKey = $key;
                    $qid       = $second;
                    $pctxId    = null; // sequence prefix is not a parent context
                } else {
                    $answerKey = $key;
                    $qid       = $first ?: ($explicitQid ?? 0);
                    $pctxId    = $second;
                }
                break;
            }
        }

        // Last resort: if still no qid, use the explicit question_id field
        if (!$qid && $explicitQid) {
            $qid = $explicitQid;
            // Find the answer key that ends with the question_id (any prefix pattern)
            foreach ($allKeys as $key) {
                if (preg_match('/^(\d+)_pctx_' . $explicitQid . '$/', (string)$key)) {
                    $answerKey = $key;
                    $pctxId    = null;
                    break;
                }
                if ((string)$key === (string)$explicitQid) {
                    $answerKey = $key;
                    $pctxId    = null;
                    break;
                }
            }
        }

        // ── Submit mode: no answer key provided → validate & submit ──────────
        if (!$qid) {
            return $this->handleSubmitMode($request, $rowId, $actId, $seq);
        }

        $question = Question::find($qid);
        if (!$question) {
            return response()->json(['saved' => false, 'message' => 'Question not found.'], 404);
        }

        // If the key was {parent_id}_pctx_{child_id}, the parser sets qid=parent, pctxId=child.
        // Detect this two ways: the legacy direct parent_question_id column (old conditional-child
        // mechanism), or the question_sub_questions pivot table (Multi Response sub-questions —
        // these have parent_question_id = NULL, so only the pivot check catches them; missing this
        // caused every MR sub-question answer to save with question_id/parent_context_id reversed).
        if ($pctxId !== null) {
            $pctxQuestion = Question::find($pctxId);
            $isLegacyChild = $pctxQuestion && (int)$pctxQuestion->parent_question_id === $qid;
            $isMrSubQuestionChild = QuestionSubQuestion::where('parent_question_id', $qid)
                ->where('child_question_id', $pctxId)->exists();
            if ($pctxQuestion && ($isLegacyChild || $isMrSubQuestionChild)) {
                // Swap: the child is the real question being answered
                $qid      = $pctxId;
                $pctxId   = $question->id;
                $question = $pctxQuestion;
            }
        }

        // ── Resolve project storage directory ──────────────────────────────
        $projectTemp = ProjectTemplateNameValuesNew::find($rowId);
        if (!$projectTemp) {
            return response()->json(['saved' => false, 'message' => 'Row not found.'], 404);
        }
        $projectName = $projectTemp->getProjectTemplateData->getProject->project_name;
        $dir = 'activityAnswerImages/' . $projectName . '/' . Carbon::now()->format('FY');
        $this->checkAndCreateDirectory('activityAnswerImages/' . $projectName . '/');
        $this->checkAndCreateDirectory($dir);

        // ── Process value ──────────────────────────────────────────────────
        $userAnswer = null;
        $isFile     = false;

        // File can arrive as key "file" (Pattern A) or as the _pctx_ key itself (Pattern B)
        $fileKey = ($answerKey && $request->hasFile($answerKey)) ? $answerKey : 'file';

        if ($request->hasFile($fileKey)) {
            // ── File upload (Image / Audio / Video / File Upload) ──────────
            $fileInput = $request->file($fileKey);
            // Key like "746[]" sends an array; normalise to always work as array
            $files = is_array($fileInput) ? $fileInput : [$fileInput];

            $savedPaths = [];
            foreach ($files as $file) {
                $fileValidationError = $this->validateAnswerFile($file, $question);
                if ($fileValidationError) {
                    return response()->json(['saved' => false, 'message' => $fileValidationError], 422);
                }

                $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $ext      = $file->getClientOriginalExtension();
                $fileName = $safeName . '_' . uniqid() . '.' . $ext;
                $file->move(public_path($dir), $fileName);
                @chmod(public_path($dir . '/' . $fileName), 0777);
                $savedPaths[] = $dir . '/' . $fileName;
            }

            $userAnswer = count($savedPaths) === 1 ? $savedPaths[0] : json_encode($savedPaths);
            $isFile = true;

        } else {
            // Pattern B: value is in the _pctx_ key itself; Pattern A: value is in 'value' field
            $rawValue = ($answerKey && !$request->hasFile($answerKey))
                ? $request->input($answerKey)
                : $request->input('value');

            if ($rawValue === null || (is_string($rawValue) && trim($rawValue) === '')) {
                return response()->json(['saved' => false, 'reason' => 'empty']);
            }

            // Accepts: PHP array (repeated keys), JSON array string ["A","B"], or plain string
            if (is_array($rawValue)) {
                $userAnswer = implode(',', array_filter($rawValue, fn($v) => trim((string)$v) !== ''));
            } else {
                $str = trim((string) $rawValue);
                if (str_starts_with($str, '[')) {
                    $decoded = json_decode($str, true);
                    $userAnswer = is_array($decoded)
                        ? implode(',', array_filter($decoded, fn($v) => trim((string)$v) !== ''))
                        : $str;
                } else {
                    $userAnswer = $str;
                }
            }

            // Normalise by question type
            $qtype = $question->question_type;
            if ($qtype === 'Date') {
                try { $userAnswer = Carbon::parse($userAnswer)->format('d/m/Y'); } catch (\Exception $e) {}
            } elseif ($qtype === 'Date & Time') {
                try { $userAnswer = Carbon::parse($userAnswer)->format('d/m/Y H:i'); } catch (\Exception $e) {}
            } elseif ($qtype === 'Yes / No') {
                $norm = strtolower($userAnswer);
                $userAnswer = in_array($norm, ['yes','1','true','y']) ? 'Yes'
                            : (in_array($norm, ['no','0','false','n']) ? 'No' : $userAnswer);
            } elseif (in_array($qtype, ['Audio','Video']) && str_starts_with($userAnswer, 'data:')) {
                // base64 media — decode and save
                $filePath = null;
                $decoded  = $this->decodeAndSaveMediaBase64Api($userAnswer, strtolower($qtype), $dir, $filePath);
                if (str_starts_with((string)$decoded, 'ERROR:')) {
                    return response()->json(['saved' => false, 'message' => substr($decoded, 6)], 422);
                }
                $userAnswer = $decoded;
                $isFile = true;
            }
        }

        // ── Question-level validation for text/select answers ────────────────
        if (!$isFile) {
            $validationError = $this->validateAnswerValue((string)$userAnswer, $question);
            if ($validationError) {
                return response()->json(['saved' => false, 'message' => $validationError], 422);
            }
        }

        // ── Determine subjective_parent flag ───────────────────────────────
        // 1 = main subject selection row, 0 = dependent attribute row, null = non-subjective
        $subjectiveParent = null;
        if ($question->question_type === 'Subjective') {
            $subjectiveParent = empty($subAttr) ? 1 : 0;
        }

        // ── Upsert into temp_user_activity_answers_data ───────────────────
        $seqFilter = $this->apiActivitySequenceFilter($seq);

        $query = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where('question_id', $qid)
            ->where($seqFilter)
            ->where(function ($q) use ($pctxId) {
                $pctxId !== null
                    ? $q->where('parent_context_id', $pctxId)
                    : $q->whereNull('parent_context_id');
            });

        if ($subjectiveParent !== null) {
            $query->where('subjective_parent', $subjectiveParent);
        }

        $existing = $query->first();

        if ($existing) {
            $existing->update([
                'user_answer' => $userAnswer,
                'latitude'    => $lat,
                'longitude'   => $lng,
                'updated_at'  => now(),
            ]);
            $savedRecord = $existing->fresh();
        } else {
            $lastSeq = (int) TempUserActivityAnswersData::max('same_answer_id') + 1;
            $savedRecord = TempUserActivityAnswersData::create([
                'user_id'                => $user->id,
                'row_id'                 => $rowId,
                'activity_id'            => $actId,
                'activity_sequence'      => $seq,
                'activity_group_name_id' => $groupId,
                'question_id'            => $qid,
                'user_answer'            => $userAnswer,
                'same_answer_id'         => $lastSeq,
                'parent_context_id'      => $pctxId,
                'subjective_parent'      => $subjectiveParent,
                'latitude'               => $lat,
                'longitude'              => $lng,
            ]);
        }

        // Bust the distributor-list cache so the next projectDistributorData call reflects this answer
        $projectId = optional($projectTemp->getProjectTemplateData)->project_id;
        if ($projectId) {
            Cache::forget("proj_dist_data_{$user->id}_{$projectId}");
        }

        // If the question just answered is the last one by sequence, auto-finalize this
        // instance when it's actually complete — saves the app a separate no-question_id
        // submit call. Silently does nothing if other required questions are still missing.
        // Wrapped defensively: the answer above is already saved, so this best-effort step
        // must never turn a successful save into a failed response.
        try {
            $lastQuestionId = DB::table('questions')
                ->where('activity_id', $actId)
                ->where('answer_type', 1)
                ->where('question_type', '!=', 'Multi Response')
                ->whereNull('parent_question_id')
                ->whereNotNull('question_sequence')
                ->orderByDesc('question_sequence')
                ->value('id');

            if ($lastQuestionId !== null && (int) $lastQuestionId === $qid) {
                $this->finalizeInstanceIfComplete($rowId, $actId, $seq, $user->id);
            }
        } catch (\Throwable $e) {
            \Log::warning('saveAnswer auto-finalize skipped due to error', [
                'row_id' => $rowId, 'activity_id' => $actId, 'seq' => $seq, 'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'saved'   => true,
            'data'    => [
                'id'                => (int) $savedRecord->id,
                'row_id'            => (int) $savedRecord->row_id,
                'activity_id'       => (int) $savedRecord->activity_id,
                'activity_sequence' => $savedRecord->activity_sequence !== null ? (int) $savedRecord->activity_sequence : null,
                'question_id'       => (int) $savedRecord->question_id,
                'parent_context_id' => $savedRecord->parent_context_id !== null ? (int) $savedRecord->parent_context_id : null,
                'user_answer'       => $savedRecord->user_answer,
                'latitude'          => $savedRecord->latitude,
                'longitude'         => $savedRecord->longitude,
                'status'            => (int) $savedRecord->status,
            ],
        ], 200);
    }

    /**
     * List of required (answer_type=1, non-Multi-Response) question IDs for an activity.
     */
    private function requiredQuestionIdsForActivity(int $activityId): array
    {
        return DB::table('questions')
            ->where('activity_id', $activityId)
            ->where('answer_type', 1)
            ->where('question_type', '!=', 'Multi Response')
            ->pluck('id')->toArray();
    }

    /**
     * Count how many of $requiredIds have a matching answer, tolerant of a save-path bug
     * (fixed separately) where Multi Response sub-question answers were stored with
     * question_id/parent_context_id swapped — question_id = the MR parent (excluded from
     * $requiredIds since it's a Multi Response question), parent_context_id = the actual
     * sub-question. Matches on question_id normally, and also checks parent_context_id to
     * still count those swapped rows against the sub-question they actually answer.
     */
    private function countSubmittedAgainstRequired(iterable $answers, array $requiredIds): int
    {
        $answers = collect($answers);
        $submittedIds = $answers->pluck('question_id')
            ->merge($answers->pluck('parent_context_id')->filter())
            ->unique();
        return $submittedIds->intersect($requiredIds)->count();
    }

    /**
     * If every required question for this row/activity/sequence now has an answer, mark
     * the answers and the repeat instance (if any) as submitted (status=1). Shared by
     * handleSubmitMode, finalizeAnswers, and saveAnswer's last-question auto-finalize.
     */
    private function finalizeInstanceIfComplete(int $rowId, int $actId, int $seq, int $userId): bool
    {
        $requiredIds   = $this->requiredQuestionIdsForActivity($actId);
        $requiredCount = count($requiredIds);

        $seqFilter = $this->apiActivitySequenceFilter($seq);

        $answers = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where('user_id', $userId)
            ->where($seqFilter)
            ->get(['question_id', 'parent_context_id']);
        $submittedCount = $this->countSubmittedAgainstRequired($answers, $requiredIds);

        if ($submittedCount < $requiredCount) {
            return false;
        }

        TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($seqFilter)
            ->update(['status' => 1, 'updated_at' => now()]);

        if ($seq > 0) {
            ActivityRepeatInstance::where('row_id', $rowId)
                ->where('activity_id', $actId)
                ->where('activity_sequence', $seq)
                ->update(['status' => 1]);
        }

        return true;
    }

    /**
     * POST /api/finalizeAnswers
     *
     * Run required-field validation for a completed instance, then mark answers as submitted.
     * Call this AFTER all saveAnswer calls are done (i.e. when user taps "Save/Submit").
     *
     * Body:
     *   row_id            integer  (required)
     *   activity_id       integer  (required)
     *   activity_sequence integer  (0 or omit = original)
     *   latitude          string   (required)
     *   longitude         string   (required)
     *   group_id          integer  (optional)
     */
    /**
     * Called by saveAnswer when no answer key is present (submit mode).
     * Validates all required questions, then marks the instance as submitted.
     * Returns otp_verification status from the project.
     */
    private function handleSubmitMode(Request $request, int $rowId, int $actId, int $seq)
    {
        $seqFilter = $this->apiActivitySequenceFilter($seq);

        // Load row to get project
        $projectTemp = ProjectTemplateNameValuesNew::find($rowId);
        if (!$projectTemp) {
            return response()->json(['status' => 404, 'saved' => false, 'message' => 'Row not found.'], 404);
        }
        $project = optional($projectTemp->getProjectTemplateData)->getProject;
        $otpVerification = $project ? (bool) $project->is_otp_required : false;

        // All questions for this activity
        $allQuestions = Question::where('activity_id', $actId)->get()->keyBy('id');
        $allQIds      = $allQuestions->keys()->toArray();

        // Saved answers for this instance
        $savedAnswers = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($seqFilter)
            ->whereNotNull('user_answer')
            ->where('user_answer', '!=', '')
            ->get();
        $answeredIds = $savedAnswers->pluck('question_id')->unique()->toArray();

        // Sub-question child IDs (always-visible outlet sub-questions via question_sub_questions table)
        $subChildIds = QuestionSubQuestion::whereIn('parent_question_id', $allQIds)
            ->pluck('child_question_id')->toArray();

        // Index saved answers by question_id (string key) for reliable lookup
        $answeredMap = $savedAnswers->keyBy(fn($a) => (string) $a->question_id);

        $missing = [];

        foreach ($allQuestions as $qId => $question) {
            if ($question->answer_type != 1) continue;
            if ($question->question_type === 'Multi Response') continue;

            $isSubChild = in_array((string) $qId, array_map('strval', $subChildIds));
            $hasParent  = !empty($question->parent_question_id);

            if ($hasParent) {
                $parentId     = (string)(int) $question->parent_question_id;
                $parentAnswer = $answeredMap->get($parentId);

                // Parent not answered at all → skip this child
                if (!$parentAnswer) continue;

                // parent_value set → only required when parent answer matches
                if (!empty($question->parent_value)) {
                    $parentVal = strtolower(trim((string) $parentAnswer->user_answer));
                    if ($parentVal !== strtolower(trim($question->parent_value))) {
                        continue; // condition not met → skip
                    }
                } else {
                    // parent_value not configured → cannot determine trigger condition → skip
                    continue;
                }
            } elseif ($isSubChild) {
                // Always-visible outlet sub-question — always required
            }

            if (!$answeredMap->has((string) $qId)) {
                $missing[] = ['question_id' => $qId, 'question' => $question->question];
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'status'  => 422,
                'saved'   => false,
                'message' => 'Please answer all required (*) questions.',
                'missing' => $missing,
            ], 422);
        }

        // Check if this activity allows multiple instances (repeat/add-on)
        $projectTemplate  = optional($projectTemp->getProjectTemplateData);
        $addOnActivityIds = json_decode($projectTemplate->activity_add_on_activity_ids ?? '[]', true) ?: [];
        $allowRepeat      = $projectTemplate->activity_add_on == 1
            && (empty($addOnActivityIds) || in_array($actId, array_map('intval', $addOnActivityIds)));

        // Signature still owed: template requires one and this user hasn't uploaded
        // it yet for this row+activity.
        $signatureAllowed = (bool) $projectTemplate->add_signature
            && !ActivitySignature::where('row_id', $rowId)
                ->where('activity_id', $actId)
                ->where('user_id', $request->user()->id)
                ->exists();

        // Mark this instance's answers as submitted (status=1)
        TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($seqFilter)
            ->update(['status' => 1, 'updated_at' => now()]);

        // Mark this repeat instance itself as submitted, so addInstance() stops
        // treating it as "still in progress" and allows the next instance.
        if ($seq > 0) {
            ActivityRepeatInstance::where('row_id', $rowId)
                ->where('activity_id', $actId)
                ->where('activity_sequence', $seq)
                ->update(['status' => 1]);
        }

        if ($allowRepeat) {
            // Instance saved — not the final activity submission yet.
            // Return the next sequence number the app should use for the next instance.
            $nextSequence = (int) TempUserActivityAnswersData::where('row_id', $rowId)
                ->where('activity_id', $actId)
                ->max('activity_sequence') + 1;

            return response()->json([
                'status'            => 200,
                'saved'             => true,
                'message'           => 'Instance saved successfully.',
                'allow_repeat'      => true,
                'next_sequence'     => $nextSequence,
                'otp_verification'  => $otpVerification,
                'signature_allowed' => $signatureAllowed,
            ], 200);
        }

        // Activity does not allow repeat — this is the final submission
        return response()->json([
            'status'            => 200,
            'saved'             => true,
            'message'           => 'Activity submitted successfully.',
            'allow_repeat'      => false,
            'next_sequence'     => null,
            'otp_verification'  => $otpVerification,
            'signature_allowed' => $signatureAllowed,
        ], 200);
    }

    public function finalizeAnswers(Request $request)
    {
        $request->validate([
            'row_id'      => 'required|integer',
            'activity_id' => 'required|integer',
            'latitude'    => 'required|string',
            'longitude'   => 'required|string',
        ]);

        $rowId  = (int) $request->row_id;
        $actId  = (int) $request->activity_id;
        $seq    = (int) ($request->activity_sequence ?? 0);

        $seqFilter = $this->apiActivitySequenceFilter($seq);

        // ── Load all questions for this activity ───────────────────────────
        $activityQuestionIds = Question::where('activity_id', $actId)->pluck('id')->toArray();

        // ── Load already-saved answers for this instance ──────────────────
        $savedAnswers = TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($seqFilter)
            ->get();

        $answeredIds = $savedAnswers
            ->where(fn($a) => $a->user_answer !== null && trim((string)$a->user_answer) !== '')
            ->pluck('question_id')
            ->unique()
            ->toArray();

        // ── Required parent/standalone questions ──────────────────────────
        $requiredIds = Question::whereIn('id', $activityQuestionIds)
            ->where('answer_type', 1)
            ->where('question_type', '!=', 'Multi Response')
            ->where('is_parent', 1)
            ->pluck('id')->toArray();

        // ── Required always-visible sub-question children ─────────────────
        $allSubChildIds = [];
        $toProcess = $activityQuestionIds;
        while (!empty($toProcess)) {
            $childBatch = QuestionSubQuestion::whereIn('parent_question_id', $toProcess)
                ->pluck('child_question_id')->toArray();
            $newIds = array_diff($childBatch, $allSubChildIds);
            if (empty($newIds)) break;
            $allSubChildIds = array_merge($allSubChildIds, $newIds);
            $toProcess = array_values($newIds);
        }
        $requiredSubIds = Question::whereIn('id', $allSubChildIds)
            ->where('answer_type', 1)
            ->where('question_type', '!=', 'Multi Response')
            ->whereNull('parent_question_id')
            ->pluck('id')->toArray();

        $allRequiredIds = array_unique(array_merge($requiredIds, $requiredSubIds));
        $missingIds     = array_diff($allRequiredIds, $answeredIds);

        if (!empty($missingIds)) {
            $missing = Question::whereIn('id', array_values($missingIds))
                ->get(['id', 'question'])
                ->map(fn($q) => ['question_id' => $q->id, 'question' => $q->question]);
            return response()->json([
                'status'   => 422,
                'message'  => 'Please answer all required (*) questions.',
                'missing'  => $missing,
            ], 422);
        }

        // ── Mark all answers for this instance as submitted ───────────────
        TempUserActivityAnswersData::where('row_id', $rowId)
            ->where('activity_id', $actId)
            ->where($seqFilter)
            ->update(['status' => 1, 'updated_at' => now()]);

        // Mark this repeat instance itself as submitted, so addInstance() stops
        // treating it as "still in progress" and allows the next instance.
        if ($seq > 0) {
            ActivityRepeatInstance::where('row_id', $rowId)
                ->where('activity_id', $actId)
                ->where('activity_sequence', $seq)
                ->update(['status' => 1]);
        }

        return response()->json(['status' => 200, 'message' => 'Answers submitted successfully.']);
    }

    /** Decode a base64 data-URI (video/audio) and save to disk. Returns saved path or "ERROR:..." */
    private function decodeAndSaveMediaBase64Api(string $dataUrl, string $hint, string $dir, ?string &$filePath): string
    {
        if (!str_starts_with($dataUrl, 'data:')) { $filePath = $dataUrl; return $dataUrl; }
        if (!preg_match('/^data:(video|audio)\/([^;,]+)/i', $dataUrl, $tm)) return 'ERROR:Invalid media data.';

        $b64pos = strripos($dataUrl, 'base64,');
        if ($b64pos === false) return 'ERROR:Invalid media encoding.';
        $raw = base64_decode(str_replace(' ', '+', preg_replace('/\s+/', '', substr($dataUrl, $b64pos + 7))), true);
        if ($raw === false || strlen($raw) < 100) return 'ERROR:Unable to decode media file.';

        $extMap = [
            'video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov','video/3gpp'=>'3gp',
            'audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/wav'=>'wav','audio/ogg'=>'ogg',
            'audio/webm'=>'webm','audio/mp4'=>'m4a','audio/aac'=>'aac',
        ];
        $mime  = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '';
        $ext   = $extMap[$mime] ?? ($hint === 'audio' ? 'webm' : 'mp4');
        $fname = $hint . '_' . time() . '_' . Str::random(8) . '.' . $ext;
        $abs   = public_path($dir . '/' . $fname);
        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0777, true);
        if (file_put_contents($abs, $raw) === false) return 'ERROR:Failed to save media file.';
        @chmod($abs, 0644);
        $filePath = $dir . '/' . $fname;
        return $filePath;
    }

    // -------------------------------------------------------------------------
    // Template row – get / update
    // -------------------------------------------------------------------------

    /**
     * GET /api/getTemplateRowData/{row_id}
     * Returns the stored values for a single template row, keyed by header id and name.
     */
    public function getTemplateRowData($rowId)
    {
        $row = DB::table('project_template_name_values_new')->where('id', $rowId)->first();
        if (!$row) {
            return response()->json(['status' => 404, 'message' => 'Row not found.'], 404);
        }

        $templateNameId = DB::table('project_templates')->where('id', $row->project_template_id)->value('template_name_id');
        if (!$templateNameId) {
            return response()->json(['status' => 404, 'message' => 'Project template not found.'], 404);
        }

        $storedJson = json_decode($row->template_data_json, true) ?? [];

        $data = DB::table('template_name_heads')
            ->where('template_name_id', $templateNameId)
            ->get(['id', 'template_head_name'])
            ->map(fn($hdr) => [
                'id'    => $hdr->id,
                'name'  => $hdr->template_head_name,
                'value' => $storedJson[$hdr->id] ?? null,
            ])->values()->toArray();

        return response()->json([
            'status'               => 200,
            'row_id'               => (int) $rowId,
            'project_template_id'  => $row->project_template_id,
            'data'                 => $data,
        ]);
    }

    /**
     * POST /api/updateTemplateRowData
     * Updates values for an existing template row.
     *
     * Body (ID mode — recommended):
     *   { "row_id": 5, "376": "new value", "377": "another value", ... }
     *
     * Body (name mode):
     *   { "row_id": 5, "CD Code": "new value", "CD Name": "another value", ... }
     *
     * You may send a partial payload — only the supplied headers are updated;
     * the rest are left unchanged.
     */
    public function updateTemplateRowData(Request $request)
    {
        $request->validate(['row_id' => 'required|integer']);

        try {
            $row = DB::table('project_template_name_values_new')->where('id', $request->row_id)->first();
            if (!$row) {
                return response()->json(['status' => 404, 'message' => 'Row not found.'], 404);
            }

            $templateNameId = DB::table('project_templates')->where('id', $row->project_template_id)->value('template_name_id');
            if (!$templateNameId) {
                return response()->json(['status' => 404, 'message' => 'Project template not found.'], 404);
            }

            $headers = DB::table('template_name_heads')->where('template_name_id', $templateNameId)->get();

            // Build lookup maps
            $headerIdMap   = [];
            $headerNameMap = [];
            foreach ($headers as $hdr) {
                $headerIdMap[$hdr->id] = $hdr;
                $normalizedName = strtolower(str_replace([' ', '-'], '_', trim($hdr->template_head_name)));
                $headerNameMap[$normalizedName] = $hdr;
            }

            $payload = $request->except(['row_id']);
            $nonSystemKeys = array_keys($payload);
            $useIdMode = count($nonSystemKeys) > 0 && count(array_filter($nonSystemKeys, 'is_numeric')) === count($nonSystemKeys);

            $updates     = [];
            $unknownKeys = [];

            if ($useIdMode) {
                foreach ($payload as $headerId => $value) {
                    if (!isset($headerIdMap[(int)$headerId])) {
                        $unknownKeys[] = $headerId;
                    } else {
                        $updates[(int)$headerId] = $value;
                    }
                }
            } else {
                foreach ($payload as $key => $value) {
                    $normalizedKey = strtolower(str_replace([' ', '-'], '_', trim($key)));
                    if (!isset($headerNameMap[$normalizedKey])) {
                        $unknownKeys[] = $key;
                    } else {
                        $updates[$headerNameMap[$normalizedKey]->id] = $value;
                    }
                }
            }

            if (!empty($unknownKeys)) {
                return response()->json([
                    'status'       => 422,
                    'message'      => 'Unknown header keys supplied',
                    'unknown_keys' => $unknownKeys,
                ], 422);
            }

            if (empty($updates)) {
                return response()->json(['status' => 422, 'message' => 'No valid header values provided.'], 422);
            }

            // Merge updates into the existing JSON (partial update)
            $existing = json_decode($row->template_data_json, true) ?? [];
            $merged   = array_merge($existing, $updates);

            DB::table('project_template_name_values_new')
                ->where('id', $request->row_id)
                ->update([
                    'template_data_json' => json_encode($merged),
                    'updated_at'         => now(),
                ]);

            return response()->json(['status' => 200, 'message' => 'Row updated successfully.']);

        } catch (\Exception $e) {
            Log::info('Template row update failed: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Something went wrong.'], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Repeat activity instances
    // -------------------------------------------------------------------------

    /**
     * GET /api/activityInstances?row_id=X&activity_id=Y
     * Returns the original instance + all repeat instances for this row/activity.
     * Mirrors: TaskHandlerController::userActivityInstancesList()
     */
    public function activityInstances(Request $request)
    {
        if (empty($request->all()) && ($content = $request->getContent()) && ($json = json_decode($content, true))) {
            $request->merge($json);
        }

        $request->validate([
            'row_id'      => 'required|integer',
            'activity_id' => 'required|integer',
        ]);

        $user       = $request->user();
        $row_id     = (int) $request->row_id;
        $activityId = (int) $request->activity_id;

        // Pre-load all sent-back sequences in ONE query (avoids N+1 inside the repeat instances loop)
        $sentBackSeqs = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->where('status', 3)
            ->get(['activity_sequence'])
            ->map(fn($a) => $a->activity_sequence ?? 0)
            ->unique()
            ->toArray();

        // ── Helper: does this instance have ANY sent-back answer? ─────────
        $hasSentBack = fn(int $seq): bool => in_array($seq, $sentBackSeqs);

        // ── Original instance (activity_sequence = 0) ─────────────────────
        $requiredIds   = $this->requiredQuestionIdsForActivity($activityId);
        $requiredCount = count($requiredIds);

        $originalAnswers = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->where(fn($q) => $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0))
            ->get(['question_id', 'parent_context_id']);
        $submittedCount = $this->countSubmittedAgainstRequired($originalAnswers, $requiredIds);

        $originalSentBack  = $hasSentBack(0);
        $originalSubmitted = !$originalSentBack && $submittedCount >= $requiredCount && $submittedCount > 0;

        $instances = [[
            'label'             => 'Instance 1 (Original)',
            'activity_sequence' => 0,
            'submitted'         => $originalSubmitted,
            'sent_back'         => $originalSentBack,
            'pending'           => !$originalSubmitted && !$originalSentBack,
            'instance_id'       => null,
            'is_original'       => true,
        ]];

        // ── Add-on repeat instances ────────────────────────────────────────
        $repeatInstances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->orderBy('activity_sequence')
            ->get();

        // Pre-load all sequences that have answers saved so we avoid N+1 queries
        $savedSeqs = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->where('activity_sequence', '>', 0)
            ->distinct('activity_sequence')
            ->pluck('activity_sequence')
            ->toArray();

        foreach ($repeatInstances as $inst) {
            $seq           = (int) $inst->activity_sequence;
            $instSentBack  = $hasSentBack($seq);
            $instSubmitted = !$instSentBack && in_array($seq, $savedSeqs);
            $instances[] = [
                'label'             => $inst->instance_label,
                'activity_sequence' => $seq,
                'submitted'         => $instSubmitted,
                'sent_back'         => $instSentBack,
                'pending'           => !$instSubmitted && !$instSentBack,
                'instance_id'       => $inst->id,
                'is_original'       => false,
            ];
        }

        $isClosed = ActivityInstanceClose::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'status'     => 200,
            'instances'  => $instances,
            'is_closed'  => $isClosed,
        ]);
    }

    /**
     * POST /api/addInstance  { row_id, activity_id }
     * Creates a new repeat instance only if the previous instance is submitted.
     * Mirrors: TaskHandlerController::add_activity_instance()
     */
    
    public function addInstance(Request $request)
    {
        $request->validate([
            'row_id'      => 'required|integer',
            'activity_id' => 'required|integer',
        ]);

        $user       = $request->user();
        $row_id     = $request->row_id;
        $activityId = $request->activity_id;

        // Block if activity is already closed
        if (ActivityInstanceClose::where('row_id', $row_id)->where('activity_id', $activityId)->where('user_id', $user->id)->exists()) {
            return response()->json(['status' => 400, 'message' => 'Activity is already closed. No new instances allowed.'], 400);
        }

        // Normalize any sent-back instances for this activity before checking the gate below.
        // The verifier's "send back" action resets activity_repeat_instances.status to 0 (see
        // VerifierController::activity_answer_verify) so the instance reopens for editing, while
        // marking its answers status=3. That status=0 otherwise looks identical to "still a draft,
        // never submitted" and would wrongly block new instances. Sweep ALL of this activity's
        // instances (not just the latest) — status=0 + any answer at status=3 for that sequence
        // means "was sent back" — and flip those instances back to status=1 so they stop gating
        // addInstance/is_locked. The answers themselves stay at status=3 so the sent-back instance
        // is still flagged for the auditor/verifier to see and correct later.
        $zeroStatusInstances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('status', 0)
            ->get();

        if ($zeroStatusInstances->isNotEmpty()) {
            $sentBackSequences = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $activityId)
                ->where('status', 3)
                ->whereIn('activity_sequence', $zeroStatusInstances->pluck('activity_sequence'))
                ->distinct()
                ->pluck('activity_sequence');

            if ($sentBackSequences->isNotEmpty()) {
                ActivityRepeatInstance::where('row_id', $row_id)
                    ->where('activity_id', $activityId)
                    ->whereIn('activity_sequence', $sentBackSequences)
                    ->update(['status' => 1]);
            }
        }

        $latestRepeat     = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->orderBy('activity_sequence', 'desc')
            ->first();
        $currentSequence = $latestRepeat ? $latestRepeat->activity_sequence : 0;

        // Gate on answer completeness for the current instance (original or latest repeat),
        // not on activity_repeat_instances.status — that flag only flips when a separate
        // "submit" call happens, but getAllQuestion already reports an instance as saved
        // (current_instance_saved/original_submitted) purely from whether every required
        // question has an answer. Gating on the status flag instead could block the user
        // even though the app already shows the instance as complete.
        $requiredIds   = $this->requiredQuestionIdsForActivity($activityId);
        $requiredCount = count($requiredIds);
        $currentAnswers = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)->where('user_id', $user->id)
            ->where(fn($q) => $currentSequence > 0
                ? $q->where('activity_sequence', $currentSequence)
                : $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0))
            ->get(['question_id', 'parent_context_id']);
        $submittedCount = $this->countSubmittedAgainstRequired($currentAnswers, $requiredIds);

        if ($submittedCount < $requiredCount) {
            $message = $currentSequence > 0
                ? 'Please submit the current instance before adding a new one.'
                : 'Please submit the original instance before adding a new one.';
            return response()->json(['status' => 400, 'message' => $message], 400);
        }

        // Keep the instance's own status flag in sync for anything else that reads it
        // (e.g. is_locked calculations) now that completeness — not this flag — is the gate.
        if ($latestRepeat && $latestRepeat->status == 0) {
            $latestRepeat->update(['status' => 1]);
        }

        $nextSequence = (int) ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->max('activity_sequence') + 1;

        $instance = ActivityRepeatInstance::create([
            'row_id'            => $row_id,
            'activity_id'       => $activityId,
            'activity_sequence' => $nextSequence,
            'instance_label'    => 'Instance ' . ($nextSequence + 1),
            'user_id'           => $user->id,
            'status'            => 0,
        ]);

        return response()->json([
            'status'            => 200,
            'message'           => 'New instance created.',
            'instance_id'       => $instance->id,
            'activity_sequence' => $nextSequence,
            'label'             => $instance->instance_label,
        ]);
    }

    /**
     * POST /api/deleteInstance  { instance_id }
     * Deletes an unsubmitted repeat instance and its draft answers.
     * Mirrors: TaskHandlerController::delete_activity_instance()
     */
    public function deleteInstance(Request $request)
    {
        if (empty($request->all()) && ($content = $request->getContent()) && ($json = json_decode($content, true))) {
            $request->merge($json);
        }

        $request->validate([
            'instance_id' => 'required|integer',
        ]);

        $instance = ActivityRepeatInstance::where('id', $request->instance_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 0)
            ->first();

        if (!$instance) {
            return response()->json(['status' => 400, 'message' => 'Instance not found or already submitted.'], 400);
        }

        TempUserActivityAnswersData::where('row_id', $instance->row_id)
            ->where('activity_id', $instance->activity_id)
            ->where('activity_sequence', $instance->activity_sequence)
            ->delete();

        $instance->delete();

        return response()->json(['status' => 200, 'message' => 'Instance deleted successfully.']);
    }

    /**
     * POST /api/closeActivity  { row_id, activity_id }
     * Closes all instances once every instance + original are submitted.
     * Mirrors: TaskHandlerController::closeActivityInstances()
     */
    public function closeActivity(Request $request)
    {
        if (empty($request->all()) && ($content = $request->getContent()) && ($json = json_decode($content, true))) {
            $request->merge($json);
        }

        $request->validate([
            'row_id'      => 'required|integer',
            'activity_id' => 'required|integer',
        ]);

        $user       = $request->user();
        $row_id     = $request->row_id;
        $activityId = $request->activity_id;

        // Must have at least one submitted answer (original or any repeat instance)
        $hasAnyAnswers = TempUserActivityAnswersData::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasAnyAnswers) {
            return response()->json(['status' => 400, 'message' => 'No answers submitted yet. Please submit at least one instance before closing.'], 400);
        }

        // All repeat instances must have answers saved before closing.
        // Check by querying actual answer records, not activity_repeat_instances.status
        // (status column is not updated when answers are submitted).
        $repeatInstances = ActivityRepeatInstance::where('row_id', $row_id)
            ->where('activity_id', $activityId)
            ->get(['activity_sequence', 'instance_label']);

        if ($repeatInstances->isNotEmpty()) {
            $savedSeqs = TempUserActivityAnswersData::where('row_id', $row_id)
                ->where('activity_id', $activityId)
                ->where('user_id', $user->id)
                ->where('activity_sequence', '>', 0)
                ->distinct('activity_sequence')
                ->pluck('activity_sequence')
                ->toArray();

            $unsavedInstances = $repeatInstances->filter(
                fn($inst) => !in_array((int) $inst->activity_sequence, $savedSeqs)
            );

            if ($unsavedInstances->isNotEmpty()) {
                return response()->json([
                    'status'  => 400,
                    'message' => 'All instances must be submitted before closing.',
                    'pending_instances' => $unsavedInstances->map(fn($i) => [
                        'activity_sequence' => $i->activity_sequence,
                        'label'             => $i->instance_label,
                    ])->values(),
                ], 400);
            }
        }

        ActivityInstanceClose::updateOrCreate([
            'row_id'      => $row_id,
            'activity_id' => $activityId,
            'user_id'     => $user->id,
        ]);

        // Signature still owed: template requires one and this user hasn't uploaded
        // it yet for this row+activity.
        $rowData          = ProjectTemplateNameValuesNew::find($row_id);
        $projectTemplate  = $rowData ? ProjectTemplate::find($rowData->project_template_id) : null;
        $signatureAllowed = $projectTemplate
            ? ((bool) $projectTemplate->add_signature
                && !ActivitySignature::where('row_id', $row_id)
                    ->where('activity_id', $activityId)
                    ->where('user_id', $user->id)
                    ->exists())
            : false;

        return response()->json([
            'status'            => 200,
            'message'           => 'Activity closed successfully.',
            'signature_allowed' => $signatureAllowed,
        ]);
    }

    // -------------------------------------------------------------------------
    // Password update
    // -------------------------------------------------------------------------

    /**
     * POST /api/updatePassword  { current_password, new_password, new_password_confirmation }
     */
    public function updatePassword(Request $request)
    {
        if (empty($request->all()) && ($content = $request->getContent()) && ($json = json_decode($content, true))) {
            $request->merge($json);
        }

        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8|max:20|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['status' => 400, 'message' => 'Current password is incorrect.'], 400);
        }

        $user->update([
            'password'      => Hash::make($request->new_password),
            'password_info' => $request->new_password,
        ]);

        return response()->json(['status' => 200, 'message' => 'Password updated successfully.']);
    }

    private function filterAndPaginateActivities(\Illuminate\Support\Collection $collection, $request): array
    {
        if ($request->keyword) {
            $kw = strtolower($request->keyword);
            $collection = $collection->filter(fn($a) => str_contains(strtolower($a['activity_name']), $kw))->values();
        }

        if ($request->status === 'completed') {
            $collection = $collection->filter(fn($a) => $a['is_fully_closed'])->values();
        } elseif ($request->status === 'pending') {
            $collection = $collection->filter(fn($a) => !$a['is_fully_closed'])->values();
        }

        $perPage = 10;
        $page    = max(1, (int) ($request->page ?? 1));
        $total   = $collection->count();

        $lastPage = (int) ceil($total / $perPage) ?: 1;

        return [
            'data'         => $collection->forPage($page, $perPage)->values(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'next_page'    => $page < $lastPage ? $page + 1 : null,
            'total_pages'    => $lastPage,
        ];
    }
}
