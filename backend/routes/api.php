<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Admin\DisputeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================

// Health Check
Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    try {
        // Check Redis connection
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
    
    // Email & Phone Verification
    Route::post('/email/verification-notification', [AuthController::class, 'sendEmailVerification'])
        ->middleware('throttle:6,1'); // Max 6 requests per minute
    Route::post('/phone/send-otp', [AuthController::class, 'sendPhoneOtp'])
        ->middleware('throttle:6,1');
    Route::post('/phone/verify-otp', [AuthController::class, 'verifyPhoneOtp'])
        ->middleware('throttle:3,1'); // Max 3 attempts per minute
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
    
    // Audit Logs Routes (Compliance & Tracking)
    Route::prefix('audit')->group(function () {
        Route::get('/logs', [AuditController::class, 'index']);
        Route::get('/logs/payments', [AuditController::class, 'getPaymentLogs']);
        Route::get('/logs/wallet', [AuditController::class, 'getWalletLogs']);
        Route::get('/logs/failed', [AuditController::class, 'getFailedActions']);
        Route::get('/logs/action/{action}', [AuditController::class, 'getByAction']);
        Route::get('/statistics', [AuditController::class, 'getStatistics']);
    });
    
    // Orders Routes (Week 2: Escrow System) - ACTIVE
    Route::prefix('orders')->group(function () {
        // Marketplace/Browse
        Route::get('/', [OrderController::class, 'index']); // Browse all active orders
        Route::get('/my/selling', [OrderController::class, 'mySelling']); // My listings
        Route::get('/my/buying', [OrderController::class, 'myBuying']); // My purchases
        Route::get('/{id}', [OrderController::class, 'show']); // View order details
        
        // Seller Actions
        Route::post('/', [OrderController::class, 'store']); // Create order
        Route::put('/{id}', [OrderController::class, 'update']); // Update order
        Route::delete('/{id}', [OrderController::class, 'destroy']); // Delete order
        
        // Buyer Actions
        Route::post('/{id}/purchase', [OrderController::class, 'purchase']); // Purchase order (lock funds)
        Route::post('/{id}/complete', [OrderController::class, 'complete']); // Complete order (release funds)
        
        // Shared Actions
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']); // Cancel order
        Route::post('/{id}/dispute', [OrderController::class, 'dispute']); // Raise dispute
    });
    
    // KYC Routes (TODO: Implement in Phase 3)
    Route::prefix('kyc')->group(function () {
        // Route::post('/tier-1/submit', [KycController::class, 'submitTier1']);
        // Route::post('/tier-2/submit', [KycController::class, 'submitTier2']);
        // Route::get('/status', [KycController::class, 'status']);
    });
    
    // Disputes Routes (TODO: Implement in Phase 3)
    Route::prefix('disputes')->group(function () {
        // Route::get('/', [DisputeController::class, 'index']); // My disputes
        // Route::get('/{id}', [DisputeController::class, 'show']); // View dispute
        // Route::post('/{id}/message', [DisputeController::class, 'addMessage']); // Add message to dispute
    });

    
});

// ============================================
// WEBHOOK ROUTES (External Service Callbacks)
// ============================================

Route::prefix('webhooks')->group(function () {
    // Payment Webhooks
    Route::post('/paystack', [WebhookController::class, 'paystackWebhook']);
    Route::post('/stripe', [WebhookController::class, 'stripeWebhook']);
    
    // Logistics Webhooks (TODO)
    // Route::post('/logistics/{partner}', [WebhookController::class, 'logistics'])
    //     ->middleware('verify.logistics.signature');
    
    // KYC Verification Webhooks (TODO)
    // Route::post('/smile-id', [WebhookController::class, 'smileId'])
    //     ->middleware('verify.smileid.signature');
});

// ============================================
// ADMIN ROUTES (Admin Authentication Required)
// ============================================

Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {
    
    // Dispute Management (Week 3)
    Route::prefix('disputes')->group(function () {
        Route::get('/', [DisputeController::class, 'index']); // List all disputes
        Route::get('/pending', function (Request $request) {
            $request->merge(['pending' => true]);
            return app(DisputeController::class)->index($request);
        }); // Pending disputes only
        Route::get('/statistics', [DisputeController::class, 'statistics']); // Dispute statistics
        Route::get('/{id}', [DisputeController::class, 'show']); // View dispute details
        Route::post('/{id}/resolve', [DisputeController::class, 'resolve']); // Resolve dispute
        Route::post('/{id}/note', [DisputeController::class, 'addNote']); // Add admin note
    });
    
    // KYC Approvals (TODO)
    // Route::get('/kyc/pending', [AdminKycController::class, 'pending']);
    // Route::post('/kyc/{id}/approve', [AdminKycController::class, 'approve']);
    // Route::post('/kyc/{id}/reject', [AdminKycController::class, 'reject']);
    
    // Transaction Monitoring (TODO)
    // Route::get('/transactions/flagged', [AdminMonitoringController::class, 'flagged']);
    // Route::get('/transactions/{id}', [AdminMonitoringController::class, 'show']);
    
    // System Configuration (TODO)
    // Route::get('/config', [AdminConfigController::class, 'index']);
    // Route::put('/config/{key}', [AdminConfigController::class, 'update']);
    
    // Admin Audit Logs (TODO)
    // Route::get('/audit/logs', [AdminAuditController::class, 'index']);
    // Route::get('/audit/user/{userId}', [AdminAuditController::class, 'getUserLogs']);
});

// ============================================
// RATE LIMITING
// ============================================

// Apply rate limiting to all API routes
Route::middleware('throttle:60,1')->group(function () {
    // 60 requests per minute for authenticated users
});
// Email test route
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

// OTP endpoints (public)
Route::post("/otp/send", [App\Http\Controllers\Api\OtpController::class, "sendOtp"]);
Route::post("/otp/verify", [App\Http\Controllers\Api\OtpController::class, "verifyOtp"]);

// Test Termii config
Route::get("/test-termii", function() {
    return response()->json([
        "api_key" => config("termii.api_key"),
        "sender_id" => config("termii.sender_id"),
        "channel" => config("termii.channel"),
    ]);
});
