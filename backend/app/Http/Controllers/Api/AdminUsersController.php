<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminUsersController extends Controller
{
    /**
     * Get all users with filters
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['wallet']);

            // Search filter
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'ILIKE', "%{$search}%")
                      ->orWhere('email', 'ILIKE', "%{$search}%")
                      ->orWhere('username', 'ILIKE', "%{$search}%")
                      ->orWhere('phone_number', 'ILIKE', "%{$search}%");
                });
            }

            // Filter by user type
            if ($request->user_type) {
                $query->where('user_type', $request->user_type);
            }

            // Filter by KYC tier
            if ($request->kyc_tier) {
                $query->where('kyc_tier', $request->kyc_tier);
            }

            // Filter by KYC status
            if ($request->kyc_status) {
                $query->where('kyc_status', $request->kyc_status);
            }

            // Filter by account status
            if ($request->account_status) {
                $query->where('account_status', $request->account_status);
            }

            // Order by created_at
            $query->orderBy('created_at', 'desc');

            // Paginate
            $perPage = $request->per_page ?? 20;
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function show($id)
    {
        try {
            $user = User::with(['wallet', 'orders', 'businessVerification', 'tier3Verification'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'full_name' => $user->full_name,
                        'username' => $user->username,
                        'user_type' => $user->user_type,
                        'kyc_status' => $user->kyc_status,
                        'kyc_tier' => $user->kyc_tier,
                        'account_status' => $user->account_status,
                        'email_verified_at' => $user->email_verified_at,
                        'phone_verified_at' => $user->phone_verified_at,
                        'mfa_enabled' => $user->mfa_enabled,
                        'last_login_at' => $user->last_login_at,
                        'created_at' => $user->created_at,
                    ],
                    'wallet' => $user->wallet ? [
                        'available_balance' => $user->wallet->available_balance,
                        'locked_escrow_funds' => $user->wallet->locked_escrow_funds,
                        'total_balance' => $user->wallet->total_balance,
                        'wallet_status' => $user->wallet->wallet_status,
                    ] : null,
                    'stats' => [
                        'total_orders' => $user->orders->count(),
                        'total_spent' => $user->orders->where('order_status', 'COMPLETED')->sum('price'),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update user account status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'account_status' => 'required|in:ACTIVE,SUSPENDED,BANNED',
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            // Don't allow banning admins
            if ($user->user_type === 'ADMIN' && $request->account_status !== 'ACTIVE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot suspend or ban admin accounts'
                ], 403);
            }

            $user->update([
                'account_status' => $request->account_status
            ]);

            // TODO: Log the status change with reason
            // TODO: Send email notification to user

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'account_status' => $user->account_status
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user email address (Admin only)
     */
    public function updateEmail(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $id,
            'reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::findOrFail($id);

            $oldEmail = $user->email;

            $user->update([
                'email' => $request->email,
                'email_verified_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User email updated successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'old_email' => $oldEmail,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('account_status', 'ACTIVE')->count(),
                'suspended_users' => User::where('account_status', 'SUSPENDED')->count(),
                'banned_users' => User::where('account_status', 'BANNED')->count(),
                'by_type' => [
                    'buyers' => User::where('user_type', 'BUYER')->count(),
                    'sellers' => User::where('user_type', 'SELLER')->count(),
                    'admins' => User::where('user_type', 'ADMIN')->count(),
                ],
                'by_tier' => [
                    'tier1' => User::where('kyc_tier', 1)->count(),
                    'tier2' => User::where('kyc_tier', 2)->count(),
                    'tier3' => User::where('kyc_tier', 3)->count(),
                ],
                'verified_email' => User::whereNotNull('email_verified_at')->count(),
                'verified_phone' => User::whereNotNull('phone_verified_at')->count(),
                'mfa_enabled' => User::where('mfa_enabled', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}