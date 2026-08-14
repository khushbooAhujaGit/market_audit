<?php

namespace App\Services;

use App\Models\PasswordResetOtp;
use Carbon\Carbon;

class ForgotPasswordOtpService
{
    public function __construct(protected OutlookMailerService $mailer) {}

    public function sendOtp(string $email, string $userName): Carbon
    {
        // Invalidate any previous unused OTPs for this email
        PasswordResetOtp::where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(10);

        PasswordResetOtp::create([
            'email'      => $email,
            'otp'        => $otp,
            'expires_at' => $expiresAt,
        ]);

        $subject = 'Your Password Reset OTP — Market Audit';

        $body = "
        <div style='font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:24px'>
            <h2 style='color:#1A2A40;margin-bottom:8px'>Password Reset Request</h2>
            <p style='color:#4A5568'>Hi {$userName},</p>
            <p style='color:#4A5568'>Use the OTP below to reset your Market Audit password.
               This code expires in <strong>10 minutes</strong>.</p>
            <div style='background:#F0F4FA;border-radius:8px;padding:20px 32px;text-align:center;margin:24px 0'>
                <span style='font-size:36px;font-weight:700;letter-spacing:10px;color:#2563EB'>{$otp}</span>
            </div>
            <p style='color:#718096;font-size:13px'>If you did not request a password reset, please ignore this email.
               Your account is safe.</p>
        </div>";

        $this->mailer->sendMail($email, $subject, $body);

        return $expiresAt;
    }

    public function verifyOtp(string $email, string $otp): bool
    {
        $record = PasswordResetOtp::where('email', $email)
            ->where('otp', $otp)
            ->whereNull('used_at')
            ->latest()
            ->first();

        return $record && $record->isValid();
    }

    public function consumeOtp(string $email, string $otp): bool
    {
        $record = PasswordResetOtp::where('email', $email)
            ->where('otp', $otp)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (!$record || !$record->isValid()) {
            return false;
        }

        $record->update(['used_at' => now()]);
        return true;
    }
}
