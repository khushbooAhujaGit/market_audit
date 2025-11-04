<?php

namespace App\Traits;

use App\Helpers\EncryptRouteHelper;

trait DecryptsRouteModel
{
    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $id = EncryptRouteHelper::decrypt($value);
            return $this->where($field ?? 'id', $id)->firstOrFail();
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
