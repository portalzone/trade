<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\Admin\DisputeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

// Health Check
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    try {
        Cache::get('health_check');
        $redisStatus = 'connected';
    } catch (\Exception $e) {
        $redisStatus = 'disconnected';
    }

    $status = ($dbStatus === 'connected' && $redisStatus === 'connected') ? 'ok' : 'degraded';

    return response()->json([
        'status' => $status,
        'database' => $dbStatus,
        'redis' => $redisStatus,
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
    ]);
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::post('/email/verification-notification', [AuthController::class, 'sendEmailVerification'])
        ->middleware('throttle:6,1');
    Route::post('/phone/send-otp', [AuthController::class, 'sendPhoneOtp'])
        ->middleware('throttle:6,1');
    Route::post('/phone/verify-otp', [AuthController::class, 'verifyPhoneOtp'])
        ->middleware('throttle:3,1');
});

// ============================================
// PROTECTED ROUTES (Authentication Required)
// ============================================

Route::middleware('auth:sanctum')->group(function () {
    
    // Authentication
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Wallet Routes
    Route::prefix('wallet')->group(function () {
        Route::get('/', function (Request $request) {
            $wallet = $request->user()->wallet;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'wallet' => [
                        'id' => $wallet->id,
                        'available_balance' => $wallet->available_balance,
                        'locked_escrow_funds' => $wallet->locked_escrow_funds,
                        'total_balance' => $wallet->total_balance,
                        'currency' => $wallet->currency,
                        'wallet_status' => $wallet->wallet_status,
                    ]
                ]
            ]);
        });
        
        Route::get('/transactions', function (Request $request) {
            $wallet = $request->user()->wallet;
            $limit = $request->query('limit', 50);
            
            $transactions = $wallet->getTransactionHistory($limit);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transactions' => $transactions,
                    'count' => $transactions->count(),
                ]
            ]);
        });
        
        Route::get('/locked-funds', function (Request $request) {
            $wallet = $request->user()->wallet;
            $breakdown = $wallet->getLockedFundsBreakdown();
            
            return response()->json([
                'success' => true,
                'data' => $breakdown
            ]);
        });
    });
    
    // Payments Routes
    Route::prefix('payments')->group(function () {
        Route::post('/deposit/initiate', [PaymentController::class, 'initiateDeposit']);
        Route::post('/deposit/verify', [PaymentController::class, 'verifyDeposit']);
        Route::post('/withdraw/initiate', [PaymentController::class, 'initiateWithdrawal']);
        Route::get('/transactions', [PaymentController::class, 'getTransactions']);
        Route::get('/banks', [PaymentController::class, 'getBanks']);
        Route::post('/verify-bank-account', [PaymentController::class, 'verifyBankAccount']);
    });
    
    // Audit Logs Routes
    Route::prefix('audit')->group(function () {
        Route::get('/logs', [AuditController::class, 'index']);
        Route::get('/logs/payments', [AuditController::class, 'getPaymentLogs']);
        Route::get('/logs/wallet', [AuditController::class, 'getWalletLogs']);
        Route::get('/logs/failed', [AuditController::class, 'getFailedActions']);
        Route::get('/logs/action/{action}', [AuditController::class, 'getByAction']);
        Route::get('/statistics', [AuditController::class, 'getStatistics']);
    });
    
    // Orders Routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/my/selling', [OrderController::class, 'mySelling']);
        Route::get('/my/buying', [OrderController::class, 'myBuying']);
        Route::get('/{id}', [OrderController::class, 'show']);
        
        Route::post('/', [OrderController::class, 'store']);
        Route::put('/{id}', [OrderController::class, 'update']);
        Route::delete('/{id}', [OrderController::class, 'destroy']);
        
        Route::post('/{id}/purchase', [OrderController::class, 'purchase']);
        Route::post('/{id}/complete', [OrderController::class, 'complete']);
        
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/dispute', [OrderController::class, 'dispute']);
    });
    
    // Image Upload
    Route::post('/orders/{orderId}/images', [ImageUploadController::class, 'uploadProductImages']);
    Route::delete('/images/{imageId}', [ImageUploadController::class, 'deleteProductImage']);
    
    // Transaction Limits
    Route::get('/transaction-limits/stats', [App\Http\Controllers\Api\TransactionLimitController::class, 'getStats']);
    Route::post('/transaction-limits/check', [App\Http\Controllers\Api\TransactionLimitController::class, 'checkLimit']);
    
    // Bank Accounts
    Route::get('/bank-accounts', [App\Http\Controllers\Api\BankAccountController::class, 'index']);
    Route::post('/bank-accounts', [App\Http\Controllers\Api\BankAccountController::class, 'store']);
    Route::put('/bank-accounts/{id}/primary', [App\Http\Controllers\Api\BankAccountController::class, 'setPrimary']);
    Route::delete('/bank-accounts/{id}', [App\Http\Controllers\Api\BankAccountController::class, 'destroy']);
    
    // Withdrawals
    Route::get('/withdrawals', [App\Http\Controllers\Api\WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [App\Http\Controllers\Api\WithdrawalController::class, 'store']);
    Route::post('/withdrawals/{id}/cancel', [App\Http\Controllers\Api\WithdrawalController::class, 'cancel']);
    
    // Payment Links
    Route::get('/payment-links', [App\Http\Controllers\Api\PaymentLinkController::class, 'index']);
    Route::post('/payment-links', [App\Http\Controllers\Api\PaymentLinkController::class, 'store']);
    Route::put('/payment-links/{id}', [App\Http\Controllers\Api\PaymentLinkController::class, 'update']);
    Route::delete('/payment-links/{id}', [App\Http\Controllers\Api\PaymentLinkController::class, 'destroy']);
    Route::get('/payment-links/{id}/stats', [App\Http\Controllers\Api\PaymentLinkController::class, 'stats']);
    
    // KYC Verification
    Route::post('/kyc/verify-nin', [App\Http\Controllers\Api\KYCController::class, 'verifyNIN']);
    Route::post('/kyc/verify-bvn', [App\Http\Controllers\Api\KYCController::class, 'verifyBVN']);
    Route::get('/kyc/nin-status', [App\Http\Controllers\Api\KYCController::class, 'getNINStatus']);
    Route::get('/kyc/bvn-status', [App\Http\Controllers\Api\KYCController::class, 'getBVNStatus']);
    Route::get('/kyc/status', [App\Http\Controllers\Api\KYCController::class, 'getKYCStatus']);
});

