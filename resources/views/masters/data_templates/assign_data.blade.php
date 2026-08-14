@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* khushboo 01-04-2025 */
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

        /* khushboo 01-04-2025 */
    </style>
@endsection
@section('content')
    <div class="loader-backdrop">
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
                            <h4>Assign Data
                                {{-- <a href="{{route('auditorAssigned.list')}}"> --}}
                                {{-- <button class="btn btn-primary float-end">View Assigned Data</button> --}}
                                {{-- </a> --}}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{ route('assignToUsers.data') }}"
                                                    enctype="multipart/form-data" class="row g-3 needs-validation"
                                                    novalidate=""
                                                    onsubmit="this.querySelector('#assignNowButton').disabled=true;">
                                                    @csrf

                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="project_id">Select
                                                            Project</label>
                                                        <select class="form-select" id="project_id" name="project_id"
                                                            required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($projects as $project)
                                                                <option value="{{ $project->id }}">
                                                                    {{ $project->project_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('project_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 ">
                                                        <label class="form-label" for="template_name_id">Select Data
                                                            Template</label>
                                                        <select class="form-select" id="template_name_id"
                                                            name="template_name_id" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($template_names as $template_name)
                                                                <option value="{{ $template_name->id }}"
                                                                    @if (old('company_id') == $template_name->id) selected @endif>
                                                                    {{ $template_name->template_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('template_name_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none" id="group_display">
                                                        <label class="form-label" for="activity_group_name_id">Select
                                                            Activity Group</label>
                                                        <select class="form-select" id="activity_group_name_id"
                                                            name="activity_group_name_id" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($activity_groups as $activity_group)
                                                                <option value="{{ $activity_group->id }}"
                                                                    @if (old('activity_group_name_id') == $activity_group->id) selected @endif>
                                                                    {{ $activity_group->activity_group_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('activity_group_name_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-xl-3 col-sm-3 d-none" id="activites_display">
                                                        <label class="form-label" for="activity_id">Select
                                                            Activity</label>
                                                        <select class="form-select" id="activity_id" name="activity_id[]"
                                                            multiple="" required="">
                                                            @foreach ($activities as $activity)
                                                                <option value="{{ $activity->id }}"
                                                                    @if (old('company_id') == $activity->id) selected @endif>
                                                                    {{ $activity->activity_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('activity_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label"
                                                            for="template_name_head_id">Selector</label>
                                                        <select class="form-select" id="template_name_head_id"
                                                            name="template_name_head_id" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                        </select>
                                                        @error('template_name_head_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                The template Selector field is required.
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="template_name_head_values"
                                                            id="template_head_values">Selector
                                                            Values</label>
                                                        <select class="form-select" id="template_name_head_values"
                                                            name="template_name_head_values[]" multiple="multiple"
                                                            required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                        </select>
                                                        @error('template_name_head_values')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                The Selector values field is required.
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    {{-- //khushboo 31-03-2024 --}}
                                                    @if ($currentUserRole == 'Agency')
                                                    @else
                                                        <div class="col-xl-3 col-sm-3 d-none" id="agencyListDiv">
                                                            <label class="form-label" for="user_type">Agencies</label>
                                                            <select class="form-select" id="agency_list"
                                                                name="agency_list">
                                                                <option selected="" disabled="" value="">
                                                                    Choose...
                                                                </option>

                                                            </select>
                                                            @error('agency_list')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror
                                                        </div>
                                                    @endif
                                                    <div class="col-xl-3 col-sm-3 d-none" id="companyUserList">
                                                        <label class="form-label" for="user_type">Company Users</label>
                                                        <select class="form-select" id="company_user"
                                                            name="company_user">
                                                            <option selected="" disabled="" value="">
                                                                Choose...
                                                            </option>

                                                        </select>
                                                        @error('company_user')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    {{-- //khushboo 31-03-2024 --}}

                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="auditor_names">User
                                                            Name</label>
                                                        <select class="form-select" id="auditor_names"
                                                            name="auditor_names[]" required="" multiple="">
                                                            <option selected="" disabled="" value="">
                                                                Choose...
                                                            </option>
                                                            @foreach ($users as $user)
                                                                @if ($user->getRoleNames()->first() == 'Auditor')
                                                                    <option value="{{ $user->id }}"
                                                                        @if (is_array(old('auditor_names')) && in_array($user->id, old('auditor_names'))) selected @endif>
                                                                        {{ $user->name }}
                                                                    </option>
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                        @error('auditor_names')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="verify_users_names">Verify User
                                                            Name</label>
                                                        <select class="form-select" id="verify_users_names"
                                                            name="verify_users_names[]" multiple="" required="">
                                                            <option selected="" disabled="" value="">
                                                                Choose...
                                                            </option>
                                                            @if (empty($verifier_users))
                                                                @foreach ($users as $user)
                                                                    @if ($user->getRoleNames()->first() == 'Verifier')
                                                                        <option value="{{ $user->id }}"
                                                                            @if (is_array(old('verify_users_names')) && in_array($user->id, old('verify_users_names'))) selected @endif>
                                                                            {{ $user->name }}
                                                                        </option>
                                                                    @endif
                                                                @endforeach
                                                            @else
                                                                @foreach ($verifier_users as $user)
                                                                    <option value="{{ $user->id }}"
                                                                        @if (is_array(old('verify_users_names')) && in_array($user->id, old('verify_users_names'))) selected @endif>
                                                                        {{ $user->name }}
                                                                    </option>
                                                                @endforeach
                                                            @endif
                                                        </select>
                                                        @error('verify_users_names')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none" id="assign_outlets_con">
                                                        <div class="form-check checkbox checkbox-primary mb-0 mt-4">
                                                            <input class="form-check-input" name="is_outlet_assigned"
                                                                id="is_outlet_assigned" type="checkbox">
                                                            <label class="form-check-label"
                                                                for="is_outlet_assigned">Assign Outlets Also</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 text-end">
                                                        <button class="btn btn-primary" id="assignNowButton">Assign
                                                            Now
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>

                                        </div>
                                        <div class="modal_con">
                                            <div class="modal fade" id="errorMessage_modal" tabindex="-1"
                                                role="dialog" aria-labelledby="errorMessage_modal" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-body">
                                                            <div class="modal-toggle-wrapper">
                                                                <ul class="modal-img">
                                                                    <li><img src="{{ url('assets/images/gif/danger.gif') }}"
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
        const currentUserRole = "{{ $currentUserRole }}";
        console.log(currentUserRole);

        $('#project_id').select2();

        function render_head_values(head_values) {
            let template_name_head_values_select = $("#template_name_head_values");
            template_name_head_values_select.empty();
            template_name_head_values_select.append($('<option>').text('Choose...').attr('disabled', 'disabled')
                .attr('selected', 'selected').val(''));
            $.each(head_values, function(index, head_value) {
                template_name_head_values_select.append($('<option>').text(head_value).val(head_value));
            })

            // $("#template_name_head_values").select2();
        }


        function templateNameHeadValues() {
            const template_name_head_id = $("#template_name_head_id").val();
            const project_id = $("#project_id").val();
            const template_name_id = $("#template_name_id").val();
            // document.querySelector('.loader-backdrop').style.display = 'flex';
            if (project_id) {
                if (template_name_head_id) {
                    $.ajax({
                        url: '{{ route('project.templateHead_values') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": template_name_head_id,
                            "project_id": project_id,
                            "template_name_id": template_name_id,
                        },
                        success: function(response) {
                            console.log(response)
                            // document.querySelector('.loader-backdrop').style.display ='none';
                            if (response.message == "Success") {
                                if (response.related_head_values.length == 0) {
                                    $('#template_head_values').addClass('d-none');
                                    $('#template_name_head_values').removeAttr('required');
                                } else {
                                    $('#template_head_values').removeClass('d-none');
                                    $('#template_name_head_values').attr('required', true);
                                }
                                render_head_values(response.related_head_values);
                            }
                        }
                    })
                }
            }
        }

        $(document).ready(function() {
            document.querySelector('.loader-backdrop').style.display = 'none';

            $("#auditor_names").select2();
            $("#verify_users_names").select2();
            $("#template_name_head_values").select2();
            // $("#activity_id").select2();
            $("#agency_list").select2();
            $("#activity_id").select2({
                placeholder: "Select Activity",
                closeOnSelect: false,
                allowClear: true
            });


            //khushboo 31-03-2024

            $('#template_name_head_id').on('change', function() {
                templateNameHeadValues();
            });

            $('#agency_list').on('change', function() {
                console.log($(this).val());
                let user_id = $(this).val();
                let template_name_id = $('#template_name_id').val();
                let selectedOption = $('#template_name_id option:selected');

                let project_template_id = selectedOption.data('id');
                // console.log(selectedOption);
                let activity_group_name_id = $('#activity_group_name_id').val();
                let activity_ids = $('#activity_id').val();
                // console.log($('#activity_id').val())
                let template_name_head_id = $('#template_name_head_id').val();
                if (template_name_head_id == '') {
                    alert('Please Select Selector Data First');
                }

                document.querySelector('.loader-backdrop').style.display = 'flex';
                $.ajax({
                    url: "{{ route('get_agency_users') }}",
                    method: "POST",
                    data: {
                        "_token": "{{ @csrf_token() }}",
                        "user_id": user_id,
                        "project_template_id": project_template_id,
                        "activity_group_name_id": activity_group_name_id,
                        "activity_id": activity_ids,
                        "template_name_head_id": template_name_head_id
                    },
                    success: function(response) {
                        console.log(response);
                        document.querySelector('.loader-backdrop').style.display = 'none';
                        if (response.auditor_user_list == '' && response.verifier_users == '') {
                            alert('Data Already Assigned for Selected Agency Users');
                            return;
                        }
                        $('#verify_users_names').html('');
                        if (response.message) {
                            $('#verify_users_names').html(response.verifier_users);
                            $('#auditor_names').html(response.auditor_user_list);
                            $('#auditor_names').prop('required', false);
                            $('#verify_users_names').prop('required', false);
                        } else {
                            $$("#error_message").html(`${response.message}`);
                            $("#errorMessage_modal").modal('show');
                            $('#auditor_names').prop('required', true);
                            $('#verify_users_names').prop('required', true);
                        }
                    }
                });

            });

            //khushboo 31-03-2024

            let all_activites = @json($activities);
            $("#activity_group_name_id").change(function(e) {
                const selectedGroup = $(this).val();
                $("#activity_group_name_id option").each(function() {
                    if ($(this).val() !== selectedGroup) {
                        $(this).prop("disabled", true);
                    } else {
                        $(this).prop("disabled", false); // Ensure selected option remains enabled
                    }
                });
                if (selectedGroup) {
                    document.querySelector('.loader-backdrop').style.display = 'flex';
                    $.ajax({
                        url: "{{ route('group_activities') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ @csrf_token() }}",
                            "g_id": selectedGroup
                        },
                        success: function(response) {
                            document.querySelector('.loader-backdrop').style.display = 'none';
                            renderGroupActivities(response.get_group_activities)
                        }
                    })
                }
            })

            $("#template_name_id").change(function(e) {
                const template_name_id = $(this).val();
                const project_id = $("#project_id").val();
                $("#activity_group_name_id option").each(function() {
                    $(this).prop("disabled", false); // Ensure selected option remains enabled
                });
                document.querySelector('.loader-backdrop').style.display = 'flex';
                if (template_name_id) {
                    $.ajax({
                        url: '{{ route('projectTemplate.info') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "t_id": template_name_id,
                            "p_id": project_id
                        },
                        success: function(response) {
                            console.log(response);
                            document.querySelector('.loader-backdrop').style.display = 'none';
                            if (response.message == "success") {
                                const pro_temp = response.projectTemplate;
                                //for empty data check
                                if (pro_temp['with_data'] == 0 && !response.head_data_exists) {
                                    $('#template_head_values').addClass('d-none');
                                    $('#template_name_head_values').removeAttr('required');
                                } else {
                                    $('#template_head_values').removeClass('d-none');
                                    $('#template_name_head_values').attr('required', true);
                                }
                                //for empty data check

                                if (response.project_templates_count > 1 && response
                                    .projectTemplate['is_master'] == 1) {
                                    $("#assign_outlets_con").removeClass('d-none');
                                } else {
                                    $("#assign_outlets_con").addClass('d-none');
                                    $('#is_outlet_assigned').prop('checked', false);
                                }
                                if (pro_temp.activity_group_name_id_or_activity_id) {
                                    $("#activity_id").val('');
                                    $("#activity_group_name_id").val('');
                                    let make_select = pro_temp
                                        .activity_group_name_id_or_activity_id;
                                    if (pro_temp.activityType == 0) {
                                        $("#activites_display").removeClass('d-none');
                                        $("#group_display").addClass('d-none');
                                        activitySelectFunc(make_select)
                                    } else {
                                        $("#activites_display").addClass('d-none');
                                        $("#group_display").removeClass('d-none');
                                        $("#activity_group_name_id").val(make_select).trigger(
                                            'change');
                                    }

                                    $('#assignNowButton').prop('disabled', false);
                                } else {

                                    // $("#error_message").html(
                                    //     "Project Template Name Mapping is not completed")
                                    // $("#errorMessage_modal").modal('show');

                                    $('#assignNowButton').prop('disabled', true);
                                }
                                render_heads(pro_temp.get_template.get_template_heads);

                                if (response.checkDataAssign) {
                                    let assignedData = response.checkDataAssign;
                                    console.log(assignedData);
                                    $('#template_name_head_id').val(assignedData
                                        .template_name_head_id);
                                    // Instead of disabling, make it readonly
                                    if (currentUserRole == 'Agency') {
                                        if ($('#template_name_head_id').val() == '') {

                                            $('#template_name_head_id').prop('readonly', false);
                                            $('#template_name_head_id').css({
                                                'pointer-events': '',
                                                'background-color': ''
                                            });

                                        } else {

                                            $('#template_name_head_id').prop('readonly', true);

                                            $('#template_name_head_id').css({
                                                'pointer-events': 'none',
                                                'background-color': '#e9ecef'
                                            });
                                        }
                                    }

                                    templateNameHeadValues();
                                }
                                // if(response.verifier_exist){
                                //     $("#error_message").html(
                                //         "Project is Already Assign to Agency")
                                //     $("#errorMessage_modal").modal('show');
                                // }
                            }
                        }
                    })
                }
            })

            function render_heads(heads) {
                let template_name_head_select = $("#template_name_head_id");
                template_name_head_select.empty();
                // const render_skip = ['key_code','key_name' ]; // this is to skip the selection of key_code and key_name
                //khushboo 17-05-25
                const render_skip = ['latitude', 'longitude', 'Lat', 'Long', 'Latitude', 'Longitude', 'lat',
                    'long'
                ];


                template_name_head_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr(
                    'selected', 'selected').val(''));
                $.each(heads, function(index, value) {
                    const cleanValue = value.template_head_name.trim().replace(/\s+/g, '');
                    if ($.inArray(cleanValue, render_skip) === -1) {
                        template_name_head_select.append($('<option>').text(value.template_head_name).val(
                            value.id));
                    }
                })
            }

            function activitySelectFunc(a_id = 0) {
                let activitySelect = $("#activity_id");
                activitySelect.empty();
                $.each(all_activites, function(index, activity) {
                    if (activity.id == a_id) {
                        activitySelect.append($('<option>').text(activity.activity_name).val(activity.id)
                            .attr('selected', 'selected'));
                    } else {
                        activitySelect.append($('<option>').text(activity.activity_name).val(activity.id)
                            .attr('disabled', 'disabled'));
                    }
                })

            }

            function renderGroupActivities(activities) {
                $("#activites_display").removeClass('d-none');
                let activitySelect = $("#activity_id");
                activitySelect.empty();

                let activity_idArr = [];

                $.each(activities, function(index1, group_activity) {
                    activity_idArr.push(group_activity.activity_id);
                });

                // Add all activities as selectable options
                $.each(all_activites, function(index, activity) {
                    if ($.inArray(activity.id, activity_idArr) !== -1) {
                        activitySelect.append(
                            $('<option></option>')
                            .val(activity.id)
                            .text(activity.activity_name)
                        );
                    }
                });

                // Destroy existing Select2 instance if any
                if (activitySelect.hasClass('select2-hidden-accessible')) {
                    activitySelect.select2('destroy');
                }

                // Re-initialize Select2 with minimal configuration
                activitySelect.select2({
                    placeholder: "Select Activity",
                    closeOnSelect: false,
                    allowClear: true
                });

                // Initially select all activities
                activitySelect.val(activity_idArr).trigger('change');

                // Function to update the display
                function updateDisplay() {
                    setTimeout(function() {
                        var $container = activitySelect.next('.select2-container');
                        var $selection = $container.find('.select2-selection--multiple');
                        var $rendered = $selection.find('.select2-selection__rendered');

                        // Get current selection
                        var selectedValues = activitySelect.val() || [];

                        // Clear all existing tags but keep the search input
                        $rendered.find('.select2-selection__choice').remove();

                        if (selectedValues.length === 0) {
                            // Show placeholder
                            return;
                        }

                        // Check if all activities are selected
                        var allActivityIds = activity_idArr.map(String);
                        var allSelected = allActivityIds.length > 0 && allActivityIds.every(function(id) {
                            return selectedValues.indexOf(id) !== -1;
                        });

                        // Create custom display
                        var displayText = allSelected ? 'Select All' : selectedValues.length +
                            ' activities selected';
                        var $customTag = $('<li class="select2-selection__choice" title="' + displayText +
                            '">' +
                            displayText +
                            '<span class="select2-selection__choice__remove" role="presentation">×</span>' +
                            '</li>');

                        // Insert at the beginning
                        $rendered.prepend($customTag);

                        // Handle remove click
                        $customTag.find('.select2-selection__choice__remove').on('click', function(e) {
                            e.stopPropagation();
                            e.preventDefault();
                            activitySelect.val([]).trigger('change');
                            return false;
                        });
                    }, 50);
                }

                // Update display on changes
                activitySelect.on('change', function() {
                    updateDisplay();
                });

                // Initial update
                setTimeout(updateDisplay, 100);

                // Add "Select All" option in dropdown
                setTimeout(function() {
                    var $dropdown = activitySelect.data('select2').$dropdown;
                    var $resultsContainer = $dropdown.find('.select2-results');

                    // Remove existing custom option if any
                    $resultsContainer.find('.select2-all-option').remove();

                    // Create Select All option
                    var $selectAllOption = $(
                        '<div class="select2-all-option" style="padding: 8px 12px; border-bottom: 1px solid #eee; cursor: pointer; background-color: #f8f9fa; font-weight: bold;">' +
                        '<input type="checkbox" id="select-all-activities" ' +
                        (activitySelect.val() && activitySelect.val().length === activity_idArr.length ?
                            'checked' : '') + '> ' +
                        '<label for="select-all-activities" style="margin-left: 5px; cursor: pointer;">Select All Activities</label>' +
                        '</div>');

                    // Insert at the top of results
                    $resultsContainer.prepend($selectAllOption);

                    // Handle Select All checkbox click
                    $selectAllOption.find('#select-all-activities').off('change').on('change', function(e) {
                        e.stopPropagation();
                        if ($(this).is(':checked')) {
                            activitySelect.val(activity_idArr).trigger('change');
                        } else {
                            activitySelect.val([]).trigger('change');
                        }
                        updateDisplay();
                    });

                    // Handle click on the whole div
                    $selectAllOption.on('click', function(e) {
                        if ($(e.target).is('input, label')) return;
                        var $checkbox = $(this).find('#select-all-activities');
                        $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
                    });

                    // Update checkbox state when dropdown opens
                    activitySelect.on('select2:open', function() {
                        var $checkbox = $resultsContainer.find('#select-all-activities');
                        var currentSelection = activitySelect.val() || [];
                        var allSelected = activity_idArr.length > 0 && activity_idArr.every(
                            function(id) {
                                return currentSelection.indexOf(String(id)) !== -1;
                            });
                        $checkbox.prop('checked', allSelected);
                    });

                }, 200);

                // Also update when dropdown closes
                activitySelect.on('select2:close', function() {
                    updateDisplay();
                });
            }

            function renderGroupActivitiesss(activities) {
                $("#activites_display").removeClass('d-none');
                let activitySelect = $("#activity_id");
                activitySelect.empty();
                let activity_idArr = [];
                $.each(activities, function(index1, group_activity) {
                    activity_idArr.push(group_activity.activity_id);
                })
                $.each(all_activites, function(index, activity) {
                    if ($.inArray(activity.id, activity_idArr) !== -1) {
                        activitySelect.append($('<option>').text(activity.activity_name).val(activity.id));
                    }
                })

            }

            function render_templates(projectTemplates) {
                let template_name_select = $("#template_name_id");
                template_name_select.empty();
                $.each(projectTemplates, function(index, projectTemplate) {
                    template_name_select.append(
                        $('<option>')
                        .text(projectTemplate.get_template.template_name)
                        .val(projectTemplate.get_template.id)
                        .attr('data-id', projectTemplate.id) // Add your desired data-id here
                    );
                });
                $("#template_name_id").trigger('change');
            }



            $("#project_id").change(function(e) {
                const selectedProjectId = $(this).val();
                // document.querySelector('.loader-backdrop').style.display = 'flex';
                if (selectedProjectId) {
                    $.ajax({
                        url: '{{ route('get_project_info') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedProjectId
                        },
                        success: function(response) {
                            // document.querySelector('.loader-backdrop').style.display = 'none';
                            console.log(response);
                            if (response.message == "Success") {
                                //khushboo 02-04-2025
                                //if agency applicable
                                if (response.is_agency_required) {
                                    $('#agency_list').html(response.agency_list);
                                    $('#agencyListDiv').removeClass('d-none');
                                } else {
                                    $('#agency_list').html('');
                                    $('#agencyListDiv').addClass('d-none');
                                    $('#verify_users_names').html(response.verifier_user_list);
                                    $('#auditor_names').html(response.auditor_user_list);
                                }

                                //if company applicable
                                if (response.isCompanyApplicable) {
                                    $('#company_user').html(response.company_users);
                                    $('#companyUserList').removeClass('d-none');
                                } else {
                                    $('#company_user').html('');
                                    $('#companyUserList').addClass('d-none');
                                }

                                //khushboo 02-04-2025
                                // let templateId = response.projectInfo.template_name_id;
                                render_templates(response.projectTemplateNames);
                                // Select the option with the template name ID
                                // $("#template_name_id").val(templateId);
                                // // Disable all options except the selected one
                                // $("#template_name_id option").prop('disabled', true);
                                // $("#template_name_id option[value='" + templateId + "']").prop('disabled', false);
                                // let activity_group_val = response.projectInfo.activity_group_name_id_or_activity_id;
                                // $("#activity_id").val('');
                                // $("#activity_group_name_id").val('');
                                // if(response.projectInfo.activityType==0){
                                //     $("#activity_id").val(activity_group_val);
                                //     $("#activity_id option").prop('disabled', true);
                                //     $("#activity_group_name_id option").prop('disabled', true);
                                //     $("#activity_id option[value='" + activity_group_val + "']").prop('disabled', false);
                                //     $("#independent_activity").prop('checked', true);
                                //     $("#independent_activity").trigger('change');
                                //     let selectedValues = [activity_group_val];
                                //     $('#activity_id').val(selectedValues).trigger('change');
                                //
                                // }
                                // else{
                                //     $("#activity_group_name_id").val(activity_group_val);
                                //     $("#activity_group_name_id option").prop('disabled', true);
                                //     $("#activity_id option").prop('disabled', true);
                                //     $("#activity_group_name_id option[value='" + activity_group_val + "']").prop('disabled', false);
                                //     $("#group_activity").prop('checked', true);
                                //     $("#group_activity").trigger('change');
                                // }
                                // // // Manually trigger the change event after setting the value
                                // $("#template_name_id").trigger('change');
                                // $("#activity_group_name_id").trigger('change');
                                //
                            }
                        }
                    })
                }
            })




        })
    </script>
@endsection
