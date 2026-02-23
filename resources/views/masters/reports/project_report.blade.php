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
                                                <form method="POST" action="{{route('project-distributor-report')}}"
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
                                                                <option value="{{$project->id}}">{{$project->project_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('project_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 ">
                                                        <label class="form-label" for="template_name_id">Select Data Template</label>
                                                        <select class="form-select" id="template_name_id"
                                                                name="template_name_id" required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($template_names as $template_name)
                                                                <option value="{{$template_name->id}}"
                                                                        @if(old('company_id') == $template_name->id) selected @endif>{{$template_name->template_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('template_name_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 ">
                                                        <label class="form-label" for="row_id">Distributor</label>
                                                        <select class="form-select" id="row_id"
                                                                name="row_id" required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('row_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 d-none">
                                                        <div class="form-check checkbox checkbox-primary mb-0 ">
                                                            <input class="form-check-input" name="user_details" id="user_details" type="checkbox">
                                                            <label class="form-check-label" for="user_details">Auditors Details Required</label></div>
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 d-none">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="date_required" id="date_required" type="checkbox">
                                                            <label class="form-check-label" for="date_required">Date Required </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4 d-none">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="time_required" id="time_required" type="checkbox">
                                                            <label class="form-check-label" for="time_required">Time Required</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4 col-4">
                                                        <label class="form-label" for="data_get_helper">Project Data<span class="txt-danger"></span></label>
                                                        <select name="data_get_helper" class="form-select" id="data_get_helper" required="">
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
                                                    <div class="col-xl-4 col-4">
                                                        <label class="form-label" for="verification_helper">Outlet Verification Data<span class="txt-danger"></span></label>
                                                        <select name="verification_helper" class="form-select" id="verification_helper" required="">
                                                            <option value="all">All Data</option>
                                                            <option value="pending">Only Pending</option>
                                                            <option value="approved">Only Approved</option>
                                                            <option value="rejected">Only Rejected</option>
{{--                                                            <option value="rejected">Only Rejected</option>--}}
                                                        </select>
                                                        @error('verification_helper')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-12 text-end">
                                                        <button name="get_report" class="btn btn-primary">Get Report</button>
{{--                                                        <button name="get_mail" class="btn btn-primary">Get Mail</button>--}}
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="modal_con">
                                            <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog" aria-labelledby="errorMessage_modal" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-body">
                                                            <div class="modal-toggle-wrapper">
                                                                <ul class="modal-img">
                                                                    <li> <img src="{{url('assets/images/gif/danger.gif')}}" alt="error"></li>
                                                                </ul>
                                                                <h4 class="text-center pb-2">Ohh! Warning!</h4>
                                                                <p class="text-center" id="error_message"></p>
                                                                <button class="btn btn-secondary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
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
        </div>
    </div>

@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            @if(session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            const row_id_select = $("#row_id").select2();
            const project_id_select = $("#project_id").select2();
            const template_name_id_select = $("#template_name_id").select2();
            function render_headers(main_headers, sub_headers){
               const rowIds = $("#row_id");
               rowIds.empty();
                rowIds.append($('<option>').text('Select Distributor').val(''));
                $.each(main_headers, function (index, main_header_info){
                    const sub_header_info = sub_headers[index];
                    // console.log(main_header_info.value . sub_header_info.value)
                    rowIds.append($('<option>').text(main_header_info.value + ' - ' + sub_header_info.value).val(main_header_info.id));
                })
            }
            $("#template_name_id").change(function (e) {
                const template_name_id = $(this).val();
                const project_id = $("#project_id").val();
                if (template_name_id) {
                    $.ajax({
                        url: '{{route('projectTemplateProjectReport.info')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "t_id": template_name_id,
                            "p_id": project_id
                        },
                        success: function (response) {
                            console.log(response);
                            if (response.message == "success") {
                                // this is for the main headers
                                render_headers(response.main_headers, response.sub_headers);

                                const pro_temp = response.projectTemplate;
                                if(pro_temp.activity_group_name_id_or_activity_id ){
                                    $("#activity_id").val('');
                                    $("#activity_group_name_id").val('');
                                    let make_select = pro_temp.activity_group_name_id_or_activity_id;

                                    if(pro_temp.activityType==0){
                                        $("#activites_display").removeClass('d-none');
                                        $("#group_display").addClass('d-none');
                                        activitySelectFunc(make_select)
                                    }
                                    else{
                                        $("#activites_display").addClass('d-none');
                                        $("#group_display").removeClass('d-none');
                                        $("#activity_group_name_id").val(make_select).trigger('change')
                                    }

                                }else{
                                    $("#error_message").html("Project Template Name Mapping is not completed")
                                    $("#errorMessage_modal").modal('show');
                                }
                                // render_heads(pro_temp.get_template.get_template_heads);
                            }
                        }
                    })
                }
            })



            function render_templates(projectTemplates){
                let template_name_select = $("#template_name_id");
                template_name_select.empty();
                $.each(projectTemplates, function (index,projectTemplate){
                    if(projectTemplate.is_master == 1){
                    template_name_select.append($('<option>').text(projectTemplate.get_template.template_name).val(projectTemplate.get_template.id));
                    }
                });
                $("#template_name_id").trigger('change')
            }

            $("#project_id").change(function (e){
                const selectedProjectId = $(this).val();
                if(selectedProjectId){
                    $.ajax({
                        url:'{{route('get_project_info')}}',
                        type:"POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id":selectedProjectId
                        },
                        success:function (response){
                            console.log(response);
                            if(response.message=="Success"){
                                // let templateId = response.projectInfo.template_name_id;
                                render_templates(response.projectTemplateNames);

                            }
                        }
                    })
                }
            })
            $("#template_name_head_id").change(function (e) {
                const template_name_head_id = $(this).val();
                const project_id = $("#project_id").val();
                const template_name_id = $("#template_name_id").val();
                if (project_id) {
                    if (template_name_head_id) {
                        $.ajax({
                            url: '{{route('project.templateHead_values')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": template_name_head_id,
                                "project_id": project_id,
                                "template_name_id": template_name_id,
                            },
                            success: function (response) {
                                console.log(response)
                                if (response.message == "Success") {
                                    render_head_values(response.related_head_values);
                                }
                            }
                        })
                    }
                }
            })

        })
    </script>
@endsection
