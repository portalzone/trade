<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Waybill;
use App\Services\WaybillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WaybillController extends Controller
{
    protected WaybillService $waybillService;

    public function __construct(WaybillService $waybillService)
    {
        $this->waybillService = $waybillService;
    }

    /**
     * Generate waybill for an order
     */
    public function generate(Request $request, $orderId)
    {
        $validator = Validator::make($request->all(), [
            'sender_address' => 'required|string',
            'recipient_address' => 'required|string',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string',
            'delivery_type' => 'nullable|in:standard,express,same_day',
            'courier_service' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = Order::findOrFail($orderId);
            
            // Only seller can generate waybill
            if ($order->seller_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only the seller can generate a waybill.',
                ], 403);
            }

            $waybill = $this->waybillService->generateWaybill($order, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Waybill generated successfully',
                'data' => $waybill,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate waybill',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get waybill for an order
     */
    public function show(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Only seller or buyer can view waybill
            if (!in_array($request->user()->id, [$order->seller_id, $order->buyer_id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $waybill = $this->waybillService->getByOrder($order);

            if (!$waybill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waybill not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $waybill,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve waybill',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download waybill PDF
     */
    public function downloadPDF(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Only seller or buyer can download
            if (!in_array($request->user()->id, [$order->seller_id, $order->buyer_id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $waybill = $this->waybillService->getByOrder($order);

            if (!$waybill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waybill not found. Please generate one first.',
                ], 404);
            }

            return $this->waybillService->downloadPDF($waybill);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download waybill',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * View waybill PDF in browser
     */
    public function viewPDF(Request $request, $orderId)
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Only seller or buyer can view
            if (!in_array($request->user()->id, [$order->seller_id, $order->buyer_id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $waybill = $this->waybillService->getByOrder($order);

            if (!$waybill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waybill not found',
                ], 404);
            }

            return $this->waybillService->streamPDF($waybill);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to view waybill',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
