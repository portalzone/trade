<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\EscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected EscrowService $escrowService;

    public function __construct(OrderService $orderService, EscrowService $escrowService)
    {
        $this->orderService = $orderService;
        $this->escrowService = $escrowService;
    }

    /**
     * List all active orders (marketplace)
     */
    public function index(Request $request)
    {
        $query = Order::with(['seller:id,full_name,email'])
            ->active()
            ->latest();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Search by title/description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $orders = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get my orders as seller
     */
    public function mySelling(Request $request)
    {
        $orders = Order::with(['buyer:id,full_name,email'])
            ->bySeller($request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get my orders as buyer
     */
    public function myBuying(Request $request)
    {
        $orders = Order::with(['seller:id,full_name,email'])
            ->byBuyer($request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * View single order
     */
    public function show($id)
    {
        $order = Order::with(['seller:id,full_name,email', 'buyer:id,full_name,email', 'escrowLock'])
            ->findOrFail($id);

        // Calculate escrow details if order is active
        $escrowDetails = null;
        if ($order->isAvailable()) {
            $escrowDetails = $this->escrowService->calculateEscrowDetails($order);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $order,
                'escrow_details' => $escrowDetails,
            ],
        ]);
    }

    /**
     * Create new order (seller)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:' . config('escrow.min_order_amount') . '|max:' . config('escrow.max_order_amount'),
            'currency' => 'nullable|in:NGN,USD',
            'category' => 'nullable|string|max:100',
            'images' => 'nullable|array',
            'images.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = $this->orderService->createOrder($request->user(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update order (seller only, ACTIVE orders only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:' . config('escrow.min_order_amount') . '|max:' . config('escrow.max_order_amount'),
            'category' => 'nullable|string|max:100',
            'images' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);
            $updatedOrder = $this->orderService->updateOrder($order, $request->user(), $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $updatedOrder,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete order (seller only, ACTIVE orders only)
     */
    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            $this->orderService->deleteOrder($order, request()->user());

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Purchase order (buyer locks funds in escrow)
     */
    public function purchase(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            $purchasedOrder = $this->orderService->purchaseOrder($order, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Order purchased successfully. Funds locked in escrow.',
                'data' => $purchasedOrder->load(['seller:id,full_name,email', 'escrowLock']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Complete order (buyer confirms delivery, releases funds to seller)
     */
    public function complete(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            $completedOrder = $this->orderService->completeOrder($order, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Order completed successfully. Funds released to seller.',
                'data' => $completedOrder->load(['seller:id,full_name,email', 'buyer:id,full_name,email']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);
            $cancelledOrder = $this->orderService->cancelOrder($order, $request->user(), $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.',
                'data' => $cancelledOrder,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Raise dispute
     */
    public function dispute(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($id);
            $dispute = $this->orderService->raiseDispute($order, $request->user(), $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Dispute raised successfully. Admin will review.',
                'data' => $dispute->load(['order', 'raisedBy:id,full_name,email']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to raise dispute',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
