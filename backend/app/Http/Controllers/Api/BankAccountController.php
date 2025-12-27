<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    /**
     * Get user's bank accounts
     */
    public function index(Request $request)
    {
        $accounts = BankAccount::where('user_id', $request->user()->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Add bank account
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|size:10',
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_code' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if this is user's first account
            $isFirst = !BankAccount::where('user_id', $request->user()->id)->exists();

            $account = BankAccount::create([
                'user_id' => $request->user()->id,
                'account_number' => $request->account_number,
                'account_name' => $request->account_name,
                'bank_name' => $request->bank_name,
                'bank_code' => $request->bank_code,
                'is_verified' => true, // Auto-verify for now (TODO: integrate Paystack verification)
                'is_primary' => $isFirst, // First account is primary
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank account added successfully',
                'data' => $account,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add bank account',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Set primary bank account
     */
    public function setPrimary(Request $request, $id)
    {
        try {
            $account = BankAccount::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            // Remove primary from all user's accounts
            BankAccount::where('user_id', $request->user()->id)
                ->update(['is_primary' => false]);

            // Set this as primary
            $account->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Primary account updated',
                'data' => $account,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update primary account',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete bank account
     */
    public function destroy(Request $request, $id)
    {
        try {
            $account = BankAccount::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($account->is_primary) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete primary account. Set another account as primary first.',
                ], 400);
            }

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank account',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
