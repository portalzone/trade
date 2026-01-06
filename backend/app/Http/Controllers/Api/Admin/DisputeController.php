<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\DisputeService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    protected DisputeService $disputeService;
    protected NotificationService $notificationService;

    public function __construct(
        DisputeService $disputeService,
        NotificationService $notificationService
    ) {
        $this->disputeService = $disputeService;
        $this->notificationService = $notificationService;
    }

    /**
     * List all disputes
     */
    public function index(Request $request)
    {
        $query = Dispute::with([
            'order:id,title,price,order_status',
            'raisedBy:id,full_name,email'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('dispute_status', $request->status);
        }

        // Filter by pending (open or under review)
        if ($request->boolean('pending')) {
            $query->whereIn('dispute_status', ['OPEN', 'UNDER_REVIEW']);
        }

        // Sort by newest first
        $disputes = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $disputes,
        ]);
    }

    /**
     * View dispute details
     */
    public function show($id)
    {
        $dispute = Dispute::with([
            'order.seller:id,full_name,email,phone_number',
            'order.buyer:id,full_name,email,phone_number',
            'order.escrowLock',
            'raisedBy:id,full_name,email'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dispute,
        ]);
    }

    /**
     * Resolve dispute
     */
    public function resolve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'resolution' => 'required|in:buyer,seller,partial',
            'buyer_amount' => 'required_if:resolution,partial|numeric|min:0',
            'seller_amount' => 'required_if:resolution,partial|numeric|min:0',
            'admin_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dispute = Dispute::with(['order.buyer', 'order.seller'])->findOrFail($id);

            // Check if already resolved
            if ($dispute->isResolved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispute has already been resolved',
                ], 400);
            }

            switch ($request->resolution) {
                case 'buyer':
                    $result = $this->disputeService->resolveInFavorOfBuyer(
                        $dispute,
                        $request->admin_notes
                    );
                    $message = 'Dispute resolved in favor of buyer - Full refund issued';
                    $resolution = 'Resolved in favor of buyer with full refund';
                    break;

                case 'seller':
                    $result = $this->disputeService->resolveInFavorOfSeller(
                        $dispute,
                        $request->admin_notes
                    );
                    $message = 'Dispute resolved in favor of seller - Full payment released';
                    $resolution = 'Resolved in favor of seller with full payment';
                    break;

                case 'partial':
                    $result = $this->disputeService->resolvePartialRefund(
                        $dispute,
                        $request->buyer_amount,
                        $request->seller_amount,
                        $request->admin_notes
                    );
                    $message = "Dispute resolved with partial refund - Buyer: ₦{$request->buyer_amount}, Seller: ₦{$request->seller_amount}";
                    $resolution = "Partial refund - Buyer receives ₦{$request->buyer_amount}, Seller receives ₦{$request->seller_amount}";
                    break;
            }

            // Send email notifications to both parties
            $this->notificationService->sendDisputeUpdate($dispute->order->buyer, $result, $resolution);
            $this->notificationService->sendDisputeUpdate($dispute->order->seller, $result, $resolution);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result->load(['order', 'raisedBy:id,full_name,email']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve dispute',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add admin notes to dispute
     */
    public function addNote(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dispute = Dispute::findOrFail($id);

            $existingNotes = $dispute->admin_notes ?? '';
            $timestamp = now()->format('Y-m-d H:i:s');
            $adminName = $request->user()->full_name;
            $newNote = "\n[{$timestamp}] {$adminName}: {$request->note}";

            $dispute->update([
                'admin_notes' => $existingNotes . $newNote,
                'dispute_status' => $dispute->dispute_status === 'OPEN' ? 'UNDER_REVIEW' : $dispute->dispute_status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully',
                'data' => $dispute->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dispute statistics
     */
    public function statistics()
    {
        $stats = [
            'total' => Dispute::count(),
            'open' => Dispute::where('dispute_status', 'OPEN')->count(),
            'under_review' => Dispute::where('dispute_status', 'UNDER_REVIEW')->count(),
            'resolved_buyer' => Dispute::where('dispute_status', 'RESOLVED_BUYER')->count(),
            'resolved_seller' => Dispute::where('dispute_status', 'RESOLVED_SELLER')->count(),
            'resolved_partial' => Dispute::where('dispute_status', 'RESOLVED_REFUND')->count(),
            'pending' => Dispute::whereIn('dispute_status', ['OPEN', 'UNDER_REVIEW'])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
