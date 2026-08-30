<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($module = $request->query('module')) {
            $query->where('module', $module);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->paginate($request->integer('per_page', 25));

        return response()->json($logs->through(fn (ActivityLog $log) => [
            'id' => $log->id,
            'admin' => $log->user?->name ?? 'System',
            'action' => $log->action,
            'module' => $log->module,
            'record' => $log->subject_label,
            'ip' => $log->ip_address,
            'at' => $log->created_at->toIso8601String(),
        ]));
    }
}
