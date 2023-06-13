<?php
/**
 * Created by PhpStorm.
 * User: sudhanshuigi
 * Date: 15/11/18
 * Time: 11:47 AM
 */

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class LogCollection extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'api_log';
    protected $primaryKey = '_id';
    protected $fillable = ['api_name', 'status','v_id', 'store_id','client', 'url', 'para', 'data', 'date'];
}