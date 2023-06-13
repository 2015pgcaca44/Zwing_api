<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Search\SearchableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class ProductSearch extends Model
{
	use SearchableTrait;
	use SoftDeletes;

	protected $table = 'vendor_sku_flat_table';

	protected $documentType = 'products';

    protected $fillable = ['*'];

    #protected $fillable = ['v_id', 'c_id','legal_name', 'state_id', 'created_by', 'gstin'];

	#protected $searchable = ['v_id', 'name', 'barcode', 'store_id'];
	
}
