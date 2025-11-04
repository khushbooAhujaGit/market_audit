<?php
// app/Traits/HasEncryptedId.php
namespace App\Traits;

use App\Helpers\EncryptHelper;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait HasEncryptedId
{
    public function getRouteKey()
    {
        // Encrypt the model's ID for use in the URL
        return EncryptHelper::encrypt($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $id = EncryptHelper::decrypt($value);
            return static::where($field ?? 'id', $id)->firstOrFail();
        } catch (\Exception $e) {
            abort(404);
        }
    }

}

