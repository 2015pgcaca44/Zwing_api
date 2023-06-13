<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class SettlementSession extends Model
{
    protected $table = 'settlement_sessions';
    
    protected $fillable = ['store_id', 'v_id','type','vu_id','opening_balance','closing_balance','opening_time','closing_time','cash_register_id','short_access','status','partant_session_id','session_close_type','session_id','denomination_status','settlement_date','trans_from'];

}