// ============================================
// PUBLIC PAYMENT LINK ROUTES
// ============================================

Route::get('/pay/{slug}', [App\Http\Controllers\Api\PaymentLinkController::class, 'show']);
Route::post('/pay/{slug}', [App\Http\Controllers\Api\PaymentLinkController::class, 'pay'])->middleware('auth:sanctum');

// ============================================
// WEBHOOK ROUTES
// ============================================

Route::prefix('webhooks')->group(function () {
    Route::post('/paystack', [WebhookController::class, 'paystackWebhook']);
    Route::post('/stripe', [WebhookController::class, 'stripeWebhook']);
});

// ============================================
// ADMIN ROUTES
// ============================================

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::prefix('disputes')->group(function () {
        Route::get('/', [DisputeController::class, 'index']);
        Route::get('/pending', function (Request $request) {
            $request->merge(['pending' => true]);
            return app(DisputeController::class)->index($request);
        });
        Route::get('/statistics', [DisputeController::class, 'statistics']);
        Route::get('/{id}', [DisputeController::class, 'show']);
        Route::post('/{id}/resolve', [DisputeController::class, 'resolve']);
        Route::post('/{id}/note', [DisputeController::class, 'addNote']);
    });
});

// ============================================
// OTP ENDPOINTS (Public)
// ============================================

Route::post("/otp/send", [OtpController::class, "sendOtp"]);
Route::post("/otp/verify", [OtpController::class, "verifyOtp"]);

// ============================================
// TEST ROUTES (Remove in production)
// ============================================

