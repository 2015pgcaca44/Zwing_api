<?php

namespace App\Jobs;

use DB;
use Auth;
use Log;

class FlatProduct extends Job
{

    protected $productData;

    public $queue = 'sequential';

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($productData)
    {
        $this->productData = $productData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $dbName = $this->productData['dbname'];
        if(isset($this->productData['product_id']) ){//Running for Multiple Items

            foreach ($this->productData['product_id'] as $key => $product_id) {
    
                DB::statement('call '.$dbName.'.CreateVendorSkuFlatTable(?,?,?)', [$this->productData['v_id'], $product_id, $dbName]);
            }

        }else if(isset($this->productData['id']) ){//Running for Single Item

            // Log::info('call '.$dbName.'.CreateVendorSkuFlatTable(?,?,?) Params '.$this->productData['v_id'].' '.$this->productData['id'].' '. $dbName);
            DB::statement('call '.$dbName.'.CreateVendorSkuFlatTable(?,?,?)', [$this->productData['v_id'], $this->productData['id'], $dbName]);
        }else{//Running for All Items

            DB::statement('call '.$dbName.'.CreateVendorSkuFlatTable(?,?,?)', [$this->productData['v_id'], 0, $dbName]);            
        }
    }
}
