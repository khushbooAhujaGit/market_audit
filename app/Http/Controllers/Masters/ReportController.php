<?php

namespace App\Http\Controllers\Masters;

use App\Exports\MultiSheetExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityRepeatInstance;
use App\Models\Company;
use App\Models\Verifier;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\Question;
use App\Models\QuestionSubQuestion;
use App\Models\RemarkMaster;
use App\Models\TemplateName;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth")->except(['downloadImagesZip']);
    }
    
    /**
     * Report pages show open projects by default; ?view=completed switches the project
     * dropdown to projects the admin marked completed (projects.is_completed). Applies to
     * every role, Super Admin included.
     */
    private function completionView(Request $request): array
    {
        $view = \App\Services\ProjectCompletion::reportView($request);

        return [$view, fn($query) => $view === 'completed' ? $query->completed() : $query->open()];
    }

    public function index(Request $request)
    {
        // $projects = Project::all();
        // $template_names = TemplateName::all();
        $companies = Company::all();
        $activities = Activity::all();
        $activity_groups = ActivityGroup::all();

        //khushboo 02-04-2025
        $currentuser = User::find(Auth::user()->id);
        $currentUserRole = $currentuser->getRoleNames()->first();
        [$view, $applyCompletionFilter] = $this->completionView($request);

        if (Auth::user()->is_agency_user == 1 || $currentUserRole == 'Agency') {
            // $projects = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            // $projectsIds = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->pluck('id')->toArray();
            // $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)->pluck('template_name_id')->toArray();
            // $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(fn($q) => $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id))
                ->tap($applyCompletionFilter)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        } else if ($currentUserRole == "Super Admin") {
            $template_names = TemplateName::all();
            $projects = Project::query()->tap($applyCompletionFilter)->get();
        } else if ($currentuser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('company_user_id', $currentuser->id);
                })
                ->tap($applyCompletionFilter)
                ->orderBy('id', 'DESC')
                ->get();
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            // dd($project_templates_ids);
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        } else {
            // $template_names = TemplateName::all();
            // $projects = Project::all();
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            // dd($project_templates_ids);
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(fn($q) => $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id))
                ->tap($applyCompletionFilter)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }
        // dd($projects);
        //khushboo 02-04-2025

        return view('masters.reports.index', compact('projects', 'template_names', 'companies', 'activities', 'activity_groups', 'view', 'currentUserRole'));
    }

    public function project_report(Request $request)
    {
        // $projects = Project::all();
        // $template_names = TemplateName::all();
        $companies = Company::all();
        $activities = Activity::all();
        $activity_groups = ActivityGroup::all();

        //khushboo 03-04-2025
        $currentuser = User::find(Auth::user()->id);
        $currentUserRole = $currentuser->getRoleNames()->first();
        [$view, $applyCompletionFilter] = $this->completionView($request);
        // dd($currentUserRole);

        if ($currentuser->is_agency_user == 1 || $currentuser->getRoleNames()->first() == 'Agency') {

            // $projects = Project::where('is_agency_required', 1)
            //     ->where(function ($query) {
            //         $query->where('agency_id', Auth::user()->agency_user_id)
            //             ->orWhere('agency_id', Auth::user()->id);
            //     })
            //     ->get();
            //     // dd($currentuser->id, $projects, Project::where('is_agency_required', 1)->get() );
            // $projectsIds = Project::where('is_agency_required', 1)
            //     ->where(function ($query) {
            //         $query->where('agency_id', Auth::user()->agency_user_id)
            //             ->orWhere('agency_id', Auth::user()->id);
            //     })->pluck('id')->toArray();

            // $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)->pluck('template_name_id')->toArray();
            // $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
            // $my_verifications = DB::table('verifiers')->where('user_id', $currentuser->id)
            // ->with('projectTemplateInfo.getProject')->orderBy('id', 'DESC')->get();
            // dd($my_verifications);

            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(fn($q) => $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id))
                ->tap($applyCompletionFilter)->get();

            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();

        } else if ($currentUserRole == "Super Admin") {
            // dd('ghg');
            $template_names = TemplateName::all();
            $projects = Project::query()->tap($applyCompletionFilter)->get();
        } else if ($currentuser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('company_user_id', $currentuser->id);
                })
                ->tap($applyCompletionFilter)
                ->orderBy('id', 'DESC')
                ->get();
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            // dd($project_templates_ids);
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        } else {
            // dd('fgf');
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            // dd($project_templates_ids);
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::where(fn($q) => $q->whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id))
                ->tap($applyCompletionFilter)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }
        // dd($projects);
        //khushboo 03-04-2025
        // dd($projects, Auth::user()->id, $currentuser->getRoleNames()->first());

        return view('masters.reports.project_report', compact('projects', 'template_names', 'companies', 'activities', 'activity_groups', 'view', 'currentUserRole'));
    }


    

    /**
     * Every answerable question of an activity in form order, at any nesting depth: Multi Response
     * sub-questions (also inside a child Multi Response) and conditional questions (also a
     * conditional of a conditional). Multi Response groups have no answer of their own, so they
     * only show up in the relation of their sub-questions.
     *
     * Each item: ['question' => Question, 'pctx' => parent_context_id the answer is saved with
     * (the Multi Response group for a sub-question, the parent question for a conditional one,
     * null at the top level), 'relation' => path from the top question down to this one, e.g.
     * "Outlet status [Open] → Asset available? [Yes] → Asset photo" ('' for top-level questions),
     * 'legacy' => the one-line header the report used before the relation row ("Parent → child",
     * "Group → sub [value] → conditional"), which the Infiltration and Godrej reports look up].
     */
    private function flattenActivityQuestions($questions): array
    {
        $questions = collect($questions);
        $ids = $questions->pluck('id')->all();

        $subLinks = QuestionSubQuestion::whereIn('parent_question_id', $ids)->orderBy('sequence')->orderBy('id')->with('childQuestion')->get();
        $subLinksByParent = $subLinks->groupBy('parent_question_id');
        $subChildIds = $subLinks->pluck('child_question_id')->flip();
        $condByParent = Question::whereIn('parent_question_id', array_merge($ids, $subLinks->pluck('child_question_id')->all()))
            ->orderBy('question_sequence')->orderBy('id')->get()->groupBy('parent_question_id');

        // Ids whose sub-question links / conditional children were preloaded (even when there are none)
        $linksLoaded = array_flip($ids);
        $condsLoaded = $linksLoaded + $subChildIds->all();

        // A question's name in a relation, without its own question type when the name ends with
        // it ("Asset 1 (Multi Response)", "Cooler checks (child Multi Response)" → "Asset 1", "Cooler checks")
        $relationName = function (Question $question): string {
            $type = preg_quote((string) $question->question_type, '/');
            $name = $type === '' ? $question->question : preg_replace('/\s*\((?:child\s+)?' . $type . '\)\s*$/i', '', $question->question);
            return $name !== '' ? $name : $question->question;
        };

        $items = [];
        $walk = function (Question $question, ?int $pctx, array $path, array $legacy)
            use (&$walk, &$items, $subLinksByParent, $condByParent, $linksLoaded, $condsLoaded, $subChildIds, $relationName) {
            $id = (int) $question->getKey();
            if (isset($path[$id]) || count($path) > 8) return; // a question set up as its own ancestor

            $isGroup = $question->question_type === 'Multi Response';
            if (!$isGroup) {
                $items[] = [
                    'question' => $question,
                    'pctx'     => $pctx,
                    'relation' => $path ? implode(' → ', array_merge($path, [$relationName($question)])) : '',
                    'legacy'   => implode(' → ', array_merge($legacy, [$question->question])),
                ];
            }

            // Child questions of a sub-question that comes from another activity are not preloaded
            $links = isset($linksLoaded[$id])
                ? ($subLinksByParent[$id] ?? collect())
                : QuestionSubQuestion::where('parent_question_id', $id)->orderBy('sequence')->orderBy('id')->with('childQuestion')->get();
            $siblingIds = collect($links)->pluck('child_question_id')->map(fn($v) => (int) $v)->flip();
            foreach ($links as $link) {
                $child = $link->childQuestion;
                // A sub-question that is also a conditional of a sibling sub-question is saved
                // under that sibling: it is listed there instead
                if ($child && !isset($siblingIds[(int) $child->parent_question_id])) {
                    $walk($child, $id, $path + [$id => $relationName($question)], $legacy + [$id => $question->question]);
                }
            }

            $conditionals = isset($condsLoaded[$id])
                ? ($condByParent[$id] ?? [])
                : Question::where('parent_question_id', $id)->orderBy('question_sequence')->orderBy('id')->get();
            foreach ($conditionals as $child) {
                // A Multi Response group has no answer to show a question on; when such a
                // "conditional" is also linked as a sub-question it is listed as that
                if ($isGroup && isset($subChildIds[$child->id])) continue;
                $trigger = in_array((string) $child->parent_value, ['', '__non_empty__'], true) ? 'any answer' : $child->parent_value;
                // The old header named the trigger value only below a sub-question
                $legacyName = $pctx !== null ? $question->question . ' [' . ($child->parent_value ?? 'if answered') . ']' : $question->question;
                $walk($child, $id, $path + [$id => $relationName($question) . ' [' . $trigger . ']'], $legacy + [$id => $legacyName]);
            }
        };

        foreach ($questions as $question) {
            if ($question->parent_question_id === null && !isset($subChildIds[$question->id])) {
                $walk($question, null, [], []);
            }
        }
        return $items;
    }

    /**
     * The answer to show for a question in one context. A sub/conditional question's answer is
     * normally saved with its parent as parent_context_id; the app saves some with no context.
     * $otherContexts are the contexts of the same question's other columns, whose answers must
     * not fill this one.
     */
    private function pickContextAnswer($pool, ?int $pctx, array $otherContexts = [])
    {
        $pool = collect($pool);
        if ($pctx === null) {
            return $pool->first(fn($a) => $a->parent_context_id === null) ?? $pool->first();
        }
        return $pool->first(fn($a) => (int) $a->parent_context_id === $pctx)
            ?? $pool->first(fn($a) => $a->parent_context_id === null)
            ?? $pool->first(fn($a) => !in_array((int) $a->parent_context_id, $otherContexts, true));
    }

    /** Per question id, the contexts its columns use (see pickContextAnswer). */
    private function contextsByQuestion(array $flatItems): array
    {
        $contexts = [];
        foreach ($flatItems as $item) {
            if ($item['pctx'] !== null) $contexts[(int) $item['question']->getKey()][] = $item['pctx'];
        }
        return $contexts;
    }

    /**
     * Filter an answer collection to just the ones belonging to one instance "group", as
     * produced by buildReportFile()'s $allGroupIds: either a new-style activity_sequence
     * (int, 0 = original) or an old-style same_answer_id group (['sid' => id]).
     */
    /** "Open Location" column after latitude/longitude. Gated by .env OPEN_LOCATION_COLUMN. */
    private function openLocationEnabled(): bool
    {
        return filter_var(env('OPEN_LOCATION_COLUMN', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** Show "City, State" as the Open Location text (off for the copies the Infiltration/Godrej tools read). */
    private bool $mapsCityLabels = false;

    /**
     * Google Maps link for a lat/long pair ('' when missing); the Excel writer makes it clickable.
     * With $mapsCityLabels the cell shows "City, State" (CityState, cached; at most
     * OPEN_LOCATION_LOOKUPS_PER_REPORT new spots looked up per report) and "Open Location" until it is known.
     */
    private function mapsLink($lat, $lng): string
    {
        if (!is_numeric($lat) || !is_numeric($lng) || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return '';
        }
        $url = 'https://www.google.com/maps?q=' . trim((string) $lat) . ',' . trim((string) $lng);
        if (!$this->mapsCityLabels) {
            return $url;
        }
        $label = \App\Services\CityState::label($lat, $lng, (int) env('OPEN_LOCATION_LOOKUPS_PER_REPORT', 60));
        return \App\Services\FastXlsxWriter::mapsValue($url, $label !== '' ? $label : 'Open Location');
    }

    /**
     * Verifier remark text for an answer. Answers normally store a remark id (remark_masters);
     * older ones may hold the typed text, or the bare word "other" (saved when "Other Remark"
     * was chosen without text), which has no remark to show.
     */

    private function verificationRemarkText($answer, $remarkMap = null): string
    {
        $remark = trim((string) ($answer->remark ?? ''));
        if ($remark === '' || strcasecmp($remark, 'other') === 0) return '';
        if (!ctype_digit($remark)) return $remark;

        $info = $remarkMap !== null ? ($remarkMap[$remark] ?? null) : $answer->get_remark_info;
        return $info ? (string) $info->remark : $remark . ' is deleted';
    }

    private function filterAnswersByInstanceGroup(iterable $items, $group)
    {
        return collect($items)->filter(
            fn($a) => is_array($group)
                ? $a->same_answer_id == $group['sid']
                : ((int)$group > 0
                    ? (int)($a->activity_sequence ?? 0) === (int)$group
                    : (int)($a->activity_sequence ?? 0) === 0)
        );
    }

    /**
     * Shared report builder — called by both get_report() (direct download) and GenerateReportJob (mail).
     * Returns ['path' => <temp xlsx file path>, 'file_name' => <suggested download name>].
     */
    /**
     * $withRelationRow: a second header row with each sub/conditional question's relation (data
     * starts on row 3), and the report header block above them. Off for the tools that read this
     * report (Infiltration, Godrej): they read row 1 as the headers and every row after it as data,
     * and look columns up by the old one-line headers ("Parent → child"), which they then get.
     */
    public function buildReportFile(Request $request, array $validated, bool $withRelationRow = true): array
    {
        $start_date = $validated['from_date'];
        $end_date   = $validated['to_date'];
        $this->mapsCityLabels = $withRelationRow;
        \App\Services\CityState::resetLookups();
        $additionalHeaders = [];
        $show_auditor = 0;
        $show_user = 0;
        $show_date = 0;
        $show_time = 0;

        if ($request->has('auditor_details')) {
            $additionalVerifierHeaders = ['Auditor Name', 'Auditor Contact No.', 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
            $show_auditor = 1;
        } else {
            $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
        }

        // Check if we want details of the user in the report
        if ($request->has('user_details')) {
            $additionalHeaders[] = 'User';
            $show_user = 1;
        }

        // Get the date of the answer submitted
        if ($request->has('date_required')) {
            $additionalHeaders[] = 'Date';
            $show_date = 1;
        }

        // Get the time in the report
        if ($request->has('time_required')) {
            $additionalHeaders[] = 'Time';
            $show_time = 1;
        }

        // Get the project and template data
        $projectTemplate = ProjectTemplate::where('project_id', $validated['project_id'])
            ->where('template_name_id', $validated['template_name_id'])
            ->first();

        $template_heads = $projectTemplate->getTemplate->getTemplateHeads->toArray();
        $projectTemplateHeads = array_column($template_heads, 'template_head_name');
        $templateColCount = count($projectTemplateHeads); // pure template column count (before activity cols)

        // Which specific activities are actually eligible for repeat instances — an empty list
        // means "no restriction, applies to every activity under this add-on template" (matches
        // the same convention used when saving this field from the mapping screen).
        $activityAddOnIds = json_decode($projectTemplate->activity_add_on_activity_ids ?? '[]', true) ?: [];

        // Get all question IDs for the selected activities
        $allQuestionIds = [];
        $questionSets        = []; // question IDs per activity set
        $questionDisplayNames = []; // display names per activity set (same order as $questionSets)
        $scanExpectedHeaders  = []; // question_id => configured expected headers, for Barcode/QR Code/RFID

        // Preload ALL Questions for all activities in one query and index by activity_id
        $allActivityQuestions = \App\Models\Question::whereIn('activity_id', $request->activity_id)
            ->withoutOrphans()
            ->orderBy('question_sequence')
            ->get()
            ->groupBy('activity_id');

        // One column per answerable question at any depth (see flattenActivityQuestions). Keys:
        // "{qid}" at the top level, "{qid}|pctx|{parent}" for a sub/conditional question, plus
        // "|scanhead|{header}" / "|scanextra" for the columns of a Barcode/QR Code/RFID question.
        $columnInfo    = []; // per activity set: key => ['qid', 'pctx', 'scan' => null|'head'|'extra', 'scan_header', 'relation']
        $relationHeads = array_fill(0, $templateColCount, ''); // second header row, parallel to $projectTemplateHeads
        $contextsByQ   = []; // question id => contexts of its columns (see pickContextAnswer)

        $addColumn = function (int $activityIndex, string $key, array $info, string $name, string $relation, string $legacy)
            use (&$projectTemplateHeads, &$relationHeads, &$questionSets, &$questionDisplayNames, &$columnInfo, &$allQuestionIds, $withRelationRow) {
            // Without the relation row the header is the old one-line header (parents joined in)
            $header = $withRelationRow ? $name : $legacy;
            $projectTemplateHeads[] = $header;
            $relationHeads[]        = $relation;
            $questionSets[$activityIndex][]              = $key;
            $questionDisplayNames[$activityIndex][$key]  = $header;
            $columnInfo[$activityIndex][$key]            = $info + ['relation' => $relation];
            $allQuestionIds[] = $info['qid'];
        };

        foreach ($request->activity_id as $activityIndex => $activity) {
            $questionSets[$activityIndex]        = [];
            $questionDisplayNames[$activityIndex] = [];
            $columnInfo[$activityIndex]           = [];

            // Metadata columns lead each activity's block (the data rows fill them for every activity)
            $metaHeads = $show_auditor == 1 ? ['Auditor Name', 'Auditor Contact No.'] : [];
            array_push($metaHeads, 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude');
            if ($this->openLocationEnabled()) $metaHeads[] = 'Open Location';
            if ($show_user) $metaHeads[] = 'User';
            if ($show_date || $show_time) $metaHeads[] = 'Date';
            foreach ($metaHeads as $metaHead) {
                $projectTemplateHeads[] = $metaHead;
                $relationHeads[]        = '';
            }

            $flatItems = $this->flattenActivityQuestions($allActivityQuestions[(int) $activity] ?? collect());
            foreach ($this->contextsByQuestion($flatItems) as $qid => $contexts) {
                $contextsByQ[$qid] = array_values(array_unique(array_merge($contextsByQ[$qid] ?? [], $contexts)));
            }

            foreach ($flatItems as $item) {
                $question = $item['question'];
                $qid      = (int) $question->getKey();
                $base     = $item['pctx'] === null ? (string) $qid : $qid . '|pctx|' . $item['pctx'];
                $info     = ['qid' => $qid, 'pctx' => $item['pctx'], 'scan' => null, 'scan_header' => null];

                if (in_array($question->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                    // Scan question: one column per configured expected header (matched scan data)
                    // + one column for anything scanned that matches none of them
                    $scanHeaders = $scanExpectedHeaders[$qid] ??= \App\Models\QuestionDropdown::where('question_id', $qid)
                        ->pluck('option')->map('trim')->filter()->values()->all();
                    foreach ($scanHeaders as $scanHeader) {
                        $addColumn($activityIndex, $base . '|scanhead|' . $scanHeader, ['scan' => 'head', 'scan_header' => $scanHeader] + $info,
                            $question->question . ' → ' . $scanHeader, $item['relation'] !== '' ? $item['relation'] . ' → ' . $scanHeader : '',
                            $item['legacy'] . ' → ' . $scanHeader);
                    }
                    $addColumn($activityIndex, $base . '|scanextra', ['scan' => 'extra'] + $info, $question->question, $item['relation'], $item['legacy']);
                } else {
                    $addColumn($activityIndex, $base, $info, $question->question, $item['relation'], $item['legacy']);
                }
            }
        }

        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';
        $projectTemplateData = $projectTemplate->getProjectTemplateData;
        $projectTemplateRowIds = $projectTemplate->getProjectTemplateData->pluck('id')->unique()->values()->toArray();
        $activityIds = $request->activity_id;

        // Main sheet: single activity block (original/first instance only — no horizontal repeat).
        // Row 1 = question names, row 2 = how each sub/conditional question hangs from its parents.
        $header_data = [$projectTemplateHeads];
        if ($withRelationRow) {
            $header_data[] = $relationHeads;
        }

        // Eager load all answers
        // Load answers — filter by date range and optionally by verification status.
        // Default: ALL statuses included (pending=0, rejected=4, verified=5).
        $verificationStatusFilter = $request->input('verification_status_filter', 'all');
        $statusMap = ['pending' => [0], 'verified' => [5], 'rejected' => [4]];

        // Load answers — NO eager loading. We fetch users/question-types separately as flat maps.
        $allAnswersRaw = TempUserActivityAnswersData::whereIn('row_id', $projectTemplateRowIds)
            ->whereIn('question_id', $allQuestionIds)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->when(isset($statusMap[$verificationStatusFilter]),
                fn($q) => $q->whereIn('status', $statusMap[$verificationStatusFilter])
            )
            ->select(['id','row_id','question_id','activity_id','user_id','user_answer',
                      'same_answer_id','parent_context_id','activity_sequence','status','verified_by',
                      'remark','updated_at','created_at','latitude','longitude'])
            ->get();

        $allAnswers   = $allAnswersRaw->groupBy('row_id');
        $filledRowIds = $allAnswers->keys()->toArray();

        // Question type map — one query, used instead of $ans->getQuestionInfo->question_type
        $questionTypeMap = \App\Models\Question::whereIn('id', $allQuestionIds)
            ->pluck('question_type', 'id');

        // User map — one query, used instead of $ans->getUser->name
        $userIds  = $allAnswersRaw->pluck('user_id')->filter()->unique()->toArray();
        $allUsers = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Media fallback collection (no date filter, Image/File/Audio only)
        $mediaQuestionIds = $questionTypeMap->filter(
            fn($t) => in_array($t, ['Image', 'File Upload', 'Audio'])
        )->keys()->toArray();

        $allAnswersNoFilter = !empty($mediaQuestionIds) && !empty($filledRowIds)
            ? TempUserActivityAnswersData::whereIn('row_id', $filledRowIds)
                ->whereIn('question_id', $mediaQuestionIds)
                ->select(['id','row_id','question_id','activity_id','user_id','user_answer',
                          'same_answer_id','parent_context_id','activity_sequence','status','verified_by',
                          'remark','updated_at','created_at','latitude','longitude'])
                ->orderByDesc('updated_at')
                ->get()
                ->groupBy('row_id')
            : collect();

        // Preload pctx answers (sub/conditional question answers, any context) — replaces
        // per-row-per-question DB queries for "{child_id}|pctx|{parent_id}" columns. Also loads the
        // parent ids: a save-path bug (fixed separately) stored some Multi Response sub-answers with
        // question_id and parent_context_id swapped, so those rows only turn up under the parent's id.
        $pctxQuestionIds = [];
        foreach ($columnInfo as $setColumns) {
            foreach ($setColumns as $col) {
                if ($col['pctx'] !== null) {
                    $pctxQuestionIds[$col['qid']] = true;
                    $pctxQuestionIds[$col['pctx']] = true;
                }
            }
        }
        $pctxQuestionIds = array_keys($pctxQuestionIds);

        $allPctxAnswers = !empty($pctxQuestionIds)
            ? TempUserActivityAnswersData::whereIn('row_id', $projectTemplateRowIds)
                ->whereIn('question_id', $pctxQuestionIds)
                ->whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->when(isset($statusMap[$verificationStatusFilter]),
                    fn($q) => $q->whereIn('status', $statusMap[$verificationStatusFilter])
                )
                ->select(['id','row_id','question_id','activity_id','user_id','user_answer',
                          'same_answer_id','parent_context_id','activity_sequence','status','verified_by',
                          'remark','updated_at','created_at','latitude','longitude'])
                ->get()
                // Index: [row_id][question_id] = answers (every context)
                ->groupBy('row_id')
                ->map(fn($g) => $g->groupBy('question_id'))
            : collect();

        // One sub/conditional answer from a row's pctx answers, limited to one instance group
        $pickPctxAnswer = function ($rowPctx, array $col, $gid) use ($contextsByQ) {
            $qid = $col['qid'];
            return $this->pickContextAnswer(
                    $this->filterAnswersByInstanceGroup($rowPctx[$qid] ?? [], $gid), $col['pctx'], $contextsByQ[$qid] ?? [])
                // Swapped storage (question_id = parent, parent_context_id = this question)
                ?? $this->filterAnswersByInstanceGroup($rowPctx[$col['pctx']] ?? [], $gid)
                    ->first(fn($a) => (int) $a->parent_context_id === $qid);
        };

        // Preload distinct activity_sequence values (new-style repeat instances) per row+activity —
        // this is the actual mechanism the app currently uses for multiple instances (sequence 0 =
        // original, sequence > 0 = a named repeat instance created via ActivityRepeatInstance).
        $rawSeqRows = DB::table('temp_user_activity_answers_data')
            ->whereIn('row_id', $projectTemplateRowIds)
            ->whereIn('activity_id', $activityIds)
            ->where('activity_sequence', '>', 0)
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->when(isset($statusMap[$verificationStatusFilter]),
                fn($q) => $q->whereIn('status', $statusMap[$verificationStatusFilter])
            )
            ->select('row_id', 'activity_id', 'activity_sequence')
            ->distinct()
            ->get();

        $seqsByRowAct = [];
        foreach ($rawSeqRows as $r) {
            $seqsByRowAct[$r->row_id][(int)$r->activity_id][] = (int)$r->activity_sequence;
        }

        // Preload same_answer_id groups with q_count — fallback for old-style data that predates
        // activity_sequence-based instances (each "visit" grouped by a shared same_answer_id).
        $rawGroupRows = DB::table('temp_user_activity_answers_data')
            ->whereIn('row_id', $projectTemplateRowIds)
            ->whereIn('activity_id', $activityIds)
            ->whereNotNull('same_answer_id')
            ->whereDate('created_at', '>=', $start_date)
            ->whereDate('created_at', '<=', $end_date)
            ->when(isset($statusMap[$verificationStatusFilter]),
                fn($q) => $q->whereIn('status', $statusMap[$verificationStatusFilter])
            )
            ->groupBy('row_id', 'activity_id', 'same_answer_id')
            ->select('row_id', 'activity_id', 'same_answer_id',
                     DB::raw('COUNT(DISTINCT question_id) as q_count'))
            ->orderBy('same_answer_id')
            ->get();

        $rawGroupsByRowAct = [];
        foreach ($rawGroupRows as $g) {
            $rawGroupsByRowAct[$g->row_id][(int)$g->activity_id][] = $g;
        }

        // Build $allGroupIds: prefer activity_sequence-based groups (new-style multi-instance) —
        // represented as ints, 0 = original. Only fall back to same_answer_id-based groups
        // (represented as ['sid' => id]) when no activity_sequence data exists at all.
        // [0] = single-instance sentinel (original only, no separate repeat instances).
        $rowActPairs = [];
        foreach ($seqsByRowAct as $rowId => $m) {
            foreach ($m as $actId => $v) { $rowActPairs[$rowId][$actId] = true; }
        }
        foreach ($rawGroupsByRowAct as $rowId => $m) {
            foreach ($m as $actId => $v) { $rowActPairs[$rowId][$actId] = true; }
        }

        $allGroupIds = [];
        foreach ($rowActPairs as $rowId => $actMap) {
            foreach (array_keys($actMap) as $actId) {
                $newSeqs = $seqsByRowAct[$rowId][$actId] ?? [];

                if (!empty($newSeqs)) {
                    $allGroupIds[$rowId][$actId] = array_merge(
                        [0], collect($newSeqs)->unique()->sort()->values()->toArray()
                    );
                    continue;
                }

                $groups = $rawGroupsByRowAct[$rowId][$actId] ?? [];
                $groupCount = count($groups);
                // Old-style detection: each question gets its own same_answer_id (q_count=1 for
                // most groups) → majority rule: if >50% of groups are singletons → new-style
                // per-question saves with no real repeat instances, collapse to original only.
                $singletonCount = collect($groups)->filter(fn($g) => $g->q_count <= 1)->count();

                if ($groupCount > 1 && $groupCount > 0 && ($singletonCount / $groupCount) > 0.5) {
                    $allGroupIds[$rowId][$actId] = [0];
                } else {
                    $allGroupIds[$rowId][$actId] = collect($groups)->pluck('same_answer_id')
                        ->map(fn($id) => ['sid' => $id])->toArray();
                }
            }
        }

        $verifierIds = $allAnswersRaw->pluck('verified_by')->filter()->unique()->toArray();
        $verifiers   = User::whereIn('id', $verifierIds)->get()->keyBy('id');
        $remarkIds   = $allAnswersRaw->pluck('remark')->filter()->unique()->toArray();
        $remarks     = RemarkMaster::whereIn('id', $remarkIds)->get()->keyBy('id');

        // Activity name map — loaded in one query, not via find() in a loop.
        $activityNameMap = [];
        $activityObjs = Activity::whereIn('id', $activityIds)->get()->keyBy('id');
        foreach ($activityIds as $idx => $actId) {
            $actObj = $activityObjs[(int)$actId] ?? null;
            $activityNameMap[$idx] = $actObj ? $actObj->activity_name : ('Activity ' . ($idx + 1));
        }

        // Instance summary data collected per activity, across ALL rows — one shared sheet per
        // activity rather than one sheet per (row, activity) pair. Each row's main/sub header
        // values are carried as the first two columns of its own rows so the sheet still shows
        // which distributor/outlet each instance belongs to.
        $activitySheetsAccum = [];

        // File columns (Template Documents): a link to the stored file instead of its name
        $documentCells = \App\Services\TemplateDocuments::reportCells((int) $projectTemplate->id, $projectTemplateData);
        $templateHeadIds = array_column($template_heads, 'id');

        foreach ($projectTemplateData as $projectTemplate_data) {
            // Template columns in header order, by header id (a row's JSON keys can be in any order)
            $rowData = [];
            $get_json_data = json_decode($projectTemplate_data->template_data_json, true) ?: [];
            foreach ($templateHeadIds as $headId) {
                $value = $documentCells[$projectTemplate_data->id][$headId] ?? ($get_json_data[$headId] ?? '');
                $rowData[] = is_scalar($value) || $value === null ? $value : json_encode($value);
            }

            $rowAnswers = $allAnswers[$projectTemplate_data->id] ?? collect();

            // Per-activity ordered same_answer_id lists — from preloaded map (no per-row DB queries).
            $perActGroupIds = [];
            foreach ($activityIds as $actId) {
                $perActGroupIds[(int)$actId] = $allGroupIds[$projectTemplate_data->id][(int)$actId] ?? [];
            }

            $any_answer = $rowAnswers->isNotEmpty() ? 1 : 0;

            // Build row-level indexes ONCE per row (not once per activity per row)
            $rowByQ           = $rowAnswers->groupBy('question_id');
            $noFilterRowAll   = $allAnswersNoFilter[$projectTemplate_data->id] ?? collect();
            $rowPctx          = $allPctxAnswers[$projectTemplate_data->id] ?? collect();

            // Main sheet: fill ORIGINAL (first) instance only for each activity
            foreach ($questionSets as $setIndex => $questionSet) {
                $actId = (int)($activityIds[$setIndex] ?? $activityIds[0]);
                $gid   = $perActGroupIds[$actId][0] ?? 0;

                $actAnswersForRow = $rowAnswers->where('activity_id', $actId);
                $visitAnswers = $this->filterAnswersByInstanceGroup($actAnswersForRow, $gid);
                if ($visitAnswers->isEmpty()) {
                    // Legacy data with neither activity_sequence nor same_answer_id set
                    $visitAnswers = $actAnswersForRow->isNotEmpty() ? $actAnswersForRow : $rowAnswers;
                }

                $visitByQ    = $visitAnswers->groupBy('question_id');
                $noFilterRow = $noFilterRowAll->where('activity_id', $actId)->groupBy('question_id');

                $setAnswers = [];
                foreach ($questionSet as $key) {
                    $col = $columnInfo[$setIndex][$key];
                    $qid = $col['qid'];

                    if ($col['pctx'] !== null) {
                        $ans = $pickPctxAnswer($rowPctx, $col, $gid);
                    } else {
                        $ans = $this->pickContextAnswer($visitByQ[$qid] ?? [], null)
                            ?? $this->pickContextAnswer($rowByQ[$qid] ?? [], null);

                        // Fallback: preloaded no-date-filter collection for media questions
                        if (!$ans) {
                            $ans = $this->pickContextAnswer(collect($noFilterRow[$qid] ?? [])->sortByDesc('updated_at'), null);
                        }
                    }

                    if ($ans) { $setAnswers[$key] = $ans; $any_answer = 1; }
                }

                $answersInSet = collect($setAnswers);
                if ($answersInSet->isNotEmpty()) {
                    // Verifier/location details from the first top-level answer (any answer when
                    // the activity has only sub-questions)
                    $firstAnswer = $answersInSet->first(fn($a, $key) => $columnInfo[$setIndex][$key]['pctx'] === null)
                        ?? $answersInSet->first();
                    $firstUser   = $allUsers[$firstAnswer->user_id] ?? null;
                    if ($show_auditor == 1) {
                        $rowData[] = $firstUser->name   ?? '';
                        $rowData[] = $firstUser->mobile ?? '';
                    }
                    $rowData[] = $firstAnswer->verified_by
                        ? ($verifiers[$firstAnswer->verified_by]?->name ?? "User (ID: {$firstAnswer->verified_by}) has been deleted")
                        : '';
                    $rowData[] = $firstAnswer->status == 5 ? 'Verified' : ($firstAnswer->status == 4 ? 'Rejected' : '');
                    $rowData[] = $this->verificationRemarkText($firstAnswer, $remarks);
                    $rowData[] = in_array($firstAnswer->status, [4, 5]) && $firstAnswer->updated_at
                        ? $firstAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') : '';
                    $rowData[] = $firstAnswer->latitude  ?? '';
                    $rowData[] = $firstAnswer->longitude ?? '';
                    if ($this->openLocationEnabled()) { $rowData[] = $this->mapsLink($firstAnswer->latitude ?? '', $firstAnswer->longitude ?? ''); }
                    if ($show_user) {
                        $rowData[] = $firstUser ? $firstUser->name : "User (ID: {$firstAnswer->user_id}) has been deleted";
                    }
                    if ($show_date || $show_time) {
                        $rowData[] = $firstAnswer->created_at
                            ? $firstAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i')
                            : '';
                    }
                } else {
                    if ($show_auditor == 1) { $rowData[] = ''; $rowData[] = ''; }
                    $rowData[] = ''; $rowData[] = ''; $rowData[] = '';
                    $rowData[] = ''; $rowData[] = ''; $rowData[] = '';
                    if ($this->openLocationEnabled()) { $rowData[] = ''; }
                    if ($show_user) { $rowData[] = ''; }
                    if ($show_date || $show_time) { $rowData[] = ''; }
                }

                foreach ($questionSet as $key) {
                    $rowData[] = isset($setAnswers[$key])
                        ? $this->reportAnswerCell($setAnswers[$key], $columnInfo[$setIndex][$key], $questionTypeMap, $scanExpectedHeaders)
                        : '';
                }
            }

            // Determine if this row has multiple instances (needed before main-sheet push)
            $isAddOn  = $projectTemplate->activity_add_on == 1;
            $hasMulti = false;
            foreach ($activityIds as $actId) {
                if (count($perActGroupIds[(int)$actId]) > 1) { $hasMulti = true; break; }
            }

            // Always include every distributor on the main sheet (first/original instance).
            // Distributors with multiple instances also get their own per-instance sheet below.
            if ($request->data_get_helper == "all") {
                $header_data[] = $rowData;
            } elseif ($request->data_get_helper == "filled") {
                if ($any_answer == 1) $header_data[] = $rowData;
            } else {
                if ($any_answer == 0) $header_data[] = $rowData;
            }

            // Only build the per-instance sheet for add-on activities (single or multiple instances)
            if (!$isAddOn) continue;

            $jsonArr = json_decode($projectTemplate_data->template_data_json, true);
            $mainVal = $jsonArr[$projectTemplate->main_header] ?? '';
            $subVal  = $jsonArr[$projectTemplate->sub_header]  ?? '';
            $auditDateForSheet = '';

            foreach ($questionSets as $setIndex => $questionSet) {
                $actId   = (int)($activityIds[$setIndex] ?? $activityIds[0]);

                // Skip activities not actually eligible for repeat instances — $isAddOn above is
                // template-level only, so without this check every activity in the template got
                // its own instance sheet regardless of whether it was ever marked add-on-eligible.
                if (!empty($activityAddOnIds) && !in_array($actId, array_map('intval', $activityAddOnIds))) continue;

                $groupIds = $perActGroupIds[$actId];
                if (empty($groupIds)) continue;

                // Precompute no-filter index once per activity (not per instance)
                $noFilterActAnswers = ($allAnswersNoFilter[$projectTemplate_data->id] ?? collect())
                    ->where('activity_id', $actId);
                // Group by question_id for O(1) lookup
                $noFilterByQ = $noFilterActAnswers->groupBy('question_id');

                $actInstRows = [];
                foreach ($groupIds as $instIdx => $gid) {
                    $instAnswers = $this->filterAnswersByInstanceGroup(
                        $rowAnswers->where('activity_id', $actId), $gid
                    );
                    // Index by question_id for O(1) lookups
                    $instByQ = $instAnswers->groupBy('question_id');

                    $instRow = [$mainVal, $subVal, $activityNameMap[$setIndex], $instIdx + 1];

                    $instVerifierName = '';
                    $instStatus       = '';
                    $instRemark       = '';
                    $instVerifDT      = '';
                    $instLatitude     = '';
                    $instLongitude    = '';
                    $instUser         = '';
                    $instDate         = '';

                    foreach ($questionSet as $key) {
                        $col = $columnInfo[$setIndex][$key];
                        $qid = $col['qid'];

                        if ($col['pctx'] !== null) {
                            // Sub/conditional question: this instance's answer in its context, else
                            // a media answer uploaded before the date range (same instance only)
                            $ans = $pickPctxAnswer($rowPctx, $col, $gid)
                                ?? $this->pickContextAnswer(
                                    $this->filterAnswersByInstanceGroup($noFilterByQ[$qid] ?? collect(), $gid)->sortByDesc('updated_at'),
                                    $col['pctx'], $contextsByQ[$qid] ?? []);
                        } else {
                            $ans = $this->pickContextAnswer($instByQ[$qid] ?? [], null);

                            // Fallback: preloaded no-date-filter collection (image uploaded before range).
                            // Must still be scoped to this instance group — otherwise a different
                            // instance's most-recently-updated media answer could bleed into this row.
                            if (!$ans) {
                                $ans = $this->pickContextAnswer(
                                    $this->filterAnswersByInstanceGroup($noFilterByQ[$qid] ?? collect(), $gid)->sortByDesc('updated_at'), null);
                            }
                        }

                        if ($ans) {
                            if (!$auditDateForSheet && $ans->created_at) {
                                $auditDateForSheet = $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y');
                            }
                            if (!$instUser && isset($ans->user_id)) {
                                $u = $allUsers[$ans->user_id] ?? null;
                                $instUser = $u ? $u->name : '';
                                $instDate = $ans->created_at ? $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') : '';
                            }
                            if (!$instLatitude  && $ans->latitude)  { $instLatitude  = $ans->latitude; }
                            if (!$instLongitude && $ans->longitude)  { $instLongitude = $ans->longitude; }
                            if (!$instStatus) {
                                $instStatus = $ans->status == 5 ? 'Verified' : ($ans->status == 4 ? 'Rejected' : '');
                            }
                            if (!$instVerifierName && $ans->verified_by) {
                                $vd = $verifiers[$ans->verified_by] ?? null;
                                $instVerifierName = $vd ? $vd->name : "User (ID: {$ans->verified_by}) has been deleted";
                            }
                            if (!$instRemark && $ans->remark) {
                                $instRemark = $this->verificationRemarkText($ans, $remarks);
                            }
                            if (!$instVerifDT && in_array($ans->status, [4, 5])) {
                                $instVerifDT = $ans->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                            }

                            $instRow[] = $this->reportAnswerCell($ans, $col, $questionTypeMap, $scanExpectedHeaders);
                        } else {
                            $instRow[] = '';
                        }
                    }

                    $instRow[] = $instVerifierName;
                    $instRow[] = $instStatus;
                    $instRow[] = $instRemark;
                    $instRow[] = $instVerifDT;
                    $instRow[] = $instLatitude;
                    $instRow[] = $instLongitude;
                    if ($this->openLocationEnabled()) { $instRow[] = $this->mapsLink($instLatitude, $instLongitude); }
                    $instRow[] = $instUser;
                    $instRow[] = $instDate;

                    $actInstRows[] = $instRow;
                }

                if (!empty($actInstRows)) {
                    // Accumulate into the shared per-activity sheet (across all rows), rather than
                    // a fresh sheet per row — appending this row's instances after any already
                    // collected for the same activity from earlier rows.
                    if (!isset($activitySheetsAccum[$actId])) {
                        $activitySheetsAccum[$actId] = [
                            'activityName' => $activityNameMap[$setIndex],
                            'setIndex'     => $setIndex,
                            'questionSet'  => $questionSet,
                            'rows'         => [],
                        ];
                    }
                    array_push($activitySheetsAccum[$actId]['rows'], ...$actInstRows);
                }
            }
        }
 
        $projectName     = $projectTemplate->getProject->project_name;
        $templateName    = $projectTemplate->getTemplate->template_name;
        $mainHeaderLabel = $projectTemplate->getMainHeader?->template_head_name ?? 'Main';
        $subHeaderLabel  = $projectTemplate->getSubHeader?->template_head_name ?? 'Sub';

        // --- Build multi-sheet export ---
        // Sheet 1: main flat-table data (plain, no special styling)
        // Sheet 2+: one instance-summary sheet PER ACTIVITY, shared across every distributor/outlet
        // row that activity appears on — not one sheet per (row, activity) pair. Each row carries
        // its own main/sub header values as its first two columns so rows stay attributable.
        $mainSheetData   = $header_data;
        $extraSheets     = [];

        $usedSheetTitles = [];
        foreach ($activitySheetsAccum as $actData) {
            if (count($actData['rows']) === 0) continue;

            $actLabel = $actData['activityName'];
            $base     = substr($actLabel, 0, 31);

            // Deduplicate sheet titles (different activities truncating to the same 31 chars)
            $sheetTitle = $base;
            if (isset($usedSheetTitles[$base])) {
                $usedSheetTitles[$base]++;
                $suffix     = ' ' . $usedSheetTitles[$base];
                $sheetTitle = substr($base, 0, 31 - strlen($suffix)) . $suffix;
            } else {
                $usedSheetTitles[$base] = 1;
            }

            // FastXlsxWriter hard-codes styling for "extra" sheets by row position: rows 0-5 are
            // always treated as the header block (title/activity-banner/3 meta rows/column-header),
            // row 6+ as plain data — regardless of content. Six rows must precede the data rows or
            // real data rows get styled as headers instead.
            $instSheetRows = [
                [$projectName],
                [$actLabel],
                [$templateName],
                [''],
                [''],
            ];

            $actHeader = [$mainHeaderLabel, $subHeaderLabel, 'Activity Name', 'Sr. No'];
            foreach ($actData['questionSet'] as $qId) {
                $actHeader[] = $questionDisplayNames[$actData['setIndex']][$qId] ?? '';
            }
            $actHeader[] = 'Verifier Name';
            $actHeader[] = 'Status';
            $actHeader[] = 'Remark';
            $actHeader[] = 'Verification Date & Time';
            $actHeader[] = 'latitude';
            $actHeader[] = 'longitude';
            if ($this->openLocationEnabled()) { $actHeader[] = 'Open Location'; }
            $actHeader[] = 'User';
            $actHeader[] = 'Date';

            $instSheetRows[] = $actHeader;
            $relationRows = [];
            if ($withRelationRow) {
                $relationRow = ['', '', '', ''];
                foreach ($actData['questionSet'] as $qId) {
                    $relationRow[] = $columnInfo[$actData['setIndex']][$qId]['relation'] ?? '';
                }
                $relationRows[] = count($instSheetRows);
                $instSheetRows[] = array_pad($relationRow, count($actHeader), '');
            }
            foreach ($actData['rows'] as $r) {
                $instSheetRows[] = $r;
            }

            $extraSheets[] = ['title' => $sheetTitle, 'rows' => $instSheetRows, 'relation_rows' => $relationRows];
        }

        // ── Excel generation via PhpSpreadsheet ───────────────────────────────
        $baseUrl   = rtrim(config('app.url'), '/');
        $mainTitle = substr($projectName . ' ' . $templateName, 0, 31);

        // Report header block + header filters (REPORT_HEADER_ENABLED, default on; set
        // REPORT_HEADER_ENABLED=false in .env, or restore ReportController.php.bak_before_report_header,
        // to get the original layout back). Never for the copy a tool reads (Infiltration, Godrej):
        // those read row 1 as the headers.
        $reportMeta = null;
        if ($withRelationRow && filter_var(env('REPORT_HEADER_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $companyName  = optional(\App\Models\Company::find(optional($projectTemplate->getProject)->company_id))->company_name;
            $activityIds  = array_values(array_filter((array) $request->activity_id));
            $activityMap  = \App\Models\Activity::whereIn('id', $activityIds)->pluck('activity_name', 'id');
            $activityNames = collect($activityIds)->map(fn($id) => $activityMap[$id] ?? null)->filter()->unique()->implode(', ');
            try {
                $fromLabel = \Carbon\Carbon::parse($start_date)->format('d M Y');
                $toLabel   = \Carbon\Carbon::parse($end_date)->format('d M Y');
            } catch (\Throwable $e) {
                $fromLabel = (string) $start_date;
                $toLabel   = (string) $end_date;
            }
            $reportMeta = [
                'company'  => (string) ($companyName ?: $projectName),
                'activity' => (string) $activityNames,
                'period'   => $fromLabel . ' - ' . $toLabel,
            ];
        }

        $xlsxTmp = $this->generateXlsxViaPHP($mainTitle, $mainSheetData, $extraSheets, $baseUrl, $withRelationRow ? 2 : 1, $reportMeta);

        return ['path' => $xlsxTmp, 'file_name' => $fileName];
    }

    /** One Report Dump cell for an answer: link for media, scan header split, formatted value otherwise. */
    private function reportAnswerCell($answer, array $col, $questionTypeMap, array $scanExpectedHeaders)
    {
        if ($col['scan'] === 'head') {
            return $this->scanAnswerHeaderValue($answer->user_answer, $col['scan_header']);
        }
        if ($col['scan'] === 'extra') {
            return $this->scanAnswerExtraValue($answer->user_answer, $scanExpectedHeaders[$col['qid']] ?? []);
        }
        $qType = $questionTypeMap[$col['qid']] ?? '';
        return in_array($qType, ['Image', 'File Upload', 'Audio'])
            ? $this->imageAnswerUrl($answer)
            : $this->formatAnswerValue($answer->user_answer, $qType);
    }

    
    public function get_report(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'project_id'       => 'required',
            'template_name_id' => 'required',
            'from_date'        => 'required',
            'to_date'          => 'required',
            'data_get_helper'  => 'required',
        ]);

        if ($request->has('get_mail')) {
            GenerateReportJob::dispatch($validated, $request->all(), $user);
            return back()->with(['message' => "You will get the report in mail shortly"]);
        }

        $result = $this->buildReportFile($request, $validated);
        return response()->download($result['path'], $result['file_name'])->deleteFileAfterSend(true);

        /*
        // ── Legacy PhpSpreadsheet path (kept for reference) ──
        return Excel::download(
            new class($mainSheetData, $extraSheets, $projectName . ' ' . $templateName) implements
                \Maatwebsite\Excel\Concerns\WithMultipleSheets
            {
                use \Maatwebsite\Excel\Concerns\Exportable;
                private $main; private $extra; private $mainTitle;
                public function __construct($main, $extra, $mainTitle)
                { $this->main = $main; $this->extra = $extra; $this->mainTitle = $mainTitle; }

                public function sheets(): array
                {
                    $sheets = [];

                    // Sheet 1: plain flat-table (no vertical-card styling)
                    $mainData  = $this->main;
                    $mainTitle = $this->mainTitle;
                    $sheets[]  = new class($mainData, $mainTitle) implements
                        \Maatwebsite\Excel\Concerns\FromArray,
                        \Maatwebsite\Excel\Concerns\ShouldAutoSize,
                        \Maatwebsite\Excel\Concerns\WithTitle,
                        \Maatwebsite\Excel\Concerns\WithStyles,
                        \Maatwebsite\Excel\Concerns\WithEvents
                    {
                        private $data; private $title;
                        public function __construct($d, $t) { $this->data = $d; $this->title = $t; }
                        public function array(): array { return $this->data; }
                        public function title(): string { return substr($this->title, 0, 31); }

                        public function registerEvents(): array
                        {
                            return [
                                \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    $baseUrl    = rtrim(config('app.url'), '/');
                                    $highestRow = $sheet->getHighestRow();
                                    $highestCol = $sheet->getHighestColumn();
                                    $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);
                                    // Scan ALL rows — row 1 is the bold header but won't have URLs
                                    for ($row = 1; $row <= $highestRow; $row++) {
                                        for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                                            $cell = $sheet->getCell("{$colLetter}{$row}");
                                            $val  = $cell->getValue();
                                            if (!is_string($val) || $val === '') continue;
                                            // Guard: JSON-array URL (multi-image not via imageAnswerUrl) → plain text to prevent xlsx corruption
                                            if (str_starts_with($val, 'http') && str_contains($val, '["')) { $cell->setValue('Multiple Images'); continue; }

                                            $url   = null;
                                            $label = 'Download';

                                            if (str_contains($val, '/download-images-zip/')) {
                                                $url   = $val;
                                                $label = 'Download ZIP';
                                            } elseif (preg_match('/^https?:\/\//i', $val)) {
                                                $url = $val;
                                            } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$/i', $val)) {
                                                $url = $baseUrl . '/' . ltrim($val, '/');
                                            }

                                            if ($url) {
                                                $cell->setValue($label);
                                                $cell->getHyperlink()->setUrl($url)->setTooltip($url);
                                                $argb = $label === 'Download ZIP' ? 'FF107C41' : 'FF0070C0';
                                                $sheet->getStyle("{$colLetter}{$row}")->applyFromArray([
                                                    'font' => ['color' => ['argb' => $argb], 'underline' => true, 'bold' => true],
                                                ]);
                                            }
                                        }
                                    }
                                    for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                        $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
                                    }
                                },
                            ];
                        }

                        public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
                        {
                            $sheet->getStyle('1')->getFont()->setBold(true);
                        }
                    };

                    // Instance summary sheets: styled with project name on row 1, template on row 2, etc.
                    foreach ($this->extra as $s) {
                        $sheetData  = $s['data'];
                        $sheetTitle = $s['title'];
                        $sheets[]   = new class($sheetData, $sheetTitle) implements
                            \Maatwebsite\Excel\Concerns\FromArray,
                            \Maatwebsite\Excel\Concerns\ShouldAutoSize,
                            \Maatwebsite\Excel\Concerns\WithTitle,
                            \Maatwebsite\Excel\Concerns\WithStyles,
                            \Maatwebsite\Excel\Concerns\WithEvents
                        {
                            private $data; private $title;
                            public function __construct($d, $t) { $this->data = $d; $this->title = $t; }
                            public function array(): array { return $this->data; }
                            public function title(): string { return $this->title; }

                            public function registerEvents(): array
                            {
                                return [
                                    \Maatwebsite\Excel\Events\AfterSheet::class => function(\Maatwebsite\Excel\Events\AfterSheet $event) {
                                        $sheet         = $event->sheet->getDelegate();
                                        $baseUrl       = rtrim(config('app.url'), '/');
                                        $highestRow    = $sheet->getHighestRow();
                                        $highestCol    = $sheet->getHighestColumn();
                                        $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

                                        for ($row = 1; $row <= $highestRow; $row++) {
                                            $cellA = $sheet->getCell("A{$row}")->getValue();

                                            // "Activity: X" section label → light blue background + bold
                                            if (is_string($cellA) && str_starts_with($cellA, 'Activity:')) {
                                                $sheet->getStyle("A{$row}:{$highestCol}{$row}")->applyFromArray([
                                                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBDD7EE']],
                                                    'font' => ['bold' => true, 'size' => 11],
                                                ]);
                                                continue; // no URL scanning needed for label rows
                                            }

                                            // "Activity Name" column-header rows → yellow background + bold
                                            if ($cellA === 'Activity Name') {
                                                $sheet->getStyle("A{$row}:{$highestCol}{$row}")->applyFromArray([
                                                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFF00']],
                                                    'font' => ['bold' => true],
                                                ]);
                                                continue;
                                            }

                                            // Regular cells — scan for file/image URLs to convert to hyperlinks
                                            for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                                                $cell = $sheet->getCell("{$colLetter}{$row}");
                                                $val  = $cell->getValue();
                                                if (!is_string($val) || $val === '') continue;
                                                if (str_starts_with($val, 'http') && str_contains($val, '["')) { $cell->setValue('Multiple Images'); continue; }

                                                $url   = null;
                                                $label = 'Download';

                                                if (str_contains($val, '/download-images-zip/')) {
                                                    $url   = $val;
                                                    $label = 'Download ZIP';
                                                } elseif (preg_match('/^https?:\/\//i', $val)) {
                                                    $url = $val;
                                                } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$/i', $val)) {
                                                    $url = $baseUrl . '/' . ltrim($val, '/');
                                                }

                                                if ($url) {
                                                    $cell->setValue($label);
                                                    $cell->getHyperlink()->setUrl($url)->setTooltip($url);
                                                    $argb = $label === 'Download ZIP' ? 'FF107C41' : 'FF0070C0';
                                                    $sheet->getStyle("{$colLetter}{$row}")->applyFromArray([
                                                        'font' => ['color' => ['argb' => $argb], 'underline' => true, 'bold' => true],
                                                    ]);
                                                }
                                            }
                                        }

                                        // Freeze first row of data (after metadata rows)
                                        $sheet->freezePane('B6');

                                        for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx))->setAutoSize(true);
                                        }
                                    },
                                ];
                            }

                            public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
                            {
                                $maxCol  = max(array_map('count', $this->data));
                                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);
                                $totalRows = count($this->data);

                                // Merge + centre project name (row 1) and template name (row 2)
                                $sheet->mergeCells("A1:{$lastCol}1");
                                $sheet->mergeCells("A2:{$lastCol}2");
                                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

                                // Metadata rows 3-5 — purple tint + bold labels in col A
                                $sheet->getStyle("A3:{$lastCol}5")->getFill()
                                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFCCC0DA');
                                $sheet->getStyle("A3:A5")->getFont()->setBold(true);

                                // Thin borders across the whole used area
                                $sheet->getStyle("A1:{$lastCol}{$totalRows}")->applyFromArray([
                                    'borders' => ['allBorders' => [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color'       => ['argb' => 'FFD0D0D0'],
                                    ]],
                                ]);
                                // NOTE: per-activity column-header rows (yellow) and section-label rows (blue)
                                // are applied dynamically in AfterSheet because their row numbers vary.
                            }
                        };
                    }
                    return $sheets;
                }
            },
            $fileName
        );
        */
    }



    /**
     * Helper function to format answer values based on question type
     */
    /**
     * Build the cell value for an Image/File/Audio answer.
     * - Single image/file → plain asset URL (AfterSheet converts to "Download" hyperlink).
     * - JSON array of images → ZIP download URL (AfterSheet converts to "Download ZIP" hyperlink).
     */
    private function imageAnswerUrl(\App\Models\TempUserActivityAnswersData $answerModel): string
    {
        $val     = $answerModel->user_answer ?? '';
        $decoded = json_decode($val, true);
        if (is_array($decoded) && !empty($decoded)) {
            return route('report.download.images.zip', ['answer_id' => $answerModel->id]);
        }
        return asset($val);
    }

    /**
     * Stream a ZIP archive containing all images stored for a multi-image answer.
     * Route: GET /report/download-images-zip/{answer_id}
     */
    public function downloadImagesZip($answer_id)
    {
        $answer = \App\Models\TempUserActivityAnswersData::findOrFail($answer_id);
        $paths  = json_decode($answer->user_answer ?? '', true);

        if (!is_array($paths) || empty($paths)) {
            // Single image fallback — just redirect to asset
            return redirect(asset($answer->user_answer));
        }

        // Build ZIP in a temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'img_zip_') . '.zip';
        $zip     = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::CREATE) !== true) {
            abort(500, 'Could not create ZIP archive');
        }

        $added = 0;
        foreach ($paths as $i => $path) {
            $rel     = ltrim($path, '/');
            $absPath = public_path($rel);
            if (!file_exists($absPath) && ($stamped = \App\Services\GeoStamp::stampedSibling($rel))) {
                $absPath = public_path($stamped);
            }
            if (file_exists($absPath)) {
                $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION)) ?: 'jpg';
                $zip->addFile($absPath, 'image_' . ($i + 1) . '.' . $ext);
                $added++;
            }
        }
        $zip->close();

        if ($added === 0) {
            @unlink($tmpFile);
            abort(404, 'No image files found for this answer');
        }

        $zipName = 'images_answer_' . $answer_id . '.zip';
        return response()->download($tmpFile, $zipName)->deleteFileAfterSend(true);
    }

    private function formatAnswerValue($answer, $questionType)
    {
        if ($questionType == "Date" && !empty($answer)) {
            try {
                // Try to parse common date formats
                if (strpos($answer, '/') !== false) {
                    $date = Carbon::createFromFormat('d/m/Y', $answer);
                    return $date->format('d-m-Y');
                } elseif (strpos($answer, '-') !== false) {
                    $date = Carbon::createFromFormat('d-m-Y', $answer);
                    return $date->format('d-m-Y');
                } else {
                    $date = Carbon::parse($answer);
                    return $date->format('d-m-Y');
                }
            } catch (\Exception $e) {
                return $answer;
            }
        }

        return $answer;
    }

    /**
     * Extracts one expected-header value from a Barcode/QR Code/RFID answer for the
     * report's "{question} → {header}" column. $rawAnswer is the JSON wrapper written
     * by TaskController::saveAnswer() ({format, raw_value, parsed, expected_headers,...}).
     * Returns '' if the answer isn't that JSON shape, has no "parsed" data (raw_text
     * scans have none), or simply didn't contain this particular header.
     */
    private function scanAnswerHeaderValue(?string $rawAnswer, string $headerName): string
    {
        if (!$rawAnswer) return '';
        $decoded = json_decode($rawAnswer, true);
        if (!is_array($decoded) || !is_array($decoded['parsed'] ?? null)) return '';
        return (string) ($decoded['parsed'][$headerName] ?? '');
    }

    /**
     * Builds the combined "extra" cell for a Barcode/QR Code/RFID answer — every
     * key:value pair from the scan that does NOT match one of the question's
     * currently-configured expected headers, one per line (line breaks render because
     * FastXlsxWriter wraps any main-sheet cell whose value contains "\n" — see
     * FastXlsxWriter::styleIndex()). Uses the live $currentExpectedHeaders (not
     * whatever was recorded on the answer at submit time) so this stays correct even
     * if the question's configured headers changed since. Falls back to the raw
     * scanned text for a non-JSON/vCard (raw_text) scan, and to the literal answer
     * string if it isn't the JSON wrapper at all (e.g. legacy data).
     */
    private function scanAnswerExtraValue(?string $rawAnswer, array $currentExpectedHeaders): string
    {
        if (!$rawAnswer) return '';
        $decoded = json_decode($rawAnswer, true);
        if (!is_array($decoded)) return $rawAnswer;

        $parsed = $decoded['parsed'] ?? null;
        if (!is_array($parsed)) {
            return (string) ($decoded['raw_value'] ?? $rawAnswer);
        }

        $lines = [];
        foreach ($parsed as $key => $value) {
            if (!in_array($key, $currentExpectedHeaders, true)) {
                $lines[] = $key . ':' . $value;
            }
        }
        return implode("\n", $lines);
    }


    public function project_distributor_report_old_march(Request $request)
    {
        $validate = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'row_id' => 'required'
        ]);
        $show_user = 0;
        $show_date = 0;
        $show_time = 0;
        $verificationDataHelper = $request->verification_helper;
        $row_item_helper = $request->data_get_helper; // this is to show only filled, unfilled and all row_id
        if ($request->has('user_details')) {
            $show_user = 1;
        }
        if ($request->has('date_required')) {
            $show_date = 1;
        }
        if ($request->has('time_required')) {
            $show_time = 1;
        }

        $row_id = $request->row_id;
        $sheetData = []; // Array to store the final activity data

        $dist_project_template = ProjectTemplate::with('getProject.getCompanyInfo', 'getTemplate', 'getMainHeader', 'getSubHeader')->where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)
            ->first();

        //khushboo 09-07-25
        $mainHeadId = $dist_project_template->main_header;
        $subHeadId = $dist_project_template->sub_header;

        $dist_row_headers = ProjectTemplateNameValuesNew::where('id', $request->row_id)
            ->select(
                'id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$mainHeadId\"')) as main_value"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$subHeadId\"')) as sub_value")
            )
            ->get();
        //khushboo 09-07-25

        // Initialize variables for main and sub-header values
        $main_header_val = null;
        $sub_header_val = null;

        // Assign the header values
        foreach ($dist_row_headers as $dist_row_header_info) {
            // dd($dist_row_header_info, $mainHeadId, $subHeadId);
            // if ($dist_row_header_info->template_name_head_id == $dist_project_template->main_header) {
            $main_header_val = $dist_row_header_info->main_value;
            // }
            // if ($dist_row_header_info->template_name_head_id == $dist_project_template->sub_header) {
            $sub_header_val = $dist_row_header_info->sub_value;
            // }
        }

        // dd($main_header_val, $sub_header_val, $dist_row_headers);
        // Fetch activities associated with the project template
        $dist_activites = [];
        if ($dist_project_template->activityType) {
            $group_info = ActivityGroup::with('get_group_activities')->find($dist_project_template->activity_group_name_id_or_activity_id);
            $dist_activites = $group_info->get_group_activities->pluck('activity_id')->toArray();
        } else {
            $dist_activites[] = $dist_project_template->activity_group_name_id_or_activity_id;
        }

        // dd($dist_activites);
        // Loop through each activity and retrieve questions and answers
        foreach ($dist_activites as $dist_activity) {
            // Create a new sheet for each activity within the current template
            $auditDate = ""; // This is to contain the audit date
            // Find activity and its questions
            $activity = Activity::with('questions')->find($dist_activity);
            $activitySheetData = [
                [$dist_project_template->getProject->getCompanyInfo->company_name],
                [$activity->activity_name],
                [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                ['Audit Date'],
                ['Sr. No', 'Particular', 'Remark'] // Questions & Answers
            ];
            $check_if_activity_answered = TempUserActivityAnswersData::where("row_id", $row_id)
                ->where('activity_id', $activity->id)
                ->first(); // Check if this activity has answers for the current row
            $verificationStatusShow = "Pending";
            if ($check_if_activity_answered) {
                $verificationStatus = $check_if_activity_answered->status;
                $verificationStatusShow = "Pending";
                if ($verificationStatus == 5) {
                    $verificationStatusShow = "Approved";
                } elseif ($verificationStatus == 4) {
                    $verificationStatusShow = "Rejected";
                }
            }
            $auditorName = '';
            $auditDateTime = '';
            $verifierName = '';
            $verificationRemark = '';
            $verificationDateTime = '';
            if ($check_if_activity_answered) {
                if ($row_item_helper !== "unfilled") {
                    foreach ($activity->questions as $index => $activity_question) {

                        //khushboo 16-06-2025
                        if ($activity_question->question_type == 'Subjective') {

                            $user_answer = TempUserActivityAnswersData::where("row_id", $row_id)
                                ->where('activity_id', $activity_question->activity_id)
                                ->where('question_id', $activity_question->id)
                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                ->get();

                            if (!empty($user_answer)) {

                                foreach ($user_answer as $user_answer_index => $userAnswer) {

                                    $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                                    if ($isUrlOrPath) {
                                        $activitySheetData[] = [$index + 1, $activity_question->question . ' - File', $this->imageAnswerUrl($userAnswer)];
                                    } else {
                                        $activitySheetData[] = [$index + 1, $activity_question->question, $userAnswer->user_answer];
                                    }

                                }
                            }

                        } else {

                            $user_answer = TempUserActivityAnswersData::where("row_id", $row_id)
                                ->where("question_id", '=', $activity_question->id)
                                ->where('activity_id', $activity->id)
                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')->first();

                            // Add question and answer to the activity sheet
                            if ($user_answer) {

                                if (!$auditorName) {
                                    $auditorName = $user_answer->getUser->name;
                                }
                                if (!$auditDateTime) {
                                    $auditDateTime = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                    $auditDate = $user_answer->created_at->format('d-M-Y');
                                }
                                if (!$verifierName && $user_answer->verified_by) {
                                    $verifierName = $user_answer->getVerifier->name;
                                }

                                if (!$verificationRemark && $user_answer->remark) {
                                    $verificationRemark = $user_answer->get_remark_info->remark;
                                    // $verificationDateTime = $user_answer->updated_at->format('d-M-Y H:i');
                                    $verificationDateTime = $user_answer->updated_at
                                        ->setTimezone('Asia/Kolkata') // Convert to IST
                                        ->format('d-M-Y H:i');
                                }
                                if ($activity_question->question_type == "File Upload" || $activity_question->question_type == "Image" || $activity_question->question_type == "Audio") {
                                    $activitySheetData[] = [$index + 1, $activity_question->question, $this->imageAnswerUrl($user_answer)];
                                } else {
                                    $activitySheetData[] = [$index + 1, $activity_question->question, $user_answer->user_answer];
                                }
                            } else {

                                $activitySheetData[] = [$index + 1, $activity_question->question, null]; // No answer
                            }

                        }

                    }
                }
            } else {
                if ($row_item_helper == "all" || $row_item_helper == "unfilled") {
                    foreach ($activity->questions as $index => $activity_question) {
                        if ($activity_question->question_type == 'Subjective') {
                            $hasFile = TempUserActivityAnswersData::where('question_id', $activity_question->id)
                                ->where('activity_id', $activity_question->activity_id)
                                ->where('row_id', $row_id)
                                ->where(function ($query) {
                                    $query->where('user_answer', 'like', '%/%'); // crude file path check
                                })
                                ->exists();

                            if ($hasFile) {
                                $activitySheetData[] = [$index + 1, $activity_question->question] . ' - File';
                            } else {
                                $activitySheetData[] = [$index + 1, $activity_question->question]; // Only questions, no answers
                            }
                        } else {
                            $activitySheetData[] = [$index + 1, $activity_question->question]; // Only questions, no answers
                        }

                    }
                }
            }
            if ($row_item_helper !== "unfilled") {
                $activitySheetData[] = [''];
                $activitySheetData[] = ['Auditor Name', $auditorName];
                $activitySheetData[] = ['Audit Date & Time', $auditDateTime];
                $activitySheetData[] = ['Verification Status', $verificationStatusShow];
                $activitySheetData[] = ['Verifier Name', $verifierName];
                $activitySheetData[] = ['Verification Remark', $verificationRemark];
                $activitySheetData[] = ['Verification Date & Time', $verificationDateTime];
                $activitySheetData[4][] = $auditDate; // this is to add the audit date in the top of the questions and answers of the activity
            }
            // Add this activity's data as a new sheet
            $sheetData[] = [
                'header' => [mb_substr($activity->activity_name, 0, 31)],
                'data' => $activitySheetData,
            ];
        }
        // dd($activitySheetData);

        // Handle other project templates
        $other_project_templates = ProjectTemplate::with('getTemplate', 'getMainHeader', 'getSubHeader')->where('project_id', $request->project_id)
            ->whereNot('id', $dist_project_template->id)
            ->get();

        foreach ($other_project_templates as $other_project_template) {
            $child_activites = [];
            if ($other_project_template->activityType) {
                $child_group_info = ActivityGroup::with('get_group_activities')->find($other_project_template->activity_group_name_id_or_activity_id);
                $child_activites = $child_group_info->get_group_activities->pluck('activity_id')->toArray();
            } else {
                if (!empty($other_project_template->activity_group_name_id_or_activity_id)) {
                    $child_activites[] = $other_project_template->activity_group_name_id_or_activity_id;
                }
            }
            // dd($child_activites, $other_project_templates);
            // Loop through each activity in the other templates

            // Loop through each activity in the other templates
            if (!empty($child_activites)) {
                foreach ($child_activites as $child_activity) {
                    // Initialize sheet data for each activity in the child template
                    $child_activity_info = Activity::with('questions')->find($child_activity);
                    $activitySheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name],
                        [$child_activity_info->activity_name],
                        [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                        [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                        ['', ''] // Empty row for spacing
                    ];

                    // Retrieve headers for the child activity
//                $questionsArray = $child_activity_info->questions->pluck('question')->toArray();

                    //khushboo 16-06-2025
                    // Retrieve headers for the child activity
                    $questionsArray = [];
                    $activityQuesCount = 0;

                    foreach ($child_activity_info->questions as $question) {
                        $questionsArray[] = $question->question;
                        $activityQuesCount++;

                        // Only apply file check for subjective questions
                        if ($question->question_type === 'Subjective') {
                            $hasFile = TempUserActivityAnswersData::where('question_id', $question->id)
                                ->where('activity_id', $child_activity)
                                ->where('row_id', $row_id)
                                ->where(function ($query) {
                                    $query->where('user_answer', 'like', '%/%'); // crude file path check
                                })
                                ->exists();

                            if ($hasFile) {
                                $questionsArray[] = $question->question . ' - File';
                                $activityQuesCount++; // Increase count since you are adding one more column
                            }
                        }
                    }

//                $activityQuesCount = count($questionsArray);
                    //khushboo 16-06-2025

                    array_unshift($questionsArray, 'S.NO.', $other_project_template->getMainHeader->template_head_name, $other_project_template->getSubHeader->template_head_name);

                    array_push($questionsArray, 'Auditor Name', 'Audit Date & Time', 'Verification Status', 'Verifier Name', 'Verification Remark', 'Verification Date & Time');
                    $activitySheetData[] = $questionsArray;
                    $child_main_header_id = $other_project_template->main_header;
                    $child_sub_header_id = $other_project_template->sub_header;

                    $get_master_row = DB::table('project_template_name_values_new')->where('id', $row_id)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$other_project_template->master_head_id\"')) as main_value")
                        )
                        ->first();

                    $related_childs = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $other_project_template->id)
                        ->where('distributor_id', $row_id)
                        ->get();

                    if ($related_childs->IsEmpty()) {
                        $related_childs = DB::table('project_template_name_values_new')
                            ->where('project_template_id', $other_project_template->id)
                            ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [trim($get_master_row->main_value)])
                            ->get();
                    }

