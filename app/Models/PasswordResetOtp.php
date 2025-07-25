<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PasswordResetOtp extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'attempts',
        'is_used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean'
    ];

    /**
     * Generate a 6-digit OTP
     */
    public static function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new OTP for the given email
     */
    public static function createForEmail(string $email): self
    {
        Log::info('Creating new OTP for email', ['email' => $email]);
        
        // Delete any existing OTPs for this email
        $deletedCount = self::where('email', $email)->count();
        self::where('email', $email)->delete();
        
        if ($deletedCount > 0) {
            Log::info('Deleted existing OTPs before creating new one', [
                'email' => $email,
                'deleted_count' => $deletedCount
            ]);
        }

        $otp = self::generateOtp();
        $expiresAt = Carbon::now()->addMinutes(10);
        
        $otpRecord = self::create([
            'email' => $email,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'is_used' => false
        ]);
        
        Log::info('OTP created successfully', [
            'email' => $email,
            'otp_id' => $otpRecord->id,
            'expires_at' => $expiresAt->toDateTimeString(),
            'expires_in_minutes' => 10
        ]);
        
        return $otpRecord;
    }

    /**
     * Verify OTP for the given email
     */
    public static function verifyOtp(string $email, string $otp): bool
    {
        Log::info('OTP verification attempt', [
            'email' => $email,
            'otp_provided' => $otp
        ]);
        
        $otpRecord = self::where('email', $email)
            ->where('otp', $otp)
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            // Check if there's any OTP for this email to provide better logging
            $existingOtp = self::where('email', $email)
                ->where('is_used', false)
                ->first();
                
            if ($existingOtp) {
                $isExpired = $existingOtp->expires_at->isPast();
                $wrongOtp = $existingOtp->otp !== $otp;
                
                Log::warning('OTP verification failed', [
                    'email' => $email,
                    'reason' => $isExpired ? 'expired' : ($wrongOtp ? 'wrong_otp' : 'unknown'),
                    'otp_expired' => $isExpired,
                    'current_attempts' => $existingOtp->attempts,
                    'expires_at' => $existingOtp->expires_at->toDateTimeString()
                ]);
            } else {
                Log::warning('OTP verification failed - no valid OTP found', [
                    'email' => $email
                ]);
            }
            
            // Increment attempts for any non-used OTP for this email
            $updatedCount = self::where('email', $email)
                ->where('is_used', false)
                ->increment('attempts');
                
            if ($updatedCount > 0) {
                Log::info('Incremented OTP attempts', [
                    'email' => $email,
                    'records_updated' => $updatedCount
                ]);
            }
            
            return false;
        }

        Log::info('OTP verification successful', [
            'email' => $email,
            'otp_id' => $otpRecord->id,
            'attempts_made' => $otpRecord->attempts
        ]);
        
        // Mark as used
        $otpRecord->update(['is_used' => true]);
        
        Log::info('OTP marked as used', [
            'email' => $email,
            'otp_id' => $otpRecord->id
        ]);
        
        return true;
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP has exceeded max attempts
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= 5; // Max 5 attempts
    }
}
