<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Activity extends Model
{
    use HasFactory, HasEncryptedId;
    protected $fillable = ['activity_name'];

    protected $casts = [
        'id' => 'integer',
    ];
    public function questions()
    {
        return $this->hasMany(Question::class, 'activity_id')->orderBy('question_sequence');
    }
    // Set up the deleting event to handle related records
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($activity) {
            // Delete related Question records
            foreach ($activity->questions as $question) {
                $question->delete();
            }
        });
    }
}