//                dd($related_childs, $other_project_template->id, $get_master_row->main_value);
                    foreach ($related_childs as $index => $related_child) {
//                    $child_headers = ProjectTemplateNameValue::where('row_id', $related_child->row_id)
//                        ->whereIn("template_name_head_id", [$child_main_header_id, $child_sub_header_id])
//                        ->get();

                        $child_headers = DB::table('project_template_name_values_new')->where('id', $related_child->id)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_main_header_id\"')) as main_value"),
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_sub_header_id\"')) as sub_value")
                            )
                            ->first();

                        // Fetch child headers
                        $child_main_header = $child_headers->main_value;
                        $child_sub_header = $child_headers->sub_value;

                        $check_any_row_answer = DB::table('temp_user_activity_answers_data')->where('row_id', $related_child->id)
                            ->where('activity_id', $child_activity_info->id)
                            ->first();

                        if ($check_any_row_answer) {
                            if ($row_item_helper !== "unfilled") {
                                $verificationOutletStatus = $check_any_row_answer->status;
                                $verificationOutletStatusShow = "Pending";
                                if ($verificationOutletStatus == 4) {
                                    $verificationOutletStatusShow = "Rejected";
                                } elseif ($verificationOutletStatus == 5) {
                                    $verificationOutletStatusShow = "Approved";
                                }
                                $outletRowRenderStatus = 0;
                                if ($verificationDataHelper == "all") {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "pending" && $check_any_row_answer->status == 0) {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "approved" && $check_any_row_answer->status == 5) {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "rejected" && $check_any_row_answer->status == 4) {
                                    $outletRowRenderStatus = 1;
                                }

                                //                            dd($outletRowRenderStatus, $verificationDataHelper, $check_any_row_answer);
                                if ($outletRowRenderStatus) {
                                    $answers = [$index + 1, $child_main_header, $child_sub_header];
                                    $totalQuestions = $child_activity_info->questions->count();
                                    foreach ($child_activity_info->questions as $child_ques_index => $child_activity_question) {

                                        //khushboo 16-06-2025
                                        if ($child_activity_question->question_type == 'Subjective') {

                                            $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->id)
                                                ->where('activity_id', $child_activity_question->activity_id)
                                                ->where('question_id', $child_activity_question->id)
                                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                                ->get();

                                            if (!empty($user_answer)) {

                                                foreach ($user_answer as $user_answer_index => $userAnswer) {

                                                    $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                                                    if ($isUrlOrPath) {
                                                        $answers[] = $this->imageAnswerUrl($userAnswer);
                                                    } else {
                                                        $answers[] = $userAnswer->user_answer;
                                                    }
                                                }
                                            }

                                        } else {

                                            //khushboo 16-06-2025

                                            $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->id)
                                                ->where('activity_id', $child_activity_question->activity_id)
                                                ->where('question_id', $child_activity_question->id)
                                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                                ->first();
                                            if ($user_answer) {

                                                if ($child_activity_question->question_type == "File Upload" || $child_activity_question->question_type == "Image" || $child_activity_question->question_type == "Audio") {
                                                    $answers[] = $this->imageAnswerUrl($user_answer);
                                                } else {
                                                    $answers[] = $user_answer->user_answer;
                                                }
                                                if ($child_ques_index + 1 == $totalQuestions) {
                                                    $answers[] = $user_answer->getUser->name;
                                                    // $answers[] = $user_answer->created_at->format('d-M-Y H:i');
                                                    $answers[] = $user_answer->created_at
                                                        ->setTimezone('Asia/Kolkata') // Convert to IST
                                                        ->format('d-M-Y H:i');
                                                    $answers[] = $verificationOutletStatusShow;
                                                    if ($user_answer->verified_by) {
                                                        $answers[] = $user_answer->getVerifier->name;
                                                    } else {
                                                        $answers[] = '';
                                                    }

                                                    if ($user_answer->remark) {
                                                        $answers[] = $user_answer->get_remark_info->remark;
                                                        $answers[] = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                                        // Convert to IST

                                                    } else {
                                                        $answers[] = '';
                                                        $answers[] = '';
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $activitySheetData[] = $answers;
                                }
                            }
                        } else {
                            // No answers found, add only the headers
                            $extraColumns = array_fill(0, $activityQuesCount + 2, '');
                            $extraColumns[] = 'NA';
                            if ($row_item_helper == "all" || $row_item_helper == "unfilled") {
                                $activitySheetData[] = array_merge([$index + 1, $child_main_header, $child_sub_header], $extraColumns);
                            }
                        }
                    }
                    // Add this child activity's data as a new sheet
                    $sheetData[] = [
                        'header' => [mb_substr($child_activity_info->activity_name, 0, 31)],
                        'data' => $activitySheetData
                    ];
                }
            }
        }
