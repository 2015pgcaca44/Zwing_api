<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DaySettlement extends Model
{
   protected $table = 'day_settlements';

	protected $primaryKey = 'id';

	protected $fillable = ['v_id','store_id','vu_id','opening_store_cash_balance', 'total_storecash_in','total_storecash_out','closing_storecash_balance', 'opening_petty_cash_balance','total_petty_cash_in','total_petty_cash_out', 'closing_petty_cash_balance','total_sales_recorded','nos_sales_bills_generated', 'nos_return_bills_generated','nos_advice_received','nos_grn_created', 'nos_sto_created','nos_grt_created','nos_store_expenses_generated','nos_spt_created', 'nos_adj_created','pending_sync_transactions','status','created_by','date','time', 'sync_status'];
}
