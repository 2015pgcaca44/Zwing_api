@extends('layouts.mail')
@php
     $imagepath  = env('APP_URL');
@endphp
	@section('title', 'Invoice')
	@section('content')

		<img style="width:100%:height:auto;margin-top:10px;margin-bottom:10px" src="{{$imagepath}}/images/email_images/thankYou.jpg" alt="Thank you for beating the queue" > 
        <div style="background-color:rgb(240,240,240);">
            <div style="width:30%;float:left;margin-top:40px;padding:5px">
                <p style="margin:0;font-size:15px;color:rgb(150,150,150);">ORDER ID</p>
                <p style="margin:0;font-size:20px;color:rgb(85,85,85);word-wrap: break-word;">{{$order->doc_no}}</p>
            </div>
            <div style="width:30%;float:left;margin-top:40px;padding:5px">
                <p style="margin:0;color:rgb(150,150,150);">PAYMENT MODE</p>
                <p style="margin:0;font-size:20px;color:rgb(85,85,85);">{{$order->trans_type}}</p>
            </div>
            <div style="width:30%;float:left;padding:5px">
                <p style="font-size:35px;color:rgb(85,85,85);">{{$order->amount}}</p>
            </div>
            
            <div style="clear:both"> </div>
        </div>
        <table>
	        <tr style="background-color:rgb(191,191,191);color:#ffff;font-size:20px">
	            <th style="padding:5px;text-align:left">Item</th>
	            <th style="padding:5px;">Quanity</th>
	            <th style="padding:5px;">Price</th>
	        </tr>
	        @php
	        	$subtotal = $order->amount;
	        	$qty = 1;
	        	$discount = 0;
	        @endphp
	        @if(!empty($carts) > 0)
	        @foreach($carts as $cart)
	        	@php
	        		$subtotal += @$cart->amount; 
	        		//$discount += $cart->discount;
	        		$qty += @$cart->qty;
	        	@endphp
	        <tr style="font-size:18px">
	            <td style="padding:5px;word-wrap: break-word;padding:5px">{{@$cart->item_name}}</td>
	            <td style="padding:5px;text-align:center;padding:5px">{{@$cart->qty}}</td>
	            <td style="padding:5px;text-align:right;padding:5px">{{@$cart->subtotal}} </td>
	        </tr>
	        @endforeach
	        @endif
	       
	        <tr style="background-color:rgb(191,191,191);color:#ffff;font-size:20px">
	            <th style="padding:5px;">SUBTOTAL</th>
	            <th style="padding:5px;">{{$qty}}</th>
	            <th style="padding:5px;">{{$subtotal}}</th>
	        </tr>
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Discount</td>
	            <td style="padding:5px;text-align:center"></td>
	            <td style="padding:5px;text-align:right">- {{$order->discount}}</td>
	        </tr>
	        @if($order->employee_discount > 0.00)
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Employee Discount</td>
	            <td style="padding:5px;text-align:center"></td>
	            <td style="padding:5px;text-align:right">- {{$order->employee_discount}}</td>
	        </tr>
	        @endif
	        @if($order->bill_buster_discount > 0.00)
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Bill Buster</td>
	            <td style="padding:5px;text-align:center"></td>
	            <td style="padding:5px;text-align:right">- {{$order->bill_buster_discount}}</td>
	        </tr>
	        @endif
	       
	        <tr style="background-color:rgb(111,111,111);color:#ffff;font-size:20px">
	            <th style="padding:5px;">TOTAL AMOUNT</th>
	            <th style="padding:5px;"></th>
	            <th style="padding:5px;">{{$order->total}}</th>
	        </tr>
        </table>

    @endsection