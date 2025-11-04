<?php
namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;

class EncryptRouteHelper
{
    public static function encrypt($value)
    {
        if (is_numeric($value) || is_string($value)) {
            return urlencode(Crypt::encryptString($value));
        }

        return $value;
    }
    public static function decrypt($value)
    {
        try {
            return Crypt::decryptString(urldecode($value));
        } catch (\Exception $e) {
            return null; // or handle invalid/missing decryption
        }
    }
    public static function encryptArray(array $params)
    {
        return array_map([self::class, 'encrypt'], $params);
    }

    public static function decryptArray(array $params)
    {
        return array_map([self::class, 'decrypt'], $params);
    }
}
