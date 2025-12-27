<?php

namespace App\Services\KYC;

use App\Models\User;
use App\Models\KycVerification;
use App\Services\AuditService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MockNINValidationService
{
    public function verify(User $user, string $nin): KycVerification
    {
        DB::beginTransaction();

        try {
            $verification = KycVerification::create([
                'user_id' => $user->id,
                'verification_type' => 'NIN',
                'identifier' => Crypt::encryptString($nin),
                'status' => 'pending',
                'provider' => 'mock',
            ]);

            if (strlen($nin) === 11 && is_numeric($nin)) {
                $mockData = [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'middle_name' => 'Test',
                    'date_of_birth' => '1990-01-01',
                    'phone' => '08012345678',
                    'gender' => 'Male',
                ];

                $verification->markAsVerified($mockData);
                
                // FIXED: Refresh user and update
                $user->refresh();
                $user->nin_verified = true;
                $user->save();

                AuditService::log(
                    'kyc.nin_verified',
                    'NIN verification successful (MOCK MODE)',
                    $verification,
                    [],
                    ['verified_data' => $mockData],
                    ['user_id' => $user->id]
                );
            } else {
                $verification->markAsFailed();
                throw new \Exception('Invalid NIN format (must be 11 digits)');
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
            ->where('verification_type', 'NIN')
            ->latest()
            ->first();
    }
}
