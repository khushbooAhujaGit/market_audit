<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class EncryptHelper
{
    /**
     * Encrypt the given value safely.
     */
    public static function encrypt($value)
    {
        try {
            // Only encrypt if not already encrypted (optional check)
            if (self::isEncrypted($value)) {
                return $value;
            }

            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            Log::error("Encryption failed for value: {$value}, Error: {$e->getMessage()}");
            return $value; // Fallback to original
        }
    }

    /**
     * Decrypt the given value safely.
     */
    public static function decrypt($value)
    {
        try {
            if (!self::isEncrypted($value)) {
                return $value;
            }

            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            Log::warning("Decryption failed for value: {$value}, Error: {$e->getMessage()}");
            return $value; // Fallback to original
        }
    }

    /**
     * Determine if a value is likely encrypted (basic heuristic).
     */
    public static function isEncrypted($value): bool
    {
        return is_string($value) && preg_match('/^[a-zA-Z0-9\/+=]+$/', $value) && strlen($value) > 40;
    }
}
