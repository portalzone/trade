<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\EvidenceUpload;
use App\Services\S3StorageService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EvidenceController extends Controller
{
    protected S3StorageService $s3Service;

    public function __construct(S3StorageService $s3Service)
    {
        $this->s3Service = $s3Service;
    }

    /**
     * Upload evidence for a dispute
     */
    public function upload(Request $request, $disputeId)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:5',
            'files.*' => 'required|file|max:10240', // 10MB max per file
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $dispute = Dispute::findOrFail($disputeId);
            $user = $request->user();

            // Check if user is part of the dispute
            if (!in_array($user->id, [$dispute->order->seller_id, $dispute->order->buyer_id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to upload evidence for this dispute',
                ], 403);
            }

            DB::beginTransaction();

            $uploadedFiles = [];
            $files = $request->file('files');

            foreach ($files as $file) {
                // Validate file
                $validation = $this->s3Service->validateEvidence($file);
                if (!$validation['valid']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'File validation failed',
                        'errors' => $validation['errors'],
                    ], 422);
                }

                // Upload to S3
                $result = $this->s3Service->uploadEvidence($file, $user->id, $disputeId);

                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload failed',
                        'error' => $result['error'],
                    ], 500);
                }

                // Create evidence record
                $evidence = EvidenceUpload::create([
                    'dispute_id' => $disputeId,
                    'uploaded_by_user_id' => $user->id,
                    'file_path' => $result['path'],
                    'file_url' => $result['url'],
                    'file_name' => $result['filename'],
                    'file_size' => $result['size'],
                    'mime_type' => $result['mime_type'],
                    'description' => $request->description,
                ]);

                $uploadedFiles[] = $evidence;

                // Audit log
                AuditService::log(
                    'evidence.uploaded',
                    "Evidence uploaded for dispute #{$disputeId}",
                    $evidence,
                    [],
                    ['file_name' => $result['filename']],
                    ['dispute_id' => $disputeId, 'user_id' => $user->id]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Evidence uploaded successfully',
                'data' => $uploadedFiles,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            AuditService::logFailure(
                'evidence.upload_failed',
                'Failed to upload evidence',
                $e->getMessage(),
                ['dispute_id' => $disputeId]
            );

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload evidence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get evidence for a dispute
     */
    public function index(Request $request, $disputeId)
    {
        try {
            $dispute = Dispute::findOrFail($disputeId);
            $user = $request->user();

            // Check if user is part of the dispute
            if (!in_array($user->id, [$dispute->order->seller_id, $dispute->order->buyer_id])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $evidence = EvidenceUpload::where('dispute_id', $disputeId)
                ->with('uploadedBy:id,full_name')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $evidence,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve evidence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete evidence
     */
    public function destroy(Request $request, $disputeId, $evidenceId)
    {
        try {
            $evidence = EvidenceUpload::where('dispute_id', $disputeId)
                ->where('id', $evidenceId)
                ->firstOrFail();

            // Only uploader can delete
            if ($evidence->uploaded_by_user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            // Delete from S3
            $this->s3Service->deleteEvidence($evidence->file_path);

            // Delete record
            $evidence->delete();

            return response()->json([
                'success' => true,
                'message' => 'Evidence deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete evidence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
