<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
{{-- <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet"> --}}
<title>Sync Summary</title>
</head>
@php 
$date = date('d F Y', strtotime($date));
$year = date('Y');
$imagePath = env('APP_URL').'/img/Logo.png';
@endphp
<body style="background-color: #f4f4f4;">
	<center>
		<table style="width: 75%; border-radius: 8px; border-collapse: collapse;">
			<tr>
				<td style="padding: 0.50rem;">&nbsp;</td>
			</tr>
			<tr style="background-color: #fff;">
				<td>
					<table style="width: 100%; margin-bottom: 30px; border-bottom: 1px solid rgba(0, 0, 0, 0.125);">
						<tr>
							<th width="100">
								<img src="{{ $message->embed($imagePath) }}" style="margin-top: 10px;" />
							</th>
							<th style="font-family: 'Helvetica'; color: #0b6fd3; font-size: 18px; padding-right: 100px;">
								Sync Summary - {{ $date }}
							</th>
						</tr>
					</table>
					<table style="width: 100%; text-align: left; vertical-align: middle !important; border-collapse: collapse; margin-bottom: 20px; border:1px solid rgba(0, 0, 0, 0.125);">
						<tr>
							<th width="100" style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #161616; font-weight: 100; text-align: center;">Organisation</th>
							<td style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; border-left: 1px solid #e0e0e0;">{{ $org_name }}</td>
						</tr>
						<tr>
							<th width="100" style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #161616; font-weight: 100; text-align: center;">Report Name</th>
							<td style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; border-left: 1px solid #e0e0e0;">{{ $report_name }}</td>
						</tr>
						<tr>
							<th width="100" style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #161616; font-weight: 100; text-align: center;">Report Date</th>
							<td style="padding: 0.50rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; border-left: 1px solid #e0e0e0;">{{ $date }}</td>
						</tr>
					</table>
					<table style="width: 100%; text-align: left; vertical-align: middle !important; border-collapse: collapse;">
						<thead style="background-color: #e0e0e0;">
							<tr>
								<th width="50" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100;">#</th>
								<th style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100;">Name</th>
								<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; text-align: center;">Total</th>
								<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; text-align: center;">Synced</th>
								<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; text-align: center;">Not Synced</th>
								<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #8d8d8d; font-weight: 100; text-align: center;">Synced Failed</th>
							</tr>
						</thead>
						<tbody style="background-color: #ffffff;">
							@foreach ($data as $key => $element)
								<tr>
									<td width="50" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #161616; font-weight: 100;">{{ $key + 1 }}</td>
									<th style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; font-weight: 100;">
										@if($element['fail'] == 0)
											<a style="color: #161616; text-decoration: none;">{{ $element['name'] }}</a>
										@else
										@php
											$requestData = [ 'v_id' => $v_id, 'code' => $element['code'], 'date' => $date, 'name' => $element['name'] ];
										@endphp
											<a target="_blank" style="color: #0b6fd3; text-decoration: none;" href="{{ getGlobalConsoleURL().'/admin/sync/manage?r='.base64_encode(urlencode(json_encode($requestData))) }}">{{ $element['name'] }}</a>
										@endif
									</th>
									<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #161616; font-weight: 100; text-align: center;">{{ $element['total'] }}</th>
									<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; font-weight: 100; text-align: center; color: #33cf6c;">{{ $element['done'] }}</th>
									<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; font-weight: 100; text-align: center; color: #8d8d8d;">{{ $element['not_synced'] }}</th>
									<th width="100" style="padding: 0.75rem; font-family: 'Verdana'; font-size: 0.75rem; color: #dc0404; font-weight: 100; text-align: center;">{{ $element['fail'] }}</th>
								</tr>
							@endforeach
						</tbody>
					</table>
				</td>
			</tr>
		</table>

		<table width="75%" style="margin: 0 auto; border-top: 1px #c0c0c0 solid; text-align: center;"> 
		<tr>
			<td>
				<p style="color: #161616; font-family: 'Verdana'; font-size: 13px;">Powered by ZWING POS Billing Software | {{ $year }}</p>
			</td>
		</tr>
		</table>
	</center>
</body>
</html>