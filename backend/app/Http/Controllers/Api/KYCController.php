<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KYC\NINValidationService;
use App\Services\KYC\MockNINValidationService;
use App\Services\KYC\BVNValidationService;
use App\Services\KYC\MockBVNValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KYCController extends Controller
{
    protected $ninService;
    protected $bvnService;

    public function __construct()
    {
        // Use mock services if in development or no API key configured
        $useMock = config('app.env') === 'local' || !config('kyc.providers.dojah.api_key');
        
        $this->ninService = $useMock ? new MockNINValidationService() : new NINValidationService();
        $this->bvnService = $useMock ? new MockBVNValidationService() : new BVNValidationService();
    }

    /**
     * Submit NIN for verification
     */
    public function verifyNIN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nin' => 'required|string|size:11|regex:/^[0-9]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = $this->ninService->verify($request->user(), $request->nin);

            return response()->json([
                'success' => true,
                'message' => 'NIN verification successful',
                'data' => [
                    'verification_id' => $verification->id,
                    'status' => $verification->status,
                    'verified_at' => $verification->verified_at,
                    'verified_data' => $verification->verified_data,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'NIN verification failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Submit BVN for verification
     */
    public function verifyBVN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bvn' => 'required|string|size:11|regex:/^[0-9]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $verification = $this->bvnService->verify($request->user(), $request->bvn);

            return response()->json([
                'success' => true,
                'message' => 'BVN verification successful',
                'data' => [
                    'verification_id' => $verification->id,
                    'status' => $verification->status,
                    'verified_at' => $verification->verified_at,
                    'verified_data' => $verification->verified_data,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'BVN verification failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get NIN verification status
     */
    public function getNINStatus(Request $request)
    {
        $verification = $this->ninService->getVerificationStatus($request->user());

        if (!$verification) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_submitted',
                    'nin_verified' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'verification_id' => $verification->id,
                'status' => $verification->status,
                'verification_type' => $verification->verification_type,
                'verified_at' => $verification->verified_at,
                'verification_attempts' => $verification->verification_attempts,
                'last_attempt_at' => $verification->last_attempt_at,
                'verified_data' => $verification->isVerified() ? $verification->verified_data : null,
            ],
        ]);
    }

    /**
     * Get BVN verification status
     */
    public function getBVNStatus(Request $request)
    {
        $verification = $this->bvnService->getVerificationStatus($request->user());

        if (!$verification) {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'not_submitted',
                    'bvn_verified' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'verification_id' => $verification->id,
                'status' => $verification->status,
                'verification_type' => $verification->verification_type,
                'verified_at' => $verification->verified_at,
                'verification_attempts' => $verification->verification_attempts,
                'last_attempt_at' => $verification->last_attempt_at,
                'verified_data' => $verification->isVerified() ? $verification->verified_data : null,
            ],
        ]);
    }

    /**
     * Get user's KYC overview
     */
    public function getKYCStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_tier' => $user->kyc_tier,
                'nin_verified' => $user->nin_verified,
                'bvn_verified' => $user->bvn_verified,
                'email_verified' => !is_null($user->email_verified_at),
                'phone_verified' => $user->phone_verified,
            ],
        ]);
    }
}
