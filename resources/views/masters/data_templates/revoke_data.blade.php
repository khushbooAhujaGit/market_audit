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
                            <h4>Revoke Data <a href="{{route('auditorAssigned.list')}}">
                                    <button class="btn btn-primary float-end">View Assigned Data</button>
                                </a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{route('revokeToUsers.data')}}"
                                                      enctype="multipart/form-data" class="row g-3 needs-validation"
                                                      novalidate="">
                                                    @csrf
                                                    {{--                                                    d-none class added here--}}
                                                    <div class="col-xl-3 col-sm-3 d-none">
                                                        <label class="form-label" for="company_id">Select
                                                            Company</label>
                                                        <select class="form-select" id="company_id" name="company_id"
                                                        >
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
                                                    {{--                                                    d-none class added here--}}
                                                    <div class="col-xl-3 col-sm-3 d-none">
                                                        <label class="form-label" for="zone_id">Select Zone</label>
                                                        <select class="form-select" id="zone_id" name="zone_id"
                                                        >
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('zone_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    {{--                                                    d-none class added here--}}
                                                    <div class="col-xl-3 col-sm-3 d-none">
                                                        <label class="form-label" for="unit_id">Select Unit</label>
                                                        <select class="form-select" id="unit_id" name="unit_id"
                                                        >
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('unit_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
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

                                                    <div class="col-md-12 d-none">
                                                        <div class="card-wrapper border rounded-3 checkbox-checked">
                                                            <h6 class="sub-title">Assign Any One</h6>
                                                            <div class="form-check-size">
                                                                <div class="form-check form-check-inline radio radio-primary">
                                                                    <input class="form-check-input" id="independent_activity" type="radio" name="assign_helper" value="option5">
                                                                    <label class="form-check-label mb-0" for="independent_activity">Independent Activities</label>
                                                                </div>
                                                                <div class="form-check form-check-inline radio radio-primary">
                                                                    <input class="form-check-input" id="group_activity" type="radio" name="assign_helper" value="option5">
                                                                    <label class="form-check-label mb-0" for="group_activity">Group Activities</label>
                                                                </div>


                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none" id="group_display">
                                                        <label class="form-label" for="activity_group_name_id">Select
                                                            Activity Group</label>
                                                        <select class="form-select" id="activity_group_name_id"
                                                                name="activity_group_name_id"
                                                                required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($activity_groups as $activity_group)
                                                                <option value="{{$activity_group->id}}"
                                                                        @if(old('activity_group_name_id') == $activity_group->id) selected @endif>{{$activity_group->activity_group_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('activity_group_name_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-xl-3 col-sm-3 d-none" id="activites_display" >
                                                        <label class="form-label" for="template_id">Select
                                                            Activity</label>
                                                        <select class="form-select" id="template_id" name="template_id[]" multiple=""
                                                                required="">
                                                            @foreach($activites as $activity)
                                                                <option value="{{$activity->id}}"
                                                                        @if(old('company_id') == $activity->id) selected @endif>{{$activity->template_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('template_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none">
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
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label"
                                                               for="template_name_head_id">Selector</label>
                                                        <select class="form-select" id="template_name_head_id"
                                                                name="template_name_head_id" required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('template_name_head_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            The template Selector field is required.
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="template_name_head_values">Selector
                                                            Values</label>
                                                        <select class="form-select" id="template_name_head_values"
                                                                name="template_name_head_values[]" multiple="multiple"
                                                                required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                        </select>
                                                        @error('template_name_head_values')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            The Selector values field is required.
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="auditor_names">User
                                                            Name</label>
                                                        <select class="form-select" id="auditor_names"
                                                                name="auditor_names[]"
                                                                required="" multiple="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($users as $user)

                                                                <option value="{{ $user->id }}"
                                                                        @if(is_array(old('auditor_names')) && in_array($user->id, old('auditor_names')))
                                                                            selected
                                                                    @endif>
                                                                    {{ $user->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('auditor_names')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none">
                                                        <label class="form-label" for="verify_users_names">Verify User
                                                            Name</label>
                                                        <select class="form-select" id="verify_users_names"
                                                                name="verify_users_names[]"
                                                                multiple="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($users as $user)
                                                                <option value="{{ $user->id }}"
                                                                        @if(is_array(old('verify_users_names')) && in_array($user->id, old('verify_users_names')))
                                                                            selected
                                                                    @endif>
                                                                    {{ $user->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('verify_users_names')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button class="btn btn-primary">Revoke Now</button>
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
        $(document).ready(function () {
            $("#auditor_names").select2();
            $("#verify_users_names").select2();
            $("#template_name_head_values").select2();
            $("#template_id").select2();

            let all_activites = @json($activites);
            function render_independent_activities(activites) {
                let activitySelect = $("#template_id");
                activitySelect.empty();
                activitySelect.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(activites, function (index, activity) {
                    activitySelect.append($('<option>').text(activity.template_name).val(activity.id));
                })
            }
            $('input[name="assign_helper"]').change(function() {
                // Check which radio button is checked
                if ($(this).attr('id') === 'independent_activity') {
                    $("#activites_display").removeClass('d-none');
                    $("#group_display").addClass('d-none');
                    render_independent_activities(all_activites);

                } else if ($(this).attr('id') === 'group_activity') {
                    let activitySelect = $("#template_id");
                    activitySelect.empty();
                    $("#group_display").removeClass('d-none');
                    $("#activites_display").removeClass('d-none');
                    $("#activity_group_name_id").trigger('change');
                }
            });


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

            function render_heads(heads) {
                let template_name_head_select = $("#template_name_head_id");
                template_name_head_select.empty();
                template_name_head_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(heads, function (index, value) {
                    template_name_head_select.append($('<option>').text(value.template_head_name).val(value.id));
                })
            }

            function render_head_values(head_values) {
                let template_name_head_values_select = $("#template_name_head_values");
                template_name_head_values_select.empty();
                template_name_head_values_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(head_values, function (index, head_value) {
                    template_name_head_values_select.append($('<option>').text(head_value).val(head_value));
                })

                // $("#template_name_head_values").select2();
            }
            function render_group_activties(activites) {
                let activitySelect = $("#template_id");
                activitySelect.empty();
                activitySelect.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(activites, function (index, activity) {
                    activitySelect.append($('<option>').text(activity.get_template_info.template_name).val(activity.get_template_info.id));
                })
            }




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
                            if (response.message == "Success") {
                                render_projects(response.related_projects);
                            }
                        }
                    })
                }
            })
            $("#template_name_id").change(function (e) {
                const template_name_id = $(this).val();
                if (template_name_id) {
                    $.ajax({
                        url: '{{route('get_template_heads')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": template_name_id
                        },
                        success: function (response) {
                            if (response.message == "Success") {
                                render_heads(response.related_heads);
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
                            url: '{{route('get_template_head_values')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": template_name_head_id,
                                "project_id": project_id,
                                "template_name_id": template_name_id,
                            },
                            success: function (response) {
                                if (response.message == "Success") {
                                    render_head_values(response.related_head_values);
                                }
                            }
                        })
                    }
                }
            })

            // this is to get the activites related to the group selected
            $("#activity_group_name_id").change(function (e) {
                const activityGroupNameId = $(this).val();
                if (activityGroupNameId) {
                    $.ajax({
                        url: '{{route('get_group_activites')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": activityGroupNameId,
                        },
                        success: function (response) {
                            if (response.message == "Success") {
                                render_group_activties(response.group_info.get_group_activities);
                            }
                        }
                    })
                }
            })


            /*
            //
             var changingSelect = false;
              // this is for the activityGroup and activity
              $("#activity_group_name_id").change(function () {
                  if (!changingSelect) {
                      changingSelect = true;
                      $("#template_id").val('').trigger('change');
                      changingSelect = false;
                  }
              });
              // When template select changes
              $("#template_id").change(function () {
                  if (!changingSelect) {
                      changingSelect = true;
                      $("#activity_group_name_id").val('').trigger('change');
                      changingSelect = false;
                  }
              });

            */

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
                            console.log(response)
                            if(response.message=="Success"){
                                let templateId = response.projectInfo.template_name_id;
                                // Select the option with the template name ID
                                $("#template_name_id").val(templateId);
                                // Disable all options except the selected one
                                $("#template_name_id option").prop('disabled', true);
                                $("#template_name_id option[value='" + templateId + "']").prop('disabled', false);
                                let activity_group_val = response.projectInfo.activity_group_name_id_or_activity_id;
                                $("#template_id").val('');
                                $("#activity_group_name_id").val('');
                                if(response.projectInfo.activityType==0){
                                    $("#template_id").val(activity_group_val);
                                    $("#template_id option").prop('disabled', true);
                                    $("#activity_group_name_id option").prop('disabled', true);
                                    $("#template_id option[value='" + activity_group_val + "']").prop('disabled', false);
                                    $("#independent_activity").prop('checked', true);
                                    $("#independent_activity").trigger('change');
                                } else{
                                    $("#activity_group_name_id").val(activity_group_val);
                                    $("#activity_group_name_id option").prop('disabled', true);
                                    $("#template_id option").prop('disabled', true);
                                    $("#activity_group_name_id option[value='" + activity_group_val + "']").prop('disabled', false);
                                    $("#group_activity").prop('checked', true);
                                    $("#group_activity").trigger('change');
                                }
                                // Manually trigger the change event after setting the value
                                $("#template_name_id").trigger('change');
                                $("#activity_group_name_id").trigger('change');
                                //
                            }
                        }
                    })
                }
            })
        })
    </script>
@endsection
