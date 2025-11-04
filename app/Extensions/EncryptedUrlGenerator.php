<?php
namespace App\Extensions;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Crypt;

class EncryptedUrlGenerator extends UrlGenerator
{
    public function to($path, $extra = [], $secure = null)
    {
        $extra = $this->encryptParams($extra);
        return parent::to($path, $extra, $secure);
    }

    public function route($name, $parameters = [], $absolute = true)
    {
        $parameters = $this->encryptParams($parameters);
        return parent::route($name, $parameters, $absolute);
    }

    protected function encryptParams($parameters)
    {
        // Convert single scalar value to array
        if (is_scalar($parameters)) {
            $parameters = [$parameters];
        }

        foreach ($parameters as $key => $value) {
            if (is_scalar($value)) {
                $parameters[$key] = urlencode(Crypt::encryptString($value));
            }
        }

        return $parameters;
    }
}
