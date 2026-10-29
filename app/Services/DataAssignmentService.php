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
    public static function assignOutletChildren($dataAssignId, $auditorId, ProjectTemplate $masterTemplate, array $masterRowIds, $activityGroupId, array $verifierIds = [], ?int $limitToVerifierId = null): void
    {
        foreach (self::outletChildren($masterTemplate, $masterRowIds) as $child) {
            $childProject = $child['template'];
            $childRowIds = $child['row_ids'];

            // A verifier assigning their own auditors: only the outlet rows they were given
            $allowed = function (int $activityId) use ($limitToVerifierId, $childProject, $childRowIds) {
                return $limitToVerifierId === null
                    ? $childRowIds
                    : VerifierAssignment::ownRowIds($limitToVerifierId, (int) $childProject->id, $activityId, $childRowIds);
            };
            $withoutDataAllowed = $limitToVerifierId === null
                || Verifier::where('user_id', $limitToVerifierId)->where('project_template_name_id', $childProject->id)->exists();

            if ($childProject->activityType == 0) {
                // Assign child template rows (same logic, different template)
                $activityId = (int) $childProject->activity_group_name_id_or_activity_id;
                if (!empty($childRowIds)) {
                    self::assignRows($dataAssignId, $auditorId, $childProject->id, $activityId, $activityGroupId, $allowed($activityId), $verifierIds);
                } else if ($childProject->with_data == 0 && $withoutDataAllowed) {
                    self::assignWithoutData($dataAssignId, $auditorId, $childProject->id, $activityId, $activityGroupId, null, $verifierIds);
                }
            } else if ($childProject->activityType == 1) {
                foreach ($child['activity_ids'] as $childActivityId) {
                    if (!empty($childRowIds)) {
                        self::assignRows($dataAssignId, $auditorId, $childProject->id, $childActivityId, $childProject->activity_group_name_id_or_activity_id, $allowed((int) $childActivityId), $verifierIds);
                    } else if ($childProject->with_data == 0 && $withoutDataAllowed) {
                        self::assignWithoutData($dataAssignId, $auditorId, $childProject->id, $childProject->activity_group_name_id_or_activity_id, $activityGroupId);
                        // The call above records the group id as the activity; verifier links
                        // need the real activity id that rows and the queue use.
                        VerifierAssignment::linkAuditor($verifierIds, (int) $auditorId, (int) $childProject->id, (int) $childActivityId, true);
                    }
                }
            }
        }
    }

    /**
     * Verifier-only assignment: the verifiers get the rows (and, with outlets, the linked outlet
     * rows) without an auditor; they assign their own auditors later.
     */
    public static function assignToVerifiersOnly(array $verifierIds, ProjectTemplate $projectTemplate, int $activityId, array $rowIds, bool $withOutlets): void
    {
        VerifierAssignment::assignRows($verifierIds, (int) $projectTemplate->id, $activityId, $rowIds);
        if (!$withOutlets || empty($rowIds)) return;

        foreach (self::outletChildren($projectTemplate, $rowIds) as $child) {
            foreach ($child['activity_ids'] as $childActivityId) {
                VerifierAssignment::assignRows($verifierIds, (int) $child['template']->id, (int) $childActivityId, $child['row_ids']);
            }
        }
    }

    /**
     * Outlet (child) templates of a master template with the child rows linked to the given
     * master rows: [['template' => row of project_templates, 'activity_ids' => [...], 'row_ids' => [...]], ...]
     */
    private static function outletChildren(ProjectTemplate $masterTemplate, array $masterRowIds): array
    {
        $childProjectTemplate = DB::table('project_templates')
            ->where('project_id', $masterTemplate->project_id)
            ->where('is_master', 0)
            ->get();

        $children = [];
        foreach ($childProjectTemplate as $childProject) {
            //get master head id value
            $getHeadValues = DB::table('project_template_name_values_new')
                ->whereIn('id', $masterRowIds)
                ->pluck(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$childProject->master_head_id}\"')) as head_value")
                )
                ->toArray();

            // Outlets of these distributors: a value equal to the distributor value (as before), or
            // the outlet's own reference head (e.g. DB Code) holding the distributor value followed
            // by extra text, e.g. "Disha Enteprises (SS)" (see OutletDistributorMatch).
            $getHeadValues = array_values(array_filter($getHeadValues, fn($v) => trim((string) $v) !== ''));
            $ownHeadId = $childProject->own_reference_head_id;
            $distributorNames = OutletDistributorMatch::distributorValues((int) $masterTemplate->id, $childProject->master_head_id);

            $childdatacheck = empty($getHeadValues) ? collect() : DB::table('project_template_name_values_new')
                ->where('project_template_id', $childProject->id)
                ->where(function ($query) use ($getHeadValues, $ownHeadId) {
                    foreach ($getHeadValues as $value) {
                        $query->orWhereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [$value]);
                        if ($ownHeadId) {
                            $query->orWhereRaw(
                                "LOWER(JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"{$ownHeadId}\"'))) LIKE ?",
                                [addcslashes(OutletDistributorMatch::normalize($value), '%_\\') . '%']
                            );
                        }
                    }
                })->get()
                ->filter(function ($row) use ($getHeadValues, $ownHeadId, $distributorNames) {
                    $values = json_decode($row->template_data_json, true) ?: [];
                    $ownValue = OutletDistributorMatch::headValue($values, $ownHeadId);
                    foreach ($getHeadValues as $value) {
                        if (in_array($value, $values, true)
                            || ($ownHeadId && OutletDistributorMatch::belongsTo($ownValue, $value, $distributorNames))) {
                            return true;
                        }
                    }
                    return false;
                })->values();

            $children[] = [
                'template'     => $childProject,
                'activity_ids' => $childProject->activityType == 1
                    ? ActivityGroupPivot::where('activity_group_id', $childProject->activity_group_name_id_or_activity_id)->pluck('activity_id')->toArray()
                    : [$childProject->activity_group_name_id_or_activity_id],
                'row_ids'      => $childdatacheck->pluck('id')->toArray(),
            ];
        }
        return $children;
    }
}
