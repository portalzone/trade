<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessVerification;
use App\Models\BusinessDirector;
use App\Services\BusinessVerificationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BusinessDirectorController extends Controller
{
    protected BusinessVerificationService $businessService;

    public function __construct(BusinessVerificationService $businessService)
    {
        $this->businessService = $businessService;
    }

    /**
     * Add director to business
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'nin' => 'nullable|string|size:11',
            'bvn' => 'nullable|string|size:11',
            'date_of_birth' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'residential_address' => 'nullable|string',
            'ownership_percentage' => 'nullable|integer|min:0|max:100',
            'is_primary_contact' => 'nullable|boolean',
            'role' => 'required|in:director,shareholder,beneficial_owner,secretary',
            'id_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
            
            // Get user's business verification
            $verification = BusinessVerification::where('user_id', $user->id)->latest()->firstOrFail();

            // Upload ID document if provided
            $idPath = null;
            $idUrl = null;
            if ($request->hasFile('id_document')) {
                $file = $request->file('id_document');
                $filename = 'director-id-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = "business-docs/user-{$user->id}/directors/{$filename}";
                
                $storedPath = \Storage::disk('public')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path)
                );
                
                $idPath = $storedPath;
                $idUrl = \Storage::disk('public')->url($storedPath);
            }

            DB::beginTransaction();

            // Create director
            $director = BusinessDirector::create([
                'business_verification_id' => $verification->id,
                'full_name' => $request->full_name,
                'nin' => $request->nin,
                'bvn' => $request->bvn,
                'date_of_birth' => $request->date_of_birth,
                'phone' => $request->phone,
                'email' => $request->email,
                'residential_address' => $request->residential_address,
                'ownership_percentage' => $request->ownership_percentage,
                'is_primary_contact' => $request->is_primary_contact ?? false,
                'role' => $request->role,
                'id_document_path' => $idPath,
                'id_document_url' => $idUrl,
            ]);

            AuditService::log(
                'business.director.added',
                "Director added to business: {$director->full_name}",
                $director,
                [],
                ['director_name' => $director->full_name],
                ['user_id' => $user->id, 'business_id' => $verification->id]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Director added successfully',
                'data' => $director,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'business.director.add_failed',
                'Failed to add director',
                $e->getMessage(),
                ['user_id' => $request->user()->id]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to add director',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List all directors for user's business
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $verification = BusinessVerification::where('user_id', $user->id)->latest()->firstOrFail();

            $directors = BusinessDirector::where('business_verification_id', $verification->id)
                ->orderBy('is_primary_contact', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $directors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve directors',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update director
     */
    public function update(Request $request, $directorId)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'nullable|string|max:255',
            'nin' => 'nullable|string|size:11',
            'bvn' => 'nullable|string|size:11',
            'date_of_birth' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'residential_address' => 'nullable|string',
            'ownership_percentage' => 'nullable|integer|min:0|max:100',
            'is_primary_contact' => 'nullable|boolean',
            'role' => 'nullable|in:director,shareholder,beneficial_owner,secretary',
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
            $verification = BusinessVerification::where('user_id', $user->id)->latest()->firstOrFail();
            
            $director = BusinessDirector::where('id', $directorId)
                ->where('business_verification_id', $verification->id)
                ->firstOrFail();

            $director->update($request->only([
                'full_name',
                'nin',
                'bvn',
                'date_of_birth',
                'phone',
                'email',
                'residential_address',
                'ownership_percentage',
                'is_primary_contact',
                'role',
            ]));

            AuditService::log(
                'business.director.updated',
                "Director updated: {$director->full_name}",
                $director,
                [],
                [],
                ['user_id' => $user->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Director updated successfully',
                'data' => $director->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update director',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete director
     */
    public function destroy(Request $request, $directorId)
    {
        try {
            $user = $request->user();
            $verification = BusinessVerification::where('user_id', $user->id)->latest()->firstOrFail();
            
            $director = BusinessDirector::where('id', $directorId)
                ->where('business_verification_id', $verification->id)
                ->firstOrFail();

            $directorName = $director->full_name;
            $director->delete();

            AuditService::log(
                'business.director.deleted',
                "Director removed: {$directorName}",
                null,
                [],
                [],
                ['user_id' => $user->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Director removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove director',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload director ID document
     */
    public function uploadDocument(Request $request, $directorId)
    {
        $validator = Validator::make($request->all(), [
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
            $verification = BusinessVerification::where('user_id', $user->id)->latest()->firstOrFail();
            
            $director = BusinessDirector::where('id', $directorId)
                ->where('business_verification_id', $verification->id)
                ->firstOrFail();

            // Upload file
            $file = $request->file('id_document');
            $filename = 'director-id-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = "business-docs/user-{$user->id}/directors/{$filename}";
            
            $storedPath = \Storage::disk('public')->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );

            // Update director
            $director->update([
                'id_document_path' => $storedPath,
                'id_document_url' => \Storage::disk('public')->url($storedPath),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'ID document uploaded successfully',
                'data' => [
                    'id_document_path' => $director->id_document_path,
                    'id_document_url' => $director->id_document_url,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
