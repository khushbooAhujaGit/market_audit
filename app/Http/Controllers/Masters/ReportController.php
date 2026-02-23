<?php

namespace App\Http\Controllers\Masters;

use App\Exports\MultiSheetExport;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\Company;
use App\Models\DataAssign;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\ProjectTemplateNameValuesNew;
use App\Models\Question;
use App\Models\RemarkMaster;
use App\Models\TemplateName;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Models\Verifier;
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
            $projects = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            $projectsIds = Project::where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->pluck('id')->toArray();
            $project_templates_ids = ProjectTemplate::whereIn('project_id', $projectsIds)->pluck('template_name_id')->toArray();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();
        }
        else if($currentUserRole == "Super Admin"){
            $template_names = TemplateName::all();
            $projects = Project::all();
        }
        else {
            $template_names = TemplateName::all();
            $projects = Project::all();
        }
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

        if ($currentuser->is_agency_user == 1 || $currentuser->getRoleNames()->first() == 'Agency') {

            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::whereIn('id', $projectsIds)->where('is_agency_required', 1)->orWhere('agency_id', Auth::user()->agency_user_id)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();

        }
        else if($currentUserRole == "Super Admin"){
            $template_names = TemplateName::all();
            $projects = Project::all();
        }
        else {

            $project_templates_ids = Verifier::where('user_id', $currentuser->id)
                ->distinct('project_template_name_id')->pluck('project_template_name_id')->toArray();
            $projectsIds = ProjectTemplate::whereIn('id', $project_templates_ids)->pluck('project_id')->toArray();
            $projects = Project::whereIn('id', $projectsIds)->get();
            $template_names = TemplateName::whereIn('id', $project_templates_ids)->get();

        }
        //khushboo 03-04-2025

        return view('masters.reports.project_report', compact('projects', 'template_names', 'companies', 'activities', 'activity_groups'));
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
//            $g = GenerateReportJob::dispatch($validated, $requestData, $user);
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
                        $hasFile = TempUserActivityAnswersData::where('question_id', $question['id'])
                            ->where('activity_id', $question['activity_id'])
                            ->whereIn('row_id', $projectTemplateRowIds)
                            ->whereDate('created_at', '>=', $start_date)
                            ->whereDate('created_at', '<=', $end_date)
                            ->where(function ($query) {
                                $query->where('user_answer', 'like', '%/%'); // crude check for file paths
                            })->exists();

                        if ($hasFile) {
                            $combinedHeaders[] = $question['question'] . ' - File';
                        }
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
            $latlongcount = 0;
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
                                $rowData[] = asset($userAnswer->user_answer);
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
                                // $rowData[] = asset('storage/' . $user_answer->user_answer);
                                $rowData[] = asset($user_answer->user_answer);
                                // $rowData[] = $user_answer->user_answer;
                            } else {
                                $rowData[] = $user_answer->user_answer;
                            }

                        } else {
                            if ($user_answer->getQuestionInfo->question_type == "Image" || $user_answer->getQuestionInfo->question_type == "File Upload" || $user_answer->getQuestionInfo->question_type == "Audio") {
                                // $rowData[] = asset('storage/' . $user_answer->user_answer);
                                $rowData[] = asset($user_answer->user_answer);
                                // $rowData[] = $user_answer->user_answer;
                            } else {
                                $rowData[] = $user_answer->user_answer;
                            }
                            if ($user_answer->getQuestionInfo->question_type == "Subjective") {
                                $d_value = $user_answer->user_answer;
                                $extension = strtolower(pathinfo($d_value, PATHINFO_EXTENSION));
                                $isUrlOrPath = str_contains($d_value, '/');
                                if ($isUrlOrPath) {
                                    $rowData[] = asset($user_answer->user_answer);
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


    public function project_distributor_report2(Request $request)
    {
        $validate = $request->validate([
            'project_id' => 'required',
            'template_name_id' => 'required',
            'row_id' => 'required'
        ]);
        $row_id = $request->row_id;
        $sheetData = []; // Array to store the final activity data

        $dist_project_template = ProjectTemplate::with('getProject.getCompanyInfo', 'getTemplate', 'getMainHeader', 'getSubHeader')->where('project_id', $request->project_id)
            ->where('template_name_id', $request->template_name_id)
            ->first();

        // Gives headers related to the template (main and sub-header)
        $dist_row_headers = ProjectTemplateNameValue::with('getDataOfRows')
            ->where('row_id', $request->row_id)
            ->whereIn('template_name_head_id', [$dist_project_template->main_header, $dist_project_template->sub_header])
            ->get();


        // Initialize variables for main and sub-header values
        $main_header_val = null;
        $sub_header_val = null;

        // Assign the header values
        foreach ($dist_row_headers as $dist_row_header_info) {
            if ($dist_row_header_info->template_name_head_id == $dist_project_template->main_header) {
                $main_header_val = $dist_row_header_info->value;
            }
            if ($dist_row_header_info->template_name_head_id == $dist_project_template->sub_header) {
                $sub_header_val = $dist_row_header_info->value;
            }
        }

        // Fetch activities associated with the project template
        $dist_activites = [];
        if ($dist_project_template->activityType) {
            $group_info = ActivityGroup::with('get_group_activities')->find($dist_project_template->activity_group_name_id_or_activity_id);
            $dist_activites = $group_info->get_group_activities->pluck('activity_id')->toArray();
        } else {
            $dist_activites[] = $dist_project_template->activity_group_name_id_or_activity_id;
        }
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
            $auditorName = '';
            $auditDateTime = '';
            $verifierName = '';
            $verificationRemark = '';
            $verificationDateTime = '';
            if ($check_if_activity_answered) {
                foreach ($activity->questions as $index => $activity_question) {
                    $user_answer = TempUserActivityAnswersData::where("row_id", $row_id)
                        ->where("question_id", '=', $activity_question->id)
                        ->where('activity_id', $activity->id)
                        ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')->first();
                    // Add question and answer to the activity sheet
                    if ($user_answer) {

                        //khushboo 16-06-2025
                        if ($activity_question->question_type == 'Subjective') {
                            if (!empty($user_answer)) {

                                $isUrlOrPath = str_contains($user_answer->user_answer, '/');

                                if ($isUrlOrPath) {
                                    $activitySheetData[] = [$index + 1, $activity_question->question, asset($user_answer->user_answer)];
                                } else {
                                    $activitySheetData[] = [$index + 1, $activity_question->question, $user_answer->user_answer];
                                }
                            }
                        }
                        //khushboo 16-06-2025

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
                            $verificationDateTime = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                        }
                        if ($activity_question->question_type == "File Upload" || $activity_question->question_type == "Image") {
                            $activitySheetData[] = [$index + 1, $activity_question->question, asset($user_answer->user_answer)];
                        } else {
                            $activitySheetData[] = [$index + 1, $activity_question->question, $user_answer->user_answer];
                        }
                    } else {
                        $activitySheetData[] = [$index + 1, $activity_question->question, null]; // No answer
                    }
                }
            } else {
                foreach ($activity->questions as $index => $activity_question) {
                    $activitySheetData[] = [$index + 1, $activity_question->question]; // Only questions, no answers
                }
            }
            $activitySheetData[] = [''];
            $activitySheetData[] = ['Auditor Name', $auditorName];
            $activitySheetData[] = ['Audit Date & Time', $auditDateTime];
            $activitySheetData[] = ['Verifier Name', $verifierName];
            $activitySheetData[] = ['Verification Remark', $verificationRemark];
            $activitySheetData[] = ['Verification Date & Time', $verificationDateTime];
            $activitySheetData[4][] = $auditDate; // this is to add the audit date in the top of the questions and answers of the activity

            // Add this activity's data as a new sheet
            $sheetData[] = [
                'header' => [$dist_project_template->getTemplate->template_name . ' ' . $activity->activity_name],
                'data' => $activitySheetData,
            ];
        }

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
            // Loop through each activity in the other templates
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
                $questionsArray = $child_activity_info->questions->pluck('question')->toArray();
                array_unshift($questionsArray, 'S.NO.', $other_project_template->getMainHeader->template_head_name, $other_project_template->getSubHeader->template_head_name);
                array_push($questionsArray, 'Auditor Name', 'Audit Date & Time', 'Verifier Name', 'Verification Remark', 'Verification Date & Time');
                $activitySheetData[] = $questionsArray;
                $child_main_header_id = $other_project_template->main_header;
                $child_sub_header_id = $other_project_template->sub_header;
                $get_master_row = ProjectTemplateNameValue::where('row_id', $row_id)
                    ->where('template_name_head_id', $other_project_template->master_head_id)
                    ->first();
                $related_childs = ProjectTemplateNameValue::where('project_template_id', $other_project_template->id)
                    ->where('template_name_head_id', $other_project_template->own_reference_head_id)
                    ->where('value', $get_master_row->value)
                    ->get();
                foreach ($related_childs as $index => $related_child) {
                    $child_headers = ProjectTemplateNameValue::where('row_id', $related_child->row_id)
                        ->whereIn("template_name_head_id", [$child_main_header_id, $child_sub_header_id])
                        ->get();
                    // Fetch child headers
                    $child_main_header = '';
                    $child_sub_header = '';
                    foreach ($child_headers as $child_header) {
                        if ($child_header->template_name_head_id == $child_main_header_id) {
                            $child_main_header = $child_header->value;
                        } else {
                            $child_sub_header = $child_header->value;
                        }
                    }
                    $check_any_row_answer = TempUserActivityAnswersData::where('row_id', $related_child->row_id)
                        ->where('activity_id', $child_activity_info->id)
                        ->first();
                    if ($check_any_row_answer) {
                        $answers = [$index + 1, $child_main_header, $child_sub_header];
                        $totalQuestions = $child_activity_info->questions->count();
                        foreach ($child_activity_info->questions as $child_ques_index => $child_activity_question) {
                            $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->row_id)
                                ->where('activity_id', $child_activity_question->activity_id)
                                ->where('question_id', $child_activity_question->id)
                                ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                ->first();
                            if ($user_answer) {

                                //khushboo 16-06-2025
                                if ($child_activity_question->question_type == 'Subjective') {
                                    if (!empty($user_answer)) {

                                        $isUrlOrPath = str_contains($user_answer->user_answer, '/');

                                        if ($isUrlOrPath) {
                                            $answers[] = asset($user_answer->user_answer);
                                        } else {
                                            $answers[] = $user_answer->user_answer;
                                        }
                                    }
                                }
                                //khushboo 16-06-2025

                                if ($child_activity_question->question_type == "File Upload" || $child_activity_question->question_type == "Image") {
                                    $answers[] = asset($user_answer->user_answer);
                                } else {
                                    $answers[] = $user_answer->user_answer;
                                }
                                if ($child_ques_index + 1 == $totalQuestions) {
                                    $answers[] = $user_answer->getUser->name;
                                    $answers[] = $user_answer->created_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                    if ($user_answer->verified_by) {
                                        $answers[] = $user_answer->getVerifier->name;
                                    } else {
                                        $answers[] = '';
                                    }

                                    if ($user_answer->remark) {
                                        $answers[] = $user_answer->get_remark_info->remark;
                                        $answers[] = $user_answer->updated_at->setTimezone('Asia/Kolkata')->format('d-M-Y H:i');
                                    } else {
                                        $answers[] = '';
                                        $answers[] = '';
                                    }
                                }
                            }
                        }
                        $activitySheetData[] = $answers;
                    } else {
                        // No answers found, add only the headers
                        $activitySheetData[] = [$index + 1, $child_main_header, $child_sub_header];
                    }
                }
                // Add this child activity's data as a new sheet
                $sheetData[] = [
                    'header' => [$other_project_template->getTemplate->template_name . ' ' . $child_activity_info->activity_name],
                    'data' => $activitySheetData
                ];
            }
        }
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
//            if ($dist_row_header_info->template_name_head_id == $dist_project_template->main_header) {
                $main_header_val = $dist_row_header_info->main_value;
