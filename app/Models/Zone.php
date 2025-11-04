<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Zone extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = [
        'company_id',
        'zone_code',
        'zone_name'
        // Add other fields here if necessary
    ];

    public function getCompany(){
        return $this->belongsTo(Company::class, 'company_id');
    }
}
