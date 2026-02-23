@extends('template.layouts.simple.master')
@section('style')
    <style>
        .loader-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            /* Semi-transparent black backdrop */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1050;
            /* Higher than Bootstrap modal (default is 1040) */
        }

        .large-spinner {
            width: 5rem;
            /* Adjust size as needed */
            height: 5rem;
            border-width: 0.4rem;
            /* Optional: Increase border thickness */
        }
    </style>
@endsection
@section('content')
    <div class="loader-backdrop d-none">
        <div class="spinner-border text-light large-spinner" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Add Data Project Template <a href="{{route('templateName.list')}}">
                                    <button class="btn btn-primary float-end">View Data Templates</button>
                                </a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('projectData.store')}}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    <div class="col-xl-6 col-sm-6 d-none">
                                                        <label class="form-label" for="company_id">Select
                                                            Company</label>
                                                        <select class="form-select" id="company_id" name="company_id">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($companies as $company)
                                                                <option value="{{$company->id}}"
                                                                        @if(old('company_id') == $company->id) selected @endif>{{$company->company_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('company_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6 d-none">
                                                        <label class="form-label" for="zone_id">Select Zone</label>
                                                        <select class="form-select" id="zone_id" name="zone_id">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('zone_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6 d-none">
                                                        <label class="form-label" for="unit_id">Select Unit</label>
                                                        <select class="form-select" id="unit_id" name="unit_id">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('unit_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
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
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="template_name_id">Select Data
                                                            Template</label>
                                                        <select class="form-select" id="template_name_id"
                                                                name="template_name_id" required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($data_templates as $data_template)
                                                                <option value="{{$data_template->id}}"
                                                                        @if(old('company_id') == $data_template->id) selected @endif>{{$data_template->template_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('template_name_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-12">
                                                        <label class="form-label" for="data_excel">Data Excel File<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="data_excel" name="data_excel"
                                                               type="file" required="" placeholder="">
                                                        @error('data_excel')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    {{--                                                <div class="col-12 text-end">--}}
                                                    {{--                                                    <button class="btn btn-primary">Upload Data</button>--}}
                                                    {{--                                                </div>--}}

                                                    <div class="col-12 text-end">
                                                        <button id="uploadBtn" name="upload_data"
                                                                class="btn btn-primary" >Upload Data
                                                        </button>
                                                        <button id="uploadDataJob" name="upload_with_job"
                                                                class="btn btn-primary">Upload Data With Jobs
                                                        </button>
                                                        <!-- Loader -->
                                                        {{--                                                        <div id="loader"--}}
                                                        {{--                                                             class="d-none position-fixed top-50 start-50 translate-middle"--}}
                                                        {{--                                                             style="z-index: 1051;">--}}
                                                        {{--                                                            <div class="spinner-border text-primary" role="status"--}}
                                                        {{--                                                                 style="width: 4rem; height: 4rem;">--}}
                                                        {{--                                                                <span class="visually-hidden">Loading...</span>--}}
                                                        {{--                                                            </div>--}}
                                                        {{--                                                        </div>--}}
                                                    </div>
                                                </form>
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
        $('#project_id').select2();

        $(document).ready(function () {

            $('.loader-backdrop').addClass('d-none');

            @if (session('success'))
            $('.loader-backdrop').addClass('d-none');
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('success') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif
            @if (session('error'))
            $('.loader-backdrop').addClass('d-none');
            Swal.fire({
                icon: "error",
                title: "Oops...",
                text: "Something went wrong!",
                footer: '<a href="#">  {{ session('error') }}</a>'
            });
                @endif

            $('form').on('submit', function (e) {
                $('#uploadBtn').attr('disabled', 'disabled');
                // $('.loader-backdrop').removeClass('d-none');
                // $('#loader').removeClass('d-none');
            });
            // $("#uploadBtn").click(function(e){
            //     // Show loader
            //     $(this).prop('disabled', true);
            //     $("#loader").removeClass('d-none');
            // });
            function render_zones(zones) {
                let zone_select = $("#zone_id");
                zone_select.empty();
                zone_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(zones, function (index, value) {
                    zone_select.append($('<option>').text(value.zone_name).val(value.id));
                })
            }

            function render_units(units) {
                let unit_select = $("#unit_id");
                unit_select.empty();
                unit_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(units, function (index, value) {
                    unit_select.append($('<option>').text(value.unit_name).val(value.id));
                })
            }

            function render_projects(projects) {
                let project_select = $("#project_id");
                project_select.empty();
                project_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(projects, function (index, value) {
                    project_select.append($('<option>').text(value.project_name).val(value.id));
                })
            }

            function render_templates(projectTemplates) {
                let template_name_select = $("#template_name_id");
                template_name_select.empty();
                $.each(projectTemplates, function (index, projectTemplate) {
                    template_name_select.append($('<option>').text(projectTemplate.get_template.template_name).val(projectTemplate.get_template.id));
                });
            }

            $("#project_id").change(function (e) {
                const selectedProjectId = $(this).val();
                if (selectedProjectId) {
                    $.ajax({
                        url: '{{route('get_project_info')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedProjectId
                        },
                        success: function (response) {
                            if (response.message == "Success") {
                                render_templates(response.projectTemplateNames);
                            }
                        }
                    })
                }
            })
            $("#company_id").change(function (e) {
                const selectedCompanyId = $(this).val();
                if (selectedCompanyId) {
                    $.ajax({
                        url: '{{route('get_company_zones')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedCompanyId
                        },
                        success: function (response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_zones(response.related_zones);
                            }
                        }
                    })
                }
            })
            $("#zone_id").change(function (e) {
                const selectedZoneId = $(this).val();
                if (selectedZoneId) {
                    $.ajax({
                        url: '{{route('get_zones_units')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedZoneId
                        },
                        success: function (response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_units(response.related_units);
                            }
                        }
                    })
                }
            })
            $("#unit_id").change(function (e) {
                const selectedUnitId = $(this).val();
                if (selectedUnitId) {
                    $.ajax({
                        url: '{{route('get_unit_projects')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedUnitId
                        },
                        success: function (response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_projects(response.related_projects);
                            }
                        }
                    })
                }
            })
        })
    </script>
@endsection
