<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class CompanyType extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['company_type_name'];

}
