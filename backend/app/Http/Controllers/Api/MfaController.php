<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MfaActivityLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class MfaController extends Controller
{
    protected $google2fa;
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->google2fa = new Google2FA();
        $this->notificationService = $notificationService;
    }

    /**
     * Generate MFA secret and QR code
     */
    public function setup(Request $request)
    {
        try {
            $user = $request->user();

            // Generate secret
            $secret = $this->google2fa->generateSecretKey();

            // Generate QR code URL
            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            // Generate SVG QR code
            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrCodeSvg = $writer->writeString($qrCodeUrl);

            // Save secret to user (but don't enable MFA yet)
            $user->update(['mfa_secret' => $secret]);

            // Log activity
            MfaActivityLog::logActivity($user->id, 'setup');

            return response()->json([
                'success' => true,
                'data' => [
                    'secret' => $secret,
                    'qr_code_svg' => base64_encode($qrCodeSvg),
                    'manual_entry_key' => $secret,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate MFA setup',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify MFA code and enable MFA
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->mfa_secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'MFA not set up. Please call /setup first.'
                ], 400);
            }

            // Verify the code
            $valid = $this->google2fa->verifyKey($user->mfa_secret, $request->code);

            if (!$valid) {
                // Log failed verification
                MfaActivityLog::logActivity($user->id, 'verify_failed', [
                    'reason' => 'Invalid code'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code'
                ], 400);
            }

            // Generate recovery codes
            $recoveryCodes = $this->generateRecoveryCodes();

            // Enable MFA
            $user->update([
                'mfa_enabled' => true,
                'mfa_method' => 'TOTP',
                'mfa_recovery_codes' => json_encode($recoveryCodes),
            ]);

            // Log successful verification
            MfaActivityLog::logActivity($user->id, 'verify_success');

            // Send email notification
            $this->notificationService->sendMfaNotification($user, 'enabled');

            return response()->json([
                'success' => true,
                'message' => 'MFA enabled successfully',
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify MFA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify MFA code during login
     */
    public function verifyLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = \App\Models\User::where('email', $request->email)->first();

            if (!$user || !$user->mfa_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'MFA not enabled for this account'
                ], 400);
            }

            // Verify the code
            $valid = $this->google2fa->verifyKey($user->mfa_secret, $request->code);

            if (!$valid) {
                // Log failed login attempt
                MfaActivityLog::logActivity($user->id, 'login_failed', [
                    'reason' => 'Invalid MFA code'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code'
                ], 400);
            }

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;
            $user->recordLogin();

            // Log successful login
            MfaActivityLog::logActivity($user->id, 'login_success');

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'full_name' => $user->full_name,
                        'kyc_tier' => $user->kyc_tier,
                    ],
                    'token' => $token,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recovery codes for the authenticated user
     */
    public function getRecoveryCodes(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->mfa_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'MFA is not enabled for this account',
                ], 400);
            }

            if (empty($user->mfa_recovery_codes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recovery codes found',
                ], 404);
            }

            $recoveryCodes = json_decode($user->mfa_recovery_codes, true);

            return response()->json([
                'success' => true,
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recovery codes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->mfa_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'MFA is not enabled for this account',
                ], 400);
            }

            $recoveryCodes = [];
            for ($i = 0; $i < 10; $i++) {
                $recoveryCodes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            }

            $user->update([
                'mfa_recovery_codes' => json_encode($recoveryCodes),
            ]);

            // Log activity
            MfaActivityLog::logActivity($user->id, 'codes_regenerated');

            return response()->json([
                'success' => true,
                'message' => 'Recovery codes regenerated successfully',
                'data' => [
                    'recovery_codes' => $recoveryCodes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate recovery codes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable MFA for the authenticated user
     */
    public function disable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();

            if (!$user->mfa_enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'MFA is not enabled for this account',
                ], 400);
            }

            if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password_hash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password',
                ], 401);
            }

            $user->update([
                'mfa_enabled' => false,
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
                'mfa_method' => null,
            ]);

            // Log activity
            MfaActivityLog::logActivity($user->id, 'disabled');

            // Send email notification
            $this->notificationService->sendMfaNotification($user, 'disabled');

            return response()->json([
                'success' => true,
                'message' => 'MFA disabled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable MFA',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get MFA activity logs
     */
    public function getActivityLogs(Request $request)
    {
        try {
            $user = $request->user();
            
            $logs = MfaActivityLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($request->get('limit', 50))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}
