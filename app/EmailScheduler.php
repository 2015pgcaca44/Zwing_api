<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmailScheduler extends Model
{
    protected $table = 'email_schedulers';

    protected $fillable = ['v_id', 'store_id', 'type', 'email_list', 'bcc_email_list', 'last_schedule_date', 'status'];
}
