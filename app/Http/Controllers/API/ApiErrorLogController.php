<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApiErrorLog;
use Illuminate\Http\Request;

class ApiErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ApiErrorLog::orderBy('created_at', 'desc');

        if ($request->keyword) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('endpoint', 'like', "%{$kw}%")
                  ->orWhere('error_message', 'like', "%{$kw}%");
            });
        }

        if ($request->status_code) {
            $query->where('status_code', $request->status_code);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $logs    = $query->paginate($perPage);

        return response()->json([
            'status'       => 200,
            'data'         => $logs->items(),
            'total'        => $logs->total(),
            'per_page'     => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page'    => $logs->lastPage(),
        ]);
    }
}
