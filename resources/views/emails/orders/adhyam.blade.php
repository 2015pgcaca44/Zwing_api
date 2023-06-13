<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
	<title>Mailer</title>
	<style type="text/css">
		body{
			margin: 0;
			font-family: 'Roboto', sans-serif;
			padding: 0;
		}
		table h1{
			font-weight: 400;
			font-size: 32px;
			margin: 0;
		}
		table h5{
			font-size: 18px;
			color: #606060;
			letter-spacing: 1px;
			font-weight: 400;

		}
		table h5 span{
			color: #000;
			letter-spacing: 1px;
			font-size: 18px;	
			font-weight: 400;

		}
		table p{
			color: #808080;
		}
	</style>
</head>
@php
     $imagepath  = env('API_URL');
@endphp
<body>
	<table  width="100%">
		<tr>
			<td>
				<table width="100%" style="background-color: #c6a474;">
					<tr>
						<td>
							<table width="80%" style="margin: 0 auto;">
								<tr>
									<td>
										<img src="{{$imagepath}}/Store-logo@2x.png" alt="">
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td>
				<table width="100%" style="padding: 50px 0px;">
					<tr>
						<td>
							<table width="80%" style="margin: 0 auto;">
								<tr>
									<td>
										<h1>Thank you for shopping!</h1>
									</td>
								</tr>
								<tr>
									<td>
										<h5>Dear customer, <br><br>
											We hope you enjoyed your recent purchase worth  <span>₹{{$order->total}}</span> on <span>{{formatDate($order->created_at)}}</span> at <span>{{$order->store->name}},{{$order->store->address1}} {{$order->store->address2}},{{$order->store->state}}</span> We look forward to you shopping with us again.</h5>
											<h5>A PDF copy of the invoice is attached with this e-mail, you may save it for future reference.</h5>
										</td>
									</tr>
								</table>
								<table width="80%" style="margin: 0 auto; border-top: 1px #c0c0c0 solid;"> 
								<tr>
									<td>
										<p>Powered by ZWING POS Billing Software | 2020</p>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>					
				</td>
			</tr>
		</table>
	</body>
	</html>