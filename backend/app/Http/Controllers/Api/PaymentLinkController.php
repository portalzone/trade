<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentLink;
use App\Services\PaymentLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentLinkController extends Controller
{
    protected PaymentLinkService $paymentLinkService;

    public function __construct(PaymentLinkService $paymentLinkService)
    {
        $this->paymentLinkService = $paymentLinkService;
    }

    /**
     * Get user's payment links
     */
    public function index(Request $request)
    {
        $links = PaymentLink::where('user_id', $request->user()->id)
            ->withCount('payments')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $links,
        ]);
    }

    /**
     * Create payment link
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:100',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $paymentLink = $this->paymentLinkService->createPaymentLink(
                $request->user(),
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment link created successfully',
                'data' => $paymentLink,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment link',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment link details
     */
    public function show($slug)
    {
        try {
            $paymentLink = PaymentLink::where('slug', $slug)
                ->with('user:id,full_name')
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_link' => $paymentLink,
                    'is_active' => $paymentLink->isActive(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }
    }

    /**
     * Update payment link
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:100',
            'status' => 'sometimes|in:active,inactive',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $paymentLink = PaymentLink::findOrFail($id);
            $updated = $this->paymentLinkService->updatePaymentLink(
                $paymentLink,
                $request->user(),
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment link updated successfully',
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment link',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete payment link
     */
    public function destroy(Request $request, $id)
    {
        try {
            $paymentLink = PaymentLink::findOrFail($id);
            $this->paymentLinkService->deletePaymentLink($paymentLink, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Payment link deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment link',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment link statistics
     */
    public function stats(Request $request, $id)
    {
        try {
            $paymentLink = PaymentLink::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $stats = $this->paymentLinkService->getStats($paymentLink);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found',
            ], 404);
        }
    }

    /**
     * Process payment via link
     */
    public function pay(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'payer_name' => 'required_without:payer_id|string|max:255',
            'payer_email' => 'required_without:payer_id|email',
            'payer_phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $paymentLink = PaymentLink::where('slug', $slug)->firstOrFail();

            $payment = $this->paymentLinkService->processPayment($paymentLink, [
                'payer' => $request->user(),
                'payer_name' => $request->payer_name,
                'payer_email' => $request->payer_email,
                'payer_phone' => $request->payer_phone,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => $payment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
