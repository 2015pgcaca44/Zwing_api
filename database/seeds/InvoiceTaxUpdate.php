<?php

use Illuminate\Database\Seeder;
use App\InvoiceDetails;
use App\Invoice;
use App\Http\Controllers\CloudPos\CartController;
 

class InvoiceTaxUpdate extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
    	$v_id     = 26;	
    	JobdynamicConnection($v_id);
        $invoices = InvoiceDetails::join('vendor_sku_flat_table as vsft','vsft.barcode','invoice_details.barcode')
        						->join('tax_hsn_cat as thc','thc.hsncode','vsft.hsn_code')
        						->join('tax_category as tc','tc.id','thc.cat_id')
        						->join('invoices as inv','inv.id','invoice_details.t_order_id')
        						->where('tc.slab','YES')
        						->where('invoice_details.tax','0')
        						->where('invoice_details.v_id',$v_id)
        						->select('invoice_details.*','vsft.hsn_code','inv.invoice_id','inv.id as invid')
        						->get();
        $sr = 0;
        foreach($invoices as $invitem){
        	//echo $sr++;
        	$cart = new CartController;
        	$params = array('barcode'=>$invitem->barcode,'qty'=>$invitem->qty,'s_price'=>$invitem->total,'hsn_code'=>$invitem->hsn_code,'store_id'=>$invitem->store_id,'v_id'=>$invitem->v_id );
        	$taxd 	  = $cart->taxCal($params);
        	$totaltax = $taxd['tax'];
        	$tdata    = json_encode($taxd);
        	echo InvoiceDetails::where('id',$invitem->id)->update(['tax'=>$totaltax,'tdata'=>$tdata]);
        	$sumInvoice = InvoiceDetails::select(DB::raw("SUM(tax) as sum_tax"))->where('t_order_id',$invitem->invid)->first();
        	//echo $sumInvoice->sum_tax;
        	echo Invoice::where('invoice_id',$invitem->invoice_id)->update(['tax'=>$sumInvoice->sum_tax]);

        	echo '<br>';

        }

    }
}