//        dd('gfhgfh');
        // Generates the name of the excel file name
        $fileName = $dist_project_template->getProject->project_name . $dist_project_template->getTemplate->template_name . '.xlsx';
        return $this->downloadXlsxFast($sheetData, $fileName);
    }


    public function project_distributor_report(Request $request)
    {
        $validate = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'row_id' => 'required'
        ]);

        $show_user = 0;
        $show_date = 0;
        $show_time = 0;
        $verificationDataHelper = $request->verification_helper;
        $row_item_helper = $request->data_get_helper; // this is to show only filled, unfilled and all row_id

        if ($request->has('user_details')) {
            $show_user = 1;
        }
        if ($request->has('date_required')) {
            $show_date = 1;
        }
        if ($request->has('time_required')) {
            $show_time = 1;
        }

        $row_id = $request->row_id;
        $sheetData = []; // Array to store the final activity data

        $dist_project_template = ProjectTemplate::with('getProject.getCompanyInfo', 'getTemplate', 'getMainHeader', 'getSubHeader')
            ->where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)
            ->first();

        // Get main and sub header values
        $mainHeadId = $dist_project_template->main_header;
        $subHeadId = $dist_project_template->sub_header;

        $dist_row_headers = ProjectTemplateNameValuesNew::where('id', $request->row_id)
            ->select(
                'id',
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$mainHeadId\"')) as main_value"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$subHeadId\"')) as sub_value")
            )
            ->first();

        $main_header_val = $dist_row_headers->main_value ?? null;
        $sub_header_val = $dist_row_headers->sub_value ?? null;

        // Fetch activities associated with the project template
        $dist_activites = [];
        if ($dist_project_template->activityType) {
            $group_info = ActivityGroup::with('get_group_activities')->find($dist_project_template->activity_group_name_id_or_activity_id);
            $dist_activites = $group_info->get_group_activities->pluck('activity_id')->toArray();
        } else {
            $dist_activites[] = $dist_project_template->activity_group_name_id_or_activity_id;
        }

        // Collects per-activity instance data for the extra tabular instance sheet
        $distInstanceActivities = [];

        // ── Bulk preload: all answers for this distributor row ────────────────
        // Replaces N per-question DB queries with a single query + in-memory filtering.
        $distAllAnswersColl = TempUserActivityAnswersData::where('row_id', $row_id)
            ->whereIn('activity_id', $dist_activites)
            ->with('getUser', 'getVerifier', 'get_remark_info')
            ->get();

        // Index: [activity_id][question_id][] = answer model
        $distAnsIdx = [];
        foreach ($distAllAnswersColl as $_da) {
            $distAnsIdx[$_da->activity_id][$_da->question_id][] = $_da;
        }

        // Preload submission group IDs per activity.
        // Each element is either an int (activity_sequence for new-style instances)
        // or ['sid' => same_answer_id] for old-style instances stored via same_answer_id.
        $distGroupIdsByAct = [];
        foreach ($dist_activites as $_dact) {
            $actAnswers = collect($distAnsIdx[$_dact] ?? [])->flatten(1);

            // New-style: distinct activity_sequence > 0
            $newSeqs = $actAnswers->filter(fn($a) => (int)($a->activity_sequence ?? 0) > 0)
                ->map(fn($a) => (int)$a->activity_sequence)->unique()->sort()->values();

            if ($newSeqs->isNotEmpty()) {
                // Original (seq 0) first, then each new-style instance
                $distGroupIdsByAct[$_dact] = array_merge([0], $newSeqs->toArray());
            } else {
                // Old-style: each distinct same_answer_id is one visit/instance.
                // BUT new-style app saves give each question its own unique same_answer_id
                // (q_count per sid = 1). Detect that and collapse to [0] (single original instance).
                $sameIds = $actAnswers->filter(fn($a) => !empty($a->same_answer_id))
                    ->map(fn($a) => $a->same_answer_id)->unique()->sort()->values();

                if ($sameIds->count() > 1) {
                    // Count how many same_answer_id groups have only 1 distinct question.
                    // New-style saves: each question gets its own same_answer_id → majority are singletons.
                    // Old-style saves: each same_answer_id covers many questions → very few singletons.
                    $withAnswers = $actAnswers->filter(fn($a) => !empty($a->same_answer_id));
                    $qsPerSid = [];
                    foreach ($withAnswers as $_a) {
                        $qsPerSid[$_a->same_answer_id][$_a->question_id] = true;
                    }
                    $singletonCount = collect($qsPerSid)->filter(fn($qs) => count($qs) <= 1)->count();
                    $groupCount     = count($qsPerSid);

                    if ($groupCount > 0 && ($singletonCount / $groupCount) > 0.5) {
                        // New-style: majority of groups are single-question → single original instance
                        $distGroupIdsByAct[$_dact] = [0];
                    } else {
                        $distGroupIdsByAct[$_dact] = $sameIds->map(fn($id) => ['sid' => $id])->toArray();
                    }
                } else {
                    $distGroupIdsByAct[$_dact] = $sameIds->isNotEmpty()
                        ? $sameIds->map(fn($id) => ['sid' => $id])->toArray()
                        : [0];
                }
            }
        }

        // Decode the per-activity add-on list once (JSON array of activity IDs)
        $distAddOnActivityIds = json_decode($dist_project_template->activity_add_on_activity_ids ?? '[]', true) ?: [];

        // Preload repeat-instance labels per activity
        $distRepeatLabelsByAct = ActivityRepeatInstance::where('row_id', $row_id)
            ->whereIn('activity_id', $dist_activites)
            ->orderBy('activity_sequence')
            ->get()
            ->groupBy('activity_id')
            ->map(fn($g) => $g->pluck('instance_label')->toArray());

        // Preload all questions for all activities in one query
        $allDistQuestions = Question::whereIn('activity_id', $dist_activites)
            ->withoutOrphans()
            ->orderBy('question_sequence')
            ->get()
            ->groupBy('activity_id');

        // Preload Activity models
        $distActivityModels = Activity::whereIn('id', $dist_activites)->get()->keyBy('id');

        // Helper: filter an answer collection by visit descriptor.
        // $visit may be an int (activity_sequence, 0 = original) or ['sid' => same_answer_id].
        $distSeqFilter = fn(iterable $items, $visit) => collect($items)->filter(
            fn($a) => is_array($visit)
                ? $a->same_answer_id == $visit['sid']
                : ((int)$visit > 0
                    ? (int)($a->activity_sequence ?? 0) === (int)$visit
                    : (int)($a->activity_sequence ?? 0) === 0)
        );

        // Loop through each activity and retrieve questions and answers
        foreach ($dist_activites as $dist_activity) {
            $auditDate = ""; // This is to contain the audit date

            // Use preloaded activity model
            $activity = $distActivityModels[$dist_activity] ?? Activity::find($dist_activity);

            // Create a new sheet for each activity within the current template
            $activitySheetData = [
                [$dist_project_template->getProject->getCompanyInfo->company_name],
                [$activity->activity_name],
                [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                ['Audit Date'],
                ['Sr. No', 'Particular', 'Relation', 'Remark'] // Questions, how they hang from their parents, answers
            ];

            // Use preloaded questions
            $allQuestions = $allDistQuestions[$dist_activity] ?? collect();

            // Every answerable question at any depth (Multi Response sub-questions, conditional
            // questions), each with the context its answer is saved under and its relation
            $questionsWithHierarchy = array_map(
                fn($item) => $item + ['prefix' => '', 'is_outlet' => false, 'mr_parent_id' => $item['pctx']],
                $this->flattenActivityQuestions($allQuestions)
            );
            $distContextsByQ = $this->contextsByQuestion($questionsWithHierarchy);

            // Use preloaded answers — no DB query
            $check_if_activity_answered = collect($distAnsIdx[$activity->id] ?? [])->flatten(1)->first();

            $verificationStatusShow = "Pending";
            if ($check_if_activity_answered) {
                $verificationStatus = $check_if_activity_answered->status;
                $verificationStatusShow = "Pending";
                if ($verificationStatus == 5) {
                    $verificationStatusShow = "Approved";
                } elseif ($verificationStatus == 4) {
                    $verificationStatusShow = "Rejected";
                }
            }

            $auditorName = '';
            $auditDateTime = '';
            $verifierName = '';
            $verificationRemark = '';
            $verificationDateTime = '';
            $submissionGroupIds = [];
            $repeatInstanceLabels = [];

            // Closure to scope an answer query to a specific activity_sequence.
            // Seq 0 (or null) = original submission; seq > 0 = a named repeat instance.
            $seqFilter = function ($q, $seq) {
                if ((int) $seq > 0) {
                    $q->where('activity_sequence', (int) $seq);
                } else {
                    $q->where(function ($q2) {
                        $q2->whereNull('activity_sequence')->orWhere('activity_sequence', 0);
                    });
                }
            };

            if ($check_if_activity_answered) {
                // Use preloaded data — no DB queries needed
                $submissionGroupIds   = $distGroupIdsByAct[$dist_activity] ?? [];
                $repeatInstanceLabels = $distRepeatLabelsByAct[$dist_activity] ?? [];

                // Only show the original (first) instance on this sheet.
                // Extra instances appear on the separate Instance Summary sheet.
                $firstSeq = $submissionGroupIds[0] ?? 0; // activity_sequence for original = 0
                $extraInstanceCount = 0; // no extra Remark columns on main sheet

                if ($row_item_helper !== "unfilled") {
                    $rowCounter = 1;

                    foreach ($questionsWithHierarchy as $qItem) {
                        $activity_question = $qItem['question'];
                        $qPrefix           = $qItem['prefix'];
                        $isOutlet          = $qItem['is_outlet'];

                        if ($isOutlet) {
                            continue;
                        }

                        // The question's own name; its parents go in the Relation column
                        $displayQuestion = $activity_question->question;
                        $qRelation       = $qItem['relation'];

                        // Original submission — filter by first activity_sequence (in-memory, no DB query)
                        if ($activity_question->question_type == 'Subjective') {
                            $user_answers = $distSeqFilter(
                                $distAnsIdx[$activity_question->activity_id][$activity_question->id] ?? [],
                                $firstSeq
                            )->sortBy('created_at')->values();

                            if (!empty($user_answers) && $user_answers->count() > 0) {
                                foreach ($user_answers as $answerIndex => $userAnswer) {
                                    $displayQ = $displayQuestion;
                                    if ($answerIndex > 0) {
                                        $displayQ .= ' (Dependent)';
                                    }

                                    $isUrlOrPath = str_contains($userAnswer->user_answer, '/');
                                    $origVal = $isUrlOrPath
                                        ? $this->imageAnswerUrl($userAnswer)
                                        : $userAnswer->user_answer;

                                    // Build extra instance answer columns (only for the first sub-answer row)
                                    $extraCols = [];
                                    if ($answerIndex === 0 && $extraInstanceCount > 0) {
                                        for ($ii = 1; $ii <= $extraInstanceCount; $ii++) {
                                            $instSeq = $submissionGroupIds[$ii] ?? null;
                                            if ($instSeq !== null) {
                                                $instSubjAnswers = $distSeqFilter(
                                                    $distAnsIdx[$activity_question->activity_id][$activity_question->id] ?? [],
                                                    $instSeq
                                                )->sortBy('created_at');
                                                $vals = $instSubjAnswers->map(fn($a) => str_contains($a->user_answer, '/') ? $this->imageAnswerUrl($a) : $a->user_answer)->toArray();
                                                $extraCols[] = implode(' | ', $vals) ?: null;
                                            } else {
                                                $extraCols[] = null;
                                            }
                                        }
                                    } else {
                                        $extraCols = array_fill(0, $extraInstanceCount, null);
                                    }

                                    $label = $isUrlOrPath ? $displayQ . ' - File' : $displayQ;
                                    $activitySheetData[] = array_merge([$rowCounter++, $label, $qRelation, $origVal], $extraCols);

                                    // Capture metadata from first answer
                                    if ($answerIndex == 0) {
                                        if (!$auditorName && $userAnswer->getUser) {
                                            $auditorName = $userAnswer->getUser->name;
                                        }
                                        if (!$auditDateTime) {
                                            $auditDateTime = $userAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                            $auditDate = $userAnswer->created_at->format('d-M-Y');
                                        }
                                        if (!$verifierName && $userAnswer->verified_by && $userAnswer->getVerifier) {
                                            $verifierName = $userAnswer->getVerifier->name;
                                        }
                                        if (!$verificationRemark) {
                                            $verificationRemark = $this->verificationRemarkText($userAnswer);
                                        }
                                        if (!$verificationDateTime && in_array((int) $userAnswer->status, [4, 5]) && $userAnswer->updated_at) {
                                            $verificationDateTime = $userAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                        }
                                    }
                                }
                            } else {
                                $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, null], array_fill(0, $extraInstanceCount, null));
                            }
                        } else {
                            // Non-subjective question — original submission (first same_answer_id group)
                            // For sub-questions of Multi Response parents: use the pctx answer (mr_parent_id set).
                            // For standalone/conditional questions: prefer null-pctx answer.
                            // In-memory lookup — no DB queries (preloaded in $distAnsIdx)
                            $mrPctxId = $qItem['mr_parent_id'] ?? null;
                            $sqKey    = $activity_question->getKey();
                            $_qPool   = collect($distAnsIdx[$activity->id][$sqKey] ?? []);
                            $_seqColl = $distSeqFilter($_qPool, $firstSeq);
                            if ($mrPctxId !== null) {
                                $_others = $distContextsByQ[$sqKey] ?? [];
                                $user_answer = $this->pickContextAnswer($_seqColl, $mrPctxId, $_others)
                                    ?? $this->pickContextAnswer($_qPool, $mrPctxId, $_others);

                                // Fallback: swapped storage (question_id = MR parent, parent_context_id
                                // = sub-question) — a save-path bug (fixed separately) produced this
                                // for existing Multi Response sub-question answers.
                                if (!$user_answer) {
                                    $_swappedPool = collect($distAnsIdx[$activity->id][$mrPctxId] ?? [])
                                        ->where('parent_context_id', $sqKey);
                                    $user_answer = $distSeqFilter($_swappedPool, $firstSeq)->first()
                                        ?? $_swappedPool->first();
                                }
                            } else {
                                $user_answer = $_seqColl->filter(fn($a) => is_null($a->parent_context_id))->first()
                                    ?? $_seqColl->first()
                                    ?? $_qPool->filter(fn($a) => is_null($a->parent_context_id))->first()
                                    ?? $_qPool->first();
                            }

                            // Extra instance columns (already in memory)
                            $extraCols = [];
                            for ($ii = 1; $ii <= $extraInstanceCount; $ii++) {
                                $instSeqExtra = $submissionGroupIds[$ii] ?? null;
                                if ($instSeqExtra !== null) {
                                    $ia = $distSeqFilter($_qPool, $instSeqExtra)->first();
                                    if ($ia) {
                                        if (in_array($activity_question->question_type, ['File Upload', 'Image', 'Audio'])) {
                                            $extraCols[] = $this->imageAnswerUrl($ia);
                                        } elseif ($activity_question->question_type == 'Location' && empty($ia->user_answer) && $ia->latitude && $ia->longitude) {
                                            $extraCols[] = $ia->latitude . ', ' . $ia->longitude;
                                        } else {
                                            $extraCols[] = $ia->user_answer;
                                        }
                                    } else {
                                        $extraCols[] = null;
                                    }
                                } else {
                                    $extraCols[] = null;
                                }
                            }

                            if ($user_answer) {
                                if (!$auditorName && $user_answer->getUser) {
                                    $auditorName = $user_answer->getUser->name;
                                }
                                if (!$auditDateTime) {
                                    $auditDateTime = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                    $auditDate = $user_answer->created_at->format('d-M-Y');
                                }
                                if (!$verifierName && $user_answer->verified_by && $user_answer->getVerifier) {
                                    $verifierName = $user_answer->getVerifier->name;
                                }
                                if (!$verificationRemark) {
                                    $verificationRemark = $this->verificationRemarkText($user_answer);
                                }
                                if (!$verificationDateTime && in_array((int) $user_answer->status, [4, 5]) && $user_answer->updated_at) {
                                    $verificationDateTime = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                }

                                if (in_array($activity_question->question_type, ['File Upload', 'Image', 'Audio'])) {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, $this->imageAnswerUrl($user_answer)], $extraCols);
                                } elseif ($activity_question->question_type == 'Location' && empty($user_answer->user_answer) && $user_answer->latitude && $user_answer->longitude) {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, $user_answer->latitude . ', ' . $user_answer->longitude], $extraCols);
                                } elseif (in_array($activity_question->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                                    // One row per configured expected header (matched scan data), plus
                                    // one more row for anything scanned that doesn't match a header —
                                    // same split as the main project report, adapted to this sheet's
                                    // vertical (one-row-per-question) layout instead of columns.
                                    $scanHeaders = $activity_question->getOptions->pluck('option')->map('trim')->filter()->values()->all();
                                    foreach ($scanHeaders as $scanHeader) {
                                        $activitySheetData[] = array_merge(
                                            [$rowCounter++, $displayQuestion . ' → ' . $scanHeader, $qRelation !== '' ? $qRelation . ' → ' . $scanHeader : '', $this->scanAnswerHeaderValue($user_answer->user_answer, $scanHeader)],
                                            $extraCols
                                        );
                                    }
                                    $activitySheetData[] = array_merge(
                                        [$rowCounter++, $displayQuestion, $qRelation, $this->scanAnswerExtraValue($user_answer->user_answer, $scanHeaders)],
                                        $extraCols
                                    );
                                } else {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, $user_answer->user_answer], $extraCols);
                                }
                            } elseif (in_array($activity_question->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                                $scanHeaders = $activity_question->getOptions->pluck('option')->map('trim')->filter()->values()->all();
                                foreach ($scanHeaders as $scanHeader) {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion . ' → ' . $scanHeader, $qRelation !== '' ? $qRelation . ' → ' . $scanHeader : '', null], $extraCols);
                                }
                                $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, null], $extraCols);
                            } else {
                                $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $qRelation, null], $extraCols);
                            }
                        }
                    }
                }
            } else {
                if ($row_item_helper == "all" || $row_item_helper == "unfilled") {
                    $rowCounter = 1;
                    foreach ($questionsWithHierarchy as $qItem) {
                        $activity_question = $qItem['question'];
                        if ($qItem['is_outlet']) {
                            $outletLabel = $qPrefix ? $qPrefix . ' → ' . $activity_question->question : $activity_question->question;
                            $activitySheetData[] = [$rowCounter++, '— ' . $outletLabel . ' —', ''];
                        } else {
                            $displayQuestion = $activity_question->question;
                            $qRelation       = $qItem['relation'];
                            if (in_array($activity_question->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                                $scanHeaders = $activity_question->getOptions->pluck('option')->map('trim')->filter()->values()->all();
                                foreach ($scanHeaders as $scanHeader) {
                                    $activitySheetData[] = [$rowCounter++, $displayQuestion . ' → ' . $scanHeader, $qRelation !== '' ? $qRelation . ' → ' . $scanHeader : '', null];
                                }
                                $activitySheetData[] = [$rowCounter++, $displayQuestion, $qRelation, null];
                            } else {
                                $activitySheetData[] = [$rowCounter++, $displayQuestion, $qRelation, null];
                            }
                        }
                    }
                }
            }

            if ($row_item_helper !== "unfilled") {
                $activitySheetData[] = [''];
                $activitySheetData[] = ['Auditor Name', $auditorName];
                $activitySheetData[] = ['Audit Date & Time', $auditDateTime];
                $activitySheetData[] = ['Verification Status', $verificationStatusShow];
                $activitySheetData[] = ['Verifier Name', $verifierName];
                $activitySheetData[] = ['Verification Remark', $verificationRemark];
                $activitySheetData[] = ['Verification Date & Time', $verificationDateTime];
                $activitySheetData[4][] = $auditDate;
            }

            // Add-on activities always use tabular sheet (all instances incl. original).
            // Non-add-on activities always use the vertical sheet.
            $isActivityAddOn = $dist_project_template->activity_add_on == 1
                               && in_array($dist_activity, $distAddOnActivityIds);
            $hasMultiInstance = $isActivityAddOn;

            if (!$hasMultiInstance) {
                // No multiple instances — use vertical (old format) sheet
                $sheetData[] = [
                    'header'        => [mb_substr($activity->activity_name, 0, 31)],
                    'data'          => $activitySheetData,
                    'relation_cols' => [2],
                ];
            }

            if ($hasMultiInstance) {
                // Build ordered question list (same as used for vertical sheet, excluding outlets)
                $distInstQCols = []; // [['name' => displayName, 'id' => rawQId, 'type' => type]]
                foreach ($questionsWithHierarchy as $qItem) {
                    if ($qItem['is_outlet']) continue;
                    $distInstQCols[] = [
                        'name'     => $qItem['question']->question,
                        'relation' => $qItem['relation'],
                        'pctx'     => $qItem['pctx'],
                        'id'       => $qItem['question']->getKey(), // raw numeric PK
                        'type'     => $qItem['question']->question_type,
                    ];
                }

                $distInstRows = [];
                foreach ($submissionGroupIds as $instIdx => $instSeqVal) {
                    $instRow = [$activity->activity_name, $instIdx + 1];

                    // Collect metadata while scanning answers
                    $instAuditorName    = '';
                    $instAuditDateTime  = '';
                    $instVerifStatus    = '';
                    $instVerifierName   = '';
                    $instVerifRemark    = '';
                    $instVerifDateTime  = '';

                    foreach ($distInstQCols as $col) {
                        // In-memory lookup from preloaded answers
                        $_instPool = collect($distAnsIdx[$activity->id][$col['id']] ?? []);
                        $_instSeqd = $distSeqFilter($_instPool, $instSeqVal);
                        if ($col['type'] === 'Subjective') {
                            $answers = $_instSeqd->sortBy('created_at')->values();
                            foreach ($answers as $a) {
                                if (!$instAuditorName && $a->getUser)    { $instAuditorName   = $a->getUser->name; $instAuditDateTime = $a->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifierName && $a->verified_by && $a->getVerifier) { $instVerifierName  = $a->getVerifier->name; }
                                if (!$instVerifRemark) { $instVerifRemark = $this->verificationRemarkText($a); } if (!$instVerifDateTime && in_array((int) $a->status, [4, 5]) && $a->updated_at) { $instVerifDateTime = $a->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifStatus)  { $instVerifStatus = $a->status == 5 ? 'Approved' : ($a->status == 4 ? 'Rejected' : 'Pending'); }
                            }
                            $vals = $answers->map(function ($a) {
                                return str_contains($a->user_answer, '/') ? $this->imageAnswerUrl($a) : $a->user_answer;
                            })->toArray();
                            $instRow[] = implode(' | ', $vals) ?: null;
                        } else {
                            $ans = $this->pickContextAnswer($_instSeqd, $col['pctx'], $distContextsByQ[$col['id']] ?? []);
                            if ($ans) {
                                if (!$instAuditorName && $ans->getUser)    { $instAuditorName   = $ans->getUser->name; $instAuditDateTime = $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifierName && $ans->verified_by && $ans->getVerifier) { $instVerifierName  = $ans->getVerifier->name; }
                                if (!$instVerifRemark) { $instVerifRemark = $this->verificationRemarkText($ans); } if (!$instVerifDateTime && in_array((int) $ans->status, [4, 5]) && $ans->updated_at) { $instVerifDateTime = $ans->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifStatus)  { $instVerifStatus = $ans->status == 5 ? 'Approved' : ($ans->status == 4 ? 'Rejected' : 'Pending'); }

                                if (in_array($col['type'], ['File Upload', 'Image', 'Audio'])) {
                                    $instRow[] = $this->imageAnswerUrl($ans);
                                } elseif ($col['type'] === 'Location' && empty($ans->user_answer) && $ans->latitude) {
                                    $instRow[] = $ans->latitude . ', ' . $ans->longitude;
                                } else {
                                    $instRow[] = $ans->user_answer;
                                }
                            } else {
                                $instRow[] = null;
                            }
                        }
                    }

                    // Append metadata columns after all question answers
                    $instRow[] = $instAuditorName;
                    $instRow[] = $instAuditDateTime;
                    $instRow[] = $instVerifStatus;
                    $instRow[] = $instVerifierName;
                    $instRow[] = $instVerifRemark;
                    $instRow[] = $instVerifDateTime;

                    $distInstRows[] = $instRow;
                }

                $distInstanceActivities[] = [
                    'activityName' => $activity->activity_name,
                    'questions'    => $distInstQCols,
                    'rows'         => $distInstRows,
                    'auditDate'    => $auditDate,
                ];
            }

            // All activities now use the tabular format — vertical sheet is never added here.

            if (false) { // removed: instances now appear as extra columns, not separate sheets
                foreach (array_slice($submissionGroupIds, 1) as $groupIndex => $groupId) {
                    $instLabel = 'Instance ' . ($groupIndex + 2);

                    $instanceSheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name],
                        [$activity->activity_name],
                        [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                        [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                        ['Audit Date'],
                        ['Sr. No', 'Particular', 'Remark'],
                    ];

                    $instRowCounter       = 1;
                    $instAuditorName      = '';
                    $instAuditDateTime    = '';
                    $instAuditDate        = '';
                    $instVerifierName     = '';
                    $instVerifRemark      = '';
                    $instVerifDateTime    = '';

                    foreach ($questionsWithHierarchy as $qItem) {
                        $aq = $qItem['question'];
                        if ($qItem['is_outlet']) continue;

                        $displayQ = $qItem['prefix'] ? $qItem['prefix'] . ' → ' . $aq->question : $aq->question;

                        if ($aq->question_type == 'Subjective') {
                            $inst_answers = TempUserActivityAnswersData::where('row_id', $row_id)
                                ->where('activity_id', $aq->activity_id)
                                ->where('question_id', $aq->id)
                                ->where('same_answer_id', $groupId)
                                ->with('getUser', 'getVerifier', 'get_remark_info')
                                ->orderBy('created_at')
                                ->get();
                            if ($inst_answers->count()) {
                                foreach ($inst_answers as $ai => $ia) {
                                    $dq = $ai > 0 ? $displayQ . ' (Dependent)' : $displayQ;
                                    $isUrl = str_contains($ia->user_answer, '/');
                                    $instanceSheetData[] = [$instRowCounter++, $dq, $isUrl ? $this->imageAnswerUrl($ia) : $ia->user_answer];
                                    if ($ai == 0) {
                                        if (!$instAuditorName && $ia->getUser) {
                                            $instAuditorName   = $ia->getUser->name;
                                            $instAuditDateTime = $ia->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                            $instAuditDate     = $ia->created_at->format('d-M-Y');
                                        }
                                        if (!$instVerifierName && $ia->verified_by && $ia->getVerifier) {
                                            $instVerifierName = $ia->getVerifier->name;
                                        }
                                        if (!$instVerifRemark) { $instVerifRemark = $this->verificationRemarkText($ia); } if (!$instVerifDateTime && in_array((int) $ia->status, [4, 5]) && $ia->updated_at) { $instVerifDateTime = $ia->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                    }
                                }
                            } else {
                                $instanceSheetData[] = [$instRowCounter++, $displayQ, null];
                            }
                        } else {
                            $ia = TempUserActivityAnswersData::where('row_id', $row_id)
                                ->where('question_id', $aq->id)
                                ->where('activity_id', $activity->id)
                                ->where('same_answer_id', $groupId)
                                ->with('getUser', 'getVerifier', 'get_remark_info')
                                ->first();
                            if ($ia) {
                                if (!$instAuditorName && $ia->getUser) {
                                    $instAuditorName   = $ia->getUser->name;
                                    $instAuditDateTime = $ia->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                    $instAuditDate     = $ia->created_at->format('d-M-Y');
                                }
                                if (!$instVerifierName && $ia->verified_by && $ia->getVerifier) {
                                    $instVerifierName = $ia->getVerifier->name;
                                }
                                if (!$instVerifRemark) { $instVerifRemark = $this->verificationRemarkText($ia); } if (!$instVerifDateTime && in_array((int) $ia->status, [4, 5]) && $ia->updated_at) { $instVerifDateTime = $ia->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (in_array($aq->question_type, ["File Upload", "Image", "Audio"])) {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $this->imageAnswerUrl($ia)];
                                } elseif ($aq->question_type == "Location" && empty($ia->user_answer) && $ia->latitude && $ia->longitude) {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $ia->latitude . ', ' . $ia->longitude];
                                } elseif (in_array($aq->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                                    $scanHeaders = $aq->getOptions->pluck('option')->map('trim')->filter()->values()->all();
                                    foreach ($scanHeaders as $scanHeader) {
                                        $instanceSheetData[] = [$instRowCounter++, $displayQ . ' → ' . $scanHeader, $this->scanAnswerHeaderValue($ia->user_answer, $scanHeader)];
                                    }
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $this->scanAnswerExtraValue($ia->user_answer, $scanHeaders)];
                                } else {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $ia->user_answer];
                                }
                            } elseif (in_array($aq->question_type, ['Barcode', 'QR Code', 'RFID'])) {
                                $scanHeaders = $aq->getOptions->pluck('option')->map('trim')->filter()->values()->all();
                                foreach ($scanHeaders as $scanHeader) {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ . ' → ' . $scanHeader, null];
                                }
                                $instanceSheetData[] = [$instRowCounter++, $displayQ, null];
                            } else {
                                $instanceSheetData[] = [$instRowCounter++, $displayQ, null];
                            }
                        }
                    }

                    $instanceSheetData[] = [''];
                    $instanceSheetData[] = ['Auditor Name', $instAuditorName];
                    $instanceSheetData[] = ['Audit Date & Time', $instAuditDateTime];
                    $instanceSheetData[] = ['Verification Status', $verificationStatusShow];
                    $instanceSheetData[] = ['Verifier Name', $instVerifierName];
                    $instanceSheetData[] = ['Verification Remark', $instVerifRemark];
                    $instanceSheetData[] = ['Verification Date & Time', $instVerifDateTime];
                    $instanceSheetData[4][] = $instAuditDate;

                    $sheetData[] = [
                        'header' => [mb_substr($activity->activity_name . ' (' . $instLabel . ')', 0, 31)],
                        'data'   => $instanceSheetData,
                    ];
                }
            }
        }

        // One sheet per add-on activity with multiple instances.
        $usedDistInstTitles = [];
        foreach ($distInstanceActivities as $actData) {

            $base      = substr($actData['activityName'], 0, 31);
            $sheetTitle = $base;
            if (isset($usedDistInstTitles[$base])) {
                $usedDistInstTitles[$base]++;
                $suffix     = ' ' . $usedDistInstTitles[$base];
                $sheetTitle = substr($base, 0, 31 - strlen($suffix)) . $suffix;
            } else {
                $usedDistInstTitles[$base] = 1;
            }

            $instSheetRows = [
                [$dist_project_template->getProject->getCompanyInfo->company_name],
                [$actData['activityName']],
                [$dist_project_template->getMainHeader->template_head_name ?? 'Dist Name', $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name ?? 'Dist Code', $sub_header_val],
                ['Audit Date', $actData['auditDate'] ?? ''],
            ];

            $actHeader = ['Activity Name', 'Sr. No'];
            foreach ($actData['questions'] as $col) {
                $actHeader[] = $col['name'];
            }
            $actHeader[] = 'Auditor Name';
            $actHeader[] = 'Audit Date & Time';
            $actHeader[] = 'Verification Status';
            $actHeader[] = 'Verifier Name';
            $actHeader[] = 'Verification Remark';
            $actHeader[] = 'Verification Date & Time';

            $instSheetRows[] = $actHeader;
            $instSheetRows[] = array_pad(
                array_merge(['', ''], array_column($actData['questions'], 'relation')),
                count($actHeader), ''
            );
            foreach ($actData['rows'] as $r) {
                $instSheetRows[] = $r;
            }

            $sheetData[] = [
                'header'        => [$sheetTitle],
                'data'          => $instSheetRows,
                'relation_rows' => [count($instSheetRows) - count($actData['rows']) - 1],
            ];
        }

        // Handle other project templates (outlet templates)
        $other_project_templates = ProjectTemplate::with('getTemplate', 'getMainHeader', 'getSubHeader')
            ->where('project_id', $request->project_id)
            ->whereNot('id', $dist_project_template->id)
            ->get();

        foreach ($other_project_templates as $other_project_template) {
            $child_activites = [];
            if ($other_project_template->activityType) {
                $child_group_info = ActivityGroup::with('get_group_activities')->find($other_project_template->activity_group_name_id_or_activity_id);
                $child_activites = $child_group_info->get_group_activities->pluck('activity_id')->toArray();
            } else {
                if (!empty($other_project_template->activity_group_name_id_or_activity_id)) {
                    $child_activites[] = $other_project_template->activity_group_name_id_or_activity_id;
                }
            }

            if (!empty($child_activites)) {
                foreach ($child_activites as $child_activity) {
                    // Initialize sheet data for each activity in the child template
                    $child_activity_info = Activity::with('questions')->find($child_activity);
                    $activitySheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name],
                        [$child_activity_info->activity_name],
                        [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                        [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                        ['', ''] // Empty row for spacing
                    ];

                    // Get all questions with hierarchical structure for child activity
                    $allChildQuestions = Question::where('activity_id', $child_activity)
                        ->withoutOrphans()
                        ->orderBy('question_sequence')
                        ->get();

                    // Every answerable question at any depth (Multi Response sub-questions,
                    // conditional questions): one column each, named by the question itself,
                    // with its relation on the row under the header
                    $childFlatQuestions = []; // for answer lookup: ['id', 'question', 'pctx', 'relation']
                    foreach ($this->flattenActivityQuestions($allChildQuestions) as $fItem) {
                        $childFlatQuestions[] = ['id' => $fItem['question']->id] + $fItem;
                    }
                    $childContextsByQ = $this->contextsByQuestion($childFlatQuestions);
                    $questionsArray = array_map(fn($fq) => $fq['question']->question, $childFlatQuestions);

                    $activityQuesCount = count($questionsArray);

                    array_unshift($questionsArray, 'S.NO.', $other_project_template->getMainHeader->template_head_name, $other_project_template->getSubHeader->template_head_name);
                    array_push($questionsArray, 'Auditor Name', 'Audit Date & Time', 'Verification Status', 'Verifier Name', 'Verification Remark', 'Verification Date & Time');

                    $activitySheetData[] = $questionsArray;
                    $outletRelationRow = count($activitySheetData);
                    $activitySheetData[] = array_pad(
                        array_merge(['', '', ''], array_column($childFlatQuestions, 'relation')),
                        count($questionsArray), ''
                    );

                    $child_main_header_id = $other_project_template->main_header;
                    $child_sub_header_id = $other_project_template->sub_header;

                    $get_master_row = DB::table('project_template_name_values_new')->where('id', $row_id)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$other_project_template->master_head_id\"')) as main_value")
                        )
                        ->first();

                    $related_childs = DB::table('project_template_name_values_new')
                        ->where('project_template_id', $other_project_template->id)
                        ->where('distributor_id', $row_id)
                        ->get();

                    if ($related_childs->isEmpty()) {
                        $related_childs = DB::table('project_template_name_values_new')
                            ->where('project_template_id', $other_project_template->id)
                            ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [trim($get_master_row->main_value ?? '')])
                            ->get();
                    }

                    // Preload ALL answers for all outlet rows in one query
                    $outletRowIds = $related_childs->pluck('id')->toArray();
                    $outletAllAnswersColl = !empty($outletRowIds)
                        ? TempUserActivityAnswersData::whereIn('row_id', $outletRowIds)
                            ->where('activity_id', $child_activity)
                            ->with('getUser', 'getVerifier', 'get_remark_info')
                            ->get()
                        : collect();

                    // Index: [row_id][question_id][] = answer
                    $outletAnsIdx = [];
                    foreach ($outletAllAnswersColl as $_oa) {
                        $outletAnsIdx[$_oa->row_id][$_oa->question_id][] = $_oa;
                    }

                    // Preload submission group IDs per outlet row.
                    // Each element is either an int (activity_sequence for new-style instances)
                    // or ['sid' => same_answer_id] for old-style instances stored via same_answer_id
                    // (mirrors project_distributor_report's $distGroupIdsByAct — that function already
                    // handles both conventions; this one previously only checked activity_sequence,
                    // silently collapsing old-style multi-instance data down to a single group).
                    $outletGroupIdsByRow = [];
                    foreach ($outletRowIds as $_oRowId) {
                        $oActAnswers = collect($outletAnsIdx[$_oRowId] ?? [])->flatten(1);

                        $oNewSeqs = $oActAnswers->filter(fn($a) => (int)($a->activity_sequence ?? 0) > 0)
                            ->map(fn($a) => (int)$a->activity_sequence)->unique()->sort()->values();

                        if ($oNewSeqs->isNotEmpty()) {
                            $outletGroupIdsByRow[$_oRowId] = array_merge([0], $oNewSeqs->toArray());
                        } else {
                            $oSameIds = $oActAnswers->filter(fn($a) => !empty($a->same_answer_id))
                                ->map(fn($a) => $a->same_answer_id)->unique()->sort()->values();

                            if ($oSameIds->count() > 1) {
                                $oWithAnswers = $oActAnswers->filter(fn($a) => !empty($a->same_answer_id));
                                $oQsPerSid = [];
                                foreach ($oWithAnswers as $_a) {
                                    $oQsPerSid[$_a->same_answer_id][$_a->question_id] = true;
                                }
                                $oSingletonCount = collect($oQsPerSid)->filter(fn($qs) => count($qs) <= 1)->count();
                                $oGroupCount     = count($oQsPerSid);

                                if ($oGroupCount > 0 && ($oSingletonCount / $oGroupCount) > 0.5) {
                                    $outletGroupIdsByRow[$_oRowId] = [0];
                                } else {
                                    $outletGroupIdsByRow[$_oRowId] = $oSameIds->map(fn($id) => ['sid' => $id])->toArray();
                                }
                            } else {
                                $outletGroupIdsByRow[$_oRowId] = $oSameIds->isNotEmpty()
                                    ? $oSameIds->map(fn($id) => ['sid' => $id])->toArray()
                                    : [0];
                            }
                        }
                    }

                    // Collect per-outlet per-activity instance data for separate instance sheets.
                    $outletInstanceData = [];

                    $outletSeqFilter = fn(iterable $items, $visit) => collect($items)->filter(
                        fn($a) => is_array($visit)
                            ? $a->same_answer_id == $visit['sid']
                            : ((int)$visit > 0
                                ? (int)($a->activity_sequence ?? 0) === (int)$visit
                                : (int)($a->activity_sequence ?? 0) === 0)
                    );

                    $isOutletActivityAddOn = $other_project_template->activity_add_on == 1;

                    foreach ($related_childs as $index => $related_child) {
                        $child_headers = DB::table('project_template_name_values_new')->where('id', $related_child->id)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_main_header_id\"')) as main_value"),
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_sub_header_id\"')) as sub_value")
                            )
                            ->first();

                        $child_main_header = $child_headers->main_value ?? '';
                        $child_sub_header = $child_headers->sub_value ?? '';

                        // Use preloaded index — no DB query
                        $check_any_row_answer = collect($outletAnsIdx[$related_child->id] ?? [])->flatten(1)->first();

                        if ($check_any_row_answer) {
                            if ($row_item_helper !== "unfilled") {
                                $verificationOutletStatus = $check_any_row_answer->status;
                                $verificationOutletStatusShow = "Pending";
                                if ($verificationOutletStatus == 4) {
                                    $verificationOutletStatusShow = "Rejected";
                                } elseif ($verificationOutletStatus == 5) {
                                    $verificationOutletStatusShow = "Approved";
                                }

                                $outletRowRenderStatus = 0;
                                if ($verificationDataHelper == "all") {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "pending" && $check_any_row_answer->status == 0) {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "approved" && $check_any_row_answer->status == 5) {
                                    $outletRowRenderStatus = 1;
                                } elseif ($verificationDataHelper == "rejected" && $check_any_row_answer->status == 4) {
                                    $outletRowRenderStatus = 1;
                                }

                                if ($outletRowRenderStatus) {
                                    $answers = [$index + 1, $child_main_header, $child_sub_header];
                                    $auditorNameChild = '';
                                    $auditDateTimeChild = '';

                                    // Process each question using preloaded answers (no per-question DB queries)
                                    foreach ($childFlatQuestions as $flatQItem) {
                                        $question = $flatQItem['question'];
                                        $_oPool   = collect($outletAnsIdx[$related_child->id][$question->id] ?? []);

                                        if ($question->question_type == 'Subjective') {
                                            $user_answers = $_oPool->sortBy('created_at')->values();

                                            if ($user_answers->isNotEmpty()) {
                                                foreach ($user_answers as $ansIndex => $userAnswer) {
                                                    $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                                                    if ($ansIndex == 0) {
                                                        $answers[] = $isUrlOrPath ? $this->imageAnswerUrl($userAnswer) : $userAnswer->user_answer;
                                                    } else {
                                                        $existingValue = end($answers);
                                                        $answers[key($answers)] = $existingValue . "\n" . ($isUrlOrPath ? $this->imageAnswerUrl($userAnswer) : $userAnswer->user_answer);
                                                    }

                                                    if (!$auditorNameChild && $userAnswer->getUser) {
                                                        $auditorNameChild = $userAnswer->getUser->name;
                                                    }
                                                    if (!$auditDateTimeChild) {
                                                        $auditDateTimeChild = $userAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                                    }
                                                }
                                            } else {
                                                $answers[] = '';
                                            }
                                        } else {
                                            $user_answer = $this->pickContextAnswer($_oPool, $flatQItem['pctx'], $childContextsByQ[$question->id] ?? []);

                                            if ($user_answer) {
                                                if (!$auditorNameChild && $user_answer->getUser) {
                                                    $auditorNameChild = $user_answer->getUser->name;
                                                }
                                                if (!$auditDateTimeChild) {
                                                    $auditDateTimeChild = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                                }

                                                if ($question->question_type == "File Upload" || $question->question_type == "Image" || $question->question_type == "Audio") {
                                                    $answers[] = $this->imageAnswerUrl($user_answer);
                                                } else {
                                                    $answers[] = $user_answer->user_answer;
                                                }
                                            } else {
                                                $answers[] = '';
                                            }
                                        }
                                    }

                                    // Use preloaded answers for verification details
                                    $lastAnswer = collect($outletAnsIdx[$related_child->id] ?? [])->flatten(1)->sortByDesc('created_at')->first();

                                    if ($lastAnswer) {
                                        $answers[] = $auditorNameChild ?: ($lastAnswer->getUser->name ?? '');
                                        $answers[] = $auditDateTimeChild ?: ($lastAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') ?? '');
                                        $answers[] = $verificationOutletStatusShow;
                                        $answers[] = $lastAnswer->verified_by ? ($lastAnswer->getVerifier->name ?? '') : '';
                                        $answers[] = $this->verificationRemarkText($lastAnswer);
                                        $answers[] = in_array((int) $lastAnswer->status, [4, 5]) && $lastAnswer->updated_at ? $lastAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') : '';
                                    }

                                    $activitySheetData[] = $answers;

                                    // Collect outlet instance data if activity_add_on is enabled and there are multiple instances.
                                    if ($isOutletActivityAddOn) {
                                        // Use preloaded group IDs — no DB query
                                        $outletGroupIds = $outletGroupIdsByRow[$related_child->id] ?? [];

                                        if (count($outletGroupIds) > 1) {
                                            $outletInstQCols = [];
                                            foreach ($childFlatQuestions as $fq) {
                                                $outletInstQCols[] = [
                                                    'name'     => $fq['question']->question,
                                                    'relation' => $fq['relation'],
                                                    'pctx'     => $fq['pctx'],
                                                    'id'       => $fq['question']->id,
                                                    'type'     => $fq['question']->question_type,
                                                ];
                                            }

                                            $outletInstRows = [];
                                            foreach ($outletGroupIds as $oInstIdx => $oInstSeq) {
                                                $oRow = [$child_activity_info->activity_name, $oInstIdx + 1];
                                                $oAuditorName = ''; $oAuditDT = ''; $oVerifStatus = ''; $oVerifierName = ''; $oVerifRemark = ''; $oVerifDT = '';
                                                foreach ($outletInstQCols as $col) {
                                                    // In-memory lookup from preloaded outlet answers
                                                    $_oInstPool = collect($outletAnsIdx[$related_child->id][$col['id']] ?? []);
                                                    $_oInstSeqd = $outletSeqFilter($_oInstPool, $oInstSeq);
                                                    if ($col['type'] === 'Subjective') {
                                                        $oas = $_oInstSeqd->sortBy('created_at')->values();
                                                        foreach ($oas as $oa) {
                                                            if (!$oAuditorName && $oa->getUser) { $oAuditorName = $oa->getUser->name; $oAuditDT = $oa->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                                            if (!$oVerifierName && $oa->verified_by && $oa->getVerifier) { $oVerifierName = $oa->getVerifier->name; }
                                                            if (!$oVerifRemark) { $oVerifRemark = $this->verificationRemarkText($oa); } if (!$oVerifDT && in_array((int) $oa->status, [4, 5]) && $oa->updated_at) { $oVerifDT = $oa->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                                            if (!$oVerifStatus) { $oVerifStatus = $oa->status == 5 ? 'Approved' : ($oa->status == 4 ? 'Rejected' : 'Pending'); }
                                                        }
                                                        $oRow[] = $oas->map(fn($a) => str_contains($a->user_answer, '/') ? $this->imageAnswerUrl($a) : $a->user_answer)->implode(' | ') ?: null;
                                                    } else {
                                                        $oa = $this->pickContextAnswer($_oInstSeqd, $col['pctx'], $childContextsByQ[$col['id']] ?? []);
                                                        if ($oa) {
                                                            if (!$oAuditorName && $oa->getUser) { $oAuditorName = $oa->getUser->name; $oAuditDT = $oa->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                                            if (!$oVerifierName && $oa->verified_by && $oa->getVerifier) { $oVerifierName = $oa->getVerifier->name; }
                                                            if (!$oVerifRemark) { $oVerifRemark = $this->verificationRemarkText($oa); } if (!$oVerifDT && in_array((int) $oa->status, [4, 5]) && $oa->updated_at) { $oVerifDT = $oa->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                                            if (!$oVerifStatus) { $oVerifStatus = $oa->status == 5 ? 'Approved' : ($oa->status == 4 ? 'Rejected' : 'Pending'); }
                                                            if (in_array($col['type'], ['File Upload', 'Image', 'Audio'])) { $oRow[] = $this->imageAnswerUrl($oa); }
                                                            elseif ($col['type'] === 'Location' && empty($oa->user_answer) && $oa->latitude) { $oRow[] = $oa->latitude . ', ' . $oa->longitude; }
                                                            else { $oRow[] = $oa->user_answer; }
                                                        } else { $oRow[] = null; }
                                                    }
                                                }
                                                $oRow[] = $oAuditorName; $oRow[] = $oAuditDT; $oRow[] = $oVerifStatus; $oRow[] = $oVerifierName; $oRow[] = $oVerifRemark; $oRow[] = $oVerifDT;
                                                $outletInstRows[] = $oRow;
                                            }

                                            $outletInstanceData[] = [
                                                'outletLabel'  => $child_main_header ?: ('Outlet ' . ($index + 1)),
                                                'activityName' => $child_activity_info->activity_name,
                                                'questions'    => $outletInstQCols,
                                                'rows'         => $outletInstRows,
                                            ];
                                        }
                                    }
                                }
                            }
                        } else {
                            // No answers found, add only the headers
                            $extraColumns = array_fill(0, $activityQuesCount, '');
                            if ($row_item_helper == "all" || $row_item_helper == "unfilled") {
                                $activitySheetData[] = array_merge([$index + 1, $child_main_header, $child_sub_header], $extraColumns, ['NA', '', '', '', '', '']);
                            }
                        }
                    }

                    // Add this child activity's data as a new sheet
                    $sheetData[] = [
                        'header'        => [mb_substr($child_activity_info->activity_name, 0, 31)],
                        'data'          => $activitySheetData,
                        'relation_rows' => [$outletRelationRow],
                    ];

                    // One sheet per outlet per activity — only when that outlet has multiple instances.
                    $usedOutletInstTitles = [];
                    foreach ($outletInstanceData as $oInstData) {
                        $base = substr($oInstData['outletLabel'] . ' - ' . $oInstData['activityName'], 0, 31);
                        $sheetTitle = $base;
                        if (isset($usedOutletInstTitles[$base])) {
                            $usedOutletInstTitles[$base]++;
                            $suffix     = ' ' . $usedOutletInstTitles[$base];
                            $sheetTitle = substr($base, 0, 31 - strlen($suffix)) . $suffix;
                        } else {
                            $usedOutletInstTitles[$base] = 1;
                        }

                        $oInstSheetRows = [
                            [$dist_project_template->getProject->getCompanyInfo->company_name],
                            [$other_project_template->getTemplate->template_name],
                            [$dist_project_template->getMainHeader->template_head_name ?? 'Dist Name', $main_header_val],
                        ];

                        $oActHeader = ['Activity Name', 'Sr. No'];
                        foreach ($oInstData['questions'] as $col) { $oActHeader[] = $col['name']; }
                        $oActHeader[] = 'Auditor Name'; $oActHeader[] = 'Audit Date & Time';
                        $oActHeader[] = 'Verification Status'; $oActHeader[] = 'Verifier Name';
                        $oActHeader[] = 'Verification Remark'; $oActHeader[] = 'Verification Date & Time';

                        $oInstSheetRows[] = [];
                        $oInstSheetRows[] = ['Outlet: ' . $oInstData['outletLabel']];
                        $oInstSheetRows[] = ['Activity: ' . $oInstData['activityName']];
                        $oInstSheetRows[] = $oActHeader;
                        $oRelationRow = count($oInstSheetRows);
                        $oInstSheetRows[] = array_pad(
                            array_merge(['', ''], array_column($oInstData['questions'], 'relation')),
                            count($oActHeader), ''
                        );
                        foreach ($oInstData['rows'] as $r) { $oInstSheetRows[] = $r; }

                        $sheetData[] = [
                            'header'        => [$sheetTitle],
                            'data'          => $oInstSheetRows,
                            'relation_rows' => [$oRelationRow],
                        ];
                    } // end foreach outletInstanceData
                } // end foreach child_activites
            } // end if child_activites
        } // end foreach other_project_templates

        $fileName = $dist_project_template->getProject->project_name . $dist_project_template->getTemplate->template_name . '.xlsx';
        return $this->downloadXlsxFast($sheetData, $fileName);
    }


    public function getReportMail($validated, $requestData, $user)
    {
//        dd($requestData);
        $start_date = $validated['from_date'];
        $end_date = $validated['to_date'];

        $startingQuestionOfOtherActivity = [];

        // this is to get the project and template head  to get the data of the project and template selected
        $projectTemplate = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('project_id', $validated['project_id'])->where('template_name_id', $validated['template_name_id'])->first();

        $template_heads = $projectTemplate->getTemplate->getTemplateHeads->toArray();
        $projectTemplateHeads = array_column($template_heads, 'template_head_name');
        $activity_questions_array = []; // this is to get the question of the activity related to that project and template
        foreach ($requestData['activity_id'] as $activity) {
            $checkingInDataAssigned = DataAssign::where('project_template_id', $projectTemplate->id)->where('activity_id', $activity)->first();
            // the below gives the activity name
            if ($checkingInDataAssigned !== null && isset($checkingInDataAssigned->activityName) && $checkingInDataAssigned->activityName !== null && isset($checkingInDataAssigned->activityName->questions)) {
                $activity_questions = $checkingInDataAssigned->activityName->questions;
            }
            $type = gettype($activity_questions);
            if ($type == "object") {
                $activity_questions_array[] = $activity_questions->toArray();
            } else {
                $activity_questions_array = [];
            }
        }
        foreach ($activity_questions_array as $activityQuestions) {
            foreach ($activityQuestions as $index => $question) {
                if ($index == 0) {

                    $startingQuestionOfOtherActivity[] = $question['id'];
                }
                $combinedHeaders[] = $question['question'];
                $activities_questions_id[] = $question['id'];
            }
        }
        $projectActivitiesHeaders = array_merge($projectTemplateHeads, $combinedHeaders);
        $header_data = [$projectActivitiesHeaders];
        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';
        $projectTemplateData = $projectTemplate->getProjectTemplateData;
        $templateHeadsCount = count($projectTemplate->getTemplate->getTemplateHeads);
        $dataCount = count($projectTemplateData);
        $counter = 0;
        $combinedData = [];

//        khushboo 22-05-25
        $pdfFilePaths = [];
//        khushboo 22-05-25


//        dd($projectTemplateData);
        foreach ($projectTemplateData as $projectTemplate_data) {
            if ($counter % $templateHeadsCount === 0) {
                $rowData = []; // Start a new row with the index
            }
//            dd($projectTemplate_data);
            $rowData[] = $projectTemplate_data->value;
            if ($counter % $templateHeadsCount === ($templateHeadsCount - 1) || $counter === ($dataCount - 1)) {
                $combinedData[] = $rowData; // Add the completed row to the combined data array
                $any_answer = 0;

                foreach ($activities_questions_id as $questionId) {
                    $user_answer = TempUserActivityAnswersData::with(['getUser'])
                        ->where("row_id", $projectTemplate_data->row_id)
                        ->where("question_id", '=', $questionId)
//                        ->where('template_id', $questionInfo->template_id)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->with('getUser', 'getQuestionInfo', 'get_remark_info')->first();
                    if ($user_answer) {

                        //khushboo 22-05-25
                        foreach ($requestData['activity_id'] as $activity) {

                            $pdfFilePath = $this->viewTemplateFile($projectTemplate_data->row_id, $activity);
                            $fileUrl = $pdfFilePath->original['file_url'];
                            $fileName = $pdfFilePath->original['file_name'];
                            $pdfFilePaths[] = [$fileUrl, $fileName];
                        }
                        //khushboo 22-05-25

                        $any_answer = 1;
                        if (in_array($questionId, $startingQuestionOfOtherActivity)) {
                            if ($user_answer->verified_by) {

                                $verifierDetails = User::find($user_answer->verified_by);
                                if ($verifierDetails) {
                                    $rowData[] = $verifierDetails->name;
                                } else {
                                    $rowData[] = "User (ID: " . $user_answer->verified_by . ") has been deleted";
                                }
                            } else {
                                $rowData[] = '';
                            }
                            if ($user_answer->status == 5) {
                                $rowData[] = "Verified";
                            } elseif ($user_answer->status == 4) {
                                $rowData[] = "Rejected";
                            } else {
                                $rowData[] = '';
                            }
                            $rowData[] = $user_answer->get_remark_info->remark;

                            if ($user_answer->getQuestionInfo->question_type == "Image" || $user_answer->getQuestionInfo->question_type == "File Upload" || $user_answer->getQuestionInfo->question_type == "Audio") {
                                $rowData[] = $this->imageAnswerUrl($user_answer);
                            } else {
                                $rowData[] = $user_answer->user_answer;
                            }
                        } else {
                            if ($user_answer->getQuestionInfo->question_type == "Image" || $user_answer->getQuestionInfo->question_type == "File Upload" || $user_answer->getQuestionInfo->question_type == "Audio") {
                                $rowData[] = $this->imageAnswerUrl($user_answer);
                            } else {
                                $rowData[] = $user_answer->user_answer;
                            }
                        }

                    }
                }
                $header_data[] = $rowData;
            }
            $counter++;
        }
        dd($pdfFilePaths);

    }

    public function viewTemplateFile($r, $a, $g = null)
    {

        $related_values = ProjectTemplateNameValuesNew::where('id', $r)->get();

        //khushboo 15-05-25
        $projectTemplateData = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('id', $related_values[0]->project_template_id)->first();
//         dd($projectTemplateData);

        $remarks = RemarkMaster::where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();
//         dd($projectTemplateData);
        //khushboo 15-05-25

        $activity_check = Activity::find($a);
//        dd($activity_check);
        // $remarks = RemarkMaster::where('status', 1)->get();

        $related_questions = Question::where('activity_id', $activity_check->id)->withoutOrphans()->orderBy('question_sequence', 'asc')->get();
//        dd($related_questions);


        $user_responses = TempUserActivityAnswersData::with('getUser')
            ->where('row_id', $r)
            ->where('activity_id', $activity_check->id)->get();

        $checkLatLongData = TempUserActivityAnswersData::where('row_id', $r)
            ->where('activity_id', $activity_check->id)->whereNotNull('latitude')->whereNotNull('longitude')->exists();
        $userLatLongData = [];

        if ($checkLatLongData) {
            $userLatLongData = TempUserActivityAnswersData::where('row_id', $r)
                ->where('activity_id', $activity_check->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get(['latitude', 'longitude'])
                ->toArray();
        }

        $auditorInfo = $user_responses[0];
        $auditDate = $user_responses[0]->created_at;
        $auditDate = Carbon::parse($auditDate)->format('d-m-Y');

        $auditTime = $user_responses[0]->created_at;
        $auditTime = Carbon::parse($auditTime)->format('H:i A');

        $templateJson = json_decode($related_values[0]->template_data_json, true);

        $mainHeaderValue = $templateJson[$projectTemplateData->main_header] ?? null;
        $subHeaderValue = $templateJson[$projectTemplateData->sub_header] ?? null;

//        dd($auditorInfo);
        $pdf = Pdf::loadView('masters.pdf_templates.verify_template', [
            'projectTemplateData' => $projectTemplateData,
            'related_questions' => $related_questions,
            'auditorInfo' => $auditorInfo,
            'auditDate' => $auditDate,
            'auditTime' => $auditTime,
            'user_responses' => $user_responses,
            'main_header' => $mainHeaderValue,
            'sub_header' => $subHeaderValue
        ]);


        // File name and path
        $fileName = 'Audit-' . uniqid() . '.pdf';
        $filePath = public_path('temp/' . $fileName);
//        dd($filePath);

        // Ensure the directory exists
        if (!file_exists(public_path('temp'))) {
            mkdir(public_path('temp'), 0755, true);
        }

        // Save the PDF to the public/temp folder
        $pdf->save($filePath);

        // Return the public URL to access the PDF
        $url = asset('temp/' . $fileName);

        return response()->json([
            'file_url' => $url,
            'file_name' => $fileName
        ]);

//        return $pdf->download('Audit-' . '.pdf');

    }

    // ── Excel generation helpers ─────────────────────────────────────────────

    /**
     * Build a sheet name that fits Excel's 31-char limit.
     * Keeps the full activity name; truncates the template prefix only as needed.
     * If the activity name alone exceeds 31 chars it is itself truncated.
     */
    private function makeSheetName(string $templateName, string $activityName, string $suffix = ''): string
    {
        $limit = 31;
        $suffix = $suffix ? ' ' . $suffix : '';
        $actFull = $activityName . $suffix;

        if (mb_strlen($actFull) >= $limit) {
            return mb_substr($actFull, 0, $limit);
        }

        $prefixBudget = $limit - mb_strlen($actFull) - 1; // -1 for space separator
        if ($prefixBudget <= 0) {
            return mb_substr($actFull, 0, $limit);
        }

        $prefix = mb_strlen($templateName) > $prefixBudget
            ? mb_substr($templateName, 0, $prefixBudget)
            : $templateName;

        return $prefix . ' ' . $actFull;
    }

    /**
     * Generate XLSX via the Python CGI script (called through curl — no exec needed).
     * Returns the temp file path on success, null on failure.
     */
    private function generateXlsxViaPython(string $mainTitle, array $mainRows, array $extraSheets, string $baseUrl): ?string
    {
        // Write JSON data to a temp file (avoid large POST body)
        $jsonFile = tempnam(sys_get_temp_dir(), 'rpt_json_') . '.json';
        file_put_contents($jsonFile, json_encode([
            'main_sheet'   => ['title' => $mainTitle, 'rows' => $mainRows],
            'extra_sheets' => $extraSheets,
        ], JSON_UNESCAPED_UNICODE));

        // CGI endpoint URL — call via localhost to skip TLS overhead
        $cgiUrl = $baseUrl . '/cgi-bin/gen_xlsx.py';
        $secret = config('app.key');

        $payload = json_encode([
            'file'     => $jsonFile,
            'secret'   => $secret,
            'base_url' => $baseUrl,
        ]);

        $ch = curl_init($cgiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        @unlink($jsonFile);

        // Validate response looks like an XLSX (PK magic bytes)
        if ($httpCode === 200 && is_string($response) && strlen($response) > 100
            && substr($response, 0, 2) === 'PK') {
            $xlsxTmp = tempnam(sys_get_temp_dir(), 'rpt_py_') . '.xlsx';
            file_put_contents($xlsxTmp, $response);
            return $xlsxTmp;
        }

        \Log::warning('Python CGI xlsx failed', ['http' => $httpCode, 'curl_err' => $curlErr]);
        return null;
    }

    /**
     * Generate XLSX via PhpSpreadsheet (reliable fallback — already installed).
     */
    /** Generate XLSX using FastXlsxWriter (no PhpSpreadsheet) — 10-50x faster. */
    private function generateXlsxViaPHP(string $mainTitle, array $mainRows, array $extraSheets, string $baseUrl, int $mainHeaderRows = 1, ?array $reportMeta = null): string
    {
        $writer  = new \App\Services\FastXlsxWriter($baseUrl);
        if ($reportMeta !== null) {
            $writer->enableReportHeader($reportMeta);
        }
        $writer->addMainSheet($mainTitle, $mainRows, $mainHeaderRows);
        foreach ($extraSheets as $sheet) {
            $writer->addExtraSheet($sheet['title'], $sheet['rows'], $sheet['relation_rows'] ?? []);
        }
        $xlsxTmp = tempnam(sys_get_temp_dir(), 'rpt_fast_') . '.xlsx';
        $writer->save($xlsxTmp);
        return $xlsxTmp;
    }

    /** Generate XLSX from multi-sheet data using FastXlsxWriter and return a download response. */
    private function downloadXlsxFast(array $sheetData, string $fileName): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $baseUrl = config('app.url');
        $writer  = new \App\Services\FastXlsxWriter($baseUrl);
        foreach ($sheetData as $sheet) {
            $rows = $sheet['data'];
            // Compute max data-column width from rows ri>=5 (header + data rows)
            $dataWidth = 2;
            foreach ($rows as $ri => $row) {
                if ($ri >= 5 && is_array($row) && count($row) > $dataWidth) {
                    $dataWidth = count($row);
                }
            }
            // Pad meta rows (ri=1..5) to data width so fill extends across all data columns
            for ($ri = 1; $ri <= 5; $ri++) {
                if (isset($rows[$ri]) && is_array($rows[$ri])) {
                    while (count($rows[$ri]) < $dataWidth) {
                        $rows[$ri][] = '';
                    }
                }
            }
            $writer->addExtraSheet($sheet['header'][0], $rows, $sheet['relation_rows'] ?? [], $sheet['relation_cols'] ?? []);
        }
        $xlsxTmp = tempnam(sys_get_temp_dir(), 'rpt_dist_') . '.xlsx';
        $writer->save($xlsxTmp);
        return response()->download($xlsxTmp, $fileName)->deleteFileAfterSend(true);
    }
}
