<?php

namespace App\Model\Client;

use Illuminate\Database\Eloquent\Model;

class ClientState extends Model
{
    protected $table = 'client_state_mapping';
    protected $connection = 'mysql';

    protected $primaryKey = 'id';

    protected $fillable = ['state_id', 'client_id', 'ref_state_code'];

    public function stores(){
    	return $this->hasMany('App\Client\ClientStoreMapping', 'cvm_id');
    }
}
