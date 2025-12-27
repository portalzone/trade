<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TransactionLimitService;
use Illuminate\Http\Request;

class TransactionLimitController extends Controller
{
    protected TransactionLimitService $limitService;

    public function __construct(TransactionLimitService $limitService)
    {
        $this->limitService = $limitService;
    }

    /**
     * Get current user's transaction limit stats
     */
    public function getStats(Request $request)
    {
        $stats = $this->limitService->getLimitStats($request->user());

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Check if user can make a transaction
     */
    public function checkLimit(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $check = $this->limitService->canTransact(
            $request->user(),
            $request->amount
        );

        return response()->json([
            'success' => true,
            'data' => $check,
        ]);
    }
}
