<?php

namespace App\Services;

use App\Models\User;
use App\Models\BusinessVerification;
use App\Models\BusinessDirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

class BusinessVerificationService
{
    protected S3StorageService $s3Service;

    public function __construct(S3StorageService $s3Service)
    {
        $this->s3Service = $s3Service;
    }

    /**
     * Submit business verification for Tier 2
     */
    public function submitTier2Verification(User $user, array $data, array $files = []): BusinessVerification
    {
        DB::beginTransaction();

        try {
            // Check if user already has pending/verified business
            $existing = BusinessVerification::where('user_id', $user->id)
                ->whereIn('verification_status', ['pending', 'under_review', 'verified'])
                ->first();

            if ($existing) {
                throw new \Exception('You already have a pending or verified business verification');
            }

            // Upload CAC certificate
            $cacPath = null;
            $cacUrl = null;
            if (isset($files['cac_certificate'])) {
                $result = $this->uploadDocument($files['cac_certificate'], $user->id, 'cac');
                $cacPath = $result['path'];
                $cacUrl = $result['url'];
            }

            // Upload TIN certificate
            $tinPath = null;
            $tinUrl = null;
            if (isset($files['tin_certificate'])) {
                $result = $this->uploadDocument($files['tin_certificate'], $user->id, 'tin');
                $tinPath = $result['path'];
                $tinUrl = $result['url'];
            }

            // Create business verification
            $verification = BusinessVerification::create([
                'user_id' => $user->id,
                'tier' => 'tier2',
                'business_name' => $data['business_name'],
                'registration_number' => $data['registration_number'],
                'cac_number' => $data['cac_number'] ?? null,
                'registration_date' => $data['registration_date'] ?? null,
                'business_address' => $data['business_address'],
                'business_phone' => $data['business_phone'] ?? null,
                'business_email' => $data['business_email'] ?? null,
                'business_type' => $data['business_type'] ?? 'limited_liability',
                'cac_certificate_path' => $cacPath,
                'cac_certificate_url' => $cacUrl,
                'tin_certificate_path' => $tinPath,
                'tin_certificate_url' => $tinUrl,
                'verification_status' => 'pending',
            ]);

            // Add directors if provided
            if (isset($data['directors']) && is_array($data['directors'])) {
                foreach ($data['directors'] as $directorData) {
                    $this->addDirector($verification, $directorData);
                }
            }

            // Mark as submitted
            $verification->markAsSubmitted();

            // Audit log
            AuditService::log(
                'business.verification.submitted',
                "Tier 2 business verification submitted: {$verification->business_name}",
                $verification,
                [],
                ['business_name' => $verification->business_name],
                ['user_id' => $user->id]
            );

            DB::commit();

            return $verification->load('directors');
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'business.verification.submit_failed',
                'Failed to submit business verification',
                $e->getMessage(),
                ['user_id' => $user->id]
            );

            throw $e;
        }
    }

    /**
     * Add director to business verification
     */
    public function addDirector(BusinessVerification $verification, array $data): BusinessDirector
    {
        return BusinessDirector::create([
            'business_verification_id' => $verification->id,
            'full_name' => $data['full_name'],
            'nin' => $data['nin'] ?? null,
            'bvn' => $data['bvn'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'residential_address' => $data['residential_address'] ?? null,
            'ownership_percentage' => $data['ownership_percentage'] ?? null,
            'is_primary_contact' => $data['is_primary_contact'] ?? false,
            'role' => $data['role'] ?? 'director',
        ]);
    }

    /**
     * Upload business document
     */
    protected function uploadDocument(UploadedFile $file, int $userId, string $type): array
    {
        $filename = $type . '-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $path = "business-docs/user-{$userId}/{$filename}";

        // Store file
        $storedPath = \Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return [
            'path' => $storedPath,
            'url' => \Storage::disk('public')->url($storedPath),
        ];
    }

    /**
     * Get user's business verification
     */
    public function getUserVerification(User $user): ?BusinessVerification
    {
        return BusinessVerification::where('user_id', $user->id)
            ->with('directors')
            ->latest()
            ->first();
    }

    /**
     * Admin: Approve verification
     */
    public function approveVerification(BusinessVerification $verification, User $admin): BusinessVerification
    {
        DB::beginTransaction();

        try {
            $verification->markAsVerified($admin);

            AuditService::log(
                'business.verification.approved',
                "Business verification approved: {$verification->business_name}",
                $verification,
                ['status' => 'under_review'],
                ['status' => 'verified'],
                ['admin_id' => $admin->id, 'user_id' => $verification->user_id]
            );

            DB::commit();

            return $verification->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Admin: Reject verification
     */
    public function rejectVerification(BusinessVerification $verification, User $admin, string $reason): BusinessVerification
    {
        DB::beginTransaction();

        try {
            $verification->markAsRejected($admin, $reason);

            AuditService::log(
                'business.verification.rejected',
                "Business verification rejected: {$verification->business_name}",
                $verification,
                ['status' => 'under_review'],
                ['status' => 'rejected', 'reason' => $reason],
                ['admin_id' => $admin->id, 'user_id' => $verification->user_id]
            );

            DB::commit();

            return $verification->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
