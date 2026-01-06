<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MfaActivityLog extends Model
{
    use HasUuids;

    protected $table = 'mfa_activity_logs';
    
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'activity_type',
        'ip_address',
        'user_agent',
        'device_type',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log MFA activity
     */
    public static function logActivity(
        int $userId,
        string $activityType,
        ?array $details = null
    ): self {
        $request = request();
        
        return self::create([
            'user_id' => $userId,
            'activity_type' => $activityType,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => self::detectDeviceType($request->userAgent()),
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    /**
     * Detect device type from user agent
     */
    private static function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        if (preg_match('/mobile/i', $userAgent)) {
            return 'mobile';
        }

        if (preg_match('/tablet/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * Get activity type label
     */
    public function getActivityLabelAttribute(): string
    {
        return match($this->activity_type) {
            'setup' => 'MFA Setup',
            'verify_success' => 'Verification Success',
            'verify_failed' => 'Verification Failed',
            'recovery_used' => 'Recovery Code Used',
            'disabled' => 'MFA Disabled',
            'login_success' => 'Login Success',
            'login_failed' => 'Login Failed',
            'codes_regenerated' => 'Recovery Codes Regenerated',
            default => 'Unknown Activity',
        };
    }

    /**
     * Get activity icon
     */
    public function getActivityIconAttribute(): string
    {
        return match($this->activity_type) {
            'setup' => '🔐',
            'verify_success', 'login_success' => '✅',
            'verify_failed', 'login_failed' => '❌',
            'recovery_used' => '🔑',
            'disabled' => '⚠️',
            'codes_regenerated' => '🔄',
            default => '📝',
        };
    }
}
