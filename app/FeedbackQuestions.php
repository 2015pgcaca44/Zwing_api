<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeedbackQuestions extends Model
{
    protected $table = 'feedback_questions';

    protected $primaryKey = 'id';

    protected $fillable = ['vendor_id', 'store_id', 'question', 'options', 'status'];


}
