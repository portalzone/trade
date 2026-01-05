<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tier3Verification;
use App\Models\BeneficialOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class Tier3VerificationController extends Controller
{
    /**
     * Get verification status for current user
     */
    public function getStatus(Request $request)
    {
        try {
            $verification = Tier3Verification::where('user_id', $request->user()->id)
                ->with('beneficialOwners')
                ->first();

            return response()->json([
                'success' => true,
                'data' => $verification
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verification status'
            ], 500);
        }
    }

    /**
     * Submit Tier 3 verification
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'annual_revenue' => 'required|string',
            'transaction_volume' => 'required|string',
            'source_of_funds' => 'required|string',
            'business_purpose' => 'required|string',
            'ubos' => 'required|string',
            'financial_statements' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'bank_statements' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
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

            $user = $request->user();

            if ($user->kyc_tier < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be Tier 2 to apply for Tier 3'
                ], 403);
            }

            $existing = Tier3Verification::where('user_id', $user->id)
                ->whereIn('verification_status', ['pending', 'under_review'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a pending Tier 3 verification'
                ], 400);
            }

            $financialStatementsPath = null;
            $bankStatementsPath = null;

            if ($request->hasFile('financial_statements')) {
                $file = $request->file('financial_statements');
                $filename = 'tier3-financial-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $financialStatementsPath = $file->storeAs('public/tier3-docs/user-' . $user->id, $filename);
            }

            if ($request->hasFile('bank_statements')) {
                $file = $request->file('bank_statements');
                $filename = 'tier3-bank-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $bankStatementsPath = $file->storeAs('public/tier3-docs/user-' . $user->id, $filename);
            }

            $verification = Tier3Verification::create([
                'user_id' => $user->id,
                'annual_revenue' => $request->annual_revenue,
                'transaction_volume' => $request->transaction_volume,
                'source_of_funds' => $request->source_of_funds,
                'business_purpose' => $request->business_purpose,
                'financial_statements_path' => $financialStatementsPath,
                'financial_statements_url' => $financialStatementsPath ? Storage::url($financialStatementsPath) : null,
                'bank_statements_path' => $bankStatementsPath,
                'bank_statements_url' => $bankStatementsPath ? Storage::url($bankStatementsPath) : null,
                'verification_status' => 'pending',
                'submitted_at' => now(),
            ]);

            $ubos = json_decode($request->ubos, true);
            
            if (is_array($ubos)) {
                foreach ($ubos as $ubo) {
                    BeneficialOwner::create([
                        'tier3_verification_id' => $verification->id,
                        'full_name' => $ubo['full_name'],
                        'date_of_birth' => $ubo['date_of_birth'],
                        'nationality' => $ubo['nationality'],
                        'ownership_percentage' => $ubo['ownership_percentage'],
                        'id_type' => $ubo['id_type'],
                        'id_number' => $ubo['id_number'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tier 3 verification submitted successfully',
                'data' => $verification->load('beneficialOwners')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit verification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: List all Tier 3 verifications
     */
    public function adminIndex(Request $request)
    {
        try {
            $query = Tier3Verification::with(['user', 'beneficialOwners']);

            if ($request->has('pending') && $request->pending) {
                $query->whereIn('verification_status', ['pending', 'under_review']);
            }

            $verifications = $query->orderBy('submitted_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $verifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verifications'
            ], 500);
        }
    }

    /**
     * Admin: Approve Tier 3 verification
     */
    public function adminApprove(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $verification = Tier3Verification::with('user')->findOrFail($id);

            if ($verification->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'This verification is already approved'
                ], 400);
            }

            $verification->update([
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
            ]);

            $verification->user->update([
                'kyc_tier' => 3,
                'kyc_status' => 'ENTERPRISE_VERIFIED',  // FIX: Changed from 'verified' to 'ENTERPRISE_VERIFIED'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tier 3 verification approved successfully',
                'data' => $verification->fresh(['user', 'beneficialOwners'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve verification: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Admin: Reject Tier 3 verification
     */
    public function adminReject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10',
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

            $verification = Tier3Verification::findOrFail($id);

            $verification->update([
                'verification_status' => 'rejected',
                'rejection_reason' => $request->reason,
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tier 3 verification rejected',
                'data' => $verification
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject verification: ' . $e->getMessage()
            ], 500);
        }
    }
}
