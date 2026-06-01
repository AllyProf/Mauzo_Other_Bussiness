<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index()
    {
        // Get the latest 500 audit logs with user and business relationships
        $logs = AuditLog::with(['user', 'business'])->latest()->paginate(100);
        
        return view('admin.audit_logs.index', compact('logs'));
    }
}
