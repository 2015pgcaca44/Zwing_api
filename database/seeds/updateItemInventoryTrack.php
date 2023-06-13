<?php

use Illuminate\Database\Seeder;
use App\Items\VendorItems;

class updateItemInventoryTrack extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
		echo $v_id     = 89;	
		JobdynamicConnection($v_id);
		$items  = VendorItems::where('v_id',$v_id)->update(['track_inventory'=>'1']);
    }
}
