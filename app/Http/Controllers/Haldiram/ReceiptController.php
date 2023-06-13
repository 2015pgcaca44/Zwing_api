<?php

namespace App\Http\Controllers\Haldiram;

use App\Http\Controllers\Controller;
use App\Http\Controllers\VendorSettingController;

use App\Order;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\LabelAlignment;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Response\QrCodeResponse;

class ReceiptController extends Controller
{
    
    public function sms_order_receipt($encoded_data){
        
        $par = base64_decode($encoded_data);
        $data = explode('/', $par);
        //dd($data);

        $c_id = $data[0];
        $v_id = $data[1];
        $store_id = $data[2];
        $order_id = $data[3];

        $order = Order::where('user_id',$c_id)->where('v_id',$v_id)->where('store_id',$store_id)->where('order_id', $order_id)->first();

        if($order->verify_status == '0'){
            $qrCode = new QrCode($order_id);
            $qrCode->setSize(250);

            $path =  storage_path();
            $file_name = $c_id.'_qrcode.png';
            $path_with_file_name = "images/qr/".$file_name;

            //$path = __DIR__.'/qrcode.png';

            $qrCode->writeFile($path_with_file_name);

          
            // Directly output the QR code
            //header('Content-Type: '.$qrCode->getContentType());
            //echo $qrCode->writeString();


            $html ='<!DOCTYPE html>
            <html>
                <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                </head>
                <title></title>
                <style type="text/css">
                .container {
                    max-width : 400px;
                    margin:auto;
                    margin : auto;
                   #font-family: Arial, Helvetica, sans-serif;
                    font-family: courier, sans-serif;
                    font-size: 14px;
                }

                .myButton {
                    background-color:#50ef7a;
                    border:1px solid #18ab29;
                    border-radius: 15px;
                    color:#ffffff;
                    font-family:Arial;
                    font-size:17px;
                    padding:16px 31px;
                }

                body {
                    background-color:#ffff;

                }
                </style>
                <body>
                    <div class="container">
                    <center>
                    <h1> Please Show this Qr code to staff for verification </h1>
                    <h4> Rs '.$order->total.'</h4>
                       <img src="'. env('API_URL').'/'.$path_with_file_name.'" >
                    <h4> Order Id '.$order_id.'</h4>
                    <p id="loader">  </p>
                    <input class="myButton" onclick="checkVerify()" type="button" name="submit" value="Done">
                    </center>
                    </div>
                </body>

                <script>
                function checkVerify() {
                    document.getElementById("loader").innerHTML = "Please Wait....";
                    location.reload();
                }
                </script>
            </html>'
            ;

            echo $html;
        
        }else if($order->verify_status_guard =='0'){


            $html ='<!DOCTYPE html>
            <html>
                <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                </head>
                <title></title>
                <style type="text/css">
                .container {
                    max-width : 400px;
                    margin:auto;
                    margin : auto;
                   #font-family: Arial, Helvetica, sans-serif;
                    font-family: courier, sans-serif;
                    font-size: 14px;
                }

                input[type=text],input[type=password], select {
                    width: 100%;
                    padding: 12px 20px;
                    margin: 8px 0;
                    display: inline-block;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    box-sizing: border-box;
                }

                .myButton {
                    background-color:#50ef7a;
                    border:1px solid #18ab29;
                    border-radius: 15px;
                    color:#ffffff;
                    font-family:Arial;
                    font-size:17px;
                    padding:16px 31px;
                }

                .message {
                    background-color:#f44336;
                    font-family:Arial;
                    font-size:17px;
                    border-radius: 7px;
                    color:#ffffff;
                    padding:16px 31px;

                }

                body {
                    background-color:#ffff;

                }
                </style>
                <body>
                    <div class="container">
                    <center>
                    <h1> Please Move to exit and show this screen to Guard </h1>
                    
                        <p id="message" class="message" style="display:none"></p>
                        <p id="loader" class="loader" ></p>
                        <input type="password" placeholder="Ask guard to enter code" id="guard_code" name="guard_code">
                       
                        <input class="myButton" onclick="verifyByGuard()" type="button" name="submit" value="submit">
                   
                    </center>
                    </div>
                </body>

                <script>
                    function verifyByGuard() {
                      var guard_code = document.getElementById("guard_code").value;
                      var xhttp = new XMLHttpRequest();
                      xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("loader").innerHTML = "Please Wait....";
                            document.getElementById("message").innerHTML = "";
                            var response = JSON.parse(this.responseText);
                            
                            if(response.status == "fail"){
                                document.getElementById("message").innerHTML = response.message;
                                document.getElementById("message").style.display = "block";
                                document.getElementById("loader").innerHTML = "";
                                //x.style.display = "block";
                            }else{
                                location.reload();
                            }
                          
                        }
                      };
                      xhttp.open("POST", "'.env('API_URL').'/vendor/verify-order-by-guard?order_id='.$order_id.'&v_id='.$v_id.'&store_id='.$store_id.'&guard_code="+guard_code, true);
                      xhttp.send();
                    }
                </script>
            </html>'
            ;

            echo $html;

        
        }else{

            $cart_c = new CartController;
            return $cart_c->order_receipt($c_id,$v_id , $store_id, $order_id);    
        }
        
    }



}