//            }
//            if ($dist_row_header_info->template_name_head_id == $dist_project_template->sub_header) {
                $sub_header_val = $dist_row_header_info->sub_value;
//            }
        }

        // dd($main_header_val, $sub_header_val);
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
                                        $activitySheetData[] = [$index + 1, $activity_question->question . ' - File', asset($userAnswer->user_answer)];
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
                                    $activitySheetData[] = [$index + 1, $activity_question->question, asset($user_answer->user_answer)];
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
                                                        $answers[] = asset($userAnswer->user_answer);
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
                                                    $answers[] = asset($user_answer->user_answer);
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

    public function project_distributor_reportOld(Request $request)
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
            if ($dist_row_header_info->template_name_head_id == $dist_project_template->main_header) {
                $main_header_val = $dist_row_header_info->value;
            }
            if ($dist_row_header_info->template_name_head_id == $dist_project_template->sub_header) {
                $sub_header_val = $dist_row_header_info->value;
            }
        }

        // dd($main_header_val, $sub_header_val);
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
                                        $activitySheetData[] = [$index + 1, $activity_question->question . ' - File', asset($userAnswer->user_answer)];
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
                                    $activitySheetData[] = [$index + 1, $activity_question->question, asset($user_answer->user_answer)];
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
                $child_activites[] = $other_project_template->activity_group_name_id_or_activity_id;
            }
            // dd($child_activites);
            // Loop through each activity in the other templates
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

                $get_master_row = ProjectTemplateNameValuesNew::where('id', $row_id)
                    ->select(
                        DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$other_project_template->master_head_id\"')) as main_value")
                    )
                    ->get();

                $related_childs = ProjectTemplateNameValuesNew::where('project_template_id', $other_project_template->id)
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$other_project_template->own_reference_head_id\"')) = ?", [trim($get_master_row->main_value)])
                    ->get();

                foreach ($related_childs as $index => $related_child) {
//                    $child_headers = ProjectTemplateNameValue::where('row_id', $related_child->row_id)
//                        ->whereIn("template_name_head_id", [$child_main_header_id, $child_sub_header_id])
//                        ->get();

                    $child_headers = ProjectTemplateNameValuesNew::where('id', $related_child->row_id)
                        ->select(
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_main_header_id\"')) as main_value"),
                            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(template_data_json, '$.\"$child_sub_header_id\"')) as sub_value")
                        )
                        ->get();

                    // Fetch child headers
                    $child_main_header = $child_headers->main_value;
                    $child_sub_header = $child_headers->sub_value;

