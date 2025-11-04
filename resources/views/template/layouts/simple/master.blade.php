<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Market Audit project by Raj Singh Rajput, designed for efficient market audit analysis and audit management.">
    <meta name="keywords" content="market audit, market audit management, analysis, reporting, Raj Singh Rajput">
    <meta name="author" content="Raj Singh Rajput">
    <link rel="icon" href="{{asset('assets/images/favicontnbt.png')}}" type="image/x-icon">
    <link rel="shortcut icon" href="{{asset('assets/images/favicontnbt.png')}}" type="image/x-icon">
    <title>Office Automation</title>
    <!-- Google font-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&amp;display=swap"
        rel="stylesheet">

    @include('template.layouts.simple.css')
    @yield('style')
</head>
<body>
<div class="loader-wrapper">
    <div class="loader loader-1">
        <div class="loader-outter"></div>
        <div class="loader-inner"></div>
        <div class="loader-inner-1"></div>
    </div>
</div>
<!-- loader ends-->
<!-- tap on top starts-->
<div class="tap-top"><i data-feather="chevrons-up"></i></div>
<!-- tap on tap ends-->
<!-- page-wrapper Start-->
<div class="page-wrapper compact-wrapper" id="pageWrapper">


    <!-- Page Header Start-->
    @include('template.layouts.simple.header')
    <!-- Page Body Start-->
    <div class="page-body-wrapper">
        <!-- Page Sidebar Start-->
        @include('template.layouts.simple.sidebar')
        <!-- Page Sidebar Ends-->
            <!-- Container-fluid starts-->
            @yield('content')

        <!-- footer start-->
        @include('template.layouts.simple.footer')
    </div>
</div>
@include('template.layouts.simple.script')
@yield('scripts')
</body>
</html>
