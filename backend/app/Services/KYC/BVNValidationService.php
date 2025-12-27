<?php

namespace App\Services\KYC;

use App\Models\User;
use App\Models\KycVerification;
use App\Services\AuditService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class BVNValidationService
{
    protected string $provider;
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->provider = config('kyc.bvn_provider', 'dojah');
        $this->apiKey = config('kyc.providers.dojah.api_key');
        $this->baseUrl = config('kyc.providers.dojah.base_url', 'https://api.dojah.io');
    }

    /**
     * Verify BVN
     */
    public function verify(User $user, string $bvn): KycVerification
    {
        DB::beginTransaction();

        try {
            // Check if user already has verified BVN
            $existing = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'BVN')
                ->where('status', 'verified')
                ->first();

            if ($existing) {
                throw new \Exception('User already has a verified BVN');
            }

            // Check if too many attempts
            $recentAttempts = KycVerification::where('user_id', $user->id)
                ->where('verification_type', 'BVN')
                ->where('last_attempt_at', '>=', now()->subHour())
                ->count();

            if ($recentAttempts >= 3) {
                throw new \Exception('Too many verification attempts. Please try again later.');
            }

            // Create verification record
            $verification = KycVerification::create([
                'user_id' => $user->id,
                'verification_type' => 'BVN',
                'identifier' => Crypt::encryptString($bvn),
                'status' => 'pending',
                'provider' => $this->provider,
            ]);

            // Call provider API
            $response = $this->callProviderAPI($bvn);

            if ($response['success']) {
                // Verification successful
                $verification->markAsVerified($response['data']);

                // Update user
                $user->refresh();
                $user->bvn_verified = true;
                $user->save();

                AuditService::log(
                    'kyc.bvn_verified',
                    'BVN verification successful',
                    $verification,
                    [],
                    ['verified_data' => $response['data']],
                    ['user_id' => $user->id]
                );
            } else {
                // Verification failed
                $verification->update([
                    'provider_response' => $response,
                ]);

                // After 3 failed attempts, send for manual review
                if ($verification->verification_attempts >= 2) {
                    $verification->sendForManualReview();
                    
                    AuditService::log(
                        'kyc.bvn_manual_review',
                        'BVN sent for manual review after multiple failures',
                        $verification,
                        [],
                        [],
                        ['user_id' => $user->id]
                    );
                } else {
                    $verification->markAsFailed();
                }

                throw new \Exception($response['message'] ?? 'BVN verification failed');
            }

            DB::commit();

            return $verification->fresh();
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'kyc.bvn_verification_failed',
                'BVN verification failed',
                $e->getMessage(),
                ['user_id' => $user->id, 'bvn' => substr($bvn, 0, 3) . '***']
            );

            throw $e;
        }
    }

    /**
     * Call provider API (Dojah example)
     */
    protected function callProviderAPI(string $bvn): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
                'AppId' => config('kyc.providers.dojah.app_id'),
            ])->post($this->baseUrl . '/api/v1/kyc/bvn', [
                'bvn' => $bvn,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['entity']['first_name']) {
                    return [
                        'success' => true,
                        'data' => [
                            'first_name' => $data['entity']['first_name'],
                            'last_name' => $data['entity']['last_name'],
                            'middle_name' => $data['entity']['middle_name'] ?? null,
                            'date_of_birth' => $data['entity']['date_of_birth'] ?? null,
                            'phone' => $data['entity']['phone_number'] ?? null,
                            'gender' => $data['entity']['gender'] ?? null,
                        ],
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid BVN or verification failed',
                'provider_response' => $response->json(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API call failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get verification status
     */
    public function getVerificationStatus(User $user): ?KycVerification
    {
        return KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'BVN')
            ->latest()
            ->first();
    }
}
