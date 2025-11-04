@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Distributor Report</h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('project-distributor-report-new')}}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    <div class="col-xl-4 col-sm-4">
                                                        <label class="form-label" for="project_id">Select
                                                            Project</label>
                                                        <select class="form-select" id="project_id" name="project_id"
                                                                required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($projects as $project)
                                                                <option
                                                                    value="{{$project->id}}">{{$project->project_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('project_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 " id="activites_display">
                                                        <label class="form-label" for="activity_id">Select
                                                            Activity</label>
                                                        <select class="form-select" id="activity_id"
                                                                name="activity_id"
                                                                required="">
                                                        </select>
                                                        @error('activity_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-xl-4 col-sm-4 mt-5 d-none" id="distributor_check">
                                                        <div class="form-check checkbox checkbox-primary mb-0 ">
                                                            <input class="form-check-input"
                                                                   name="distributor_data_required"
                                                                   id="distributor_data_required" type="checkbox">
                                                            <label class="form-check-label"
                                                                   for="distributor_data_required">Distributor Data
                                                                Required</label></div>
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 mt-5 d-none" id="outlet_check">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="outlet_data_required"
                                                                   id="outlet_data_required" type="checkbox">
                                                            <label class="form-check-label" for="outlet_data_required">Outlet
                                                                Data Required</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4 col-4">
                                                        <label class="form-label" for="data_get_helper">Project
                                                            Data<span class="txt-danger"></span></label>
                                                        <select name="data_get_helper" class="form-select"
                                                                id="data_get_helper" required="">
                                                            <option value="all">All Data</option>
                                                            <option value="filled">Only Filled</option>
                                                            <option value="unfilled">Only Unfilled</option>
                                                        </select>
                                                        @error('data_get_helper')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <input type="hidden" name="child_exist" value="" id="child_exist">
                                                    <input type="hidden" name="master_check" value="" id="master_check">
                                                    <div class="col-12 text-end">
                                                        <button name="get_report" id="get_report" class="btn btn-primary">Get Report
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="modal_con">
                                            <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog"
                                                 aria-labelledby="errorMessage_modal" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-body">
                                                            <div class="modal-toggle-wrapper">
                                                                <ul class="modal-img">
                                                                    <li><img
                                                                            src="{{url('assets/images/gif/danger.gif')}}"
                                                                            alt="error"></li>
                                                                </ul>
                                                                <h4 class="text-center pb-2">Ohh! Warning!</h4>
                                                                <p class="text-center" id="error_message"></p>
                                                                <button class="btn btn-secondary d-flex m-auto"
                                                                        type="button" data-bs-dismiss="modal">Close
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="modal fade" id="processingModal" tabindex="-1" aria-labelledby="processingModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4 text-center p-5">
                                                        <div class="d-flex justify-content-center mb-4">
                                                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                                                                <span class="visually-hidden">Loading...</span>
                                                            </div>
                                                        </div>
                                                        <h4 class="fw-semibold text-dark mb-2" id="processingModalLabel">Processing<span
                                                                id="processingDots">.</span></h4>
                                                        <p class="text-secondary mb-0">{{!empty($message) ? $message : 'Please wait while we analyze and export your data.'}}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>

        $(document).ready(function () {
            @if(session()->has('error'))
            Swal.fire({
                position: "top-center",
                icon: "warning",
                title: "{{ session('error') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            $("#activity_id").select2();
            $("#project_id").select2();

            $("#project_id").change(function (e) {
                const selectedProjectId = $(this).val();
                $("#activity_id").html('');
                if (selectedProjectId) {
                    $.ajax({
                        url: '{{route('get_project_activity')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "project_id": selectedProjectId
                        },
                        success: function (response) {
                            // console.log(response);
                            if (response.status) {
                                let activitySelect = $("#activity_id");
                                let all_activites = response.activities;
                                console.log(all_activites);
                                $.each(all_activites, function (index, activity) {
                                    activitySelect.append($(`<option data-master="${activity.activityType}">`).text(activity.activity_name).val(activity.id));
                                })
                                $("#activity_id").trigger('change')
                                if (response.isChildTemplateAvailable) {
                                    $('#child_exist').val(1);
                                }
                            }
                        }
                    })
                }
            });

            $('#activity_id').on('change', function () {
                let data_master = $(this).find(':selected').data('master');
                if (data_master == 0) {
                    $('#distributor_check').removeClass('d-none');
                    $('#outlet_check').addClass('d-none');
                } else {
                    if ($('#child_exist').val() == 1) {
                        $('#distributor_check').addClass('d-none');
                        $('#outlet_check').removeClass('d-none');
                    }
                }
            });

            $('#get_report').on('click', function(){
                $('#processingModal').modal('show');
            });

        })
    </script>
@endsection
