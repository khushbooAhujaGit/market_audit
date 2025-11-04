<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\UserActivityDataAssign;
use Illuminate\Http\Request;
use App\Models\Unit;
use App\Models\AuditorAssignedData;
use App\Models\DataAssign;
use App\Models\Question;
use App\Models\QuestionDropdown;
use App\Models\ActivityGroupPivot;
use App\Models\RowSequancedata;
use App\Models\Project;
use App\Models\Company;
use App\Models\TemplateNameHead;
use App\Models\ProjectTemplateNameValue;
use App\Models\AnswerActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Zone;
use Illuminate\Support\Facades\Auth;
use Artisan;

class DashboardController extends Controller
{
    //

    public function dashboard(Request $request)
    {

        try {

            $user_id = $request->user_id;
//            dd($user_id);

            $assignid = UserActivityDataAssign::where('user_id', $user_id)->distinct('data_assign_id')->pluck('data_assign_id');

            $data = DataAssign::whereIn('id', $assignid)->get();

            $pending = $data->where('status', 0)->unique('project_id')->count();
            $complete = $data->where('status', 2)->unique('project_id')->count();
            $rejected = $data->where('status', 3)->unique('project_id')->count();
            $reaudit = $data->where('status', 4)->unique('project_id')->count();

            $AssignedProjectIds = $data->pluck('project_id')->toArray();

            $rejectedCount = 0;

//            dd($AssignedProjectIds);
            foreach ($AssignedProjectIds as $projectId) {
                $getProjectTemplatesId = DB::table('project_templates')->where('project_id', $projectId)->pluck('id')->toArray();

                $getAllRowIds = DB::table('project_template_name_values_new')
                    ->where('project_template_id', $getProjectTemplatesId)
                    ->pluck('id');

//                dd($getAllRowIds);

                $countTotalRowIds = $getAllRowIds->count();

                // If you need array version:
                $getAllRowIdsArray = $getAllRowIds->toArray();

//                dd($getAllRowIdsArray);
//                $checkCountOfRejectedAudits = DB::table('temp_user_activity_answers_data')
//                    ->whereIn('row_id', $getAllRowIdsArray)
//                    ->where('status', 4)
//                    ->groupBy('row_id')->count();

//                dd($checkCountOfRejectedAudits);
                $totalRejected = 0;

                $rowIdChunks = array_chunk($getAllRowIdsArray, 1000); // keep it safe under 1000

                foreach ($rowIdChunks as $chunk) {
                    $count = DB::table('temp_user_activity_answers_data')
                        ->whereIn('row_id', $chunk)
                        ->where('status', 4)
                        ->groupBy('row_id')
                        ->select(DB::raw('count(*)'))
                        ->get()
                        ->count();

                    $totalRejected += $count;
                }

                if ($countTotalRowIds === $totalRejected) {
                    $rejectedCount++;
                }

//                if ($countTotalRowIds === $checkCountOfRejectedAudits) {
//                    $rejectedCount++;
//                }

            }

//            dd($data);
            return response([
                'status' => 200,
                'pending' => $pending,
                'complete' => $complete,
                'rejected' => $rejectedCount,
                'reaudit' => $reaudit,
                'total' => ($pending + $complete + $rejected + $reaudit)
            ]);

        } catch (\Exception $e) {
//            dd($e->getMessage());
            return response([
                'status' => 401,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function fetchcompany(Request $request)
    {

        try {

            $company = Company::select('id', 'company_name')->get();

            return response([
                'status' => 200,
                'message' => 'success',
                'companyList' => $company,

            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    //fetch zone
    public function fetchzone($company_id)
    {

        try {
            $company = Zone::where('company_id', $company_id)->select('id', 'zone_name', 'company_id')->get();

            return response([
                'status' => 200,
                'message' => 'success',
                'zonelist' => $company,

            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    ///fetch unit
    public function fetchUnit($zone_id)
    {

        try {
            $zone_id = Unit::where('zone_id', $zone_id)->select('id', 'unit_name', 'zone_id')->get();

            return response([
                'status' => 200,
                'message' => 'success',
                'unit' => $zone_id,

            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    //fetch project
    public function fetchProject(Request $request)
    {

        try {
            $project = Project::where('company_id', $request->company_id)
                ->where('zone_id', $request->zone_id)
                ->where('unit_id', $request->unit_id)
                ->select('id', 'project_name')
                ->get();

            return response([
                'status' => 200,
                'message' => 'success',
                'project' => $project
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    //
    public function assignProject(Request $request)
    {

        try {

            // $user = Auth::user();
            $user = $request->user_id;



            $projectids = AuditorAssignedData::where('user_id', $user)->get();
            $uniqueProjects = $projectids->unique('project_id');
            $dataids = $uniqueProjects->pluck('id');


            $projectdata = AuditorAssignedData::with([
                'project:id,project_name',
                'dataAssign'
            ])
                ->select('id', 'project_id', 'data_assign_id', 'user_id', 'created_at')
                ->whereIn('id', $dataids)
                ->whereHas('dataAssign', function ($query) use ($request) {
                    if ($request->status != 8) {
                        $query->where('status', $request->status);
                    }
                })
                ->get();




            foreach ($projectdata as $item) {
                $statusData = DataAssign::where('project_id', $item->project_id)->pluck('status');
                if ($statusData->contains(0)) {
                    $item->status = 0;
                }
                if ($statusData->contains(3)) {
                    $item->status = 3;
                }
                if ($statusData->contains(4)) {
                    $item->status = 4;
                }
            }


            $projectdata = $projectdata->makeHidden(['dataAssign', 'data_assign_id']);



            return response([
                'status' => 200,
                'message' => 'success',
                'data' => $projectdata
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    //feych aassing acctivrei
    public function fetchActivities(Request $request)
    {
        // dd($request->all());
        try {
            $user_id = $request->user_id;

            $assignvalue = AuditorAssignedData::where('user_id', $user_id)
                ->pluck('template_name_head_value')
                ->toArray();

            $assignid = AuditorAssignedData::where('user_id', $user_id)->pluck('data_assign_id');

            $assingdata = DataAssign::with([
                'activityName:id,activity_name',
                'getActivityGroup:id,activity_group_name',
                'getProjectTemplate:id,is_master,master_head_id,completion_type,min_completion',
                'getAuditorValue'
            ])
                ->where(function ($query) use ($request) {
                    $query->whereHas('activityName', function ($q) use ($request) {
                        $q->where('activity_name', 'like', '%' . $request->keyword . '%');
                    })
                        ->orWhereHas('getActivityGroup', function ($q) use ($request) {
                            $q->where('activity_group_name', 'like', '%' . $request->keyword . '%');
                        });
                })
                ->where('project_id', $request->project_id)
                ->whereIn('id', $assignid)
                ->withCount('question')
                ->select('id', 'activity_group_id', 'activity_id', 'activity_group_id', 'status', 'project_id', 'project_template_id', 'template_name_id', 'template_name_head_id')->get();

            // dd($assingdata);
            foreach ($assingdata as $item) {
                $tyempdata    =   AuditorAssignedData::where('data_assign_id', $item->id)->first();
                $item->templatevalue = $tyempdata ? $tyempdata->template_name_head_value : null;
                $item->totalQuestion = Question::where('activity_id', $item->activity_id)->count();
                $item->totalDistributers = ProjectTemplateNameValue::where('project_template_id', $item->project_template_id)->where('template_name_head_id', $item->template_name_head_id)
                    ->whereIn('value', $assignvalue)
                    ->count();
            }
            $assingdata = $assingdata->makeHidden(['getAuditorValue']);

            return response([
                'status' => 200,
                'message' => 'success',
                'data' => $assingdata
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function  command()
    {
        Artisan::call('cache:clear');
        $exitCode = Artisan::call('route:clear');
        //   $exitCode = Artisan::call('optimize');
        return '<h1>Reoptimized class loader</h1>';
    }

    public function submitAnswer(Request $request)
    {

        try {

            // return $request;
            $validator = Validator::make($request->all(), [
                'answer' => 'required',
            ]);
            if ($validator->fails()) {
                return response([
                    'status' => 401,
                    'message' => $validator->errors()->first()
                ]);
            }

            //  $sameanswer = AnswerActivity::max('id');

            // while (AnswerActivity::where('same_answer_id', $sameanswer)->exists()) {
            //     $sameanswer = $sameanswer + 1;
            // }

            $image = '';
            if ($request->type == 'Image'  || $request->type == 'File Upload'  || $request->type == 'Video') {
                if ($request->url  ==  'true') {

                    $image = $request->answer;
                } else {

                    $folder = 'answer';
                    $image  = $request->answer;
                    $file = 'file_' . time()  . rand(0, 999) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path($folder), $file);
                    $image = url('/') . '/' . $folder . '/' . $file;
                }
            }
            $ques = Question::find($request->id);
            if ($ques->question_type == 'Dropdown') {
                $drop_value =  QuestionDropdown::find($request->answer);
                $request->answer = $drop_value->option;
            }


            $answer = AnswerActivity::where('question_id', $request->id)->where('row_id', $request->row_id)->first();

            if (!$answer) {
                $answer = new AnswerActivity();
            }
            $answer->user_id =  $request->user_id;
            $answer->row_id = $request->row_id;
            $answer->activity_group_name_id = $request->group_id;
            $answer->activity_sequence = $ques ? $ques->question_sequence : '';
            $answer->activity_id = $ques->activity_id;
            $answer->question_id = $request->id;
            $answer->user_answer = $image ? $image : $request->answer;
            $answer->same_answer_id = $request->row_id;;
            $answer->save();

            $groupActivity = ActivityGroupPivot::where('activity_group_id', $request->group_id)
                ->where('activity_id', $ques->activity_id)->first();

            if ($groupActivity &&  RowSequancedata::where('user_id',  $request->user_id)->where('row_id', $request->row_id)->doesntExist()) {
                $rqdata = new RowSequancedata();
                $rqdata->user_id =  $request->user_id;
                $rqdata->activity_id = $ques->activity_id;
                $rqdata->activity_group_id = $request->group_id;
                $rqdata->sequence = $groupActivity->sequence;
                $rqdata->row_id = $request->row_id;
                $rqdata->save();
            }



            return response([
                'status' => 200,
                'message' => 'Answer submit Successfully'
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => 401,
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    public function fetechDistributer(Request $request)
    {

        try {
            $user_id =  $request->user_id;

            $assignvalue = AuditorAssignedData::where('user_id', $user_id)
                ->pluck('template_name_head_value')
                ->toArray();



            // $dataQuery = DataAssign::where('id', $assignIds);

            // if ($request->status !=  8) {
            //     $dataQuery->where('status', $request->status);
            // }


            // $data = $dataQuery->pluck('project_template_id')->toArray();
            // $tdata = $dataQuery->pluck('template_name_head_id')->toArray();



            $projectsQuery = ProjectTemplateNameValue::with(['getDataOfRows.getHeadName'])
                ->where('project_template_id', $data)
                ->whereIn('template_name_head_id', $tdata)
                ->where('project_template_id', $request->project_template_id)
                ->whereIn('value', $assignvalue)
                ->where('template_name_head_id', $request->template_name_head_id);
            if ($request->keyword) {
                $projectsQuery->where('value', 'like', '%' . $request->keyword . '%');
            }


            $projects = $projectsQuery->paginate(10);
            $rowIds = $projects->pluck('row_id')->toArray();
            $keyCodes = ProjectTemplateNameValue::whereIn('row_id', $rowIds)
                ->where('template_name_head_id', '14')
                ->pluck('value', 'row_id')
                ->toArray();

            foreach ($projects as $project) {
                $project->key_code = $keyCodes[$project->row_id] ?? null;
                $status = DataAssign::where('project_template_id', $project->project_template_id)
                    ->where('project_template_id', $project->project_template_id)->first();
                $project->status = $status->status;
            }




            return response([
                'status' => 200,
                'message' => 'successs',
                // 'data'=>$projects
            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => '401',
                'message' => $th->getMessage(),
            ], 401);
        }
    }
    ///new api for distributer
    public function newfetechDistributer(Request $request)
    {

        try {



            $user_id =  $request->user_id;


            $dataAssing = DataAssign::where('project_template_id', $request->project_template_id)
                ->where('template_name_head_id', $request->template_name_head_id)
                ->where('activity_id', $request->activity_id)->first();

            $assignvalue = AuditorAssignedData::where('user_id', $user_id)
                ->pluck('template_name_head_value')
                ->toArray();

            $activitysequance = ActivityGroupPivot::where('activity_id', $request->activity_id)->where('activity_group_id', $dataAssing->activity_group_id)->first();

            if ($dataAssing->activity_group_id &&  $activitysequance->sequence != 1) {
                if (!RowSequancedata::where('user_id', $request->user_id)->where('activity_group_id', $dataAssing->activity_group_id)->first()) {
                    $assignvalue = [];
                }
            }



            $projectsQuery = ProjectTemplateNameValue::with(['getDataOfRows.getHeadName'])->where('project_template_id', $request->project_template_id)
                ->whereIn('value', $assignvalue)
                ->where('template_name_head_id', $request->template_name_head_id)
                ->paginate(10);

            if ($request->keyword) {
                $projectsQuery->where('value', 'like', '%' . $request->keyword . '%');
            }
            $totaldistriburtor  = ProjectTemplateNameValue::where('project_template_id', $request->project_template_id)
                ->where('value', $request->value)
                ->where('template_name_head_id', $request->template_name_head_id)->count();
            $result = 0;
            foreach ($projectsQuery as $key => $value) {
                $data = AuditorAssignedData::with('dataAssign.getProjectTemplate')->where('user_id', $user_id)->whereIn('template_name_head_value', $assignvalue)->first();

                $totalQuestion = Question::where('activity_id', $request->activity_id)->count();

                //khushboo 12-05-25
                // $keycode = TemplateNameHead::getColume($data->dataAssign->template_name_id, 'key_code')->first();
                // $keyname = TemplateNameHead::getColume($data->dataAssign->template_name_id, 'key_name')->first();

                // $templtekeyname =  ProjectTemplateNameValue::where('row_id', $value->row_id)->where('template_name_head_id', $keyname->id)->first();
                // $templtevalue =  ProjectTemplateNameValue::where('row_id', $value->row_id)->where('template_name_head_id', $keycode->id)->first();


                // $value->key_name = $templtekeyname ? $templtekeyname->value : null;
                // $value->key_code = $templtevalue ? $templtevalue->value : null;
                //khushboo 12-05-25

                //khushboo 17-05-25
                $keycode = TemplateNameHead::getColume($data->dataAssign->template_name_id, 'latitude')->first();
                $keyname = TemplateNameHead::getColume($data->dataAssign->template_name_id, 'longitude')->first();

                $templtekeyname =  ProjectTemplateNameValue::where('row_id', $value->row_id)->where('template_name_head_id', $keyname->id)->first();
                $templtevalue =  ProjectTemplateNameValue::where('row_id', $value->row_id)->where('template_name_head_id', $keycode->id)->first();

                $value->latitude = $templtekeyname ? $templtekeyname->value : null;
                $value->longitude = $templtevalue ? $templtevalue->value : null;

                //khushboo 17-05-25


                $answer =  AnswerActivity::where('row_id', $value->row_id)->where('activity_id',  $request->activity_id)->count();
                // return data->dataAssign->activity_id;
                $value->status = 0;
                if ($totalQuestion == $answer) {
                    $value->status = 2;
                }
                if ($data->dataAssign->status == 3) {
                    $value->status = 3;
                }
                if ($data->dataAssign->status == 4) {
                    $value->status = 4;
                }
                if ($data->dataAssign) {
                    $statusValue =  $data->dataAssign->getProjectTemplate;
                    if ($statusValue->completion_type == 'Percentage') {

                        $result =  ($totaldistriburtor *  $statusValue->min_completion) / 100;
                        $result =   ceil($result);
                    } else {
                        $result =   ceil($statusValue->min_completion);
                    }
                }
            }



            $status = false;
            $completestatus = $projectsQuery->where('status', 2)->count();


            if ($completestatus  >= $result) {
                $status = true;
            }

            return response([
                'status' => '200',
                'message' => 'success',
                'submit' => $status,
                'data' => $projectsQuery,

            ], 200);
        } catch (\Throwable $th) {
            return response([
                'status' => '401',
                'message' => $th->getMessage(),
            ], 401);
        }
    }
}
