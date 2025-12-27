<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    protected WithdrawalService $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    /**
     * Get user's withdrawal history
     */
    public function index(Request $request)
    {
        $withdrawals = Withdrawal::where('user_id', $request->user()->id)
            ->with('bankAccount')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
        ]);
    }

    /**
     * Create withdrawal request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1000',
            'bank_account_id' => 'required|exists:bank_accounts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $withdrawal = $this->withdrawalService->createWithdrawal(
                $request->user(),
                $request->amount,
                $request->bank_account_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request created successfully',
                'data' => $withdrawal,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create withdrawal',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel withdrawal
     */
    public function cancel(Request $request, $id)
    {
        try {
            $withdrawal = Withdrawal::findOrFail($id);
            $cancelled = $this->withdrawalService->cancelWithdrawal($withdrawal, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal cancelled successfully',
                'data' => $cancelled,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel withdrawal',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
