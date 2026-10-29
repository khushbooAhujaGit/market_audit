<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEncryptedId;

class Question extends Model
{
    use HasFactory, HasEncryptedId;

    protected $fillable = [
        'activity_id',
        'question',
        'question_type',
        'answer_type',
        'question_sequence',
        'is_parent',
        'parent_question_id',
        'parent_value',
        'parent_dropdown_id',
        'allow_multiple_images',
        'help_text',
        'validation_rule',
        'validation_min',
        'validation_max',
        'validation_regex',
        'file_types',
        'max_file_size_mb',
        'date_min',
        'date_max',
    ];

    protected $casts = [
        'id'                  => 'integer',
        'activity_id'         => 'integer',
        'answer_type'         => 'integer',
        'question_sequence'   => 'integer',
        'is_parent'           => 'integer',
        'parent_question_id'  => 'integer',
        'parent_dropdown_id'  => 'integer',
        'allow_multiple_images' => 'integer',
        'validation_min'      => 'integer',
        'validation_max'      => 'integer',
        'max_file_size_mb'    => 'integer',
    ];


    public function scopeActive($query)
    {

        return $query->where('deleted_at', null);
    }

    /**
     * Leaves out orphaned sub-questions: flagged as a sub-question (is_parent = 0) but linked to
     * no parent (no parent_question_id, not in question_sub_questions), e.g. left behind by an
     * Excel upload. The app never shows them (TaskController::getAllQuestion), so screens and
     * reports that list an activity's questions skip them too.
     */
    public function scopeWithoutOrphans($query)
    {
        $table = $query->getModel()->getTable();
        return $query->where(function ($q) use ($table) {
            $q->where("{$table}.is_parent", '!=', 0)
                ->orWhereNull("{$table}.is_parent")
                ->orWhereNotNull("{$table}.parent_question_id")
                ->orWhereExists(function ($s) use ($table) {
                    $s->selectRaw('1')->from('question_sub_questions')
                        ->whereColumn('question_sub_questions.child_question_id', "{$table}.id");
                });
        });
    }

    // Normalizes any spacing/casing variant of "yes/no" (Yes/No, yes /NO, YES / no, ...) to "Yes / No"
    public function getQuestionTypeAttribute($value)
    {
        if ($value !== null && preg_match('/^\s*yes\s*\/\s*no\s*$/i', $value)) {
            return 'Yes / No';
        }

        return $value;
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function getOptions()
    {
        return $this->hasMany(QuestionDropdown::class, 'question_id', 'id');
    }

    public function getSubjects()
    {
        return $this->hasMany(SubjectQuestion::class, 'question_id');
    }

    public function subQuestions()
    {
        return $this->hasMany(QuestionSubQuestion::class, 'parent_question_id')->orderBy('sequence');
    }

    // Set up the deleting event to handle related records
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($question) {
            // Delete related SubjectQuestion records
            foreach ($question->getSubjects as $subjectQuestion) {
                $subjectQuestion->delete();
            }

            // Delete related QuestionDropdown records
            foreach ($question->getOptions as $option) {
                $option->delete();
            }
        });
    }
}
