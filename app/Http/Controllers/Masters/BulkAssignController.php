<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroupPivot;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateNameHead;
use App\Models\User;
use App\Services\DataAssignmentService;
use App\Services\VerifierAssignment;
use App\Services\XlsxStream;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bulk Assign: download a sheet of a template's rows (one line per row × activity),
 * fill Auditor/Verifier mobile numbers, upload it. The whole sheet is validated first; then
 * assignments are made in batches through the same DataAssignmentService the Assign
 * Data form uses.
 */
class BulkAssignController extends Controller
{
    private const SHEET       = 'Assignment';
    private const META_SHEET  = 'BulkAssignInfo';
    private const FORMAT      = 'bulk_assign_v2';   // v2: auditors/verifiers by mobile number (v1 used emails)
    private const UNIT_ROWS   = 300;   // rows per processing unit
    private const BATCH_SECS  = 15;    // work per /process request before returning progress

    public function index()
    {
        $this->denyAgency();
        $verifier = $this->verifierId();
        $projects = Project::open()
            ->when($verifier, fn($q) => $q->whereIn('id', VerifierAssignment::assignedDataAssigns($verifier)->pluck('project_id')->unique()))
            ->orderBy('id', 'DESC')->get(['id', 'project_name']);
        $isVerifier = (bool) $verifier;
        return view('masters.data_templates.bulk_assign', compact('projects', 'isVerifier'));
    }

    public function templates(Request $request)
    {
        $this->denyAgency();
        $request->validate(['project_id' => 'required|integer']);
        $project = Project::open()->findOrFail($request->project_id);
        $verifier = $this->verifierId();

        $templates = ProjectTemplate::with('getTemplate')
            ->where('project_id', $project->id)
            ->when($verifier, fn($q) => $q->whereIn('id', VerifierAssignment::assignedDataAssigns($verifier)->pluck('project_template_id')->unique()))
            ->orderByDesc('is_master')
            ->orderBy('id')
            ->get();
        $hasChildren = ProjectTemplate::where('project_id', $project->id)->where('is_master', 0)->exists();

        return response()->json([
            'templates' => $templates->map(fn($pt) => [
                'id'                 => $pt->id,
                'name'               => optional($pt->getTemplate)->template_name ?? ('Template #' . $pt->id),
                'rows'               => $verifier
                    ? count($this->verifierRowsByActivity($verifier, $pt)->flatten()->unique())
                    : DB::table('project_template_name_values_new')->where('project_template_id', $pt->id)->count(),
                'activities'         => $this->activitiesFor($pt)->pluck('name')->values(),
                'can_assign_outlets' => $pt->is_master == 1 && $hasChildren,
            ])->values(),
        ]);
    }

