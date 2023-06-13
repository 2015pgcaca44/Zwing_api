<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Http\Controllers\CloudPos\TableSyncController;

class WebSocketController extends Controller implements MessageComponentInterface
{
   private $connections = [];
	// private $clients;

	public function __construct() {
       // $this->clients = new \SplObjectStorage;
    }
    
   function onOpen(ConnectionInterface $conn)
   {
        return 'Connection Established!';
    }

    function onClose(ConnectionInterface $conn){
        // $disconnectedId = $conn->resourceId;
        // unset($this->connections[$disconnectedId]);
        // foreach($this->connections as &$connection)
        //     $connection['conn']->send(json_encode([
        //         'offline_user' => $disconnectedId,
        //         'from_user_id' => 'server control',
        //         'from_resource_id' => null
        //     ]));
        return 'Connection Closed!';
    }

    function onError(ConnectionInterface $conn, \Exception $e){
        // $userId = $this->connections[$conn->resourceId]['user_id'];
        // echo "An error has occurred with user $userId: {$e->getMessage()}\n";
        // unset($this->connections[$conn->resourceId]);
        $conn->close();
        // return 'Error!'
    }

    function onMessage(ConnectionInterface $conn, $msg){
        $msg = json_decode($msg);
        $syncList = [ 'item_master' => 'item_master', 'user_master' => 'store_user', 'session_master' => 'session', 'setting_master' => 'user_wise_settings' ];
        $request = new \Illuminate\Http\Request();
        $request->merge([ 'v_id' => $msg->v_id, 'store_id' => $msg->store_id, 'vu_id' => $msg->vu_id, 'trans_from' => $msg->trans_from, 'type' => $syncList[$msg->type], 'api_token' => $msg->api_token, 'last_time' => $msg->last_time, 'udidtoken' => $msg->udidtoken ]);
        $syncController = new TableSyncController;
        $response = $syncController->sync($request);
        $conn->send($response->getContent());
    }

}
