<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;
use App\Cart;
class ExampleTest extends TestCase
{


    // Assert the file @test was stored...
    // private $api_token = 'rYbU9Bn1pnBHaitOhEclgCVsLZzIA8sLsg4ixiXHTnHf9s3sKG';
    // private $vendor_id = 1;
    // private $store_id  = 1;
    // private $trans_from= 'ANDROID_VENDOR';

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $this->get('/');

        $this->assertEquals(
            $this->app->version(), $this->response->getContent()
        );
    }
      

 /*   private function truncateDB(){
         Cart::query()->truncate();
    }

    public function testPromo1()
    {
            //Buy 2 unit from Assortment [Zwing Assort 1 ], Get 45 % Off on MRP.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1008';
            $item_2 = 'D1009';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "247.50";
            $qty   = 2;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

    }


    public function testPromo2()
    {
            //Buy 2 unit from Assortment [Zwing Assort 1 ], Get 45 % Off on MRP.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1008';
            $item_2 = 'D1008';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "192.50";
            $qty   = 2;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }



public function testPromo3()
    {
            //Buy 2 unit from Assortment [Zwing Assort 1 ], Get 45 % Off on MRP.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1008';
            $item_2 = 'D1009';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "247.50";
            $qty   = 2;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }




public function testPromo4()
    {
           

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1008';
            $item_2 = 'D1008';
            $item_3 = 'D1009';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "422.50";
            $qty   = 3;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }

public function testPromo5()
    {
            

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1008';
            $item_2 = 'D1009';
            $item_3 = 'D1009';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "477.50";
            $qty   = 3;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }

public function testPromo6()
    {
                

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1006';
            $item_2 = 'D1006';
            $item_3 = 'D1006';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "942.30";
            $qty   = 3;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }


public function testPromo7()
    {
             // BZwing Promo 2 : Flat Rs. 50/- Discount Amount  : Item Level.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1006';
            $item_2 = 'D1128';
            $item_3 = 'D1129';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "4814.10";
            $qty   = 3;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }



public function testPromo8()
    {
            //BZwing Promo 2 : Flat Rs. 50/- Discount Amount  : Item Level.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1006';
            $item_2 = 'D1006';
            $item_3 = 'D1128';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "3328.20";
            $qty   = 3;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }



public function testPromo9()
    {
            BZwing Promo 2 : Flat Rs. 50/- Discount Amount  : Item Level.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1006';
            $item_2 = 'D1128';
            $item_3 = 'D1129';
            $item_4 = 'D1129';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "5144.30";
            $qty   = 4;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }




public function testPromo10()
    {
            BZwing Promo 2 : Flat Rs. 50/- Discount Amount  : Item Level.

            $this->truncateDB();
            //DB::table('cart')->truncate();
            $item_1 = 'D1006';
            $item_2 = 'D1128';
            $item_3 = 'D1129';
            $item_4 = 'D1129';
            $item_5 = 'D1006';
            $vu_id = 1;
            $c_id  = 1;
            $amount= "3603.00";
            $qty   = 5;

            for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
            }//End loop

            $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

      
    }



    public function testPromo11(){
        
        Buy 2 unit from Assortment [Zwing Assort 3 ], @ Rs. 100  each.

        $this->truncateDB();
        $item_1 = 'D1126';
        $item_2 = 'D3130';
        $item_3 = 'D3131';
        $item_4 = 'D3132';
        $item_5 = 'D3133';
        $vu_id = 3;
        $c_id  = 1;
        $amount= "1099.00";
        $qty   = 5;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

public function testPromo12(){
        
        Buy 2 unit from Assortment [Zwing Assort 3 ], @ Rs. 100  each.

        $this->truncateDB();
        $item_1 = 'D1126';
        $item_2 = 'D3130';
        $item_3 = 'D1126';
       
        $vu_id = 3;
        $c_id  = 1;
        $amount= "899.00";
        $qty   = 3;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }



public function testPromo13(){
        
        Buy 2 unit from Assortment [Zwing Assort 3 ], @ Rs. 100  each.

        $this->truncateDB();
        $item_1 = 'D1126';
        $item_2 = 'D3130';
        $item_3 = 'D1126';
        $item_4 = 'D3130';
       
        $vu_id = 3;
        $c_id  = 1;
        $amount= "400.00";
        $qty   = 4;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }






    public function testPromo14(){

        Buy 2 unit from Assortment [Zwing Assort 4 ], @ Rs. 300 .

        $this->truncateDB();
        $item_1 = 'D10145';
        $item_2 = 'D10188';
        $item_3 = 'D10189';
        $item_4 = 'D10190';
        $item_5 = 'D10191';
        $item_6 = 'D10192';
        $vu_id = 3;
        $c_id  = 1;
        $amount= "900.00";
        $qty   = 6;
        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

 public function testPromo15(){

        Buy 2 unit from Assortment [Zwing Assort 4 ], @ Rs. 300 .

        $this->truncateDB();
        $item_1 = 'D10145';
        $item_2 = 'D10188';
        $item_3 = 'D10189';
        
        $vu_id = 3;
        $c_id  = 1;
        $amount= "999.00";
        $qty   = 3;
        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }







    public function testPromo16(){

        //Buy 2 unit from Assortment [Zwing Assort 5 ], Get 1 unit from Assortment [Zwing Assort 5] free.

        $this->truncateDB();
        $item_1 = 'D6473';
        $item_2 = 'D6474';
        $item_3 = 'D6475';
        $item_4 = 'D6476';
        $item_5 = 'D6477';
        $item_6 = 'D6478';
        $item_7 = 'D6479';
        $vu_id = 3;
        $c_id  = 1;
        $amount= "2796.00";
        $qty   = 7;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }//End 









    public function testPromo17(){
        //Buy 3 unit from Assortment [Zwing Assort 6 ], Get 1 unit from Assortment  [Zwing Assort 6]  with 10% discount on RSP.  [Offer not Valid for Items which are in exclusion list of all assortment.] 

        $this->truncateDB();
        $item_1 = 'D10000';
        $item_2 = 'D10001';
        $item_3 = 'D10002';
        $item_4 = 'D10003';
        $item_5 = 'D10004';
        $item_6 = 'D10005';
        $item_7 = 'D10006';
        $item_8 = 'D4155';
        $item_9 = 'D4156';
        $item_10='D4157';
         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "9570.20";
        $qty   = 10;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

    }//End



 public function testPromo18(){
        //Buy 3 unit from Assortment [Zwing Assort 6 ], Get 1 unit from Assortment  [Zwing Assort 6]  with 10% discount on RSP.  [Offer not Valid for Items which are in exclusion list of all assortment.]

        $this->truncateDB();
        $item_1 = 'D10000';
        $item_2 = 'D10001';
        $item_3 = 'D10000';
        
         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "3187.10";
        $qty   = 3;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

    }






    public function testPromo19(){

        // Buy 5 unit from Assortment [Zwing Assort 7 ], Get 2 unit from Assortment [Zwing Assort 7] with Rs. 50 off on RSP.  [Offer not Valid for Items which are in exclusion list of all assortment.]

        $this->truncateDB();
        $item_1 = 'D10147';
        $item_2 = 'D10368';
        $item_3 = 'D10369';
        $item_4 = 'D10370';
        $item_5 = 'D10371';
        $item_6 = 'D10372';
        
        //Exclude Item
        $item_7 = 'D2587';
        $item_8 = 'D2586';
        $item_9 = 'D2592';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "6241.00";
        $qty   = 9;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());

    }

 
    public function testPromo20(){

        //Buy 4 unit from Assortment [Zwing Assort 8 ], Get 2 unit from Assortment [Zwing Assort 8] with Rs. 200 each.

        $this->truncateDB();
        $item_1 = 'D1002';
        $item_2 = 'D1003';
        $item_3 = 'D1010';
        $item_4 = 'D1015';
        $item_5 = 'D1021';
        $item_6 = 'D1023';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "13109.00";
        $qty   = 6;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());


    } 

   
 public function testPromo21(){

        //Buy 4 unit from Assortment [Zwing Assort 8 ], Get 2 unit from Assortment [Zwing Assort 8] with Rs. 200 each.

        $this->truncateDB();
        $item_1 = 'D1002';
        $item_2 = 'D1003';
        $item_3 = 'D1002';
        $item_4 = 'D1003';
        $item_5 = 'D1010';
        $item_6 = 'D1015';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "24113.00";
        $qty   = 6;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());


    } 



 public function testPromo22(){

        //Buy 4 unit from Assortment [Zwing Assort 8 ], Get 2 unit from Assortment [Zwing Assort 8] with Rs. 200 each.

        $this->truncateDB();
        $item_1 = 'D1002';
        $item_2 = 'D1003';
        $item_3 = 'D1002';
        $item_4 = 'D1003';
        

        $vu_id = 3;
        $c_id  = 1;
        $amount= "23598.00";
        $qty   = 4;

        for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());


    } 







    public function testPromo23(){

        // Buy 7 unit from Assortment [Zwing Assort 9 ], Get 3 unit from Assortment [Zwing Assort 9] with Rs. 300.
        $this->truncateDB();
        $item_1 = 'D1005';
        $item_2 = 'D1005';
        $item_3 = 'D1005';
        $item_4 = 'D1005';
        $item_5 = 'D1005';
        $item_6 = 'D1005';
        $item_7 = 'D1005';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "1216.00";
        $qty   = 7;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    } 


    public function testPromo24(){

        // Buy 7 unit from Assortment [Zwing Assort 9 ], Get 3 unit from Assortment [Zwing Assort 9] with Rs. 300.
        $this->truncateDB();
        $item_1 = 'D1005';
        $item_2 = 'D1005';
        $item_3 = 'D1005';
        $item_4 = 'D1005';
        $item_5 = 'D1005';
        $item_6 = 'D1005';
        $item_7 = 'D1005';
        $item_8 = 'D1005';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "1445.00";
        $qty   = 8;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    } 


    public function testPromo25(){

        // Buy 3 - 4 unit from Assortment [BUY 1 10% BUY 2 20% BUY 3 30% ], Get 40 Rs. Off on MRP.

        $this->truncateDB();
        $item_1 = 'D5130';
        $item_2 = 'D5131';
        $item_3 = 'D5132';
        $item_4 = 'D5133';
        $item_5 = 'D5134';
        $item_6 = 'D5135';
        $item_7 = 'D5136';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "5513.00";
        $qty   =7;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }


public function testPromo26(){

        // Buy 3 - 4 unit from Assortment [BUY 1 10% BUY 2 20% BUY 3 30% ], Get 40 Rs. Off on MRP.

        $this->truncateDB();
        $item_1 = 'D5130';
        $item_2 = 'D5131';
        $item_3 = 'D5132';
        $item_4 = 'D5133';
        $item_5 = 'D5134';
       

        $vu_id = 3;
        $c_id  = 1;
        $amount= "4155.00";
        $qty   =5;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }


public function testPromo27(){

        // Buy 3 - 4 unit from Assortment [BUY 1 10% BUY 2 20% BUY 3 30% ], Get 40 Rs. Off on MRP.

        $this->truncateDB();
        $item_1 = 'D5130';
        $item_2 = 'D5131';
        $item_3 = 'D5132';
        $item_4 = 'D5133';
        
       

        $vu_id = 3;
        $c_id  = 1;
        $amount= "3456.00";
        $qty   =4;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }


   public function testPromo28(){

        // Buy 3 - 4 unit from Assortment [BUY 1 10% BUY 2 20% BUY 3 30% ], Get 40 Rs. Off on MRP.

        $this->truncateDB();
        
        $item_1 = 'D5134';
        $item_2 = 'D5135';
        $item_3 = 'D5136';

        $vu_id = 3;
        $c_id  = 1;
        $amount= "2057.00";
        $qty   =3;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }







   

public function testPromo29(){

        // Buy 3 unit from Assortment [Zwing Priority Assort ], Get 10 % Off on MRP.

        $this->truncateDB();
        $item_1 = 'D1129';
        $item_2 = 'D1129';
        $item_3 = 'D1129';
        $item_4 = 'D1129';
        $item_5 = 'D1129';
        $item_6 = 'D1129';

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "9550.00";
        $qty   = 6;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }
    

public function testPromo30(){

        // Buy 3 unit from Assortment [Zwing Priority Assort ], Get 10 % Off on MRP.

        $this->truncateDB();
        $item_1 = 'D1128';
        $item_2 = 'D1128';
        $item_3 = 'D1128';
        $item_4 = 'D1129';
        $item_5 = 'D1129';
        $item_6 = 'D1129';

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "15000.00";
        $qty   = 6;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }
    

    public function testPromo31(){

        // Buy 3 unit from Assortment [Zwing Priority Assort ], Get 10 % Off on MRP.

        $this->truncateDB();
        $item_1 = 'D1128';
        $item_2 = 'D1129';
        $item_3 = 'D1129';
        $item_4 = 'D1129';

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "6300.00";
        $qty   = 4;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

    public function testPromo32(){

        // Zwing Priority Promo : Flat 30% Discount - Priority 30

        $this->truncateDB();
        $item_1 = 'D1128';
        $item_2 = 'D1128';
        $item_3 = 'D1129';
        $item_4 = 'D1129';
         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "7000.00";
        $qty   = 4;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

public function testPromo33(){

        // Zwing Priority Promo : Flat 30% Discount - Priority 30

        $this->truncateDB();
        $item_1 = 'D1128';
        $item_2 = 'D1128';
        $item_3 = 'D1128';
        $item_4 = 'D1128';
        $item_5 = 'D1128';
         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "11400.00";
        $qty   = 5;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

public function testPromo34(){

        // Zwing Priority Promo : Flat 30% Discount - Priority 30

        $this->truncateDB();
        $item_1 = 'D1129';
        $item_2 = 'D1129';
        $item_3 = 'D1129';
        $item_4 = 'D1129';
        $item_5 = 'D1129';
        $item_6 = 'D1129';
         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "9550.00";
        $qty   = 6;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

    public function testPromo35(){

        //  buy 5 unit from assortment 7 get 2 unit from assortment 7 rs 50 off on RSP

        $this->truncateDB();
        $item_1 = 'D10147';
        $item_2 = 'D10147';
        $item_3 = 'D10147';
        $item_4 = 'D10147';
        $item_5 = 'D10147';

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "1348.00";
        $qty   = 5;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }



    public function testPromo36(){

        //  buy 5 unit from assortment 7 get 2 unit from assortment 7 rs 50 off on RSP

        $this->truncateDB();
        $item_1 = 'D10147';
        $item_2 = 'D10147';
        $item_3 = 'D10147';
        $item_4 = 'D10147';
        $item_5 = 'D10147';
        $item_6 = 'D10374';
        $item_7 = 'D10374';
        $item_8 = 'D10374';
        $item_9 = 'D10374';
        $item_10 = 'D10374';

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "4990.00";
        $qty   = 10;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

    public function testPromo37(){

        //  buy 5 unit from assortment 7 get 2 unit from assortment 7 rs 50 off on RSP

        $this->truncateDB();
        $item_1 = 'D10147';
        $item_2 = 'D10374';
        $item_3 = 'D10371';
        $item_4 = 'D10377';
        $item_5 = 'D10380';
       

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "3445.00";
        $qty   = 5;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }


    public function testPromo38(){

        //  buy 5 unit from assortment 7 get 2 unit from assortment 7 rs 50 off on RSP

        $this->truncateDB();
        $item_1 = 'D10147';
        $item_2 = 'D10147';
        $item_3 = 'D10147';
        $item_4 = 'D10374';
        $item_5 = 'D10374';
       

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "3445.00";
        $qty   = 5;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }

    public function testPromo39(){

        //  buy 5 unit from assortment 7 get 2 unit from assortment 7 rs 50 off on RSP

        $this->truncateDB();
        $item_1 = 'D10371';
        $item_2 = 'D10371';
        $item_3 = 'D10371';
        $item_4 = 'D10377';
        $item_5 = 'D10377';
        $item_6 = 'D10380';
       

         

        $vu_id = 3;
        $c_id  = 1;
        $amount= "2193.35";
        $qty   = 6;

         for($i=1; $i <= $qty; $i++){
            $data=array('api_token'=>$this->api_token,
                        'v_id' => $this->vendor_id,
                        'store_id'=>$this->store_id,
                        'barcode'=>${"item_$i"},  
                        'c_id'=>$c_id,
                        'scan'=>TRUE,
                        'trans_from'=>$this->trans_from,
                        'vu_id'=>$vu_id);
            $response =  $this->call('post','/product-details',$data);  //Call request
        }//End loop

        $this->assertEquals('{"status":"add_to_cart","message":"Product was successfully added to your cart.","total_qty":'.$qty.',"total_amount":"'.$amount.'"}',$this->response->getContent());
    }


    public function testPromoN(){
        $this->get('/'); 
        $this->assertEquals(
            'Lumen (5.4.7) (Laravel Components 5.4.*)', $this->response->getContent());
    }*/


    
}
