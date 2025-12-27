<?php

namespace App\Services\KYC;

use App\Models\User;
use App\Models\KycVerification;
use App\Services\AuditService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MockBVNValidationService
{
    public function verify(User $user, string $bvn): KycVerification
    {
        DB::beginTransaction();

        try {
            $verification = KycVerification::create([
                'user_id' => $user->id,
                'verification_type' => 'BVN',
                'identifier' => Crypt::encryptString($bvn),
                'status' => 'pending',
                'provider' => 'mock',
            ]);

            // Mock: any 11-digit BVN is valid
            if (strlen($bvn) === 11 && is_numeric($bvn)) {
                $mockData = [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'middle_name' => 'Test',
                    'date_of_birth' => '1992-05-15',
                    'phone' => '08098765432',
                    'gender' => 'Female',
                ];

                $verification->markAsVerified($mockData);
                
                $user->refresh();
                $user->bvn_verified = true;
                $user->save();

                AuditService::log(
                    'kyc.bvn_verified',
                    'BVN verification successful (MOCK MODE)',
                    $verification,
                    [],
                    ['verified_data' => $mockData],
                    ['user_id' => $user->id]
                );
            } else {
                $verification->markAsFailed();
                throw new \Exception('Invalid BVN format (must be 11 digits)');
            }

            DB::commit();
            return $verification->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getVerificationStatus(User $user): ?KycVerification
    {
        return KycVerification::where('user_id', $user->id)
            ->where('verification_type', 'BVN')
            ->latest()
            ->first();
    }
}
