<?php

namespace App\Listeners;

use App\Events\SaleItemReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use DB;

class SaleItemLevelReport implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  SaleItemReport  $event
     * @return void
     */
    public function handle(SaleItemReport $event)
    {
        
        $params = $event->params;
        $params['job_class'] = __CLASS__;

        //print_r($params);
       
        //$invoice_id = $event->invoice_id;
        $invoice_id = $params['invoice_id'];
        $v_id = $params['v_id'];
        JobdynamicConnection($v_id);    
        $insert =  DB::select("select invoices.v_id,invoices.date,
            invoices.time, invoices.invoice_id,invoices.transaction_type,invoices.store_id, stores.name as store_name,stores.location,stores.city, stores.short_code, customer_auth.c_id, customer_auth.mobile
            , invoices.remark,
            concat(vendor_auth.first_name, ' ', vendor_auth.last_name) as cashier_name,
            vendor_auth.id as cahier_id,
            concat(customer_auth.first_name, ' ', customer_auth.last_name) as customer_name,
            concat(salesman.first_name,' ', salesman.last_name) as salesman_name,
            item.sku, id.barcode, item.uom_name,
            GROUP_CONCAT(payments.method) as payment_method,
            id.item_name ,item.category,id.unit_mrp,id.qty, id.subtotal ,id.discount as promo_discount,id.manual_discount,id.bill_buster_discount as bill_discount,
            id.total as gross_sale,
            json_extract_c(id.item_level_manual_discount, 'discount') as item_level_discount,
            json_extract_c(id.tdata, 'sgst') as sgst_per,
            json_extract_c(id.tdata, 'cgst') as cgst_per,
            (json_extract_c(id.tdata, 'sgst') + json_extract_c(id.tdata, 'cgst')) as igst_per,
            json_extract_c(id.tdata, 'sgstamt') as sgst_amount,
            json_extract_c(id.tdata, 'cgstamt') as cgst_amount,
            json_extract_c(id.tdata, 'igstamt') as igst_amount,
            json_extract_c(id.tdata, 'cess') as cess_per,
            json_extract_c(id.tdata, 'cessamt') as cess_amount,
            json_extract_c(id.tdata, 'hsn') as hsn_code,
            id.tax, invoices.cust_gstin as customer_gtsin,
            invoices.store_gstin as store_gtsin,
            (id.total - id.tax) as net_sale,id.total as tota_sale
            from invoices inner join stores on stores.store_id = invoices.store_id
            join invoice_details as id on id.t_order_id = invoices.id
            join v_item_list as item on item.barcode = id.barcode AND id.v_id = item.v_id
            inner join vendor_auth on vendor_auth.id = invoices.vu_id
            inner join customer_auth on customer_auth.c_id = invoices.user_id
            inner join payments on payments.invoice_id = invoices.invoice_id AND id.v_id = payments.v_id
            left join vendor_auth as salesman on salesman.id = id.salesman_id
            where invoices.invoice_id ='". $invoice_id ."'
            group by id.id
            order by invoices.id;
            ");

           /* echo 'good';
            print_r($insert);*/

            // DB::table('sale_item_level_report')->insert((array)$insert[0]); 
            foreach($insert as $key => $in){
                DB::table('sale_item_level_report')->insert((array)$in); 
            }
            echo $v_id.'--'.$invoice_id."--Done ";
         //DB::select("call InsertSaleItemLevelReport('".$invoice_id."')"); 

    }
}
