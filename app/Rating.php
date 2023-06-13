<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    protected $table = 'ratings';

    protected $primaryKey = 'Rating_ID';

    protected $fillable = ['V_ID', 'Store_ID', 'User_ID', 'Star', 'Date', 'Month', 'Year', 'Time'];
}
