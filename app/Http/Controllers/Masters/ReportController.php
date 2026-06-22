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
    public function index()
    {
        // $projects = Project::all();
        // $template_names = TemplateName::all();
        $companies = Company::all();
        $activities = Activity::all();
        $activity_groups = ActivityGroup::all();

        //khushboo 02-04-2025
        $currentuser = User::find(Auth::user()->id);
        $currentUserRole = $currentuser->getRoleNames()->first();

        if (Auth::user()->is_agency_user == 1 || $currentUserRole == 'Agency') {
            // $projects = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            // $projectsIds = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->pluck('id')->toArray();
            // $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)->pluck('template_name_id')->toArray();
            // $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        } else if ($currentUserRole == "Super Admin") {
            $template_names = TemplateName::all();
            $projects = Project::all();
        } else if ($currentuser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('company_user_id', $currentuser->id);
                })
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
            $projects = Project::whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }
        // dd($projects);
        //khushboo 02-04-2025

        return view('masters.reports.index', compact('projects', 'template_names', 'companies', 'activities', 'activity_groups'));
    }

    public function project_report()
    {
        // $projects = Project::all();
        // $template_names = TemplateName::all();
        $companies = Company::all();
        $activities = Activity::all();
        $activity_groups = ActivityGroup::all();

        //khushboo 03-04-2025
        $currentuser = User::find(Auth::user()->id);
        $currentUserRole = $currentuser->getRoleNames()->first();
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
            $projects = Project::whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();

            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();

        } else if ($currentUserRole == "Super Admin") {
            // dd('ghg');
            $template_names = TemplateName::all();
            $projects = Project::all();
        } else if ($currentuser->getRoleNames()->first() == 'Company User') {
            $companies = [];
            $projects = Project::with(['getZone', 'getUnit', 'getCompanyInfo'])
                ->where('isCompanyApplicable', 1)
                ->whereHas('dataAssigns', function ($query) use ($currentuser) {
                    $query->where('company_user_id', $currentuser->id);
                })
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
            $projects = Project::whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }
        // dd($projects);
        //khushboo 03-04-2025
        // dd($projects, Auth::user()->id, $currentuser->getRoleNames()->first());

        return view('masters.reports.project_report', compact('projects', 'template_names', 'companies', 'activities', 'activity_groups'));
    }


    public function get_report_old_march(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'data_get_helper' => 'required'
        ]);
        // dd($request->all());
        $start_date = $validated['from_date'];
        $end_date = $validated['to_date'];
        $additionalHeaders = [];
        $show_auditor = 0;
        if ($request->has('auditor_details')) {
            $additionalVerifierHeaders = ['Auditor Name', 'Auditor Contact No.', 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
            $show_auditor = 1;
        } else {
            $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
        }
//        $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time'];
        $show_user = 0;
        $show_date = 0;
        $show_time = 0;
        $startingQuestionOfOtherActivity = [];
        if ($request->has('get_mail')) {
            $requestData = $request->all();
            $g = GenerateReportJob::dispatch($validated, $requestData, $user);
            // $job = new GenerateReportJob($validated, $requestData, $user);
            // $job->handle();
            return back()->with(['message' => "You will get the report in mail shortly"]);
        }
        // this is to check if we want to details of the user in the report
        if ($request->has('user_details')) {
            $additionalHeaders[] = 'User';
            $show_user = 1;
        }
        // this is to get the date of the answer submitted
        if ($request->has('date_required')) {
            $additionalHeaders[] = 'Date';
            $show_date = 1;
        }
        // this to get the time in the report
        if ($request->has('time_required')) {
            $additionalHeaders[] = 'Time';
            $show_time = 1;
        }
        // this is to get the project and template head  to get the data of the project and template selected
        $projectTemplate = ProjectTemplate::where('project_id', $validated['project_id'])->where('template_name_id', $validated['template_name_id'])->first();
        //        $templateHeads = [];
        //        $templatesQuestions = [];
        $template_heads = $projectTemplate->getTemplate->getTemplateHeads->toArray();
        $projectTemplateHeads = array_column($template_heads, 'template_head_name');
        $activity_questions_array = []; // this is to get the question of the activity related to that project and template
        foreach ($request->activity_id as $activity) {
            $checkingInDataAssigned = DataAssign::where('project_template_id', $projectTemplate->id)->where('activity_id', $activity)->first();
            // the below gives the activity name
            if ($checkingInDataAssigned !== null && isset($checkingInDataAssigned->activityName) && $checkingInDataAssigned->activityName !== null && isset($checkingInDataAssigned->activityName->questions)) {
                $activity_questions = $checkingInDataAssigned->activityName->questions;
            } else {
                // this condition is added to download the report when the project activity is not assigned to any user
                $activityInfo = Activity::find($activity);
                $activity_questions = $activityInfo->questions;
            }
            $type = gettype($activity_questions);
            if ($type == "object") {
                $activity_questions_array[] = $activity_questions->toArray();
            } else {
                $activity_questions_array = [];
            }
        }

        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';
        $projectTemplateData = $projectTemplate->getProjectTemplateData;
        $templateHeadsCount = count($projectTemplate->getTemplate->getTemplateHeads);
        $projectTemplateRowIds = $projectTemplate->getProjectTemplateData->pluck('id')->unique()->values()->toArray();
//        dd($projectTemplateRowIds);

        foreach ($activity_questions_array as $activityQuestions) {

            foreach ($activityQuestions as $index => $question) {
//                dd($question);
                if ($index == 0) {
                    foreach ($additionalVerifierHeaders as $additionalVerifierHeader) {
                        $combinedHeaders[] = $additionalVerifierHeader;
                    }
                    foreach ($additionalHeaders as $header) {
                        $combinedHeaders[] = $header;
                    }
                    $startingQuestionOfOtherActivity[] = $question['id'];
                }
                if (!empty($question)) {

                    if ($question['question_type'] == 'Subjective') {
                        $combinedHeaders[] = $question['question'];
                        // Check if any subjective answer has a file path (contains '/')
                        // $hasFile = TempUserActivityAnswersData::where('question_id', $question['id'])
                        //     ->where('activity_id', $question['activity_id'])
                        //     ->whereIn('row_id', $projectTemplateRowIds)
                        //     ->whereDate('created_at', '>=', $start_date)
                        //     ->whereDate('created_at', '<=', $end_date)
                        //     ->where(function ($query) {
                        //         $query->where('user_answer', 'like', '%/%'); // crude check for file paths
                        //     })->exists();

                        // if ($hasFile) {
                        //     $combinedHeaders[] = $question['question'] . ' - File';
                        // }
                        // $activities_questions_id[] = $question['id'];

                        $combinedHeaders[] = $question['question'];
                        $combinedHeaders[] = $question['question'] . ' - Dependent';

                        $activities_questions_id[] = $question['id'];
                    } else {
                        $combinedHeaders[] = $question['question'];
                        $activities_questions_id[] = $question['id'];
                    }

                }

            }
        }

        $projectActivitiesHeaders = array_merge($projectTemplateHeads, $combinedHeaders);
        $header_data = [$projectActivitiesHeaders];
        $counter = 0;
        $combinedData = [];
        $dataCount = count($projectTemplateData);

//        dd($projectTemplateData);
        foreach ($projectTemplateData as $projectTemplate_data) {
//            if ($counter % $templateHeadsCount === 0) {
            $rowData = []; // Start a new row with the index
//            }
            $get_json_data = json_decode($projectTemplate_data->template_data_json);
            foreach ($get_json_data as $key => $value) {
                $rowData[] = $value;
            }

            $combinedData[] = $rowData; // Add the completed row to the combined data array
            $any_answer = 0;
            $subjective_answers = [];
            foreach ($activities_questions_id as $questionId) {
                $questionData = Question::find($questionId);

                if ($questionData->question_type == "Subjective") {

                    $user_answer = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                        ->where("question_id", '=', $questionId)
                        //->where('template_id', $questionInfo->template_id)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->with('getUser', 'getQuestionInfo')->get();

                    if (!empty($user_answer)) {
                        foreach ($user_answer as $userAnswer) {
                            $d_value = $userAnswer->user_answer;
                            // $extension = strtolower(pathinfo($user_answer->user_answer, PATHINFO_EXTENSION));
                            $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                            if ($isUrlOrPath) {
                                $rowData[] = $this->imageAnswerUrl($userAnswer);
                            } else {
                                $rowData[] = $userAnswer->user_answer;
                            }
                        }
                    }

                } else {

                    $user_answer = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                        ->where("question_id", '=', $questionId)
                        //->where('template_id', $questionInfo->template_id)
                        ->whereDate('created_at', '>=', $start_date)
                        ->whereDate('created_at', '<=', $end_date)
                        ->with('getUser', 'getQuestionInfo')
                        ->first();

//                        dd($user_answer, $projectTemplate_data);
                    if ($user_answer) {

                        $any_answer = 1;
                        if (in_array($questionId, $startingQuestionOfOtherActivity)) {

                            //Auditor Name and Contact
                            if ($show_auditor == 1) {
                                $rowData[] = $user_answer->getUser->name;
                                $rowData[] = $user_answer->getUser->mobile;
                            }

                            //verifier details
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

                            $get_remark = RemarkMaster::find($user_answer->remark);
                            if ($get_remark) {
                                $rowData[] = $get_remark->remark;
                            } else {
                                if (isset($user_answer->remark)) {
                                    $rowData[] = $user_answer->remark . ' is deleted';
                                } else {
                                    $rowData[] = '';
                                }
                            }

                            //khushboo 12-05-25
                            // ✅ For verification date and time
                            $rowData[] = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                            //khushboo 12-05-25

                            // Latitude & Longitude from answer data
                            $rowData[] = $user_answer->latitude ?? '';
                            $rowData[] = $user_answer->longitude ?? '';

                            if ($show_user) {
                                if ($user_answer->getUser) {
                                    $rowData[] = $user_answer->getUser->name;
                                } else {
                                    $rowData[] = "User (ID: " . $user_answer->user_id . ") has been deleted";
                                }
                            }
                            if ($show_date) {

                                $rowData[] = $user_answer->created_at
                                    ->setTimezone('Asia/Kolkata') // Convert to IST
                                    ->format('d-M-Y H:i');
                                // $rowData[] = Carbon::parse($user_answer->created_at)->format('d-m-y');
                            }
                            if ($show_time) {
                                // $rowData[] = $user_answer->created_at->format('H:i:s');
                                $rowData[] = $user_answer->created_at
                                    ->setTimezone('Asia/Kolkata') // Convert to IST
                                    ->format('d-M-Y H:i');
                            }
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
                            if ($user_answer->getQuestionInfo->question_type == "Subjective") {
                                $d_value = $user_answer->user_answer;
                                $extension = strtolower(pathinfo($d_value, PATHINFO_EXTENSION));
                                $isUrlOrPath = str_contains($d_value, '/');
                                if ($isUrlOrPath) {
                                    $rowData[] = $this->imageAnswerUrl($user_answer);
                                } else {
                                    $rowData[] = $user_answer->user_answer;
                                }
                            }
                        }
                    } else {
                        $rowData[] = '';
                    }
                }

            }

//                dd($rowData);
            if ($request->data_get_helper == "all") {
                $header_data[] = $rowData;
            } elseif ($request->data_get_helper == "filled") {
                if ($any_answer == 1) {
                    $header_data[] = $rowData;
                }
            } else {
                if ($any_answer == 0) {
                    $header_data[] = $rowData;
                }
            }
        }
//        dd($header_data);
        return Excel::download(new class($header_data) implements FromArray {
            private $header_data;

            public function __construct(array $data)
            {
                $this->header_data = $data;
            }

            public function array(): array
            {
                return $this->header_data;
            }
        }, $fileName);
    }

    public function get_report_old_new(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'data_get_helper' => 'required'
        ]);
        // dd($request);
        $start_date = $validated['from_date'];
        $end_date = $validated['to_date'];
        $additionalHeaders = [];
        $show_auditor = 0;
        if ($request->has('auditor_details')) {
            $additionalVerifierHeaders = ['Auditor Name', 'Auditor Contact No.', 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
            $show_auditor = 1;
        } else {
            $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
        }
//        $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time'];
        $show_user = 0;
        $show_date = 0;
        $show_time = 0;
        $startingQuestionOfOtherActivity = [];
        if ($request->has('get_mail')) {
            $requestData = $request->all();
            // $g = GenerateReportJob::dispatch($validated, $requestData, $user);
            $job = new GenerateReportJob($validated, $requestData, $user);
            $job->handle();
            return back()->with(['message' => "You will get the report in mail shortly"]);
        }
        // this is to check if we want to details of the user in the report
        if ($request->has('user_details')) {
            $additionalHeaders[] = 'User';
            $show_user = 1;
        }
        // this is to get the date of the answer submitted
        if ($request->has('date_required')) {
            $additionalHeaders[] = 'Date';
            $show_date = 1;
        }
        // this to get the time in the report
        if ($request->has('time_required')) {
            $additionalHeaders[] = 'Time';
            $show_time = 1;
        }
        // this is to get the project and template head  to get the data of the project and template selected
        $projectTemplate = ProjectTemplate::where('project_id', $validated['project_id'])->where('template_name_id', $validated['template_name_id'])->first();
        //        $templateHeads = [];
        //        $templatesQuestions = [];
        $template_heads = $projectTemplate->getTemplate->getTemplateHeads->toArray();
        $projectTemplateHeads = array_column($template_heads, 'template_head_name');
        $activity_questions_array = []; // this is to get the question of the activity related to that project and template

        foreach ($request->activity_id as $activity) {
            $checkingInDataAssigned = DataAssign::where('project_template_id', $projectTemplate->id)->where('activity_id', $activity)->first();
            // the below gives the activity name
            if ($checkingInDataAssigned !== null && isset($checkingInDataAssigned->activityName) && $checkingInDataAssigned->activityName !== null && isset($checkingInDataAssigned->activityName->questions)) {
                $activity_questions = $checkingInDataAssigned->activityName->questions;
            } else {
                // this condition is added to download the report when the project activity is not assigned to any user
                $activityInfo = Activity::find($activity);
                $activity_questions = $activityInfo->questions;
            }
            $type = gettype($activity_questions);
            if ($type == "object") {
                $activity_questions_array[] = $activity_questions->toArray();
            } else {
                $activity_questions_array = [];
            }
        }

        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';
        $projectTemplateData = $projectTemplate->getProjectTemplateData;
//        dd($projectTemplateData);
        $templateHeadsCount = count($projectTemplate->getTemplate->getTemplateHeads);
        $projectTemplateRowIds = $projectTemplate->getProjectTemplateData->pluck('id')->unique()->values()->toArray();
//        dd($projectTemplateRowIds);
//        dd($activity_questions_array);

        // Add "Activity Instance" as the first column in the combined section
        $combinedHeaders[] = 'Activity Instance';

        foreach ($activity_questions_array as $activityQuestions) {
            // Collect IDs of questions that are children of another question via parent_question_id.
            // These must not be added as top-level columns — they appear recursively under their parent.
            $allIdsInActivity = array_column($activityQuestions, 'id');
            $childQIds = Question::whereIn('parent_question_id', $allIdsInActivity)
                ->pluck('id')->toArray();

            foreach ($activityQuestions as $index => $question) {
                if ($index == 0) {
                    foreach ($additionalVerifierHeaders as $additionalVerifierHeader) {
                        $combinedHeaders[] = $additionalVerifierHeader;
                    }
                    foreach ($additionalHeaders as $header) {
                        $combinedHeaders[] = $header;
                    }
                    $startingQuestionOfOtherActivity[] = $question['id'];
                }

                if (!empty($question) && $question['is_parent'] == 1) {
                    // Skip questions that are already children of another — they'll appear via recursion
                    if (in_array($question['id'], $childQIds)) {
                        continue;
                    }
                    $this->buildReportHeaders(
                        Question::find($question['id']),
                        '',
                        $combinedHeaders,
                        $activities_questions_id
                    );
                }
            }
        }

//        dd($activities_questions_id);
        $projectActivitiesHeaders = array_merge($projectTemplateHeads, $combinedHeaders);
//        dd($projectActivitiesHeaders);
        $header_data = [$projectActivitiesHeaders];
        $counter = 0;
        $combinedData = [];
        $dataCount = count($projectTemplateData);

        foreach ($projectTemplateData as $projectTemplate_data) {
            $get_json_data = json_decode($projectTemplate_data->template_data_json);
            $templateValues = [];
            foreach ($get_json_data as $value) {
                $templateValues[] = $value;
            }

            $activityIds = $request->activity_id;

            // Pre-cache question→activity mapping to avoid per-question DB lookups.
            $questionActivityMap = Question::whereIn('id', $activities_questions_id)
                ->pluck('activity_id', 'id')->toArray();

            // Per-activity ordered same_answer_id lists at seq=0 (old-style data).
            // Different activities in a group may have been submitted in separate form sessions,
            // so they each have their own same_answer_id values. Grouping across activities
            // would create N×M rows instead of max(N,M) rows. We resolve this by using each
            // question's own activity's Nth same_answer_id for visit index N.
            $perActivityGroupIds = [];
            foreach ($activityIds as $actId) {
                $perActivityGroupIds[(int)$actId] = TempUserActivityAnswersData::where('activity_id', $actId)
                    ->where('row_id', $projectTemplate_data->id)
                    ->where(fn($q) => $q->whereNull('activity_sequence')->orWhere('activity_sequence', 0))
                    ->whereNotNull('same_answer_id')
                    ->distinct()->orderBy('same_answer_id')->pluck('same_answer_id')->toArray();
            }
            $maxOldVisits = count($perActivityGroupIds) > 0 ? max(array_map('count', $perActivityGroupIds)) : 0;
            if ($maxOldVisits === 0) $maxOldVisits = 1;

            // New-style instances: answers with activity_sequence > 0 (stored after our instance feature).
            $newStyleSeqs = TempUserActivityAnswersData::whereIn('activity_id', $activityIds)
                ->where('row_id', $projectTemplate_data->id)
                ->where('activity_sequence', '>', 0)
                ->distinct()->orderBy('activity_sequence')->pluck('activity_sequence')->toArray();

            $repeatInstanceLabels = ActivityRepeatInstance::whereIn('activity_id', $activityIds)
                ->where('row_id', $projectTemplate_data->id)
                ->orderBy('activity_sequence')
                ->pluck('instance_label')
                ->toArray();

            // Build the visits list: old-style (indexed by visit_index) + new-style (indexed by seq).
            $visits = [];
            for ($i = 0; $i < $maxOldVisits; $i++) {
                $label = $i === 0 ? 'Original' : ($repeatInstanceLabels[$i - 1] ?? ('Instance ' . ($i + 1)));
                $visits[] = ['label' => $label, 'visit_index' => $i, 'seq' => null];
            }
            foreach ($newStyleSeqs as $seq) {
                $label = $repeatInstanceLabels[$seq - 1] ?? ('Instance ' . $seq);
                $visits[] = ['label' => $label, 'visit_index' => null, 'seq' => $seq];
            }

            foreach ($visits as $visit) {
                $rowData = $templateValues;
                $rowData[] = $visit['label'];

                $any_answer = 0;
                foreach ($activities_questions_id as $questionId) {
                    $questionData = Question::find($questionId);
                    $qActId = (int)($questionActivityMap[$questionId] ?? $activityIds[0]);

                    // Build the per-question filter based on visit type.
                    $applyFilter = function ($q) use ($visit, $qActId, $perActivityGroupIds, $start_date, $end_date) {
                        if ($visit['seq'] !== null) {
                            $q->where('activity_sequence', $visit['seq']);
                        } else {
                            $nthGroupId = $perActivityGroupIds[$qActId][$visit['visit_index']] ?? null;
                            if ($nthGroupId !== null) {
                                $q->where('same_answer_id', $nthGroupId);
                            } else {
                                $q->whereDate('created_at', '>=', $start_date)
                                  ->whereDate('created_at', '<=', $end_date);
                            }
                        }
                    };

                    if ($questionData->question_type == "Subjective") {
                        $ansQuery = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                            ->where("question_id", $questionId);
                        $ansQuery->where($applyFilter);
                        $user_answer = $ansQuery->with('getUser', 'getQuestionInfo')->get();

                        if (!empty($user_answer)) {
                            foreach ($user_answer as $userAnswer) {
                                $isUrlOrPath = str_contains($userAnswer->user_answer, '/');
                                $rowData[] = $isUrlOrPath ? $this->imageAnswerUrl($userAnswer) : $userAnswer->user_answer;
                            }
                        }
                    } else {
                        $ansQuery = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                            ->where("question_id", $questionId);
                        $ansQuery->where($applyFilter);
                        $user_answer = $ansQuery->with('getUser', 'getQuestionInfo')->first();

                        if ($user_answer) {
                            $any_answer = 1;
                            if (in_array($questionId, $startingQuestionOfOtherActivity)) {
                                if ($show_auditor == 1) {
                                    $rowData[] = $user_answer->getUser->name;
                                    $rowData[] = $user_answer->getUser->mobile;
                                }
                                if ($user_answer->verified_by) {
                                    $verifierDetails = User::find($user_answer->verified_by);
                                    $rowData[] = $verifierDetails ? $verifierDetails->name : "User (ID: " . $user_answer->verified_by . ") has been deleted";
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
                                $get_remark = RemarkMaster::find($user_answer->remark);
                                if ($get_remark) {
                                    $rowData[] = $get_remark->remark;
                                } else {
                                    $rowData[] = (isset($user_answer->remark) && $user_answer->remark) ? $user_answer->remark . ' is deleted' : '';
                                }
                                // Verification date only when actually verified/rejected
                                $rowData[] = in_array($user_answer->status, [4, 5])
                                    ? $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i')
                                    : '';
                                $rowData[] = $user_answer->latitude ?? '';
                                $rowData[] = $user_answer->longitude ?? '';
                                if ($show_user) {
                                    $rowData[] = $user_answer->getUser ? $user_answer->getUser->name : "User (ID: " . $user_answer->user_id . ") has been deleted";
                                }
                                if ($show_date) {
                                    $rowData[] = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                }
                                if ($show_time) {
                                    $rowData[] = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                }
                                if (in_array($user_answer->getQuestionInfo->question_type, ["Image", "File Upload", "Audio"])) {
                                    $rowData[] = $this->imageAnswerUrl($user_answer);
                                } else {
                                    $rowData[] = $user_answer->user_answer;
                                }
                            } else {
                                if (in_array($user_answer->getQuestionInfo->question_type, ["Image", "File Upload", "Audio"])) {
                                    $rowData[] = $this->imageAnswerUrl($user_answer);
                                } else {
                                    $rowData[] = $user_answer->user_answer;
                                }
                                if ($user_answer->getQuestionInfo->question_type == "Subjective") {
                                    $isUrlOrPath = str_contains($user_answer->user_answer, '/');
                                    $rowData[] = $isUrlOrPath ? $this->imageAnswerUrl($user_answer) : $user_answer->user_answer;
                                }
                            }
                        } else {
                            $rowData[] = '';
                        }
                    }
                }

                if ($request->data_get_helper == "all") {
                    $header_data[] = $rowData;
                } elseif ($request->data_get_helper == "filled") {
                    if ($any_answer == 1) {
                        $header_data[] = $rowData;
                    }
                } else {
                    if ($any_answer == 0) {
                        $header_data[] = $rowData;
                    }
                }
            }
        }
//        dd($header_data);
        return Excel::download(new class($header_data) implements FromArray {
            private $header_data;

            public function __construct(array $data)
            {
                $this->header_data = $data;
            }

            public function array(): array
            {
                return $this->header_data;
            }
        }, $fileName);
    }


    /**
     * Recursively build report column headers and collect question IDs.
     * Outlet-type questions have no stored answer — they are skipped as a column
     * and their sub-questions are added instead (prefixed with the Outlet name).
     *
     * @param Question $question
     * @param array    &$headers      flat list of column header strings
     * @param array    &$questionIds  flat list of question IDs whose answers are fetched
     * @param string   $prefix        e.g. "Outlet Section Name"  (empty for top-level)
     */
    /**
     * Recursively flatten questions including Outlet sub-questions into a flat array.
     * Each entry: ['question' => Question, 'prefix' => string, 'is_outlet' => bool]
     * Used by project_distributor_report (vertical layout).
     */
    private function addOutletSubQuestionsFlat(Question $parent, array &$list, string $prefix): void
    {
        $subLinks = QuestionSubQuestion::where('parent_question_id', $parent->id)
            ->orderBy('sequence')
            ->with('childQuestion')
            ->get();
        foreach ($subLinks as $link) {
            if (!$link->childQuestion) continue;
            $child = $link->childQuestion;
            if ($child->question_type === 'Multi Response') {
                $this->addOutletSubQuestionsFlat($child, $list, $prefix . ' → ' . $child->question);
            } else {
                $list[] = ['question' => $child, 'prefix' => $prefix, 'is_outlet' => false];
            }
        }
    }


    private function buildOutletQuestionHeaders(Question $question, array &$headers, array &$questionIds, string $prefix = ''): void
    {
        $displayName = $prefix ? $prefix . ' → ' . $question->question : $question->question;

        if ($question->question_type === 'Multi Response') {
            // Outlet itself has no stored answer — recurse into its sub-questions
            $subLinks = QuestionSubQuestion::where('parent_question_id', $question->id)
                ->orderBy('sequence')
                ->with('childQuestion')
                ->get();
            foreach ($subLinks as $link) {
                if ($link->childQuestion) {
                    $this->buildOutletQuestionHeaders($link->childQuestion, $headers, $questionIds, $displayName);
                }
            }
        } else {
            // Regular question — add as a column
            $headers[]     = $displayName;
            $questionIds[] = $question->id;
        }
    }

    /**
     * Recursively add a question (and all its parent_question_id children) to report headers.
     * Skips Multi Response questions (they are section labels, not answer columns).
     * Dropdown sub-questions that have their own children are shown with full hierarchical prefix.
     */
    private function buildReportHeaders(
        ?Question $question,
        string $prefix,
        array &$combinedHeaders,
        array &$activities_questions_id
    ): void {
        if (!$question) return;

        $displayName = $prefix ? $prefix . ' → ' . $question->question : $question->question;

        if ($question->question_type !== 'Multi Response') {
            $combinedHeaders[]        = $displayName;
            $activities_questions_id[] = $question->id;
        }

        // Recurse into parent_question_id children at all levels
        $children = Question::where('parent_question_id', $question->id)
            ->orderBy('question_sequence')
            ->get();
        foreach ($children as $child) {
            $this->buildReportHeaders($child, $displayName, $combinedHeaders, $activities_questions_id);
        }
    }

    public function get_report(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'from_date' => 'required',
            'to_date' => 'required',
            'data_get_helper' => 'required'
        ]);

        $start_date = $validated['from_date'];
        $end_date = $validated['to_date'];
        $additionalHeaders = [];
        $show_auditor = 0;

        if ($request->has('auditor_details')) {
            $additionalVerifierHeaders = ['Auditor Name', 'Auditor Contact No.', 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
            $show_auditor = 1;
        } else {
            $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time', 'latitude', 'longitude'];
        }

        $show_user = 0;
        $show_date = 0;
        $show_time = 0;

        if ($request->has('get_mail')) {
            $requestData = $request->all();
            GenerateReportJob::dispatch($validated, $requestData, $user);
            return back()->with(['message' => "You will get the report in mail shortly"]);
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

        // Get all question IDs for the selected activities
        $allQuestionIds = [];
        $questionSets        = []; // question IDs per activity set
        $questionDisplayNames = []; // display names per activity set (same order as $questionSets)

        foreach ($request->activity_id as $activityIndex => $activity) {
            $checkingInDataAssigned = DataAssign::where('project_template_id', $projectTemplate->id)
                ->where('activity_id', $activity)
                ->first();

            if ($checkingInDataAssigned !== null && isset($checkingInDataAssigned->activityName) &&
                $checkingInDataAssigned->activityName !== null && isset($checkingInDataAssigned->activityName->questions)) {
                $activity_questions = $checkingInDataAssigned->activityName->questions;
            } else {
                $activityInfo = Activity::find($activity);
                $activity_questions = $activityInfo->questions;
            }

            $questionsArray = $activity_questions->toArray();
            $questionSets[$activityIndex]        = [];
            $questionDisplayNames[$activityIndex] = [];

            // Build headers and collect question IDs
            foreach ($questionsArray as $index => $question) {
                if (!empty($question)) {
                    // For the first question of each activity, add metadata headers
                    if ($index == 0) {
                        // Add all metadata headers for this question set
                        if ($show_auditor == 1) {
                            $projectTemplateHeads[] = 'Auditor Name';
                            $projectTemplateHeads[] = 'Auditor Contact No.';
                        }
                        $projectTemplateHeads[] = 'Verifier Name';
                        $projectTemplateHeads[] = 'Status';
                        $projectTemplateHeads[] = 'Remark';
                        $projectTemplateHeads[] = 'Verification Date & Time';
                        $projectTemplateHeads[] = 'latitude';
                        $projectTemplateHeads[] = 'longitude';

                        if ($show_user) {
                            $projectTemplateHeads[] = 'User';
                        }

                        if ($show_date || $show_time) {
                            $projectTemplateHeads[] = 'Date';
                        }
                    }

                    // Collect all Outlet sub-child IDs to skip them in the top-level loop
                    $outletSubChildIds = [];
                    $toCheck = [$question['id']];
                    while (!empty($toCheck)) {
                        $childIdsBatch = QuestionSubQuestion::whereIn('parent_question_id', $toCheck)
                            ->pluck('child_question_id')->toArray();
                        $newOnes = array_diff($childIdsBatch, $outletSubChildIds);
                        if (empty($newOnes)) break;
                        $outletSubChildIds = array_merge($outletSubChildIds, $newOnes);
                        $toCheck = array_values($newOnes);
                    }

                    // Handle question headers
                    if ($question['is_parent'] == 1 && !in_array($question['id'], $outletSubChildIds)) {
                        $questionModel = Question::find($question['id']);

                        if ($question['question_type'] === 'Multi Response') {
                            // Outlet: no answer column — recurse into sub-questions via helper
                            $this->buildOutletQuestionHeaders($questionModel, $projectTemplateHeads, $allQuestionIds);
                            // Also add them to the current question set + capture display names
                            $setHeaders = [];
                            $setIds     = [];
                            $this->buildOutletQuestionHeaders($questionModel, $setHeaders, $setIds);
                            foreach ($setIds as $sIdx => $sid) {
                                $questionSets[$activityIndex][] = $sid;
                                $questionDisplayNames[$activityIndex][$sid] = $setHeaders[$sIdx] ?? '';
                            }
                        } else {
                            // Normal parent question
                            $projectTemplateHeads[] = $question['question'];
                            $allQuestionIds[] = $question['id'];
                            $questionSets[$activityIndex][] = $question['id'];
                            $questionDisplayNames[$activityIndex][$question['id']] = $question['question'];

                            // Old-flow child questions (via parent_question_id)
                            $childQuestions = Question::where('parent_question_id', $question['id'])->get();
                            foreach ($childQuestions as $child) {
                                $displayName = $question['question'] . ' → ' . $child->question;
                                $projectTemplateHeads[] = $displayName;
                                $allQuestionIds[] = $child->id;
                                $questionSets[$activityIndex][] = $child->id;
                                $questionDisplayNames[$activityIndex][$child->id] = $displayName;

                                if ($child->question_type == 'Subjective') {
                                    $dependentQuestions = Question::where('parent_question_id', $child->id)->get();
                                    foreach ($dependentQuestions as $dependent) {
                                        $depName = $question['question'] . ' → ' . $child->question . ' → ' . $dependent->question;
                                        $projectTemplateHeads[] = $depName;
                                        $allQuestionIds[] = $dependent->id;
                                        $questionSets[$activityIndex][] = $dependent->id;
                                        $questionDisplayNames[$activityIndex][$dependent->id] = $depName;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';
        $projectTemplateData = $projectTemplate->getProjectTemplateData;
        $projectTemplateRowIds = $projectTemplate->getProjectTemplateData->pluck('id')->unique()->values()->toArray();
        $activityIds = $request->activity_id;

        // Main sheet: single activity block (original/first instance only — no horizontal repeat)
        $activityBlockHeads = array_slice($projectTemplateHeads, $templateColCount);
        $templateOnlyHeads  = array_slice($projectTemplateHeads, 0, $templateColCount);
        $header_data = [array_merge($templateOnlyHeads, $activityBlockHeads)];

        // Eager load all answers
        $allAnswers = TempUserActivityAnswersData::whereIn('row_id', $projectTemplateRowIds)
            ->whereIn('question_id', $allQuestionIds)
            ->with(['getUser', 'getQuestionInfo'])
            ->get()
            ->groupBy('row_id');

        $verifierIds = $allAnswers->flatten()->pluck('verified_by')->filter()->unique()->toArray();
        $verifiers = User::whereIn('id', $verifierIds)->get()->keyBy('id');
        $remarkIds = $allAnswers->flatten()->pluck('remark')->filter()->unique()->toArray();
        $remarks = RemarkMaster::whereIn('id', $remarkIds)->get()->keyBy('id');

        // Activity name map for instance summary sheets
        $activityNameMap = [];
        foreach ($activityIds as $idx => $actId) {
            $actObj = Activity::find($actId);
            $activityNameMap[$idx] = $actObj ? $actObj->activity_name : ('Activity ' . ($idx + 1));
        }

        // Instance summary data collected per distributor row
        $instanceSheets = [];

        foreach ($projectTemplateData as $projectTemplate_data) {
            $rowData = [];
            $get_json_data = json_decode($projectTemplate_data->template_data_json);
            foreach ($get_json_data as $value) {
                $rowData[] = $value;
            }

            $rowAnswers = $allAnswers[$projectTemplate_data->id] ?? collect();

            // Per-activity ordered same_answer_id lists
            $perActGroupIds = [];
            foreach ($activityIds as $actId) {
                $perActGroupIds[(int)$actId] = TempUserActivityAnswersData::where('activity_id', $actId)
                    ->where('row_id', $projectTemplate_data->id)
                    ->whereNotNull('same_answer_id')
                    ->distinct()->orderBy('same_answer_id')->pluck('same_answer_id')->toArray();
            }

            $any_answer = 0;

            // Main sheet: fill ORIGINAL (first) instance only for each activity
            foreach ($questionSets as $setIndex => $questionSet) {
                $actId = (int)($activityIds[$setIndex] ?? $activityIds[0]);
                $gid   = $perActGroupIds[$actId][0] ?? null;

                $visitAnswers = $gid !== null ? $rowAnswers->where('same_answer_id', $gid) : collect();

                $setAnswers = [];
                foreach ($questionSet as $qId) {
                    $ans = $visitAnswers->where('question_id', $qId)->first();
                    if ($ans) { $setAnswers[$qId] = $ans; $any_answer = 1; }
                }

                $answersInSet = collect($setAnswers);
                if ($answersInSet->isNotEmpty()) {
                    $firstAnswer = $answersInSet->first();
                    if ($show_auditor == 1) {
                        $rowData[] = $firstAnswer->getUser->name ?? '';
                        $rowData[] = $firstAnswer->getUser->mobile ?? '';
                    }
                    $rowData[] = $firstAnswer->verified_by
                        ? ($verifiers[$firstAnswer->verified_by]?->name ?? "User (ID: {$firstAnswer->verified_by}) has been deleted")
                        : '';
                    $rowData[] = $firstAnswer->status == 5 ? 'Verified' : ($firstAnswer->status == 4 ? 'Rejected' : '');
                    $rowData[] = $firstAnswer->remark
                        ? ($remarks[$firstAnswer->remark]?->remark ?? $firstAnswer->remark . ' is deleted')
                        : '';
                    $rowData[] = in_array($firstAnswer->status, [4, 5])
                        ? $firstAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') : '';
                    $rowData[] = $firstAnswer->latitude ?? '';
                    $rowData[] = $firstAnswer->longitude ?? '';
                    if ($show_user) {
                        $rowData[] = $firstAnswer->getUser
                            ? $firstAnswer->getUser->name : "User (ID: {$firstAnswer->user_id}) has been deleted";
                    }
                    if ($show_date || $show_time) {
                        $rowData[] = $firstAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                    }
                } else {
                    if ($show_auditor == 1) { $rowData[] = ''; $rowData[] = ''; }
                    $rowData[] = ''; $rowData[] = ''; $rowData[] = '';
                    $rowData[] = ''; $rowData[] = ''; $rowData[] = '';
                    if ($show_user) { $rowData[] = ''; }
                    if ($show_date || $show_time) { $rowData[] = ''; }
                }

                foreach ($questionSet as $questionId) {
                    if (isset($setAnswers[$questionId])) {
                        $answer = $setAnswers[$questionId];
                        $rowData[] = in_array($answer->getQuestionInfo->question_type, ['Image', 'File Upload', 'Audio'])
                            ? $this->imageAnswerUrl($answer)
                            : $this->formatAnswerValue($answer->user_answer, $answer->getQuestionInfo->question_type);
                    } else {
                        $rowData[] = '';
                    }
                }
            }

            if ($request->data_get_helper == "all") {
                $header_data[] = $rowData;
            } elseif ($request->data_get_helper == "filled") {
                if ($any_answer == 1) $header_data[] = $rowData;
            } else {
                if ($any_answer == 0) $header_data[] = $rowData;
            }

            // Collect instance data for distributors that have any activity with >1 instance
            $hasMulti = false;
            foreach ($activityIds as $actId) {
                if (count($perActGroupIds[(int)$actId]) > 1) { $hasMulti = true; break; }
            }
            if (!$hasMulti) continue;

            $jsonArr = json_decode($projectTemplate_data->template_data_json, true);
            $mainVal = $jsonArr[$projectTemplate->main_header] ?? '';
            $subVal  = $jsonArr[$projectTemplate->sub_header]  ?? '';
            $auditDateForSheet = '';
            // Group instance rows per activity so each activity gets its own header section
            $instActivitiesData = [];

            foreach ($questionSets as $setIndex => $questionSet) {
                $actId   = (int)($activityIds[$setIndex] ?? $activityIds[0]);
                $groupIds = $perActGroupIds[$actId];
                if (empty($groupIds)) continue;

                $actInstRows = [];
                foreach ($groupIds as $instIdx => $gid) {
                    $instAnswers = $rowAnswers->where('same_answer_id', $gid);
                    $instRow = [$activityNameMap[$setIndex], $instIdx + 1];

                    $instVerifierName = '';
                    $instStatus       = '';
                    $instRemark       = '';
                    $instVerifDT      = '';
                    $instLatitude     = '';
                    $instLongitude    = '';
                    $instUser         = '';
                    $instDate         = '';

                    foreach ($questionSet as $qId) {
                        $ans = $instAnswers->where('question_id', $qId)->first();
                        if ($ans) {
                            if (!$auditDateForSheet) {
                                $auditDateForSheet = $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y');
                            }
                            if (!$instUser && $ans->getUser) {
                                $instUser = $ans->getUser->name;
                                $instDate = $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                            }
                            if (!$instLatitude && $ans->latitude)  { $instLatitude  = $ans->latitude; }
                            if (!$instLongitude && $ans->longitude) { $instLongitude = $ans->longitude; }
                            if (!$instStatus) {
                                $instStatus = $ans->status == 5 ? 'Verified' : ($ans->status == 4 ? 'Rejected' : '');
                            }
                            if (!$instVerifierName && $ans->verified_by) {
                                $vd = $verifiers[$ans->verified_by] ?? null;
                                $instVerifierName = $vd ? $vd->name : "User (ID: {$ans->verified_by}) has been deleted";
                            }
                            if (!$instRemark && $ans->remark) {
                                $rd = $remarks[$ans->remark] ?? null;
                                $instRemark = $rd ? $rd->remark : $ans->remark . ' is deleted';
                            }
                            if (!$instVerifDT && in_array($ans->status, [4, 5])) {
                                $instVerifDT = $ans->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                            }

                            $instRow[] = in_array($ans->getQuestionInfo->question_type, ['Image', 'File Upload', 'Audio'])
                                ? $this->imageAnswerUrl($ans)
                                : $this->formatAnswerValue($ans->user_answer, $ans->getQuestionInfo->question_type);
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
                    $instRow[] = $instUser;
                    $instRow[] = $instDate;

                    $actInstRows[] = $instRow;
                }

                if (!empty($actInstRows)) {
                    $instActivitiesData[] = [
                        'activityName' => $activityNameMap[$setIndex],
                        'setIndex'     => $setIndex,
                        'questionSet'  => $questionSet,
                        'rows'         => $actInstRows,
                    ];
                }
            }

            $instanceSheets[] = [
                'main_val'   => $mainVal,
                'sub_val'    => $subVal,
                'audit_date' => $auditDateForSheet,
                'activities' => $instActivitiesData,
            ];
        }

        $projectName     = $projectTemplate->getProject->project_name;
        $templateName    = $projectTemplate->getTemplate->template_name;
        $mainHeaderLabel = $projectTemplate->getMainHeader?->template_head_name ?? 'Main';
        $subHeaderLabel  = $projectTemplate->getSubHeader?->template_head_name ?? 'Sub';

        // --- Build multi-sheet export ---
        // Sheet 1: main flat-table data (plain, no special styling)
        // Sheet 2+: per-distributor instance summary (styled, tabular, per-activity sections)
        $mainSheetData   = $header_data;
        $extraSheets     = [];

        foreach ($instanceSheets as $instData) {
            $sheetTitle = substr(($instData['main_val'] ?: 'Instances') . ' Inst.', 0, 31);
            $instSheetRows = [
                [$projectName],
                [$templateName],
                [$mainHeaderLabel, $instData['main_val']],
                [$subHeaderLabel,  $instData['sub_val']],
                ['Audit Date',     $instData['audit_date']],
            ];

            // Each activity gets its own header row + data rows, separated by a blank row
            foreach ($instData['activities'] as $actData) {
                // Build per-activity column header
                $actHeader = ['Activity Name', 'Sr. No'];
                foreach ($actData['questionSet'] as $qId) {
                    $actHeader[] = $questionDisplayNames[$actData['setIndex']][$qId] ?? '';
                }
                $actHeader[] = 'Verifier Name';
                $actHeader[] = 'Status';
                $actHeader[] = 'Remark';
                $actHeader[] = 'Verification Date & Time';
                $actHeader[] = 'latitude';
                $actHeader[] = 'longitude';
                $actHeader[] = 'User';
                $actHeader[] = 'Date';

                $instSheetRows[] = [];                                      // blank separator
                $instSheetRows[] = ['Activity: ' . $actData['activityName']]; // section label
                $instSheetRows[] = $actHeader;
                foreach ($actData['rows'] as $r) {
                    $instSheetRows[] = $r;
                }
            }

            $extraSheets[] = ['title' => $sheetTitle, 'data' => $instSheetRows];
        }

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
                                        $sheet = $event->sheet->getDelegate();

                                        // Freeze column A (freeze pane after column A, starting from row 8 = data rows)
                                        $sheet->freezePane('B8');

                                        $baseUrl       = rtrim(config('app.url'), '/');
                                        $highestRow    = $sheet->getHighestRow();
                                        $highestCol    = $sheet->getHighestColumn();
                                        $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

                                        for ($row = 1; $row <= $highestRow; $row++) {
                                            for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                                                $cell = $sheet->getCell("{$colLetter}{$row}");
                                                $val  = $cell->getValue();
                                                if (!is_string($val) || $val === '') continue;
                                                if (str_starts_with($val, 'http') && str_contains($val, '["')) { $cell->setValue('Multiple Images'); continue; }

                                                $url   = null;
                                                $label = 'Download';

                                                if (str_contains($val, '/download-images-zip/')) {
                                                    // Multi-image ZIP: parse count from URL query param if present
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
                                                    $argb = $label === 'Download ZIP' ? 'FF107C41' : 'FF0070C0'; // green for ZIP, blue for single
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
                                $maxCol = max(array_map('count', $this->data));
                                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);

                                // Merge project name and template name rows
                                $sheet->mergeCells("A1:{$lastCol}1");
                                $sheet->mergeCells("A2:{$lastCol}2");
                                $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

                                // Header info rows (3-5) with light background
                                $sheet->getStyle("A3:{$lastCol}5")->getFill()
                                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFCCC0DA');
                                $sheet->getStyle("A3:A5")->getFont()->setBold(true);

                                // Column header row (row 7) — bold + yellow background
                                $sheet->getStyle("A7:{$lastCol}7")->getFont()->setBold(true);
                                $sheet->getStyle("A7:{$lastCol}7")->getFill()
                                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB('FFFFFF00');

                                // Borders on data area
                                $totalRows = count($this->data);
                                $sheet->getStyle("A1:{$lastCol}{$totalRows}")->applyFromArray([
                                    'borders' => ['allBorders' => [
                                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                        'color' => ['argb' => 'FF000000'],
                                    ]],
                                ]);
                            }
                        };
                    }
                    return $sheets;
                }
            },
            $fileName
        );
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
            $absPath = public_path(ltrim($path, '/'));
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
            $activitySheetData = [
                [$dist_project_template->getProject->getCompanyInfo->company_name, '', ''],
                ['Basic Information', '', ''],
                [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                ['Audit Date'],
                ['Sr. No', 'Particular', 'Remark'] // Questions & Answers
            ];
            $auditDate = ""; // This is to contain the audit date
            // Find activity and its questions
            $activity = Activity::with('questions')->find($dist_activity);
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
                'header' => [$dist_project_template->getTemplate->template_name . ' ' . $activity->activity_name],
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
                    $activitySheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name],
                        ['Market Audit Summary'],
                        [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                        [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                        ['', ''] // Empty row for spacing
                    ];
                    $child_activity_info = Activity::with('questions')->find($child_activity);

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
                        'header' => [$other_project_template->getTemplate->template_name . ' ' . $child_activity_info->activity_name],
                        'data' => $activitySheetData
                    ];
                }
            }
        }
//        dd('gfhgfh');
        // Generates the name of the excel file name
        $fileName = $dist_project_template->getProject->project_name . $dist_project_template->getTemplate->template_name . '.xlsx';
        // this will give us the excel downloaded with multiple sheets
        return Excel::download(new MultiSheetExport($sheetData), $fileName);
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

        // Loop through each activity and retrieve questions and answers
        foreach ($dist_activites as $dist_activity) {
            // Create a new sheet for each activity within the current template
            $activitySheetData = [
                [$dist_project_template->getProject->getCompanyInfo->company_name, '', ''],
                ['Basic Information', '', ''],
                [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                ['Audit Date'],
                ['Sr. No', 'Particular', 'Remark'] // Questions & Answers
            ];

            $auditDate = ""; // This is to contain the audit date

            // Find activity and its questions
            $activity = Activity::with('questions')->find($dist_activity);

            // Get all questions with proper sequencing
            $allQuestions = Question::where('activity_id', $dist_activity)
                ->orderBy('question_sequence')
                ->get();

            // BFS: collect all sub-question child IDs (new Outlet system)
            $actQIds = $allQuestions->pluck('id')->toArray();
            $subChildIds = [];
            $toProcess = $actQIds;
            while (!empty($toProcess)) {
                $batch = QuestionSubQuestion::whereIn('parent_question_id', $toProcess)->pluck('child_question_id')->toArray();
                $newIds = array_diff($batch, $subChildIds);
                if (empty($newIds)) break;
                $subChildIds = array_merge($subChildIds, $newIds);
                $toProcess = array_values($newIds);
            }

            // Build hierarchical structure (handles Outlet + old parent-child)
            // Each entry: ['question' => Question, 'prefix' => string, 'is_outlet' => bool]
            $questionsWithHierarchy = [];
            foreach ($allQuestions as $question) {
                if ($question->parent_question_id == null && !in_array($question->id, $subChildIds)) {
                    if ($question->question_type === 'Multi Response') {
                        $this->addOutletSubQuestionsFlat($question, $questionsWithHierarchy, $question->question);
                    } else {
                        $questionsWithHierarchy[] = ['question' => $question, 'prefix' => '', 'is_outlet' => false];
                        $children = $allQuestions->where('parent_question_id', $question->id);
                        foreach ($children as $child) {
                            $questionsWithHierarchy[] = ['question' => $child, 'prefix' => $question->question, 'is_outlet' => false];
                        }
                    }
                }
            }

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
            $submissionGroupIds = [];
            $repeatInstanceLabels = [];

            if ($check_if_activity_answered) {
                // Group submissions by same_answer_id (each group = one full submission set).
                // Ordered ascending so the first group = original, subsequent = instances.
                $submissionGroupIds = TempUserActivityAnswersData::where('row_id', $row_id)
                    ->where('activity_id', $activity->id)
                    ->whereNotNull('same_answer_id')
                    ->distinct()
                    ->orderBy('same_answer_id')
                    ->pluck('same_answer_id')
                    ->toArray();

                // Fetch repeat instance labels ordered by activity_sequence for labelling extra groups
                $repeatInstanceLabels = ActivityRepeatInstance::where('row_id', $row_id)
                    ->where('activity_id', $activity->id)
                    ->orderBy('activity_sequence')
                    ->pluck('instance_label')
                    ->toArray();

                // Only show the original (first) instance on this sheet.
                // Extra instances appear on the separate Instance Summary sheet.
                $firstGroupId = $submissionGroupIds[0] ?? null;
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

                        // Build display name from pre-computed prefix
                        $displayQuestion = $qPrefix ? $qPrefix . ' → ' . $activity_question->question : $activity_question->question;

                        // Original submission — filter by first same_answer_id group
                        if ($activity_question->question_type == 'Subjective') {
                            $user_answers = TempUserActivityAnswersData::where("row_id", $row_id)
                                ->where('activity_id', $activity_question->activity_id)
                                ->where('question_id', $activity_question->id)
                                ->when($firstGroupId, fn($q) => $q->where('same_answer_id', $firstGroupId))
                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                ->orderBy('created_at')
                                ->get();

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
                                            $instGid = $submissionGroupIds[$ii] ?? null;
                                            if ($instGid) {
                                                $instSubjAnswers = TempUserActivityAnswersData::where('row_id', $row_id)
                                                    ->where('activity_id', $activity_question->activity_id)
                                                    ->where('question_id', $activity_question->id)
                                                    ->where('same_answer_id', $instGid)
                                                    ->orderBy('created_at')->get();
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
                                    $activitySheetData[] = array_merge([$rowCounter++, $label, $origVal], $extraCols);

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
                                        if (!$verificationRemark && $userAnswer->remark && $userAnswer->get_remark_info) {
                                            $verificationRemark = $userAnswer->get_remark_info->remark;
                                            $verificationDateTime = $userAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                        }
                                    }
                                }
                            } else {
                                $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, null], array_fill(0, $extraInstanceCount, null));
                            }
                        } else {
                            // Non-subjective question — original submission (first same_answer_id group)
                            $user_answer = TempUserActivityAnswersData::where("row_id", $row_id)
                                ->where('question_id', '=', $activity_question->id)
                                ->where('activity_id', $activity->id)
                                ->when($firstGroupId, fn($q) => $q->where('same_answer_id', $firstGroupId))
                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                ->first();

                            // Collect extra instance answers as additional columns
                            $extraCols = [];
                            for ($ii = 1; $ii <= $extraInstanceCount; $ii++) {
                                $instGid = $submissionGroupIds[$ii] ?? null;
                                if ($instGid) {
                                    $ia = TempUserActivityAnswersData::where('row_id', $row_id)
                                        ->where('question_id', $activity_question->id)
                                        ->where('activity_id', $activity->id)
                                        ->where('same_answer_id', $instGid)
                                        ->first();
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
                                if (!$verificationRemark && $user_answer->remark && $user_answer->get_remark_info) {
                                    $verificationRemark = $user_answer->get_remark_info->remark;
                                    $verificationDateTime = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                }

                                if (in_array($activity_question->question_type, ['File Upload', 'Image', 'Audio'])) {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $this->imageAnswerUrl($user_answer)], $extraCols);
                                } elseif ($activity_question->question_type == 'Location' && empty($user_answer->user_answer) && $user_answer->latitude && $user_answer->longitude) {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $user_answer->latitude . ', ' . $user_answer->longitude], $extraCols);
                                } else {
                                    $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, $user_answer->user_answer], $extraCols);
                                }
                            } else {
                                $activitySheetData[] = array_merge([$rowCounter++, $displayQuestion, null], $extraCols);
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
                            $displayQuestion = $qItem['prefix'] ? $qItem['prefix'] . ' → ' . $activity_question->question : $activity_question->question;
                            $activitySheetData[] = [$rowCounter++, $displayQuestion, null];
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

            // Collect tabular instance data for the extra instance sheet (if >1 instance)
            if (!empty($submissionGroupIds) && count($submissionGroupIds) > 0) {
                // Build ordered question list (same as used for vertical sheet, excluding outlets)
                $distInstQCols = []; // [['name' => displayName, 'id' => qId, 'type' => type]]
                foreach ($questionsWithHierarchy as $qItem) {
                    if ($qItem['is_outlet']) continue;
                    $distInstQCols[] = [
                        'name' => $qItem['prefix'] ? $qItem['prefix'] . ' → ' . $qItem['question']->question : $qItem['question']->question,
                        'id'   => $qItem['question']->id,
                        'type' => $qItem['question']->question_type,
                    ];
                }

                $distInstRows = [];
                foreach ($submissionGroupIds as $instIdx => $gid) {
                    $instRow = [$activity->activity_name, $instIdx + 1];

                    // Collect metadata while scanning answers
                    $instAuditorName    = '';
                    $instAuditDateTime  = '';
                    $instVerifStatus    = '';
                    $instVerifierName   = '';
                    $instVerifRemark    = '';
                    $instVerifDateTime  = '';

                    foreach ($distInstQCols as $col) {
                        if ($col['type'] === 'Subjective') {
                            $answers = TempUserActivityAnswersData::where('row_id', $row_id)
                                ->where('activity_id', $activity->id)
                                ->where('question_id', $col['id'])
                                ->where('same_answer_id', $gid)
                                ->with('getUser', 'getVerifier', 'get_remark_info')
                                ->orderBy('created_at')->get();
                            foreach ($answers as $a) {
                                if (!$instAuditorName && $a->getUser)    { $instAuditorName   = $a->getUser->name; $instAuditDateTime = $a->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifierName && $a->verified_by && $a->getVerifier) { $instVerifierName  = $a->getVerifier->name; }
                                if (!$instVerifRemark  && $a->remark && $a->get_remark_info)  { $instVerifRemark   = $a->get_remark_info->remark; $instVerifDateTime = $a->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifStatus)  { $instVerifStatus = $a->status == 5 ? 'Approved' : ($a->status == 4 ? 'Rejected' : 'Pending'); }
                            }
                            $vals = $answers->map(function ($a) {
                                return str_contains($a->user_answer, '/') ? $this->imageAnswerUrl($a) : $a->user_answer;
                            })->toArray();
                            $instRow[] = implode(' | ', $vals) ?: null;
                        } else {
                            $ans = TempUserActivityAnswersData::where('row_id', $row_id)
                                ->where('activity_id', $activity->id)
                                ->where('question_id', $col['id'])
                                ->where('same_answer_id', $gid)
                                ->with('getUser', 'getVerifier', 'get_remark_info')
                                ->first();
                            if ($ans) {
                                if (!$instAuditorName && $ans->getUser)    { $instAuditorName   = $ans->getUser->name; $instAuditDateTime = $ans->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
                                if (!$instVerifierName && $ans->verified_by && $ans->getVerifier) { $instVerifierName  = $ans->getVerifier->name; }
                                if (!$instVerifRemark  && $ans->remark && $ans->get_remark_info)  { $instVerifRemark   = $ans->get_remark_info->remark; $instVerifDateTime = $ans->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i'); }
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

                if (!empty($distInstRows)) {
                    $distInstanceActivities[] = [
                        'activityName' => $activity->activity_name,
                        'questions'    => $distInstQCols,
                        'rows'         => $distInstRows,
                    ];
                }
            }

            // Add this activity's data as a single sheet (instances appear as extra Remark columns)
            $sheetData[] = [
                'header' => [$dist_project_template->getTemplate->template_name . ' ' . $activity->activity_name],
                'data'   => $activitySheetData,
            ];

            if (false) { // removed: instances now appear as extra columns, not separate sheets
                foreach (array_slice($submissionGroupIds, 1) as $groupIndex => $groupId) {
                    $instLabel = 'Instance ' . ($groupIndex + 2);

                    $instanceSheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name, '', ''],
                        ['Basic Information', '', ''],
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
                                        if (!$instVerifRemark && $ia->remark && $ia->get_remark_info) {
                                            $instVerifRemark    = $ia->get_remark_info->remark;
                                            $instVerifDateTime  = $ia->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                        }
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
                                if (!$instVerifRemark && $ia->remark && $ia->get_remark_info) {
                                    $instVerifRemark   = $ia->get_remark_info->remark;
                                    $instVerifDateTime = $ia->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                }
                                if (in_array($aq->question_type, ["File Upload", "Image", "Audio"])) {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $this->imageAnswerUrl($ia)];
                                } elseif ($aq->question_type == "Location" && empty($ia->user_answer) && $ia->latitude && $ia->longitude) {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $ia->latitude . ', ' . $ia->longitude];
                                } else {
                                    $instanceSheetData[] = [$instRowCounter++, $displayQ, $ia->user_answer];
                                }
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
                        'header' => [$dist_project_template->getTemplate->template_name . ' ' . $activity->activity_name . ' (' . $instLabel . ')'],
                        'data'   => $instanceSheetData,
                    ];
                }
            }
        }

        // Build the instance summary sheet right after the distributor activity sheets,
        // before any outlet template sheets.
        if (!empty($distInstanceActivities)) {
            $instSheetRows = [
                [$dist_project_template->getProject->getCompanyInfo->company_name],
                [$dist_project_template->getTemplate->template_name],
                [$dist_project_template->getMainHeader->template_head_name ?? 'Dist Name', $main_header_val],
                [$dist_project_template->getSubHeader->template_head_name ?? 'Dist Code', $sub_header_val],
            ];

            foreach ($distInstanceActivities as $actData) {
                $actHeader = ['Activity Name', 'Sr. No'];
                foreach ($actData['questions'] as $col) {
                    $actHeader[] = $col['name'];
                }
                // Metadata columns match what is collected per instance row
                $actHeader[] = 'Auditor Name';
                $actHeader[] = 'Audit Date & Time';
                $actHeader[] = 'Verification Status';
                $actHeader[] = 'Verifier Name';
                $actHeader[] = 'Verification Remark';
                $actHeader[] = 'Verification Date & Time';

                $instSheetRows[] = [];
                $instSheetRows[] = ['Activity: ' . $actData['activityName']];
                $instSheetRows[] = $actHeader;
                foreach ($actData['rows'] as $r) {
                    $instSheetRows[] = $r;
                }
            }

            $sheetData[] = [
                'header' => [$dist_project_template->getTemplate->template_name . ' Instances'],
                'data'   => $instSheetRows,
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
                    $activitySheetData = [
                        [$dist_project_template->getProject->getCompanyInfo->company_name],
                        ['Market Audit Summary'],
                        [$dist_project_template->getMainHeader->template_head_name, $main_header_val],
                        [$dist_project_template->getSubHeader->template_head_name, $sub_header_val],
                        ['', ''] // Empty row for spacing
                    ];

                    $child_activity_info = Activity::with('questions')->find($child_activity);

                    // Get all questions with hierarchical structure for child activity
                    $allChildQuestions = Question::where('activity_id', $child_activity)
                        ->orderBy('question_sequence')
                        ->get();

                    // BFS: collect sub-question child IDs for child activity (Outlet system)
                    $childQIds = $allChildQuestions->pluck('id')->toArray();
                    $childSubIds = [];
                    $cProcess = $childQIds;
                    while (!empty($cProcess)) {
                        $cBatch = QuestionSubQuestion::whereIn('parent_question_id', $cProcess)->pluck('child_question_id')->toArray();
                        $cNew = array_diff($cBatch, $childSubIds);
                        if (empty($cNew)) break;
                        $childSubIds = array_merge($childSubIds, $cNew);
                        $cProcess = array_values($cNew);
                    }

                    // Build flat question list: ['question' => Question, 'prefix' => string, 'is_outlet' => bool]
                    $childFlatItems = [];
                    foreach ($allChildQuestions as $question) {
                        if ($question->parent_question_id == null && !in_array($question->id, $childSubIds)) {
                            if ($question->question_type === 'Multi Response') {
                                // Outlet: no column itself — expand sub-questions with prefix
                                $this->addOutletSubQuestionsFlat($question, $childFlatItems, $question->question);
                            } else {
                                $childFlatItems[] = ['question' => $question, 'prefix' => '', 'is_outlet' => false];
                                $children = $allChildQuestions->where('parent_question_id', $question->id);
                                foreach ($children as $child) {
                                    $childFlatItems[] = ['question' => $child, 'prefix' => $question->question, 'is_outlet' => false];
                                }
                            }
                        }
                    }

                    // Build headers array from flat items (skip Outlet section headers which have no column)
                    $questionsArray = [];
                    $childFlatQuestions = []; // for answer lookup: ['id', 'question']
                    foreach ($childFlatItems as $fItem) {
                        if (!$fItem['is_outlet']) {
                            $hdr = $fItem['prefix'] ? $fItem['prefix'] . ' → ' . $fItem['question']->question : $fItem['question']->question;
                            $questionsArray[] = $hdr;
                            $childFlatQuestions[] = ['id' => $fItem['question']->id, 'question' => $fItem['question']];
                        }
                    }

                    $activityQuesCount = count($questionsArray);

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

                    if ($related_childs->isEmpty()) {
                        $related_childs = DB::table('project_template_name_values_new')
                            ->where('project_template_id', $other_project_template->id)
                            ->whereRaw("JSON_SEARCH(template_data_json, 'one', ?) IS NOT NULL", [trim($get_master_row->main_value ?? '')])
                            ->get();
                    }

                    foreach ($related_childs as $index => $related_child) {
                        $child_headers = DB::table('project_template_name_values_new')->where('id', $related_child->id)
                            ->select(
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_main_header_id\"')) as main_value"),
                                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_sub_header_id\"')) as sub_value")
                            )
                            ->first();

                        $child_main_header = $child_headers->main_value ?? '';
                        $child_sub_header = $child_headers->sub_value ?? '';

                        $check_any_row_answer = DB::table('temp_user_activity_answers_data')
                            ->where('row_id', $related_child->id)
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

                                if ($outletRowRenderStatus) {
                                    $answers = [$index + 1, $child_main_header, $child_sub_header];
                                    $auditorNameChild = '';
                                    $auditDateTimeChild = '';

                                    // Process each question using the flat list (Outlet sub-questions expanded, Outlet headers skipped)
                                    foreach ($childFlatQuestions as $flatQItem) {
                                        $question = $flatQItem['question'];

                                        if ($question->question_type == 'Subjective') {
                                            $user_answers = TempUserActivityAnswersData::where("row_id", $related_child->id)
                                                ->where('activity_id', $child_activity)
                                                ->where('question_id', $question->id)
                                                ->with('getUser')
                                                ->orderBy('created_at')
                                                ->get();

                                            if (!empty($user_answers) && $user_answers->count() > 0) {
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
                                            $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->id)
                                                ->where('activity_id', $child_activity)
                                                ->where('question_id', $question->id)
                                                ->with('getUser')
                                                ->first();

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

                                    // Add verification details
                                    $lastAnswer = TempUserActivityAnswersData::where("row_id", $related_child->id)
                                        ->where('activity_id', $child_activity)
                                        ->latest()
                                        ->first();

                                    if ($lastAnswer) {
                                        $answers[] = $auditorNameChild ?: ($lastAnswer->getUser->name ?? '');
                                        $answers[] = $auditDateTimeChild ?: ($lastAnswer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') ?? '');
                                        $answers[] = $verificationOutletStatusShow;
                                        $answers[] = $lastAnswer->verified_by ? ($lastAnswer->getVerifier->name ?? '') : '';
                                        $answers[] = $lastAnswer->remark ? ($lastAnswer->get_remark_info->remark ?? '') : '';
                                        $answers[] = $lastAnswer->remark ? $lastAnswer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i') : '';
                                    }

                                    $activitySheetData[] = $answers;
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
                        'header' => [$other_project_template->getTemplate->template_name . ' ' . $child_activity_info->activity_name],
                        'data' => $activitySheetData
                    ];
                }
            }
        }

        $fileName = $dist_project_template->getProject->project_name . $dist_project_template->getTemplate->template_name . '.xlsx';
        return Excel::download(new MultiSheetExport($sheetData), $fileName);
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

        $related_questions = Question::where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();
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
}
