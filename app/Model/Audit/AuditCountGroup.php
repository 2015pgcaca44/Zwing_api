<?php

namespace App\Model\Audit;

use Illuminate\Database\Eloquent\Model;

class AuditCountGroup extends Model
{
    protected $table = 'audit_count_groups';

    protected $fillable = ['v_id', 'store_id', 'vu_id', 'audit_plan_id', 'audit_plan_allocation_code', 'stock_point_id', 'description', 'unique_products', 'total_product_qty', 'completed_on'];

    public function stockpoint()
    {
        return $this->belongsTo('App\Model\Stock\StockPoints', 'stock_point_id', 'id');
    }

    public function getCompletedOnAttribute($value)
    {
    	return date('d F Y', strtotime($value));
    }
}
