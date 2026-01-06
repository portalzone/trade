<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Dispute;
use App\Models\BusinessVerification;
use App\Models\Tier3Verification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function overview(Request $request)
    {
        try {
            $timeRange = $request->query('range', '30'); // days

            $stats = [
                'users' => [
                    'total' => User::count(),
                    'new_today' => User::whereDate('created_at', today())->count(),
                    'new_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                    'new_this_month' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                ],
                'transactions' => [
                    'total' => PaymentTransaction::where('status', 'COMPLETED')->count(),
                    'total_volume' => PaymentTransaction::where('status', 'COMPLETED')->sum('amount'),
                    'today' => PaymentTransaction::where('status', 'COMPLETED')->whereDate('created_at', today())->sum('amount'),
                    'this_month' => PaymentTransaction::where('status', 'COMPLETED')->whereMonth('created_at', now()->month)->sum('amount'),
                ],
                'orders' => [
                    'total' => Order::count(),
                    'active' => Order::whereIn('order_status', ['PENDING', 'IN_ESCROW'])->count(),
                    'completed' => Order::where('order_status', 'COMPLETED')->count(),
                    'disputed' => Order::where('order_status', 'DISPUTED')->count(),
                ],
                'disputes' => [
                    'total' => Dispute::count(),
                    'pending' => Dispute::where('dispute_status', 'PENDING_REVIEW')->count(),
                    'resolved' => Dispute::whereIn('dispute_status', ['RESOLVED_BUYER', 'RESOLVED_SELLER', 'RESOLVED_REFUND'])->count(),
                ],
                'kyc' => [
                    'tier2_pending' => BusinessVerification::where('verification_status', 'PENDING')->count(),
                    'tier2_approved' => BusinessVerification::where('verification_status', 'APPROVED')->count(),
                    'tier3_pending' => Tier3Verification::where('verification_status', 'PENDING_REVIEW')->count(),
                    'tier3_approved' => Tier3Verification::where('verification_status', 'APPROVED')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch overview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user growth chart data
     */
    public function userGrowth(Request $request)
    {
        try {
            $days = $request->query('days', 30);
            $startDate = now()->subDays($days)->startOfDay();

            $growth = User::where('created_at', '>=', $startDate)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Fill in missing dates with 0
            $data = [];
            $currentDate = Carbon::parse($startDate);
            $endDate = now();

            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $found = $growth->firstWhere('date', $dateStr);
                
                $data[] = [
                    'date' => $dateStr,
                    'users' => $found ? $found->count : 0,
                ];
                
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user growth',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction volume chart data
     */
    public function transactionVolume(Request $request)
    {
        try {
            $days = $request->query('days', 30);
            $startDate = now()->subDays($days)->startOfDay();

            $transactions = PaymentTransaction::where('created_at', '>=', $startDate)
                ->where('status', 'COMPLETED')
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(amount) as volume')
                )
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Fill in missing dates
            $data = [];
            $currentDate = Carbon::parse($startDate);
            $endDate = now();

            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');
                $found = $transactions->firstWhere('date', $dateStr);
                
                $data[] = [
                    'date' => $dateStr,
                    'count' => $found ? $found->count : 0,
                    'volume' => $found ? (float)$found->volume : 0,
                ];
                
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transaction volume',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get revenue breakdown
     */
    public function revenue(Request $request)
    {
        try {
            $days = $request->query('days', 30);
            $startDate = now()->subDays($days)->startOfDay();

            // Calculate revenue from completed transactions
            $revenue = PaymentTransaction::where('created_at', '>=', $startDate)
                ->where('status', 'COMPLETED')
                ->where('transaction_type', 'DEPOSIT')
                ->sum('amount');

            // Get revenue by payment method
            $byMethod = PaymentTransaction::where('created_at', '>=', $startDate)
                ->where('status', 'COMPLETED')
                ->select('payment_method', DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => (float)$revenue,
                    'by_method' => $byMethod,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch revenue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get KYC approval rates
     */
    public function kycRates(Request $request)
    {
        try {
            // Tier 2 stats
            $tier2Total = BusinessVerification::count();
            $tier2Approved = BusinessVerification::where('verification_status', 'APPROVED')->count();
            $tier2Rejected = BusinessVerification::where('verification_status', 'REJECTED')->count();
            $tier2Pending = BusinessVerification::where('verification_status', 'PENDING')->count();

            // Tier 3 stats
            $tier3Total = Tier3Verification::count();
            $tier3Approved = Tier3Verification::where('verification_status', 'APPROVED')->count();
            $tier3Rejected = Tier3Verification::where('verification_status', 'REJECTED')->count();
            $tier3Pending = Tier3Verification::whereIn('verification_status', ['PENDING_REVIEW', 'UNDER_REVIEW'])->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'tier2' => [
                        'total' => $tier2Total,
                        'approved' => $tier2Approved,
                        'rejected' => $tier2Rejected,
                        'pending' => $tier2Pending,
                        'approval_rate' => $tier2Total > 0 ? round(($tier2Approved / $tier2Total) * 100, 2) : 0,
                    ],
                    'tier3' => [
                        'total' => $tier3Total,
                        'approved' => $tier3Approved,
                        'rejected' => $tier3Rejected,
                        'pending' => $tier3Pending,
                        'approval_rate' => $tier3Total > 0 ? round(($tier3Approved / $tier3Total) * 100, 2) : 0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch KYC rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user distribution by type and tier
     */
    public function userDistribution()
    {
        try {
            $byType = User::select('user_type', DB::raw('COUNT(*) as count'))
                ->groupBy('user_type')
                ->get();

            $byTier = User::select('kyc_tier', DB::raw('COUNT(*) as count'))
                ->groupBy('kyc_tier')
                ->orderBy('kyc_tier')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'by_type' => $byType,
                    'by_tier' => $byTier,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user distribution',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