    public function download(Request $request)
    {
        $this->denyAgency();
        $request->validate(['project_template_id' => 'required|integer']);

        $pt = ProjectTemplate::with('getTemplate')->findOrFail($request->project_template_id);
        $project = Project::open()->find($pt->project_id);
        if (!$project) return back()->with('error', 'This project is completed or no longer exists.');

        $activities = $this->activitiesFor($pt);
        if ($activities->isEmpty()) return back()->with('error', 'No activity is mapped to this template yet.');

        $heads = TemplateNameHead::where('template_name_id', $pt->template_name_id)->orderBy('id')->get(['id', 'template_head_name']);
        if ($heads->isEmpty()) return back()->with('error', 'This template has no columns.');

        // Parent template with outlet (child) templates: each line gets an "Assign Outlets" Yes/No
        $hasChildren = ProjectTemplate::where('project_id', $pt->project_id)->where('is_master', 0)->exists();
        $withOutlets = $pt->is_master == 1 && $hasChildren;

        // Selector stored on the data_assigns record: reuse the one already used for this
        // template so existing assignments are extended rather than duplicated.
        $headId = DataAssign::where('project_template_id', $pt->id)->orderByDesc('id')->value('template_name_head_id')
            ?? $pt->main_header
            ?? $heads->first()->id;


        $dataColCount = 1 + $heads->count();
        $colActivity  = XlsxStream::columnLetter($dataColCount + 1);
        $colAuditor   = XlsxStream::columnLetter($dataColCount + 2);
        $colVerifier  = XlsxStream::columnLetter($dataColCount + 3);
        $colOutlet    = $withOutlets ? XlsxStream::columnLetter($dataColCount + 4) : '';
        $lastDataCol  = XlsxStream::columnLetter($dataColCount);
        $assignCols   = $withOutlets ? 3 : 2;

        $w = new XlsxStream();
        $w->beginSheet(self::SHEET, array_merge([10], array_fill(0, $heads->count(), 20), [34, 36, 36], $withOutlets ? [16] : []), 2);

        $groupRow = array_fill(0, $dataColCount + 1 + $assignCols, '');
        $groupRow[0] = 'Project Data';
        $groupRow[$dataColCount] = 'Activity Details';
        $groupRow[$dataColCount + 1] = 'Assignment';
        $groupStyles = array_merge(
            array_fill(0, $dataColCount + 1, XlsxStream::STYLE_GROUP_HEADER),
            array_fill(0, $assignCols, XlsxStream::STYLE_ASSIGN_GROUP)
        );
        $w->row($groupRow, XlsxStream::STYLE_NORMAL, $groupStyles);
        if ($dataColCount > 1) $w->merge("A1:{$lastDataCol}1");
        $w->merge("{$colAuditor}1:" . ($withOutlets ? $colOutlet : $colVerifier) . '1');

        $w->row(
            array_merge(['Row ID'], $heads->pluck('template_head_name')->all(), ['Activity', 'Auditor Mobile No', 'Verifier Mobile No'], $withOutlets ? ['Assign Outlets'] : []),
            XlsxStream::STYLE_NORMAL,
            array_merge(array_fill(0, $dataColCount + 1, XlsxStream::STYLE_HEADER), array_fill(0, $assignCols, XlsxStream::STYLE_ASSIGN_HEADER))
        );

        $headIds = $heads->pluck('id')->all();
        // A verifier's sheet has only the rows (per activity) given to them, with their own number as verifier
        $verifier = $this->verifierId();
        $ownRows = $verifier ? $this->verifierRowsByActivity($verifier, $pt)->map(fn($ids) => array_flip($ids)) : null;
        $verifierMobile = $verifier ? (string) User::find($verifier)->mobile : '';
        $assignBlank = $withOutlets ? ['', $verifierMobile, 'No'] : ['', $verifierMobile];
        $lastRow = 2;
        DB::table('project_template_name_values_new')
            ->where('project_template_id', $pt->id)
            ->when($ownRows !== null, fn($q) => $q->whereIn('id', $ownRows->flatMap(fn($ids) => array_keys($ids))->unique()->values()))
            ->select('id', 'template_data_json')
            ->lazyById(1000)
            ->each(function ($row) use ($w, $headIds, $activities, $assignBlank, $ownRows, &$lastRow) {
                $json = json_decode($row->template_data_json, true) ?: [];
                $values = [(int) $row->id];
                foreach ($headIds as $headId) {
                    $v = $json[$headId] ?? '';
                    $values[] = is_scalar($v) ? (string) $v : json_encode($v);
                }
                foreach ($activities as $activity) {
                    if ($ownRows !== null && !isset($ownRows->get($activity['id'], [])[(int) $row->id])) continue;
                    $w->row(array_merge($values, [$activity['name']], $assignBlank));
                    $lastRow++;
                }
            });

        if ($lastRow > 2) {
            $w->inputTip(
                "{$colAuditor}3:{$colAuditor}{$lastRow}", 'Assign To (Auditor)',
                $verifier
                    ? "Enter the 10-digit mobile number of one of your auditors to assign this row + activity. For more than one auditor, separate the numbers with commas."
                    : "Enter the auditor's 10-digit mobile number to assign this row + activity. For more than one auditor, separate the numbers with commas. A Verifier Mobile No is also needed, or the row is not assigned."
            );
            $w->inputTip(
                "{$colVerifier}3:{$colVerifier}{$lastRow}", 'Verified By (Verifier)',
                $verifier
                    ? "You are the verifier of the rows you assign."
                    : "Enter the verifier's 10-digit mobile number. For more than one verifier, separate the numbers with commas. With no Auditor Mobile No the row goes to the verifier only, who assigns their own auditors later."
            );
            if ($withOutlets) {
                $w->listValidation(
                    "{$colOutlet}3:{$colOutlet}{$lastRow}", '"Yes,No"', true,
                    'Assign Outlets',
                    'Yes: also assign the outlet (child template) rows linked to this row to the same auditor and verifier. No: assign only this row.'
                );
            }
        }
        $w->endSheet();

        $meta = [
            ['format', self::FORMAT],
            ['project_template_id', $pt->id],
            ['template_name_head_id', (int) $headId],
            ['row_id_column', 'A'],
            ['activity_column', $colActivity],
            ['auditor_column', $colAuditor],
            ['verifier_column', $colVerifier],
            ['outlet_column', $colOutlet],
        ];
        foreach ($activities as $activity) $meta[] = ['activity', $activity['name'], $activity['id']];
        $w->addSheet(self::META_SHEET, $meta, 'veryHidden');

        $tmp = tempnam(sys_get_temp_dir(), 'bulk') . '.xlsx';
        $w->save($tmp);

        $fileName = Str::slug($project->project_name . ' ' . optional($pt->getTemplate)->template_name, '_') . '_bulk_assign.xlsx';
        return response()->download($tmp, $fileName)->deleteFileAfterSend(true);
    }

