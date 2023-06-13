<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
   protected $table = 'shopping_list';

   protected $primaryKey = 'id';

   protected  $fillable = [ 'user_id', 'v_id', 'store_id', 'product_id',  'barcode', 'amount', 'qty', 'for_date', 'added_to_cart'];
}
