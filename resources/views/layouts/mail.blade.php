<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style type="text/css">
.container {
    width : 600px;
    margin : auto;
    font-family:  Arial, Helvetica, sans-serif;
}

body {
    background-color:#ffff;
}

table {
    width: 100%;
}
</style>
</head>
<title>Zwing - @yield('title')</title>
@php
     $imagepath  = env('APP_URL');
@endphp
<body>
    <div class="container">
    <center>
        <img style="width:100%:height:auto" src="{{$imagepath}}/images/email_images/header.png" alt="Zwing"> 
        
        @yield('content')
        
        <div style="margin-top:20px;margin-bottom:20px" >
            <div style="width:30%;float:left;">
                <img style="width:100%:height:auto" src="{{$imagepath}}/images/email_images/helpSupport.png" alt="Support"> 
            </div>
            
            <div style="width:30%;float:left;">
                <img style="width:100%:height:auto" src="{{$imagepath}}/images/email_images/contactUs.png" alt="Contact US"> 
            </div>
            
            <div style="width:30%;float:left;">
                <img style="width:100%:height:auto" src="{{$imagepath}}/images/email_images/facebook.png" alt="Follow Us"> 
            </div>
            
            <div style="clear:both"> </div>
        </div>
        
        <img style="width:100%:height:auto" src="{{$imagepath}}/images/email_images/footer.png" > 

    
    </center>
    
    </div>

</body>

</html>