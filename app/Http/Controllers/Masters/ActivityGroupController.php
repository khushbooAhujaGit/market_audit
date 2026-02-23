<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityGroup;
use App\Models\ActivityGroupPivot;
use Illuminate\Http\Request;

class ActivityGroupController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }

    public function index()
    {
        $activity_groups = ActivityGroup::paginate(25);
        $activities = Activity::all();
        return view('masters.activities.activity_group_list', compact('activity_groups', 'activities'));
    }
    public function create_group()
    {
        $activities = Activity::all();
        return view('masters.activities.activity_group', compact('activities'));
    }

    public function store_activity_group(Request $request)
    {
        $checkgroup = ActivityGroup::where('activity_group_name', $request->group_name)->first();
        if ($checkgroup) {
            return response()->json(['message' => "already"]);
        }
        $group_info = ActivityGroup::create([
            'activity_group_name' => $request->group_name
        ]);
        foreach ($request->temp_arr as $temp) {
            ActivityGroupPivot::create([
                'activity_group_id' => $group_info->id,
                'activity_id' => $temp['id'],
                'sequence' => $temp['sequence']
            ]);
        }
        return  response()->json(['message' => "success"]);
    }

    public function activity_group_edit($id)
    {
        $activities = Activity::all();
        $group_info = ActivityGroup::findOrFail($id);
        return view('masters.activities.edit_activity_group', compact('activities', 'group_info'));
    }

    public function activityGroupDestroy(Request $request)
    {
        $activityGroup = ActivityGroup::findOrFail($request->id);

        ActivityGroupPivot::where('activity_group_id', $activityGroup->id)->delete();
        $activityGroup->delete();
        return "Success";
    }
    public function groupActivityDestroy(Request $request)
    {
        ActivityGroupPivot::where('id', $request->id)->delete();
        return "Success";
    }
    public function update_activity_group(Request $request, $id)
    {
        $groupInfo = ActivityGroup::findOrFail($id);
        if ($groupInfo->activity_group_name !==  $request->group_name) {
            $groupInfo->activity_group_name = $request->group_name;
            $groupInfo->save();
        }
        foreach ($request->temp_arr as $temp) {
            $activityGroup_temp = ActivityGroupPivot::where('activity_group_id', $id)
                ->where('activity_id', $temp['id'])->first();
            if ($activityGroup_temp) {
                if ($activityGroup_temp->sequence !== $temp['sequence']) {
                    $activityGroup_temp->sequence = $temp['sequence'];
                    $activityGroup_temp->save();
                }
            } else {
                ActivityGroupPivot::create([
                    'activity_group_id' => $groupInfo->id,
                    'activity_id' => $temp['id'],
                    'sequence' => $temp['sequence']
                ]);
            }
        }
        return response()->json(['message' => "success"]);
    }

    public function group_activites(Request $request)
    {
        $groupInfo = ActivityGroup::with('get_group_activities')->findOrFail($request->g_id);
        return $groupInfo;
    }
}
