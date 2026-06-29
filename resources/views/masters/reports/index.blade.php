@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Get Project Report
                                {{--                                <a href="{{route('auditorAssigned.list')}}"> --}}
                                {{--                                    <button class="btn btn-primary float-end">View Assigned Data</button> --}}
                                {{--                                </a> --}}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                aria-labelledby="wizard-info-tab">
                                                <form method="POST" action="{{ route('get_report.download') }}"
                                                    enctype="multipart/form-data" class="row g-3 needs-validation"
                                                    novalidate="">
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
                                                        <label class="form-label" for="from_date">Start Date<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="from_date"
                                                            value="{{ old('from_date') }}" name="from_date" type="date">
                                                        @error('from_date')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <label class="form-label" for="to_date">End Date<span
                                                                class="txt-danger"></span></label>
                                                        <input class="form-control" id="to_date"
                                                            value="{{ old('to_date') }}" name="to_date" type="date">
                                                        @error('to_date')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0 ">
                                                            <input class="form-check-input" name="user_details"
                                                                id="user_details" type="checkbox">
                                                            <label class="form-check-label" for="user_details">User
                                                                Details Required</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="date_required"
                                                                id="date_required" type="checkbox">
                                                            <label class="form-check-label" for="date_required">Date
                                                                Required </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="time_required"
                                                                id="time_required" type="checkbox">
                                                            <label class="form-check-label" for="time_required">Time
                                                                Required</label>
                                                        </div>
                                                    </div>
                                                    @can('Auditor Info on Report')
                                                        <div class="col-xl-3 col-sm-3">
                                                            <div class="form-check checkbox checkbox-primary mb-0">
                                                                <input class="form-check-input" name="auditor_details"
                                                                    id="auditor_details" type="checkbox">
                                                                <label class="form-check-label" for="auditor_details">Auditor
                                                                    Details Required</label>
                                                            </div>
                                                        </div>
                                                    @endcan
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="pdf_required"
                                                                id="pdf_required" type="checkbox">
                                                            <label class="form-check-label" for="pdf_required">PDF
                                                                Required</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-4 col-sm-4">
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
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    {{-- Verification Status filter removed per admin request --}}
                                                    <input type="hidden" name="verification_status_filter" value="all">

                                                    <div class="col-12 text-end">
                                                        <button name="get_report" id="get_report"
                                                            class="btn btn-primary">Get Report
                                                        </button>
                                                        <button name="get_mail" class="btn btn-primary">Get Mail
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
        $('#project_id').select2();

        $(document).ready(function() {

            //khushboo 23-05-25
            $('#pdf_required').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#get_report').prop('disabled', true);
                } else {
                    $('#get_report').prop('disabled', false);
                }
            });
            //khushboo 23-05-25

            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif
            $("#auditor_names").select2();
            $("#verify_users_names").select2();
            $("#template_name_head_values").select2();
            // $("#activity_id").select2();

            //             $("#activity_id").select2({
            //     placeholder: "Select Activity",
            //     closeOnSelect: false,
            //     templateSelection: function (data, container) {

            //         let selectedValues = $("#activity_id").val();

            //         if (!selectedValues || selectedValues.length === 0) {
            //             return "Select Activity";
            //         }

            //         // If all real activities are selected
            //         let totalOptions = $("#activity_id option").length - 1; // minus Select All
            //         let totalSelected = selectedValues.length;

            //         if (totalSelected === totalOptions) {
            //             return "Select All";
            //         }

            //         return data.text;
            //     }
            // });

            $("#activity_id").select2({
                placeholder: "Select Activity",
                closeOnSelect: false,
                allowClear: true
            });


            let all_activites = @json($activities);
            $("#activity_group_name_id").change(function(e) {
                const selectedGroup = $(this).val();
                if (selectedGroup) {
                    $.ajax({
                        url: "{{ route('group_activities') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ @csrf_token() }}",
                            "g_id": selectedGroup
                        },
                        success: function(response) {
                            renderGroupActivities(response.get_group_activities)
                        }
                    })
                }
            })

            $("#template_name_id").change(function(e) {
                const template_name_id = $(this).val();
                const project_id = $("#project_id").val();
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
                            if (response.message == "success") {
                                const pro_temp = response.projectTemplate;
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
                                            'change')
                                    }

                                } else {
                                    $("#error_message").html(
                                        "Project Template Name Mapping is not completed")
                                    $("#errorMessage_modal").modal('show');
                                }
                                render_heads(pro_temp.get_template.get_template_heads);
                            }
                        }
                    })
                }
            })

            function render_heads(heads) {
                let template_name_head_select = $("#template_name_head_id");
                template_name_head_select.empty();
                // const render_skip = ['key_code', 'key_name']; // this is to skip the selection of key_code and key_name
                //khushboo 17-05-25
                const render_skip = ['latitude', 'longitude', 'Lat', 'Long', 'Latitude', 'Longitude', 'lat',
                'long'];
                template_name_head_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr(
                    'selected', 'selected').val(''));
                $.each(heads, function(index, value) {
                    if ($.inArray(value.template_head_name, render_skip) === -1) {
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

            // function renderGroupActivities(activities) {
            //     $("#activites_display").removeClass('d-none');
            //     let activitySelect = $("#activity_id");
            //     activitySelect.empty();
            //     let activity_idArr = [];
            //     $.each(activities, function (index1, group_activity) {
            //         activity_idArr.push(group_activity.activity_id);
            //     })
            //     $.each(all_activites, function (index, activity) {
            //         if ($.inArray(activity.id, activity_idArr) !== -1) {
            //             activitySelect.append($('<option>').text(activity.activity_name).val(activity.id));
            //         }
            //     })

            // }

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

            function renderGroupActivitiessssssss(activities) {

                $("#activites_display").removeClass('d-none');
                let activitySelect = $("#activity_id");
                activitySelect.empty();

                let activity_idArr = [];

                $.each(activities, function(index1, group_activity) {
                    activity_idArr.push(group_activity.activity_id);
                });

                $.each(all_activites, function(index, activity) {
                    if ($.inArray(activity.id, activity_idArr) !== -1) {
                        activitySelect.append(
                            $('<option>')
                            .text(activity.activity_name)
                            .val(activity.id)
                            .attr('selected', 'selected')
                        );
                    }
                });

                $("#activity_id").trigger('change');

                setTimeout(function() {

                    let container = $("#activity_id").next('.select2-container');

                    // hide only this select's tags
                    container.find('.select2-selection__choice').hide();

                    // remove previous custom label
                    container.find('.custom-select-all').remove();

                    // append one clean label
                    container.find('.select2-selection__rendered')
                        .append('<li class="custom-select-all select2-selection__choice">Select All</li>');

                }, 50);
            }

            function render_templates(projectTemplates) {
                let template_name_select = $("#template_name_id");
                template_name_select.empty();
                $.each(projectTemplates, function(index, projectTemplate) {
                    template_name_select.append($('<option>').text(projectTemplate.get_template
                        .template_name).val(projectTemplate.get_template.id));
                });
                $("#template_name_id").trigger('change')
            }

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

            $("#project_id").change(function(e) {
                const selectedProjectId = $(this).val();
                if (selectedProjectId) {
                    $.ajax({
                        url: '{{ route('get_project_info') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedProjectId
                        },
                        success: function(response) {
                            if (response.message == "Success") {
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
            $("#template_name_head_id").change(function(e) {
                const template_name_head_id = $(this).val();
                const project_id = $("#project_id").val();
                const template_name_id = $("#template_name_id").val();
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