Route::get('/test-email', function () {
    try {
        \Illuminate\Support\Facades\Mail::raw('Test email from T-Trade!', function ($message) {
            $message->to('support@basepan.com')
                    ->subject('Test Email - T-Trade System');
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Email sent! Check support@basepan.com inbox.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get("/test-termii", function() {
    return response()->json([
        "api_key" => config("termii.api_key"),
        "sender_id" => config("termii.sender_id"),
        "channel" => config("termii.channel"),
    ]);
});
// Waybills
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/orders/{orderId}/waybill', [App\Http\Controllers\Api\WaybillController::class, 'generate']);
    Route::get('/orders/{orderId}/waybill', [App\Http\Controllers\Api\WaybillController::class, 'show']);
    Route::get('/orders/{orderId}/waybill/pdf', [App\Http\Controllers\Api\WaybillController::class, 'viewPDF']);
    Route::get('/orders/{orderId}/waybill/download', [App\Http\Controllers\Api\WaybillController::class, 'downloadPDF']);
});

// Test waybill PDF (temporary - for testing)
Route::get('/test-waybill-pdf/{orderId}', function($orderId) {
    $waybill = \App\Models\Waybill::where('order_id', $orderId)->firstOrFail();
    return app(\App\Services\WaybillService::class)->streamPDF($waybill);
});

// Evidence Upload
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/disputes/{disputeId}/evidence', [App\Http\Controllers\Api\EvidenceController::class, 'upload']);
    Route::get('/disputes/{disputeId}/evidence', [App\Http\Controllers\Api\EvidenceController::class, 'index']);
    Route::delete('/disputes/{disputeId}/evidence/{evidenceId}', [App\Http\Controllers\Api\EvidenceController::class, 'destroy']);
});

// Business Verification (Tier 2/3)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/business/verify/tier2', [App\Http\Controllers\Api\BusinessVerificationController::class, 'submitTier2']);
    Route::get('/business/verification/status', [App\Http\Controllers\Api\BusinessVerificationController::class, 'getStatus']);
});

// Business Directors Management
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/business/directors', [App\Http\Controllers\Api\BusinessDirectorController::class, 'index']);
    Route::post('/business/directors', [App\Http\Controllers\Api\BusinessDirectorController::class, 'store']);
    Route::put('/business/directors/{id}', [App\Http\Controllers\Api\BusinessDirectorController::class, 'update']);
    Route::delete('/business/directors/{id}', [App\Http\Controllers\Api\BusinessDirectorController::class, 'destroy']);
    Route::post('/business/directors/{id}/document', [App\Http\Controllers\Api\BusinessDirectorController::class, 'uploadDocument']);
});

// Admin Business Verification Management
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/business/verifications', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'index']);
    Route::get('/business/verifications/pending', function (Request $request) {
        $request->merge(['pending' => true]);
        return app(App\Http\Controllers\Api\Admin\BusinessVerificationController::class)->index($request);
    });
    Route::get('/business/verifications/statistics', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'statistics']);
    Route::get('/business/verifications/{id}', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'show']);
    Route::post('/business/verifications/{id}/approve', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'approve']);
    Route::post('/business/verifications/{id}/reject', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'reject']);
    Route::post('/business/verifications/{id}/request-info', [App\Http\Controllers\Api\Admin\BusinessVerificationController::class, 'requestInfo']);
});

// Tier 3 Enterprise KYC
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/business/verify/tier3', [App\Http\Controllers\Api\Tier3VerificationController::class, 'submitTier3']);
    Route::post('/business/tier3/ubo', [App\Http\Controllers\Api\Tier3VerificationController::class, 'addUbo']);
    Route::get('/business/tier3/status', [App\Http\Controllers\Api\Tier3VerificationController::class, 'getStatus']);
});

// Admin Tier 3 Management
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/tier3/verifications', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'index']);
    Route::post('/tier3/verifications/{id}/sanctions-screening', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'runSanctionsScreening']);
    Route::post('/tier3/sanctions/{resultId}/clear', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'clearSanctionsMatch']);
    Route::post('/tier3/verifications/{id}/edd/start', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'startEdd']);
    Route::post('/tier3/verifications/{id}/edd/complete', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'completeEdd']);
    Route::post('/tier3/verifications/{id}/approve', [App\Http\Controllers\Api\Admin\Tier3VerificationController::class, 'approve']);
});

// Storefronts
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/storefront', [App\Http\Controllers\Api\StorefrontController::class, 'create']);
    Route::get('/storefront/my', [App\Http\Controllers\Api\StorefrontController::class, 'getMy']);
    Route::put('/storefront', [App\Http\Controllers\Api\StorefrontController::class, 'update']);
    Route::get('/storefront/stats', [App\Http\Controllers\Api\StorefrontController::class, 'getStats']);
});

// Public storefront access
Route::get('/store/{slug}', [App\Http\Controllers\Api\StorefrontController::class, 'show']);

// Products
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products', [App\Http\Controllers\Api\ProductController::class, 'create']);
    Route::get('/products/my', [App\Http\Controllers\Api\ProductController::class, 'getMyProducts']);
    Route::get('/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'show']);
    Route::put('/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'update']);
    Route::delete('/products/{id}', [App\Http\Controllers\Api\ProductController::class, 'delete']);
});

// Public product endpoints
Route::get('/store/{slug}/products', [App\Http\Controllers\Api\ProductController::class, 'getStorefrontProducts']);
