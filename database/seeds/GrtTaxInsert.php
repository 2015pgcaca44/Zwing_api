<?php

use Illuminate\Database\Seeder;
use App\GrtDetail;
use App\GrtHeader;
use App\LastInwardPrice;
use App\Model\Grn\GrnList;
use App\Http\Controllers\CloudPos\CartController;

class GrtTaxInsert extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $v_id     = 90; 
        JobdynamicConnection($v_id);
        $grt    = GrtDetail::join('vendor_sku_flat_table as vsft','vsft.sku_code','grt_details.sku_code')
                            ->join('tax_hsn_cat as thc','thc.hsncode','vsft.hsn_code')
                            ->join('tax_category as tc','tc.id','thc.cat_id')
                            ->join('grt_headers as grt','grt.id','grt_details.grt_id')
                            //->where('grt_details.tax','0')
                            ->where('grt_details.grt_id','12')
                            ->where('grt_details.v_id',$v_id)
                            ->select('grt_details.*','vsft.hsn_code','grt.grt_no','grt.id as grtid')
                            ->get();




        //$grt    = GrtDetail::join('vendor_sku_flat_table as vsft','vsft.barcode','grt_details.barcode')->where('grt_details.tax','0')->where('grt_details.v_id',$v_id)->get();

        $sr = 0;
        foreach($grt as $grt_item){

            //echo $grt_item->grt_no;

            /* when supply price is 0 */

            if(empty($grt_item->supply_price) ||  $grt_item->supply_price == '0'){
             $lastInwordPrice = LastInwardPrice::where('barcode',$grt_item->barcode)->where('v_id',$v_id)->where('source_site_type','supplier')->orderBy('id','desc')->first();
             if($lastInwordPrice){
                    $supply_price = $lastInwordPrice->supply_price;  
                           
             }else{
               // DB::enableQueryLog();
                $grnListPrice = GrnList::where('sku_code',$grt_item->sku_code)->where('v_id',$v_id)->orderBy('id','desc')->first();
                  echo $supply_price = $grnListPrice->unit_mrp;  
                  // dd(DB::getQueryLog());
             }
              GrtDetail::where('id',$grt_item->id)->update(['supply_price'=>$supply_price]);   
            }else{
                $supply_price  = $grt_item->supply_price;
            }

            $check_subtotal_price  =  $supply_price*$grt_item->qty+$grt_item->charge;
            if($grt_item->subtotal != $check_subtotal_price){
                $subtotal    =  $supply_price*$grt_item->qty+$grt_item->charge;
                GrtDetail::where('id',$grt_item->id)->update(['subtotal'=>$subtotal]);
            }else{
                $subtotal    = $supply_price*$grt_item->qty+$grt_item->charge;
            }

            //echo $sr++;
            $cart = new CartController;
            $params = array('barcode'=>$grt_item->barcode,'qty'=>$grt_item->qty,'s_price'=>$subtotal,'hsn_code'=>$grt_item->hsn_code,'store_id'=>$grt_item->store_id,'v_id'=>$grt_item->v_id );

            $params['from_gstin']  =  '07AJMPK7202M1ZX';
            $params['to_gstin']    =  '07AACCP8575C1ZB';
            $params['tax_for']     =  'GRT';

            $taxd     = $cart->taxCalStag($params);
            $totaltax = $taxd['tax'];
            $tdata    = json_encode($taxd);
            $taxdet  = json_decode($tdata);

        
            //print_r($tdata);die;

            $total   = $taxdet->total;

            echo GrtDetail::where('id',$grt_item->id)->update(['tax'=>$totaltax,'tax_details'=>$tdata,'total'=>$total]);
            $sumInvoice = GrtDetail::select(DB::raw("SUM(tax) as sum_tax"),DB::raw("SUM(total) as total"))->where('grt_id',$grt_item->grtid)->first();

            //print_r($sumInvoice->sum_tax);

            echo GrtHeader::where('id',$grt_item->grtid)->update(['tax_amount'=>$sumInvoice->sum_tax,'total'=>$sumInvoice->total]);

            echo '<br>';
    }
}

}
