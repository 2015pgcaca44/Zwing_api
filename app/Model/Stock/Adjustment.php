<?php

namespace App\Model\Stock;

use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
	protected $table = 'adjustments';
	protected $primaryKey = 'id';

	protected $fillable = ['v_id', 'store_id', 'vu_id', 'stock_point_id', 'name', 'remark', 'is_post', 'sync_status', 'doc_no', 'total_product'];
}
