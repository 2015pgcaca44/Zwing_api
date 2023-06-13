<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Agents extends Model
{
protected $table = 'agents';

    protected $primaryKey = 'id';

    protected $fillable = ['agent_name', 'v_id'];
}
