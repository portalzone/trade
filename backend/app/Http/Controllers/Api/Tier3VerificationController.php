<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Models\BeneficialOwner;
use App\Models\EddReview;
use App\Services\BusinessVerificationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class Tier3VerificationController extends Controller
{
    protected BusinessVerificationService $businessService;

    public function __construct(BusinessVerificationService $businessService)
    {
        $this->businessService = $businessService;
    }

    /**
     * Submit Tier 3 verification
     */
    public function submitTier3(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_name' => 'required|string|max:255',
            'registration_number' => 'required|string|max:100|unique:business_verifications',
            'cac_number' => 'required|string|max:100',
            'registration_date' => 'required|date',
            'business_address' => 'required|string',
            'business_phone' => 'required|string|max:20',
            'business_email' => 'required|email',
            'business_type' => 'required|in:sole_proprietorship,limited_liability,partnership,enterprise',
            'cac_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tin_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'source_of_funds' => 'required|string',
            'source_of_wealth' => 'required|string',
            'business_model_description' => 'required|string|min:100',
            'expected_transaction_volume' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();

            // Check if user already has pending/verified Tier 3
            $existing = BusinessVerification::where('user_id', $user->id)
                ->where('tier', 'tier3')
                ->whereIn('verification_status', ['pending', 'under_review', 'verified'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending or verified Tier 3 verification',
                ], 400);
            }

            DB::beginTransaction();

            // Upload documents
            $cacPath = null;
            $cacUrl = null;
            if ($request->hasFile('cac_certificate')) {
                $file = $request->file('cac_certificate');
                $filename = 'tier3-cac-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = "business-docs/user-{$user->id}/tier3/{$filename}";
                
                $storedPath = \Storage::disk('public')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );
                
                $cacPath = $storedPath;
                $cacUrl = \Storage::disk('public')->url($storedPath);
            }

            $tinPath = null;
            $tinUrl = null;
            if ($request->hasFile('tin_certificate')) {
                $file = $request->file('tin_certificate');
                $filename = 'tier3-tin-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = "business-docs/user-{$user->id}/tier3/{$filename}";
                
                $storedPath = \Storage::disk('public')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );
                
                $tinPath = $storedPath;
                $tinUrl = \Storage::disk('public')->url($storedPath);
            }

            // Create Tier 3 verification
            $verification = BusinessVerification::create([
                'user_id' => $user->id,
                'tier' => 'tier3',
                'business_name' => $request->business_name,
                'registration_number' => $request->registration_number,
                'cac_number' => $request->cac_number,
                'registration_date' => $request->registration_date,
                'business_address' => $request->business_address,
                'business_phone' => $request->business_phone,
                'business_email' => $request->business_email,
                'business_type' => $request->business_type,
                'cac_certificate_path' => $cacPath,
                'cac_certificate_url' => $cacUrl,
                'tin_certificate_path' => $tinPath,
                'tin_certificate_url' => $tinUrl,
                'verification_status' => 'pending',
                'total_ownership_declared' => 0,
            ]);

            // Create EDD review
            EddReview::create([
                'business_verification_id' => $verification->id,
                'status' => 'not_started',
                'source_of_funds' => $request->source_of_funds,
                'source_of_wealth' => $request->source_of_wealth,
                'business_model_description' => $request->business_model_description,
                'expected_transaction_volume' => $request->expected_transaction_volume,
                'geographic_exposure' => $request->geographic_exposure ?? null,
                'required_documents' => [
                    'financial_statements',
                    'bank_statements',
                    'tax_returns',
                    'business_plan',
                ],
            ]);

            $verification->markAsSubmitted();

            AuditService::log(
                'tier3.verification.submitted',
                "Tier 3 verification submitted: {$verification->business_name}",
                $verification,
                [],
                ['business_name' => $verification->business_name],
                ['user_id' => $user->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tier 3 verification submitted successfully. Please add beneficial owners next.',
                'data' => $verification->load('eddReview'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'tier3.verification.submit_failed',
                'Failed to submit Tier 3 verification',
                $e->getMessage(),
                ['user_id' => $request->user()->id]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit Tier 3 verification',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add beneficial owner (UBO)
     */
    public function addUbo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'ownership_percentage' => 'required|integer|min:26|max:100', // Must be >25%
            'nin' => 'nullable|string|size:11',
            'bvn' => 'nullable|string|size:11',
            'passport_number' => 'nullable|string',
            'date_of_birth' => 'required|date',
            'nationality' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'residential_address' => 'required|string',
            'ownership_type' => 'required|in:direct,indirect,voting_rights',
            'is_pep' => 'nullable|boolean',
            'pep_details' => 'nullable|string',
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'proof_of_address' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            
            // Get user's Tier 3 verification
            $verification = BusinessVerification::where('user_id', $user->id)
                ->where('tier', 'tier3')
                ->latest()
                ->firstOrFail();

            // Check if ownership would exceed 100%
            $currentTotal = $verification->beneficialOwners()->sum('ownership_percentage');
            $newTotal = $currentTotal + $request->ownership_percentage;

            if ($newTotal > 100) {
                return response()->json([
                    'success' => false,
                    'message' => "Adding this UBO would exceed 100% ownership. Current total: {$currentTotal}%, Attempted: {$request->ownership_percentage}%",
                ], 400);
            }

            DB::beginTransaction();

            // Upload ID document
            $idPath = null;
            $idUrl = null;
            if ($request->hasFile('id_document')) {
                $file = $request->file('id_document');
                $filename = 'ubo-id-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = "business-docs/user-{$user->id}/tier3/ubos/{$filename}";
                
                $storedPath = \Storage::disk('public')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );
                
                $idPath = $storedPath;
                $idUrl = \Storage::disk('public')->url($storedPath);
            }

            // Upload proof of address
            $poaPath = null;
            $poaUrl = null;
            if ($request->hasFile('proof_of_address')) {
                $file = $request->file('proof_of_address');
                $filename = 'ubo-poa-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = "business-docs/user-{$user->id}/tier3/ubos/{$filename}";
                
                $storedPath = \Storage::disk('public')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );
                
                $poaPath = $storedPath;
                $poaUrl = \Storage::disk('public')->url($storedPath);
            }

            // Create UBO
            $ubo = BeneficialOwner::create([
                'business_verification_id' => $verification->id,
                'full_name' => $request->full_name,
                'nin' => $request->nin,
                'bvn' => $request->bvn,
                'passport_number' => $request->passport_number,
                'date_of_birth' => $request->date_of_birth,
                'nationality' => $request->nationality,
                'phone' => $request->phone,
                'email' => $request->email,
                'residential_address' => $request->residential_address,
                'ownership_percentage' => $request->ownership_percentage,
                'ownership_type' => $request->ownership_type,
                'is_pep' => $request->is_pep ?? false,
                'pep_details' => $request->pep_details,
                'id_document_path' => $idPath,
                'id_document_url' => $idUrl,
                'proof_of_address_path' => $poaPath,
                'proof_of_address_url' => $poaUrl,
            ]);

            // Update total ownership
            $verification->update([
                'total_ownership_declared' => $newTotal,
                'all_ubos_identified' => $newTotal >= 100,
            ]);

            AuditService::log(
                'tier3.ubo.added',
                "UBO added: {$ubo->full_name} ({$ubo->ownership_percentage}%)",
                $ubo,
                [],
                ['ubo_name' => $ubo->full_name, 'ownership' => $ubo->ownership_percentage],
                ['user_id' => $user->id, 'business_id' => $verification->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Beneficial owner added successfully',
                'data' => [
                    'ubo' => $ubo,
                    'total_ownership' => $newTotal,
                    'all_identified' => $newTotal >= 100,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add beneficial owner',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get user's Tier 3 verification status
     */
    public function getStatus(Request $request)
    {
        try {
            $user = $request->user();
            $verification = BusinessVerification::where('user_id', $user->id)
                ->where('tier', 'tier3')
                ->with(['beneficialOwners', 'eddReview', 'sanctionsScreenings'])
                ->latest()
                ->first();

            if (!$verification) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'not_submitted',
                        'tier' => $user->kyc_tier,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $verification,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Tier 3 status',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
