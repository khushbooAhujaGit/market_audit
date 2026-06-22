@extends('template.layouts.simple.master')

@section('content')
    <div class="page-body">
        <div class="row justify-content-center">
            <div class="col-sm-6 col-md-8">

                <div class="card shadow-lg">
                    <div class="card-header text-center">

                        <h4 style="color:#fff !important;  margin-top: 40px !important;"></h4>OTP Verification</h4>
                    </div>

                    <div class="card-body">

                        <form id="otpMainForm" action="" method="post">
                            @csrf

                            {{-- MOBILE NUMBER INPUT --}}
                            <label class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile_number" name="mobile_number"
                                placeholder="Enter mobile number">
                            <input type="hidden" name="activity_id" id="activity_id" value="{{ $activity_id }}">
                            <input type="hidden" name="row_id" id="row_id" value="{{ $row_id }}">
                            <button type="button" class="btn btn-primary w-100 mt-3" id="sendOtpBtn">Send OTP</button>

                            {{-- OTP INPUT SECTION --}}
                            <div id="otpSection" class="mt-4 d-none">

                                <p class="text-center">Enter the OTP sent to your mobile</p>

                                <div class="d-flex justify-content-between mb-3 otp-input">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                    <input type="text" maxlength="1" class="form-control otp-box">
                                </div>

                                <button type="button" class="btn btn-success w-100" id="verifyOtpBtn">Verify OTP
                                </button>

                                <p class="text-center mt-3">
                                    <span id="resendTimer">Resend OTP in <b>30</b> sec</span>
                                    <a href="javascript:void(0)" id="resendOtp" class="d-none">Resend OTP</a>
                                </p>

                            </div>

                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Bottom Navigation (mobile only, hidden on desktop via d-md-none) --}}
    <nav class="mobile-bottom-nav d-md-none">
        <a href="{{ route('user.projects') }}" class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
            <i class="icon-folder"></i>
            Projects
        </a>
        
    </nav>
@endsection

@section('scripts')
    <style>
        .otp-box {
            width: 48px;
            height: 50px;
            font-size: 22px;
            text-align: center;
            border-radius: 8px;
        }
    </style>

    <script>
        $(document).ready(function() {

            let timerInterval;

            // ----------------------------
            // SEND OTP
            // ----------------------------
            $("#sendOtpBtn").click(function(e) {
                e.preventDefault();
                let mobile = $("#mobile_number").val();
                let activity_id = $("#activity_id").val();
                let row_id = $("#row_id").val();

                if (mobile.length < 10) {
                    Swal.fire("Error", "Enter valid mobile number", "error");
                    return;
                }

                $.post("{{ route('send_otp') }}", {
                    _token: "{{ csrf_token() }}",
                    mobile_no: mobile,
                    activity_id: activity_id,
                    row_id: row_id,
                }, function(res) {
                    console.log(res);
                    if (res.success) {
                        Swal.fire("Success", res.message, "success");
                        // Show OTP section
                        $("#otpSection").removeClass("d-none");
                        startResendTimer();
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                });
            });

            // ----------------------------
            // VERIFY OTP
            // ----------------------------
            $("#verifyOtpBtn").click(function() {

                let activity_id = $("#activity_id").val();
                let row_id = $("#row_id").val();
                let otp = "";
                $(".otp-box").each(function() {
                    otp += $(this).val();
                });

                if (otp.length !== 6) {
                    Swal.fire("Error", "Enter full 6-digit OTP", "error");
                    return;
                }

                $.post("{{ route('verify_otp') }}", {
                    _token: "{{ csrf_token() }}",
                    mobile_no: $("#mobile_number").val(),
                    mobile_otp: otp,
                    activity_id: activity_id,
                    row_id: row_id
                }, function(res) {
                    if (res.success) {
                        Swal.fire("Verified", res.message, "success").then(() => {
                            window.location.href = res.redirectUrl;
                        });
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                });

            });

            // ----------------------------
            // RESEND OTP
            // ----------------------------
            $("#resendOtp").click(function(e) {
                e.preventDefault();
                let mobile = $("#mobile_number").val();
                let activity_id = $("#activity_id").val();
                let row_id = $("#row_id").val();

                if (mobile.length < 10) {
                    Swal.fire("Error", "Enter valid mobile number", "error");
                    return;
                }

                $.post("{{ route('send_otp') }}", {
                    _token: "{{ csrf_token() }}",
                    mobile_no: mobile,
                    activity_id: activity_id,
                    row_id: row_id,
                }, function(res) {
                    console.log(res);
                    if (res.success) {
                        Swal.fire("Success", res.message, "success");
                        // Show OTP section
                        $("#otpSection").removeClass("d-none");
                        startResendTimer();
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                });
            });

            // ----------------------------
            // TIMER FUNCTION
            // ----------------------------
            function startResendTimer() {
                let seconds = 30;

                $("#resendOtp").addClass("d-none");
                $("#resendTimer").removeClass("d-none");

                timerInterval = setInterval(function() {
                    $("#resendTimer b").text(seconds);

                    if (seconds <= 0) {
                        clearInterval(timerInterval);
                        $("#resendTimer").addClass("d-none");
                        $("#resendOtp").removeClass("d-none");
                    }

                    seconds--;
                }, 1000);
            }

            // ----------------------------
            // OTP AUTO MOVE
            // ----------------------------
            $(".otp-box").on("input", function() {
                if (this.value.length === 1) {
                    $(this).next(".otp-box").focus();
                }
            });

            $(".otp-box").on("keydown", function(e) {
                if (e.key === "Backspace" && this.value === "") {
                    $(this).prev(".otp-box").focus();
                }
            });

        });
    </script>
@endsection