    public function upload(Request $request)
    {
        $this->denyAgency();
        $request->validate(['sheet' => 'required|file|max:102400']);
        $path = $request->file('sheet')->getRealPath();

        try {
            $sheetNames = XlsxStream::sheetNames($path);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'The file could not be read. Please upload the .xlsx sheet downloaded from this page.'], 422);
        }
        if (!in_array(self::SHEET, $sheetNames) || !in_array(self::META_SHEET, $sheetNames)) {
            return response()->json(['message' => 'This is not a Bulk Assign sheet. Download the sheet from this page, fill it in and upload it.'], 422);
        }

        // ── Sheet settings (written at download) ─────────────────────────────
        $meta = [];
        $activityIdByName = [];
        XlsxStream::readColumns($path, self::META_SHEET, ['A', 'B', 'C'], function ($row, $v) use (&$meta, &$activityIdByName) {
            if ($v['A'] === 'activity') {
                $activityIdByName[mb_strtolower(trim($v['B']))] = (int) $v['C'];
            } elseif ($v['A'] !== '') {
                $meta[$v['A']] = $v['B'];
            }
        });
        if (($meta['format'] ?? '') === 'bulk_assign_v1') {
            return response()->json(['message' => 'This sheet is from the older version that used emails. Please download a fresh sheet and enter mobile numbers.'], 422);
        }
        if (($meta['format'] ?? '') !== self::FORMAT) {
            return response()->json(['message' => 'This sheet was not generated by Bulk Assign. Please download a fresh sheet.'], 422);
        }

        $pt = ProjectTemplate::with('getTemplate')->find((int) ($meta['project_template_id'] ?? 0));
        $project = $pt ? Project::open()->find($pt->project_id) : null;
        if (!$pt || !$project) {
            return response()->json(['message' => 'The project or template in this sheet no longer exists or the project is completed.'], 422);
        }

        $cols = [
            'row'      => $meta['row_id_column'] ?? 'A',
            'activity' => $meta['activity_column'] ?? '',
            'auditor'  => $meta['auditor_column'] ?? '',
            'verifier' => $meta['verifier_column'] ?? '',
            'outlet'   => $meta['outlet_column'] ?? '',   // only on parent templates with outlets
        ];
        // Sheets downloaded before the per-line column carried one setting for the whole sheet
        $defaultOutlet = (int) ($meta['is_outlet_assigned'] ?? 0);

        // ── Lookups ───────────────────────────────────────────────────────────
        $templateRowIds = DB::table('project_template_name_values_new')->where('project_template_id', $pt->id)
            ->pluck('id')->flip();
        // A verifier assigns only their own auditors, on the rows (per activity) given to them, as the verifier
        $verifier = $this->verifierId();
        $verifierRows = null;
        if ($verifier) {
            $givenTemplateIds = VerifierAssignment::assignedDataAssigns($verifier)->pluck('project_template_id')->map(fn($id) => (int) $id)->all();
            if (!in_array((int) $pt->id, $givenTemplateIds, true)) {
                return response()->json(['message' => 'This project or template has not been assigned to you.'], 422);
            }
            $verifierRows = $this->verifierRowsByActivity($verifier, $pt)->map(fn($ids) => array_flip($ids));
        }
        $auditorIdByMobile  = User::role('Auditor')->whereNotNull('mobile')
            ->when($verifier, fn($q) => $q->where('verifier_user_id', $verifier))
            ->get(['id', 'mobile'])
            ->mapWithKeys(fn($u) => [self::normalizeMobile((string) $u->mobile) => $u->id]);
        $verifierIdByMobile = User::role('Verifier')->whereNotNull('mobile')->get(['id', 'mobile'])
            ->mapWithKeys(fn($u) => [self::normalizeMobile((string) $u->mobile) => $u->id]);
        $anyUserMobiles = DB::table('users')->whereNotNull('mobile')->pluck('mobile')
            ->mapWithKeys(fn($m) => [self::normalizeMobile((string) $m) => true]);
        // Outlet (child) template: rows whose own template head value matches no parent row
        $parentCheck = \App\Services\OutletDistributorMatch::rowsWithoutParent($pt, $templateRowIds->keys()->all());

        // ── Validate every line before assigning anything ──────────────────────
        $errorLines = [];
        $groups = [];
        $assignedLines = 0;
        $skippedLines = 0;
        $incompleteLines = 0;
        $outletLines = 0;
        $headerOk = null;

        XlsxStream::readColumns($path, self::SHEET, array_values(array_filter($cols)), function ($excelRow, $v) use (
            $cols, $defaultOutlet, $templateRowIds, $activityIdByName, $auditorIdByMobile, $verifierIdByMobile, $anyUserMobiles, $parentCheck,
            $verifier, $verifierRows,
            &$errorLines, &$groups, &$assignedLines, &$skippedLines, &$incompleteLines, &$outletLines, &$headerOk
        ) {
            $rowIdRaw = trim((string) $v[$cols['row']]);
            $activity = trim((string) $v[$cols['activity']]);
            $auditors = trim((string) $v[$cols['auditor']]);
            $verifiers = trim((string) $v[$cols['verifier']]);
            $outletRaw = $cols['outlet'] !== '' ? trim((string) $v[$cols['outlet']]) : '';

            if ($excelRow === 2) {
                $headerOk = strcasecmp($rowIdRaw, 'Row ID') === 0 && strcasecmp($activity, 'Activity') === 0
                    && strcasecmp($auditors, 'Auditor Mobile No') === 0 && strcasecmp($verifiers, 'Verifier Mobile No') === 0
                    && ($cols['outlet'] === '' || strcasecmp($outletRaw, 'Assign Outlets') === 0);
                return;
            }
            if ($excelRow < 3) return;
            // A verifier's line needs only an auditor (the verifier is themselves)
            if ($verifier) {
                if ($auditors === '') {
                    if ($rowIdRaw !== '' || $activity !== '') $skippedLines++;
                    return;
                }
            } else {
                // Admin: a verifier is always needed; with no auditor the line goes to the verifier only
                if ($auditors === '' && $verifiers === '') {
                    if ($rowIdRaw !== '' || $activity !== '') $skippedLines++;
                    return;
                }
                if ($verifiers === '') {
                    $incompleteLines++;
                    return;
                }
            }

            $errors = [];
            $rowId = ctype_digit($rowIdRaw) ? (int) $rowIdRaw : null;
            if ($rowId === null || !$templateRowIds->has($rowId)) {
                $errors[] = $rowIdRaw === '' ? 'Row ID is empty.' : "Row ID \"{$rowIdRaw}\" is not a row of this template.";
            } elseif (array_key_exists($rowId, $parentCheck['missing_rows'])) {
                $errors[] = "No matching parent row: {$parentCheck['own_head']} \"{$parentCheck['missing_rows'][$rowId]}\" matches no {$parentCheck['master_head']} value in the parent template.";
            }
            $activityId = $activityIdByName[mb_strtolower($activity)] ?? null;
            if (!$activityId) {
                $errors[] = $activity === '' ? 'Activity is empty.' : "Activity \"{$activity}\" is not an activity of this template.";
            }

            // One or more mobile numbers, separated by commas (or ; / new lines)
            $resolve = function (string $cell, $idByMobile, string $role) use ($anyUserMobiles, &$errors) {
                $ids = [];
                foreach (preg_split('/[,;\/\n\r]+/', $cell) as $entry) {
                    $entry = trim($entry);
                    if ($entry === '') continue;
                    $mobile = self::normalizeMobile($entry);
                    if (strlen($mobile) !== 10) {
                        $errors[] = "\"{$entry}\" is not a 10-digit mobile number.";
                    } elseif ($idByMobile->has($mobile)) {
                        $ids[] = (int) $idByMobile[$mobile];
                    } elseif ($anyUserMobiles->has($mobile)) {
                        $errors[] = "{$mobile} is not {$role}.";
                    } else {
                        $errors[] = "{$mobile} is not a registered user's mobile number.";
                    }
                }
                $ids = array_values(array_unique($ids));
                sort($ids);
                return $ids;
            };
            $auditorIds  = $resolve($auditors, $auditorIdByMobile, $verifier ? 'one of your auditors' : 'an Auditor');
            $verifierIds = $verifier ? [$verifier] : $resolve($verifiers, $verifierIdByMobile, 'a Verifier');
            if ($verifier && $activityId && $rowId !== null
                && !isset($verifierRows->get($activityId, [])[$rowId])) {
                $errors[] = "Row ID {$rowId} is not assigned to you for \"{$activity}\".";
            }

            $isOutlet = $defaultOutlet;
            if ($cols['outlet'] !== '') {
                $outletValue = mb_strtolower($outletRaw);
                if (!in_array($outletValue, ['', 'yes', 'no'], true)) {
                    $errors[] = "Assign Outlets must be Yes or No (found \"{$outletRaw}\").";
                }
                $isOutlet = $outletValue === 'yes' ? 1 : 0;
            }

            if ($errors) {
                $errorLines[] = [$excelRow, $rowIdRaw, $activity, $auditors, $verifiers, implode(' ', $errors)];
                return;
            }

            $key = $activityId . '|' . implode(',', $auditorIds) . '|' . implode(',', $verifierIds) . '|' . $isOutlet;
            $groups[$key] ??= ['a' => $activityId, 'aud' => $auditorIds, 'ver' => $verifierIds, 'o' => $isOutlet, 'rows' => []];
            $groups[$key]['rows'][$rowId] = $rowId;
            $assignedLines++;
            if ($isOutlet) $outletLines++;
        });

        if ($headerOk !== true) {
            return response()->json(['message' => 'The sheet columns were changed. Please use the sheet as downloaded (do not move or delete the Row ID, Activity, Auditor Mobile No, Verifier Mobile No or Assign Outlets columns).'], 422);
        }

        if ($errorLines) {
            return $this->errorFile($errorLines);
        }
        if (!$groups) {
            return response()->json(['message' => ($verifier
                    ? 'Nothing to assign: fill in Auditor Mobile No on the lines you want to assign.'
                    : 'Nothing to assign: fill in Verifier Mobile No (and Auditor Mobile No, or leave it empty to give the line to the verifier only) on the lines you want to assign.')
                . ($incompleteLines ? " {$incompleteLines} line(s) have an Auditor Mobile No but no Verifier Mobile No." : '')], 422);
        }

        $units = [];
        $verifierOutlet = [];   // verifier id => 1 if any of their lines also assigns outlets
        foreach ($groups as $group) {
            foreach ($group['ver'] as $verifierId) {
                $verifierOutlet[$verifierId] = max($verifierOutlet[$verifierId] ?? 0, $group['o']);
            }
            foreach (array_chunk(array_values($group['rows']), self::UNIT_ROWS) as $chunk) {
                $units[] = ['a' => $group['a'], 'aud' => $group['aud'], 'ver' => $group['ver'], 'o' => $group['o'], 'rows' => $chunk];
            }
        }

        // A verifier's assignments keep the agency / company user of the admin's assignment
        $given = $verifier ? VerifierAssignment::assignedDataAssigns($verifier)->where('project_template_id', $pt->id)->first() : null;

        $token = (string) Str::uuid();
        Storage::disk('local')->put("bulk_assign/{$token}.json", json_encode([
            'user_id'               => Auth::id(),
            'project_template_id'   => $pt->id,
            'template_name_head_id' => (int) ($meta['template_name_head_id'] ?? 0) ?: null,
            'verifier_outlet'       => $verifierOutlet,
            'limit_verifier'        => $verifier,
            'agency_id'             => $given->agency_id ?? null,
            'company_user_id'       => $given->company_user_id ?? null,
            'units'                 => $units,
        ]));

        return response()->json([
            'token'      => $token,
            'units'      => count($units),
            'lines'      => $assignedLines,
            'skipped'    => $skippedLines,
            'incomplete' => $incompleteLines,
            'outlets'    => $outletLines,
            'project'    => $project->project_name,
            'template'   => optional($pt->getTemplate)->template_name,
        ]);
    }

    public function process(Request $request)
    {
        $this->denyAgency();
        $request->validate(['token' => 'required|uuid', 'offset' => 'required|integer|min:0']);

        $file = "bulk_assign/{$request->token}.json";
        if (!Storage::disk('local')->exists($file)) {
            return response()->json(['message' => 'This upload has expired. Please upload the sheet again; lines already assigned are skipped.'], 404);
        }
        $job = json_decode(Storage::disk('local')->get($file), true);
        abort_if((int) $job['user_id'] !== (int) Auth::id(), 403);

        $pt = ProjectTemplate::findOrFail($job['project_template_id']);
        $project = Project::findOrFail($pt->project_id);
        $groupId = $pt->activityType == 1 ? (int) $pt->activity_group_name_id_or_activity_id : null;
        $units = $job['units'];
        $total = count($units);
        $offset = (int) $request->offset;

        @set_time_limit(300);
        if ($offset === 0) {
            foreach ($job['verifier_outlet'] as $verifierId => $withOutlets) {
                DataAssignmentService::ensureTemplateVerifiers([(int) $verifierId], $pt, (int) $withOutlets === 1);
            }
        }

        $started = microtime(true);
        $i = $offset;
        while ($i < $total && ($i === $offset || microtime(true) - $started < self::BATCH_SECS)) {
            $unit = $units[$i];
            $isOutlet = (int) $unit['o'];
            $dataAssign = DataAssignmentService::findOrCreateDataAssign(
                $project, (int) $unit['a'], $groupId, (int) $pt->template_name_id, $pt->id,
                $job['template_name_head_id'], $isOutlet, $job['company_user_id'] ?? null, $job['agency_id'] ?? null
            );
            VerifierAssignment::recordDataAssign($unit['ver'], $dataAssign->id, (int) $job['user_id']);

            if (empty($unit['aud'])) {
                // No auditor: the verifiers get the rows and assign their own auditors later
                DataAssignmentService::assignToVerifiersOnly($unit['ver'], $pt, (int) $unit['a'], $unit['rows'], $isOutlet === 1);
            }
            foreach ($unit['aud'] as $auditorId) {
                DataAssignmentService::assignRows($dataAssign->id, $auditorId, $pt->id, $unit['a'], $groupId, $unit['rows'], $unit['ver']);
                if ($isOutlet === 1) {
                    DataAssignmentService::assignOutletChildren($dataAssign->id, $auditorId, $pt, $unit['rows'], $groupId, $unit['ver'], $job['limit_verifier'] ?? null);
                }
            }
            $i++;
        }

        $done = $i >= $total;
        if ($done) Storage::disk('local')->delete($file);

        return response()->json(['processed' => $i, 'total' => $total, 'done' => $done]);
    }

    /** Logged-in Verifier's id (they bulk-assign their own auditors), null for other roles. */
    private function verifierId(): ?int
    {
        return Auth::user()->hasRole('Verifier') ? (int) Auth::id() : null;
    }

    /** Rows of the template given to a verifier, per assigned activity: [activity_id => [row ids]] */
    private function verifierRowsByActivity(int $verifierId, ProjectTemplate $pt): Collection
    {
        return VerifierAssignment::assignedDataAssigns($verifierId)
            ->where('project_template_id', $pt->id)
            ->pluck('activity_id')->map(fn($id) => (int) $id)->unique()
            ->mapWithKeys(fn($activityId) => [$activityId => VerifierAssignment::ownRowIds($verifierId, (int) $pt->id, $activityId)]);
    }

    /** Activities of a project template in order: [['id' => int, 'name' => string], ...] (a Verifier's: only theirs) */
    private function activitiesFor(ProjectTemplate $pt): Collection
    {
        $ref = $pt->activity_group_name_id_or_activity_id;
        if (!$ref) return collect();

        $ids = $pt->activityType == 1
            ? ActivityGroupPivot::where('activity_group_id', $ref)->orderBy('sequence')->pluck('activity_id')->all()
            : [(int) $ref];
        if ($verifier = $this->verifierId()) {
            $given = VerifierAssignment::assignedDataAssigns($verifier)->where('project_template_id', $pt->id)
                ->pluck('activity_id')->map(fn($id) => (int) $id)->all();
            $ids = array_values(array_filter($ids, fn($id) => in_array((int) $id, $given, true)));
        }
        $names = Activity::whereIn('id', $ids)->pluck('activity_name', 'id');

        // Names identify the activity in the sheet, so keep them unique
        $seen = [];
        return collect($ids)->filter(fn($id) => $names->has($id))->map(function ($id) use ($names, &$seen) {
            $name = trim($names[$id]);
            $key = mb_strtolower($name);
            if (isset($seen[$key])) $name .= " (#{$id})";
            $seen[mb_strtolower($name)] = true;
            return ['id' => (int) $id, 'name' => $name];
        })->values();
    }

    private function errorFile(array $errorLines)
    {
        $w = new XlsxStream();
        $w->beginSheet('Errors', [12, 12, 34, 36, 36, 80], 1);
        $w->row(['Sheet Row', 'Row ID', 'Activity', 'Auditor Mobile No', 'Verifier Mobile No', 'Error'], XlsxStream::STYLE_HEADER);
        foreach ($errorLines as $line) $w->row($line);
        $w->endSheet();

        $tmp = tempnam(sys_get_temp_dir(), 'bulkerr') . '.xlsx';
        $w->save($tmp);

        return response()->download($tmp, 'bulk_assign_errors_' . date('Ymd_His') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Error-Lines' => (string) count($errorLines),
        ])->deleteFileAfterSend(true);
    }

    /**
     * Mobile number as 10 digits: drops spaces/dashes, a +91 / 91 / 0 prefix, and the
     * ".0" or exponent form Excel can give a number typed into a cell.
     */
    private static function normalizeMobile(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d+(\.\d+)?(E\+?\d+)?$/i', $value) && (str_contains($value, '.') || stripos($value, 'e') !== false)) {
            $value = sprintf('%.0f', (float) $value);
        }
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = substr($digits, 1);
        return $digits;
    }

    private function denyAgency(): void
    {
        abort_if(Auth::user()->getRoleNames()->first() === 'Agency', 403, 'Bulk Assign is not available for agency users.');
    }
}
