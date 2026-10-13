<?php

namespace App\Services;

use App\Models\ActivityGroupPivot;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\UserActivityDataAssign;
use App\Models\UserAuditAssigns;
use App\Models\Verifier;
use Illuminate\Support\Facades\DB;

/**
 * Assignment steps shared by the Assign Data form (UserController::assignDataToUsers)
 * and Bulk Assign, so both assign data the same way.
 */
class DataAssignmentService
{
    /** Template-level verifier entries ("My Verifications" list, report dropdowns). */
    public static function ensureTemplateVerifiers(array $verifierIds, ProjectTemplate $projectTemplate, bool $isOutletAssigned): void
    {
        foreach ($verifierIds as $verifierId) {
            $check_if_allocated = Verifier::where('user_id', $verifierId)
                ->where('project_template_name_id', $projectTemplate->id)->first();
            if (!$check_if_allocated) {
                Verifier::create([
                    'user_id' => $verifierId,
                    'project_template_name_id' => $projectTemplate->id,
                ]);
            }

            if ($isOutletAssigned) {
                $project_outlets = ProjectTemplate::where('project_id', $projectTemplate->project_id)
                    ->where('is_master', 0)
                    ->get();
                foreach ($project_outlets as $project_outlet) {
                    $check_if_outlet_allocated = Verifier::where('user_id', $verifierId)
                        ->where('project_template_name_id', $project_outlet->id)->first();
                    if (!$check_if_outlet_allocated) {
                        Verifier::create([
                            'user_id' => $verifierId,
                            'project_template_name_id' => $project_outlet->id,
                        ]);
                    }
                }
            }
        }
    }

    public static function findOrCreateDataAssign(
        Project $project, int $activityId, ?int $activityGroupId, int $templateNameId, int $projectTemplateId,
        $templateNameHeadId, int $isOutletAssigned, $companyUserId = null, $agencyId = null
    ): DataAssign {
        // Transaction + pessimistic lock to prevent duplicate data_assigns when the
        // form is submitted concurrently (e.g. double-click).
        return DB::transaction(function () use (
            $project, $activityId, $activityGroupId, $templateNameId, $projectTemplateId,
            $templateNameHeadId, $isOutletAssigned, $companyUserId, $agencyId
        ) {
            $searchFields = [
                'company_id'            => $project->company_id,
                'zone_id'               => $project->zone_id,
                'unit_id'               => $project->unit_id,
                'project_id'            => $project->id,
                'activity_id'           => $activityId,
                'activity_group_id'     => $activityGroupId,
                'template_name_id'      => $templateNameId,
                'project_template_id'   => $projectTemplateId,
                'template_name_head_id' => $templateNameHeadId,
                'is_outlet_assigned'    => $isOutletAssigned,
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
    }

    public static function assignRows($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds, array $verifierIds = [])
    {
        if (empty($rowIds)) return null;

        VerifierAssignment::assignRows($verifierIds, (int) $templateId, (int) $activityId, $rowIds);
        VerifierAssignment::linkAuditor($verifierIds, (int) $userId, (int) $templateId, (int) $activityId, false);

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

    public static function assignWithoutData($dataAssignId, $userId, $templateId, $activityId, $activityGroupId, $rowIds = null, array $verifierIds = [])
    {
        // No uploaded rows: link the auditor so each row they create reaches these
        // verifiers, and hand over rows they already created here.
        VerifierAssignment::linkAuditor($verifierIds, (int) $userId, (int) $templateId, (int) $activityId, true);

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

    /**
     * "Assign Outlets Also": give the auditor the child (outlet) template rows linked to
     * the given master rows, for every child template of the project.
     */
    public static function assignOutletChildren($dataAssignId, $auditorId, ProjectTemplate $masterTemplate, array $masterRowIds, $activityGroupId, array $verifierIds = []): void
    {
        $childProjectTemplate = DB::table('project_templates')
            ->where('project_id', $masterTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        foreach ($childProjectTemplate as $childProject) {
            //get master head id value
            $getHeadValues = DB::table('project_template_name_values_new')
                ->whereIn('id', $masterRowIds)
                ->pluck(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$childProject->master_head_id}\"')) as head_value")
                )
                ->toArray();

            $childdatacheck = DB::table('project_template_name_values_new')
                ->where('project_template_id', $childProject->id)
                ->where(function ($query) use ($getHeadValues) {
                    foreach ($getHeadValues as $value) {
                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                    }
                })->get();

            $childRowIds = $childdatacheck->pluck('id')->toArray();

            if ($childProject->activityType == 0) {
                // Assign child template rows (same logic, different template)
                if (!$childdatacheck->isEmpty()) {
                    self::assignRows($dataAssignId, $auditorId, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $activityGroupId, $childRowIds, $verifierIds);
                } else if ($childProject->with_data == 0) {
                    self::assignWithoutData($dataAssignId, $auditorId, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $activityGroupId, null, $verifierIds);
                }
            } else if ($childProject->activityType == 1) {
                $getActivityIds = ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)
                    ->pluck('activity_id')->toArray();

                foreach ($getActivityIds as $childActivityId) {
                    if (!$childdatacheck->isEmpty()) {
                        self::assignRows($dataAssignId, $auditorId, $childProject->id, $childActivityId, $childProject->activity_group_name_id_or_activity_id, $childRowIds, $verifierIds);
                    } else if ($childProject->with_data == 0) {
                        self::assignWithoutData($dataAssignId, $auditorId, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $activityGroupId);
                        // The call above records the group id as the activity; verifier links
                        // need the real activity id that rows and the queue use.
                        VerifierAssignment::linkAuditor($verifierIds, (int) $auditorId, (int) $childProject->id, (int) $childActivityId, true);
                    }
                }
            }
        }
    }
}
