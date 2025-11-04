<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Company extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = [
        'company_name',
        'company_code',
        'company_type',
        'address',
        'city',
        'district',
        'state',
        'image',
        // Add other fields here if necessary
    ];

    public function getCompanyTypeInfo(){
        return $this->belongsTo(CompanyType::class, 'company_type');
    }
}
