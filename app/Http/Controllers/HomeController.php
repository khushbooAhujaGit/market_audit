<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * Numbers are cached per user and filter set for 5 minutes; ?refresh=1 rebuilds them.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Field auditors work from "My Projects", not the dashboard.
        if ($user->hasRole('Auditor') && !$user->hasAnyRole(['Super Admin', 'Audit Firm', 'Company User', 'Agency', 'Verifier'])) {
            return redirect()->route('user.projects');
        }

        $metrics = new DashboardMetrics($user, $request);

        $key = 'dashboard:' . $user->id . ':' . md5(json_encode($request->only([
            'company_id', 'zone_id', 'unit_id', 'project_id', 'from', 'to', 'include_completed',
        ])));

        if ($request->boolean('refresh')) {
            Cache::forget($key);
        }

        $data = Cache::remember($key, now()->addMinutes(5), fn() => $metrics->build());

        return view('dashboard', $data);
    }
}
