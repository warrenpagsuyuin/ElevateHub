<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedbackAnalysisUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['human_reviewed_at'=>'datetime','original_human_reviewed_at'=>'datetime','predicted_at'=>'datetime','scope_reviewed_at'=>'datetime','language_reviewed_at'=>'datetime','is_included'=>'boolean','is_test_record'=>'boolean','quote_approved'=>'boolean','independent_attested'=>'boolean','system_confidence'=>'float','review_revision'=>'integer'];
    }

    public function feedback() { return $this->belongsTo(Feedback::class); }
    public function parentUnit() { return $this->belongsTo(self::class, 'parent_unit_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'human_reviewed_by'); }
}
