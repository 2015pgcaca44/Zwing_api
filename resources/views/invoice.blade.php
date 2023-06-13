@extends('layouts.app')

	@section('title', 'Invoice')
	@section('content')

		<img style="width:100%:height:auto;margin-top:10px;margin-bottom:10px" src="images/message.png" > 
        <div style="background-color:rgb(240,240,240);">
            <div style="width:30%;float:left;margin-top:40px">
                <p style="margin:0;font-size:15px;color:rgb(150,150,150);">ORDER ID</p>
                <p style="margin:0;font-size:20px;color:rgb(85,85,85);">OD123546987</p>
            </div>
            <div style="width:30%;float:left;margin-top:40px">
                <p style="margin:0;color:rgb(150,150,150);">PAYMENT MODE</p>
                <p style="margin:0;font-size:20px;color:rgb(85,85,85);">Net Banking</p>
            </div>
            <div style="width:30%;float:left">
                <p style="font-size:35px;color:rgb(85,85,85);">999999.9</p>
            </div>
            
            <div style="clear:both"> </div>
        </div>
        <table>
	        <tr style="background-color:rgb(191,191,191);color:#ffff;font-size:20px">
	            <th style="padding:5px;text-align:left">Item</th>
	            <th style="padding:5px;">Quanity</th>
	            <th style="padding:5px;">Price</th>
	        </tr>
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Product info 1 (size) </td>
	            <td style="padding:5px;text-align:center">3 </td>
	            <td style="padding:5px;text-align:right">299.25 </td>
	        </tr>
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Product info 1 (size) </td>
	            <td style="padding:5px;text-align:center">3 </td>
	            <td style="padding:5px;text-align:right">299.25 </td>
	        </tr>
	        <tr style="background-color:rgb(191,191,191);color:#ffff;font-size:20px">
	            <th style="padding:5px;">SUBTOTAL</th>
	            <th style="padding:5px;">9</th>
	            <th style="padding:5px;">99999.9</th>
	        </tr>
	        <tr style="font-size:18px">
	            <td style="padding:5px;">Discount</td>
	            <td style="padding:5px;text-align:center">- 50 </td>
	            <td style="padding:5px;text-align:right">299.25 </td>
	        </tr>
	        <tr style="background-color:rgb(111,111,111);color:#ffff;font-size:20px">
	            <th style="padding:5px;">TOTAL AMOUNT</th>
	            <th style="padding:5px;"></th>
	            <th style="padding:5px;">99999.9</th>
	        </tr>
        </table>

    @endsection