//                    foreach ($child_headers as $child_header) {
//                        if ($child_header->template_name_head_id == $child_main_header_id) {
//                            $child_main_header = $child_header->value;
//                        } else {
//                            $child_sub_header = $child_header->value;
//                        }
//                    }

                    $check_any_row_answer = TempUserActivityAnswersData::where('row_id', $related_child->row_id)
                        ->where('activity_id', $child_activity_info->id)
                        ->first();

//                    dd($check_any_row_answer);

                    if ($check_any_row_answer) {
                        if ($row_item_helper !== "unfilled") {
                            //                        dd($check_any_row_answer,$verificationDataHelper);
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

                                        $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->row_id)
                                            ->where('activity_id', $child_activity_question->activity_id)
                                            ->where('question_id', $child_activity_question->id)
                                            ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                            ->get();

                                        if (!empty($user_answer)) {

                                            foreach ($user_answer as $user_answer_index => $userAnswer) {

                                                $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                                                if ($isUrlOrPath) {
                                                    $answers[] = asset($userAnswer->user_answer);
                                                } else {
                                                    $answers[] = $userAnswer->user_answer;
                                                }
                                            }
                                        }

                                    } else {

                                        //khushboo 16-06-2025

                                        $user_answer = TempUserActivityAnswersData::where("row_id", $related_child->row_id)
                                            ->where('activity_id', $child_activity_question->activity_id)
                                            ->where('question_id', $child_activity_question->id)
                                            ->with('getUser', 'getQuestionInfo', 'getVerifier', 'get_remark_info')
                                            ->first();
                                        if ($user_answer) {

                                            if ($child_activity_question->question_type == "File Upload" || $child_activity_question->question_type == "Image" || $child_activity_question->question_type == "Audio") {
                                                $answers[] = asset($user_answer->user_answer);
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
//        dd('gfhgfh');
        // Generates the name of the excel file name
        $fileName = $dist_project_template->getProject->project_name . $dist_project_template->getTemplate->template_name . '.xlsx';
        // this will give us the excel downloaded with multiple sheets
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
//                            $rowData[] = asset('storage/' . $user_answer->user_answer);
                                $rowData[] = asset($user_answer->user_answer);
                            } else {
                                $rowData[] = $user_answer->user_answer;
                            }
                        } else {
                            if ($user_answer->getQuestionInfo->question_type == "Image" || $user_answer->getQuestionInfo->question_type == "File Upload" || $user_answer->getQuestionInfo->question_type == "Audio") {
//                            $rowData[] = asset('storage/' . $user_answer->user_answer);
                                $rowData[] = asset($user_answer->user_answer);
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
        $subHeaderValue  = $templateJson[$projectTemplateData->sub_header] ?? null;

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
