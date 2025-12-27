<?php

namespace App\Services;

use App\Models\OtpVerification;
use Carbon\Carbon;

class OtpService
{
    public function sendOtp(string $phoneNumber, string $purpose = 'registration'): array
    {
        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Expire in 10 minutes
        $expiresAt = Carbon::now()->addMinutes(10);
        
        // Store in database
        OtpVerification::create([
            'phone_number' => $this->formatPhoneNumber($phoneNumber),
            'otp_code' => $otpCode,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);
        
        // TODO: Enable SMS when Termii sender ID is approved
        // For now, return OTP in response for testing
        
        return [
            'success' => true,
            'message' => 'OTP sent successfully (TEST MODE - check otp_code)',
            'expires_at' => $expiresAt->toIso8601String(),
            'otp_code' => $otpCode, // REMOVE IN PRODUCTION!
        ];
    }
    
    public function verifyOtp(string $phoneNumber, string $otpCode, string $purpose = 'registration'): array
    {
        $verification = OtpVerification::where('phone_number', $this->formatPhoneNumber($phoneNumber))
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();
        
        if (!$verification) {
            return [
                'success' => false,
                'message' => 'OTP expired or not found',
            ];
        }
        
        // Increment attempts
        $verification->increment('attempts');
        
        // Block after 3 failed attempts
        if ($verification->attempts > 3) {
            return [
                'success' => false,
                'message' => 'Too many failed attempts. Request a new OTP.',
            ];
        }
        
        if ($verification->otp_code !== $otpCode) {
            return [
                'success' => false,
                'message' => 'Invalid OTP code',
            ];
        }
        
        // Mark as verified
        $verification->update([
            'is_verified' => true,
            'verified_at' => Carbon::now(),
        ]);
        
        return [
            'success' => true,
            'message' => 'Phone number verified successfully',
        ];
    }
    
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove spaces, dashes, etc.
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // Add +234 if starts with 0
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '+234' . substr($phoneNumber, 1);
        }
        
        // Add +234 if no country code
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+234' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
}
