<?php

namespace App\Jobs;

use App\Mail\SendReportMail;
use App\Models\Activity;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\ProjectTemplate;
use App\Models\ProjectTemplateNameValue;
use App\Models\Question;
use App\Models\RemarkMaster;
use App\Models\TempUserActivityAnswersData;
use App\Models\User;
use App\Models\UserActivityAnswersData;
use App\Models\UserActivityDataAssign;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\OutlookMailerService;
use Illuminate\Support\Facades\Log;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $validated;
    protected $request;
    protected $user;
    protected $mailer;

    /**
     * Create a new job instance.
     */
    public function __construct($validated, $request, $user)
    {
        Log::info('job called');
        $this->validated = $validated;
        $this->request = new \Illuminate\Http\Request($request);
        $this->user = $user;
        $this->mailer = new OutlookMailerService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('job started');
        $start_date = $this->validated['from_date'];
        $end_date = $this->validated['to_date'];
        $additionalHeaders = [];
        $show_auditor = 0;
        if (isset($this->request['auditor_details'])) {
            $additionalVerifierHeaders = ['Auditor Name', 'Auditor Contact No.', 'Verifier Name', 'Status', 'Remark', 'Verification Date & Time'];
            $show_auditor = 1;
        }else{
            $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark', 'Verification Date & Time'];
        }
//        $additionalVerifierHeaders = ['Verifier Name', 'Status', 'Remark'];
        $show_user = 0;
        $show_date = 0;
        $show_time = 0;
        $startingQuestionOfOtherActivity = [];

        // this is to check if we want to details of the user in the report

        if (isset($this->request['user_details'])) {
            $additionalHeaders[] = 'User';
            $show_user = 1;
        }
        // this is to get the date of the answer submitted
        if ($this->request->has('date_required')) {
            $additionalHeaders[] = 'Date';
            $show_date = 1;
        }
        // this to get the time in the report
        if ($this->request->has('time_required')) {
            $additionalHeaders[] = 'Time';
            $show_time = 1;
        }

        // this is to get the project and template head  to get the data of the project and template selected
        $projectTemplate = ProjectTemplate::where('project_id', $this->validated['project_id'])->where('template_name_id', $this->validated['template_name_id'])->first();

        $outlet_master_info = DB::table('project_templates')->where('project_id', $projectTemplate->project_id)
            ->where('is_master', 1)
            ->first(); // this is to get the info of the master template of the project

        $activity_group_name_id_or_activity_id = $outlet_master_info->activity_group_name_id_or_activity_id;

        $checkOutletAssign = DB::table('data_assigns')->where('project_id', $outlet_master_info->project_id)
            ->where('template_name_id', $outlet_master_info->template_name_id)
            ->where('template_name_head_id', $outlet_master_info->main_header)
            ->where('project_template_id', $outlet_master_info->id)
            ->where('is_outlet_assigned', 1)
            ->where(function ($query) use ($activity_group_name_id_or_activity_id) {
                $query->where('activity_id', $activity_group_name_id_or_activity_id)
                    ->orWhere('activity_group_id', $activity_group_name_id_or_activity_id);
            })
            ->first();


        $template_heads = $projectTemplate->getTemplate->getTemplateHeads->toArray();
        $projectTemplateHeads = array_column($template_heads, 'template_head_name');
        $activity_questions_array = []; // this is to get the question of the activity related to that project and template
        $activity_questions = null;
//        dd($this->request->activity_id);
        foreach ($this->request->activity_id as $activity) {
//            $checkingInDataAssigned = DB::table('data_assigns')->where('project_template_id', $projectTemplate->id)->where('activity_id', $activity)->first();
            $checkingInDataAssigned = UserActivityDataAssign::where('project_template_id', $projectTemplate->id)->where('activity_id', $activity)->first();
            // the below gives the activity name
//             dd($checkingInDataAssigned);
//            if ($projectTemplate->is_master == 0 && !empty($checkOutletAssign) && $checkOutletAssign->is_outlet_assigned == 1) {
//                $activityData = DB::table('activities')->find($activity);
//                $activity_questions = $activityData->questions;
//            } else {
                if ($checkingInDataAssigned !== null && isset($checkingInDataAssigned->activityInfo) && $checkingInDataAssigned->activityInfo !== null && isset($checkingInDataAssigned->activityInfo->questions)) {
                    $activity_questions = $checkingInDataAssigned->activityInfo->questions;
                }
//            }

//            dd($activity_questions);
            $type = gettype($activity_questions);
            if ($type == "object") {
                $activity_questions_array[] = $activity_questions->toArray();
            } else {
                $activity_questions_array = [];
            }
        }
        // dd($activity_questions_array);

        $projectTemplateData = $projectTemplate->getProjectTemplateData;
        $projectTemplateRowIds = $projectTemplate->getProjectTemplateData->pluck('row_id')->unique()->values()->toArray();
        $combinedHeaders = [];
        $activities_questions_id = [];
//        dd($activity_questions_array);

        foreach ($activity_questions_array as $activityQuestions) {
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
                if ($question['question_type'] == 'Subjective') {
                    $combinedHeaders[] = $question['question'];
                    // Check if any subjective answer has a file path (contains '/')
                    $hasFile = DB::table('temp_user_activity_answers_data')->where('question_id', $question['id'])
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
//                $combinedHeaders[] = $question['question'];
//                $activities_questions_id[] = $question['id'];
            }
        }
        $projectActivitiesHeaders = array_merge($projectTemplateHeads, $combinedHeaders);
        $header_data = [$projectActivitiesHeaders];
        $fileName = $projectTemplate->getProject->project_name . $projectTemplate->getTemplate->template_name . '.xlsx';

        $templateHeadsCount = count($projectTemplate->getTemplate->getTemplateHeads);
        $dataCount = count($projectTemplateData);
        $counter = 0;
        $combinedData = [];

//        khushboo 22-05-25
        $pdfFilePaths = [];
        $zipFilePath = null;
        //getauditor Assign Data
        $auditorAssignDataValues = DB::table('auditor_assigned_data')->where('project_id', $this->validated['project_id'])
            ->where('template_name_id', $this->validated['template_name_id'])
            ->pluck('template_name_head_value')
            ->toArray();
//        khushboo 22-05-25

//        $count=0;
        // dd($projectTemplateData);
        $allrowids = $projectTemplateData->pluck('id')->toArray();
//        Log::info('all row ids'. json_encode($allrowids));

        foreach ($projectTemplateData as $projectTemplate_data) {
//            Log::info('single row id - '. $projectTemplate_data->id);

            //khushboo 22-05-25
            $jsondata = json_decode($projectTemplate_data->template_data_json, true);
            foreach ($jsondata as $key => $value) {
                if ($projectTemplate->is_master == 0 && !empty($checkOutletAssign) && $checkOutletAssign->is_outlet_assigned == 1) {

                    if (!empty($this->request->activity_id)) {
                        foreach ($this->request->activity_id as $activity) {

                            $key = $projectTemplate_data->id . '-' . $activity;

                            if (!isset($pdfFilePaths[$key])) {
                                $pdfFile = $this->viewTemplateFile($projectTemplate_data->id, $value, $activity);

                                if (isset($pdfFile->original['file_url'])) {
                                    $pdfFilePaths[$key] = $pdfFile->original['file_url'];
                                }
                            }

                        }
                    } else if ($this->request->activity_group_name_id) {
                        $activities = [];
                        $activityGroupId = $this->request->activity_group_name_id;
                        $group_activities = DB::table('activity_group_pivots')->where('activity_group_id', $this->request->activity_group_name_id)->get();
                        foreach ($group_activities as $group_activity) {
                            $activities[] = $group_activity->activity_id;
                        }
                        foreach ($activities as $activity) {

                            $key = $projectTemplate_data->id . '-' . $activity;

                            if (!isset($pdfFilePaths[$key])) {
                                $pdfFile = $this->viewTemplateFile($projectTemplate_data->id, $value, $activity);

                                if (isset($pdfFile->original['file_url'])) {
                                    $pdfFilePaths[$key] = $pdfFile->original['file_url'];
                                }
                            }

                        }
                    }

                }
                else {
                    if (in_array($value, $auditorAssignDataValues)) {
                        // dd($this->request->activity_id);
                        if (!empty($this->request->activity_id)) {
                            foreach ($this->request->activity_id as $activity) {

                                $key = $projectTemplate_data->id . '-' . $activity;

                                if (!isset($pdfFilePaths[$key])) {
                                    $pdfFile = $this->viewTemplateFile($projectTemplate_data->id, $value, $activity);

                                    if (isset($pdfFile->original['file_url'])) {
                                        $pdfFilePaths[$key] = $pdfFile->original['file_url'];
                                    }
                                }

                            }
                        } else if ($this->request->activity_group_name_id) {
                            $activities = [];
                            $activityGroupId = $this->request->activity_group_name_id;
                            $group_activities = DB::table('activity_group_pivots')->where('activity_group_id', $this->request->activity_group_name_id)->get();
                            foreach ($group_activities as $group_activity) {
                                $activities[] = $group_activity->activity_id;
                            }
                            foreach ($activities as $activity) {

                                $key = $projectTemplate_data->id . '-' . $activity;

                                if (!isset($pdfFilePaths[$key])) {
                                    $pdfFile = $this->viewTemplateFile($projectTemplate_data->id, $value, $activity);

                                    if (isset($pdfFile->original['file_url'])) {
                                        $pdfFilePaths[$key] = $pdfFile->original['file_url'];
                                    }
                                }
                            }
                        }

                    }

                }
            }

            //khushboo 22-05-25

//            if ($counter % $templateHeadsCount === 0) {
                $rowData = []; // Start a new row with the index
//            }

//            $rowData[] = $projectTemplate_data->value;
            foreach ($jsondata as $key => $value) {
                $rowData[] = $value;
            }

//            dd($activities_questions_id);
//            if ($counter % $templateHeadsCount === ($templateHeadsCount - 1) || $counter === ($dataCount - 1)) {
                $combinedData[] = $rowData; // Add the completed row to the combined data array
                $any_answer = 0;
                foreach ($activities_questions_id as $questionId) {

                    $activityQuestionData = DB::table('questions')->find($questionId);
                    if ($activityQuestionData->question_type == "Subjective") {

                        $user_answer = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                            ->where("question_id", '=', $questionId)
                            //->where('template_id', $questionInfo->template_id)
                            ->whereDate('created_at', '>=', $start_date)
                            ->whereDate('created_at', '<=', $end_date)
                            ->with('getUser', 'getQuestionInfo')->get();

                        if (!empty($user_answer)) {
                            foreach ($user_answer as $userAnswer) {

                                $isUrlOrPath = str_contains($userAnswer->user_answer, '/');

                                if ($isUrlOrPath) {
                                    $rowData[] = asset($userAnswer->user_answer);
                                } else {
                                    $rowData[] = $userAnswer->user_answer;
                                }
                            }
                        }

                    }
                    else {

                        $user_answer = TempUserActivityAnswersData::where("row_id", $projectTemplate_data->id)
                            ->where("question_id", '=', $questionId)
                            ->whereDate('created_at', '>=', $start_date)
                            ->whereDate('created_at', '<=', $end_date)
                            ->with('getUser', 'getQuestionInfo')->first();

//                        dd($user_answer, $projectTemplate_data->id, $questionId, $start_date, $end_date);

                        if ($user_answer) {

                            $any_answer = 1;
                            if (in_array($questionId, $startingQuestionOfOtherActivity)) {

                                //Auditor Name and Contact
                                if($show_auditor == 1){
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
                                $rowData[] = $user_answer->remark;
                                if ($show_user) {
                                    if ($user_answer->getUser) {
                                        $rowData[] = $user_answer->getUser->name;
                                    } else {
                                        $rowData[] = "User (ID: " . $user_answer->user_id . ") has been deleted";
                                    }
                                }
                                if ($show_date) {
                                    $rowData[] = date('d-m-y', strtotime($user_answer->created_at));
//                            $rowData[] = Carbon::parse($user_answer->created_at)->format('d-m-y');
                                }
                                if ($show_time) {
//                            $rowData[] = $user_answer->created_at->format('H:i:s');
                                    $rowData[] = date('H:i:s', strtotime($user_answer->created_at));
                                }
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
                        else{
                            $rowData[] = '';
                        }
                    }
                }
                if ($this->request->data_get_helper == "all") {
                    $header_data[] = $rowData;
                } elseif ($this->request->data_get_helper == "filled") {
                    if ($any_answer == 1) {
                        $header_data[] = $rowData;
                    }
                } else {
                    if ($any_answer == 0) {
                        $header_data[] = $rowData;
                    }
                }

            }
            $counter++;
//        }
//        dd($header_data);
        // After generating the report, store the file temporarily
        $fileName = 'report-' . time() . '.xlsx';
        $filePath = 'testing/' . $fileName;  // Path within 'public/testing' directory
        Excel::store(new class($header_data) implements FromArray {
            private $header_data;

            public function __construct(array $data)
            {
                $this->header_data = $data;
            }

            public function array(): array
            {
                return $this->header_data;
            }
        }, $filePath, 'public'); // Store in the 'public' disk, which maps to 'public/' directory

        // Full file path to be used in mail and deletion
        $fullFilePath = public_path($filePath);

        //khushboo 23-05-25

//        dd($pdfFilePaths);
        $localPaths = [];
        if (count($pdfFilePaths) > 0) {
            foreach ($pdfFilePaths as $url) {
                // Parse the URL to get the path component
                $parsedUrl = parse_url($url, PHP_URL_PATH);

                // Remove the base path if necessary
                $relativePath = str_replace('/marketaudit_tnbttech/public/', '', $parsedUrl);

                // Construct the full local path
                $fullPath = public_path($relativePath);

                // Check if the file exists
                if (file_exists($fullPath)) {
                    $localPaths[] = $fullPath;
                }
            }
        }

        $zipFileName = 'Pdf_Attachment-' . time() . '.zip';
        $zipFilePath = public_path('temp/' . $zipFileName);

        // Ensure the 'temp' directory exists
        if (!file_exists(public_path('temp'))) {
            mkdir(public_path('temp'), 0777, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($localPaths as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        }

//            dd($zipFilePath);
        //khushboo 23-05-25

        // Send email with the Excel file as an attachment
        // Mail::to($this->user->email)->send(new SendReportMail($filePath, $zipFilePath));

        $attachment = [$fullFilePath, $zipFilePath];
        Log::info('attachment: ' . json_encode($attachment));

        if (file_exists(public_path('temp/' . $zipFileName))) {
            chmod(public_path('temp/' . $zipFileName), 0777);
        }

        $htmlBody = '
            <!DOCTYPE html>
                <html>
                    <head>
                    <style>
                        body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
                        .container { background-color: #ffffff; padding: 20px; border-radius: 10px; }
                        .header { font-size: 24px; color: #333; margin-bottom: 10px; }
                        .content { font-size: 16px; color: #555; }
                        .footer { font-size: 12px; color: #aaa; margin-top: 20px; }
                    </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">Hello ' . htmlspecialchars($this->user->name) . ',</div>
                            <div class="content">
                                <p>Your requested report is now ready. You’ll find it attached to this email.</p>
                            </div>
                            <div class="footer">© ' . date('Y') . ' TNBT</div>
                        </div>
                    </body>
                </html>';

        Log::info('job mail started');
        $this->mailer->sendMail(
            'ahujakhushboo135@gmail.com',
            'Report Mail',
            $htmlBody,
            $attachment
        );

        Log::info('job mail send');

        //khushboo 22-05-25
        $folderPath = public_path('temp');

        if (is_dir($folderPath)) {
            $files = glob($folderPath . '/*'); // Get all files in the directory

            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file); // Delete the file
                    }
                }
            }
        }

        // After sending the email, delete the file from 'public/testing'
        if (file_exists($fullFilePath)) {
            unlink($fullFilePath);
        }

        //khushboo 22-05-25

    }

//    khushboo 22-05-25
    public function viewTemplateFile($r, $v, $a, $g = null)
    {

        $related_values = DB::table('project_template_name_values')->where('id', $r)->get();
//        dd($related_values);

        $projectTemplateData = ProjectTemplate::with(['getMainHeader', 'getSubHeader'])->where('id', $related_values[0]->project_template_id)->first();

//        dd($projectTemplateData);
        $remarks = DB::table('remark_masters')->where('project_id', $projectTemplateData->project_id)->where('status', 1)->get();

        $activity_check = DB::table('activities')->find($a);

        $related_questions = DB::table('questions')->where('activity_id', $activity_check->id)->orderBy('question_sequence', 'asc')->get();

        $user_responses = TempUserActivityAnswersData::with('getUser')
            ->where('row_id', $r)
            ->where('activity_id', $activity_check->id)->get();

//        Log::info('user response - '. $user_responses[0]->id. '-'. $activity_check->id. '-', $r);

        if (!$user_responses->IsEmpty()) {

            $auditorInfo = $user_responses[0];
            $auditDate = $user_responses[0]->created_at;
            $auditDate = Carbon::parse($auditDate)->format('d-m-Y');

            $auditTime = $user_responses[0]->created_at;
            $auditTime = Carbon::parse($auditTime)->format('H:i A');

            $pdf = Pdf::loadView('masters.pdf_templates.verify_template', [
                'projectTemplateData' => $projectTemplateData,
                'related_questions' => $related_questions,
                'auditorInfo' => $auditorInfo,
                'auditDate' => $auditDate,
                'auditTime' => $auditTime,
                'user_responses' => $user_responses,
                'value' => $v
            ]);


            // File name and path
            $fileName = 'Audit-' . uniqid() . '.pdf';
            $filePath = public_path('temp/' . $fileName);
//        dd($filePath);

            // Ensure the directory exists
            if (!file_exists(public_path('temp'))) {
                mkdir(public_path('temp'), 0777, true);
            }

            // Save the PDF to the public/temp folder
            $pdf->save($filePath);

            // Return the public URL to access the PDF
            $url = asset('temp/' . $fileName);

            return response()->json([
                'file_url' => $url,
                'file_name' => $fileName
            ]);
        }

    }

//    khushboo 22-05-25


}
