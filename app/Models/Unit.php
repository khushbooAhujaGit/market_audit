<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Unit extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['unit_name', 'unit_code', 'company_id', 'zone_id'];
    public function getZone(){
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
