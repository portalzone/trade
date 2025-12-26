<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    /**
     * Get my audit logs
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $logs = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get specific action logs for current user
     */
    public function getByAction(Request $request, string $action)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $logs = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->where('action', 'LIKE', $action . '%')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get payment-related audit logs
     */
    public function getPaymentLogs(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $logs = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->where('action', 'LIKE', 'payment.%')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get wallet-related audit logs
     */
    public function getWalletLogs(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $logs = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->where('action', 'LIKE', 'wallet.%')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get failed actions
     */
    public function getFailedActions(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $logs = DB::table('audit_logs')
            ->where('user_id', $user->id)
            ->where('status', 'FAILED')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get audit log statistics
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->count(),
            
            'successful_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('status', 'SUCCESS')
                ->count(),
            
            'failed_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('status', 'FAILED')
                ->count(),
            
            'payment_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('action', 'LIKE', 'payment.%')
                ->count(),
            
            'wallet_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->where('action', 'LIKE', 'wallet.%')
                ->count(),
            
            'recent_actions' => DB::table('audit_logs')
                ->where('user_id', $user->id)
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
