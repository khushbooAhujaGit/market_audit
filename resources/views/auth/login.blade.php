<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
          content="Cuba admin is super flexible, powerful, clean &amp; modern responsive bootstrap 5 admin template with unlimited possibilities.">
    <meta name="keywords"
          content="admin template, Cuba admin template, dashboard template, flat admin template, responsive admin template, web app">
    <meta name="author" content="pixelstrap">
    <link rel="icon" href="{{url('assets/images/favicontnbt.jpeg')}}" type="image/x-icon">
    <link rel="shortcut icon" href="{{url('assets/images/favicontnbt.jpeg')}}" type="image/x-icon">

    <title>Market Audit</title>
    <!-- Google font-->
    <link href="https://fonts.googleapis.com/css?family=Rubik:400,400i,500,500i,700,700i&amp;display=swap"
          rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900&amp;display=swap"
          rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/font-awesome.css')}}">
    <!-- ico-font-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/vendors/icofont.css')}}">
    <!-- Themify icon-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/vendors/themify.css')}}">
    <!-- Flag icon-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/vendors/flag-icon.css')}}">
    <!-- Feather icon-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/vendors/feather-icon.css')}}">
    <!-- Plugins css start-->
    <!-- Plugins css Ends-->
    <!-- Bootstrap css-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/vendors/bootstrap.css')}}">
    <!-- App css-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/style.css')}}">
    <link id="color" rel="stylesheet" href="{{url('assets/css/color-1.css')}}" media="screen">
    <!-- Responsive css-->
    <link rel="stylesheet" type="text/css" href="{{url('assets/css/responsive.css')}}">
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <div class="col-xl-12 p-0">
            <div class="login-card login-dark" style="background-image: url("")">
            <div>
                {{--                    <div class="text-center"><a class="logo text-start" href="index.html"><img--}}
                {{--                                class="img-fluid for-light text-center d-flex justify-content-center"--}}
                {{--                                style="width: 400px;height:100px"--}}
                {{--                                src="{{url('assets/images/logo/'.$logo_header)}}"--}}
                {{--                                alt="looginpage"><img--}}
                {{--                                class="img-fluid for-dark"--}}
                {{--                                src="{{url('assets/images/logo/logo_dark.png')}}" alt="looginpage"></a></div>--}}
                <div class="login-main">
                    <form method="POST" class="theme-form" action="{{ route('custom_login') }}">
                        @csrf
                        <h4 class="text-center">Sign in to account</h4>
                        <p class="text-center">Enter your mobile number & password to login</p>
                        <div class="form-group">
                            <label for="mobile" class="col-md-4 col-form-label text-md-end">{{ __('Mobile Number') }}</label>
                                <input id="mobile" type="number" class="form-control @error('mobile') is-invalid @enderror" name="mobile" value="{{ old('mobile') }}" required autofocus>

                                @error('mobile')
                                <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                        </div>
                        <div class="form-group">
                            <label for="password"
                                   class="col-md-4 col-form-label">{{ __('Password') }}</label>

                            <div class="form-input position-relative">
                                <input id="password" type="password"
                                       class="form-control password_type @error('password') is-invalid @enderror"
                                       name="password" required autocomplete="current-password">

                                @error('password')
                                <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <div class="show-hide toggle_password_btn"><span class="show"> </span></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="checkbox ">
                                <input id="checkbox1" type="checkbox">
                                <input class="form-check-input" type="checkbox" name="remember"
                                       id="remember" {{ old('remember') ? 'checked' : '' }}>

                                <label class="form-check-label" for="remember">
                                    {{ __('Remember Me') }}
                                </label>
                            </div>
                            <div class="d-flex justify-content-center">

                                <button type="submit" id="login_btn" class="btn btn-primary">
                                    {{ __('Login') }}
                                </button>
                            </div>


                            @if (Route::has('password.request'))
                                <a class="btn btn-link d-flex justify-content-center d-none"
                                   href="{{ route('password.request') }}">
                                    {{ __('Forgot Your Password?') }}
                                </a>
                            @endif
                        </div>
                        <p class="mt-2   mb-0 text-center d-none">Don't have account?<a class="ms-2"
                                                                                        href="sign-up.html">Create
                                Account</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script src="{{url('assets/js/jquery.min.js')}}"></script>
<!-- Bootstrap js-->
<script src="{{url('assets/js/bootstrap/bootstrap.bundle.min.js')}}"></script>
<!-- feather icon js-->
<script src="{{url('assets/js/icons/feather-icon/feather.min.js')}}"></script>
<script src="{{url('assets/js/icons/feather-icon/feather-icon.js')}}"></script>
<!-- scrollbar js-->
<!-- Sidebar jquery-->
<script src="{{url('assets/js/config.js')}}"></script>
<!-- Plugins JS start-->
<!-- Plugins JS Ends-->
<!-- Theme js-->
<script src="{{url('assets/js/script.js')}}"></script>
<script>
    $(document).ready(function(){
        $(".toggle_password_btn").click(function(event) {
            console.log("hr");
            var passwordInput = $(".password_type");
            var passwordFieldType = passwordInput.attr("type");

            if (passwordFieldType === "password") {
                passwordInput.attr("type", "text");
                $("#showPasswordBtn").text("Hide");
            } else {
                passwordInput.attr("type", "password");
                $("#showPasswordBtn").text("Show");
            }
        })
    })
</script>
</body>
</html>
