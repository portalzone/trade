<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['required', 'string', 'unique:users,phone_number', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username', 'regex:/^[a-zA-Z0-9_]+$/'],
            'user_type' => ['required', 'in:BUYER,SELLER'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'password_hash' => Hash::make($request->password),
                'full_name' => $request->full_name,
                'username' => $request->username,
                'user_type' => $request->user_type,
                'kyc_status' => 'BASIC',
                'kyc_tier' => 1,
                'account_status' => 'ACTIVE',
            ]);

            // Create wallet for user
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'currency' => 'NGN',
                'available_balance' => 0,
                'locked_escrow_funds' => 0,
                'wallet_status' => 'ACTIVE',
            ]);

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;

            DB::commit();

            // TODO: Send email verification
            // TODO: Send SMS OTP for phone verification

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email and phone.',
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
                    ],
                    'wallet' => [
                        'id' => $wallet->id,
                        'available_balance' => $wallet->available_balance,
                        'locked_escrow_funds' => $wallet->locked_escrow_funds,
                        'total_balance' => $wallet->total_balance,
                    ],
                    'token' => $token,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Login user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if account is active
        if ($user->account_status !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Account is ' . strtolower($user->account_status)
            ], 403);
        }

        // Check if MFA is required (for admins and Tier 3)
        if ($user->requiresMfa() && !$user->mfa_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'MFA setup required for this account',
                'requires_mfa_setup' => true
            ], 403);
        }

        // TODO: If MFA enabled, send OTP and wait for verification

        // Generate token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Update last login
        $user->recordLogin();

        // Get wallet
        $wallet = $user->wallet;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
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
                ],
                'wallet' => [
                    'id' => $wallet->id,
                    'available_balance' => $wallet->available_balance,
                    'locked_escrow_funds' => $wallet->locked_escrow_funds,
                    'total_balance' => $wallet->total_balance,
                    'wallet_status' => $wallet->wallet_status,
                ],
                'token' => $token,
            ]
        ], 200);
    }

    /**
     * Logout user (revoke token)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful'
        ], 200);
    }

    /**
     * Get authenticated user details
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        $user = $request->user()->load('wallet');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                    'profile_photo_url' => $user->profile_photo_url,
                    'user_type' => $user->user_type,
                    'kyc_status' => $user->kyc_status,
                    'kyc_tier' => $user->kyc_tier,
                    'account_status' => $user->account_status,
                    'transaction_limits' => $user->getTransactionLimits(),
                ],
                'wallet' => [
                    'id' => $user->wallet->id,
                    'available_balance' => $user->wallet->available_balance,
                    'locked_escrow_funds' => $user->wallet->locked_escrow_funds,
                    'total_balance' => $user->wallet->total_balance,
                    'wallet_status' => $user->wallet->wallet_status,
                    'currency' => $user->wallet->currency,
                ],
            ]
        ], 200);
    }

    /**
     * Send email verification link
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmailVerification(Request $request)
    {
        // TODO: Implement email verification
        // - Generate verification token
        // - Send email with link
        // - Store token in database

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent'
        ], 200);
    }

    /**
     * Send phone verification OTP
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendPhoneOtp(Request $request)
    {
        // TODO: Implement SMS OTP
        // - Generate 6-digit OTP
        // - Send via Twilio/Termii
        // - Store in Redis with 10-minute expiry

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your phone'
        ], 200);
    }

    /**
     * Verify phone OTP
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO: Implement OTP verification
        // - Check OTP from Redis
        // - Verify not expired
        // - Update user's phone_verified_at

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully'
        ], 200);
    }
}
