@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            {{-- <h4>View Project Uploaded Data <a href="{{route('templateName.list')}}"><button class = "btn btn-primary float-end">View Data Templates</button></a></h4> --}}
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                aria-labelledby="wizard-info-tab">
                                                <div class="row">
                                                    <div class="col-xl-6 col-sm-6">
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
                                                    <div class="col-xl-4 col-sm-4 d-none" id="master_temp_con">
                                                        <label class="form-label" for="master_template_id">Master
                                                            Template</label>
                                                        <select class="form-select" id="master_template_id"
                                                            name="master_template_id" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                        </select>
                                                        @error('master_template_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-2 col-sm-2 mt-4 d-none" id="master_temp_btn_con">
                                                        <button class="btn btn-sm btn-primary" id="make_ptm">Make
                                                            Master
                                                        </button>
                                                        @error('make_pt_master')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div class="mapping_con mt-4">
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary" id="map_btn">Map Now</button>
                                                </div>
                                            </div>
                                        </div>
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
                                                            <li><img src="{{ url('assets/images/gif/danger.gif') }}"
                                                                    alt="error"></li>
                                                        </ul>
                                                        <h4 class="text-center pb-2">Ohh! Something went wrong!</h4>
                                                        <p class="text-center" id="error_message"></p>
                                                        <button class="btn btn-secondary d-flex m-auto" type="button"
                                                            data-bs-dismiss="modal">Close
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
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $('#project_id').select2();
        $(document).ready(function() {
            // Add event listeners to the newly added select elements
            $(".activity-select").change(function() {
                const groupActivitySelect = $(this).closest('.row').find('.group-activity-select');
                groupActivitySelect.val('').prop('selected', false); // Unselect the group activity
            });

            $(".group-activity-select").change(function() {
                const activitySelect = $(this).closest('.row').find('.activity-select');
                activitySelect.val('').prop('selected', false); // Unselect the activity
            });

            function render_templates(projectTemplates, masterTemp, isNonComplianceApplicable) {
                // Clear the existing templates if needed
                $(".mapping_con").empty();
                const masterTemplate = masterTemp;
                const masterHeads = masterTemplate.get_template.get_template_heads;
                projectTemplates.forEach(template => {
                    const actType = template.activityType;
                    const preVal = template.activity_group_name_id_or_activity_id;
                    const is_master_row = template.is_master ? "bg-secondary" : "";
                    const templateHtml = `
                <div class="row ${is_master_row} mb-4 p-2">
                    <div class="col-md-4 col-sm-4">
                        <label class="form-label" for="template_name_id_${template.id}">Select Data Template</label>
                        <select class="form-select template_name-select" id="template_name_id_${template.id}" name="template_name_id" required>
                            <option selected disabled value="">Choose...</option>
                            <option value="${template.get_template.id}" selected="">${template.get_template.template_name}</option>
                        </select>
                    </div>
                    <div class="col-md-4 col-sm-4">
                        <label class="form-label" for="activity_id${template.id}">Select Activity</label>
                        <select class="form-select activity-select" id="activity_id${template.id}" name="activity_id" required>
                            <option selected disabled value="">Choose...</option>
                            @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->activity_name }}</option>
                            @endforeach
                        </select>
                    @error('project_id')
                    <p class="text-red-500 text-xs mt-1">
                    {{ $message }}
                    </p>
                    @enderror
                    </div>
                    <div class="col-md-4 col-sm-4">
                        <label class="form-label" for="group_project_id_${template.id}">Select Group Activity</label>
                        <select class="form-select group-activity-select" id="group_project_id_${template.id}" name="group_project_id" required>
                            <option selected disabled value="">Choose...</option>
                            @foreach ($group_activities as $group_activity)
                            <option value="{{ $group_activity->id }}">{{ $group_activity->activity_group_name }}</option>
                            @endforeach
                        </select>
                    @error('group_project_id')
                    <p class="text-red-500 text-xs mt-1">
                    {{ $message }}
                    </p>
                    @enderror
                    </div>
                    {{--  khushboo 05-04-2025 --}}
                    <div class="col-md-4 col-sm-4 d-none activitOtpId" >
                        <label class="form-label" for="activity_otp_id${template.id}">Select Activity OTP</label>
                        <select class="form-select otp-Activity-select otp-activity-select${template.id}" id="activity_otp_id${template.id}" name="activity_otp_id[]" required multiple >
                            <option selected disabled value="">Choose...</option>
                            @foreach ($activities as $activity)
                            <option value="{{ $activity->id }}">{{ $activity->activity_name }}</option>
                            @endforeach
                        </select>
                    @error('activity_otp_id')
                    <p class="text-red-500 text-xs mt-1">
                    {{ $message }}
                    </p>
                    @enderror
                    </div>
                    {{--  khushboo 05-04-2025 --}}
                    <div class="col-md-4 col-sm-4">
                            <label class="form-label" for="master_template_head${template.id}">Master Template Head<span class="txt-danger"></span></label>
                                <select class="form-select master_template_head" id="master_template_head${template.id}" name="master_template_head" required>
                            <option selected disabled value="">Choose...</option>
                    </select>
                    </div>
                    <div class="col-md-4 col-sm-4">
                            <label class="form-label" for="own_template_head${template.id}">Own Template Head<span class="txt-danger"></span></label>
                                <select class="form-select own_template_head" id="own_template_head${template.id}" name="own_template_head" required>
                                    <option selected disabled value="">Choose...</option>
                                </select>
                    </div>
                    <div class="col-md-4 col-sm-4">
                            <label class="form-label" for="main_header${template.id}">Main Header<span class="txt-danger"></span></label>
                                <select class="form-select main_header" id="main_header${template.id}" name="main_header" required>
                                    <option selected disabled value="">Choose...</option>
                                </select>
                    </div>
                    <div class="col-md-4 col-sm-4">
                            <label class="form-label" for="sub_header${template.id}">Sub Header<span class="txt-danger"></span></label>
                                <select class="form-select sub_header" id="sub_header${template.id}" name="sub_header" required>
                                    <option selected disabled value="">Choose...</option>
                                </select>
                    </div>
                    <div class="col-md-4 col-sm-4 ${template.is_master == 1 && isNonComplianceApplicable ? '' : 'd-none'} ">
                            <label class="form-label" for="compliance_column${template.id}">Compliance Column<span class="txt-danger"></span></label>
                                <select class="form-select compliance_column" id="compliance_column${template.id}" name="compliance_column" required>
                                    <option selected disabled value="">Choose...</option>
                                </select>
                    </div>
                      <div class="col-md-2 col-sm-2">
                            <label class="form-label" for="completion_type_${template.id}">Completion Type<span class="txt-danger"></span></label>
                                <select class="form-select complete_type_select" id="completion_type_${template.id}" name="completion_type" required>
                            <option selected disabled value="">Choose...</option>
                            <option value="Percentage">Percentage</option>
                            <option value="Number">Number</option>
                    </select>
                    </div>
                      <div class="col-md-2 col-sm-2">
                                                    <label class="form-label" for="min_completion_${template.id}">Minimum&nbsp;Completion<span class="txt-danger"></span></label>
                                                    <input class="form-control min_completion" id="min_completion_${template.id}" value="" name="min_completion" type="number" placeholder="Minimum Completion" required="">

                    </div>
                     <div class="col-xl-3 col-sm-3 ">
                                                    <div class="form-check checkbox checkbox-primary mb-0">
                                                        <input class="form-check-input data_add_on" name="data_add_on" id="data_add_on_${template.id}" type="checkbox">
                                                        <label class="form-check-label" for="data_add_on_${template.id}">Data Add On</label>
                                                        </div>
                                                </div>
                    <div class="col-xl-3 col-sm-3 ${template.is_master == 1 ? '' : 'd-none'}">
                                                    <div class="form-check checkbox checkbox-primary mb-0">
                                                        <input class="form-check-input activity_add_on" name="activity_add_on" id="activity_add_on_${template.id}" type="checkbox">
                                                        <label class="form-check-label" for="activity_add_on_${template.id}">Activity Add On</label>
                                                        </div>
                                                </div>
                    <div class="col-md-4 col-sm-6 activity-addon-select-wrap d-none" id="addon_wrap_${template.id}">
                        <label class="form-label">Select Activities for Add On</label>
                        <select class="form-select activity-addon-select" id="activity_addon_select_${template.id}" multiple>
                        </select>
                    </div>
                    <div class="col-xl-3 col-sm-3 ">
                                                    <div class="form-check checkbox checkbox-primary mb-0">
                                                        <input class="form-check-input with_data" name="with_data" id="with_data_${template.id}" type="checkbox">
                                                        <label class="form-check-label" for="with_data_${template.id}">With Data</label>
                                                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-3 ">
                                                    <div class="form-check checkbox checkbox-primary mb-0">
                                                        <input class="form-check-input can_edit_data" name="can_edit_data" id="can_edit_data_${template.id}" type="checkbox" >
                                                        <label class="form-check-label" for="can_edit_data_${template.id}">Can Edit Data</label>
                                                    </div>
                    </div>


</div> <hr>`;
                    $(".mapping_con").append(templateHtml);


                    //khushboo 05-04-2025
                    $(`.otp-activity-select${template.id}`).select2();

                    //khushboo 05-04-2025

                    let min_completion_check = $(`#min_completion_${template.id}`);
                    let completion_type__check = $(`#completion_type_${template.id}`);
                    if (!is_master_row) {
                        min_completion_check.parent().remove();
                        completion_type__check.parent().remove();
                    }

                    if (actType == 0 && preVal !== "") {
                        // console.log(preVal);
                        $(`#activity_id${template.id}`).val(preVal);
                        $(`#activity_otp_id${template.id}`).val(preVal).trigger('change');
                    } else if (actType == 1 && preVal !== "") {
                        $(`#group_project_id_${template.id}`).val(preVal).trigger('change');
                    }
                    if (template.data_add_on == 1) {
                        $(`#data_add_on_${template.id}`).prop("checked", true);
                    }
                    if (template.activity_add_on == 1) {
                        $(`#activity_add_on_${template.id}`).prop("checked", true);
                    }

                    // Helper: load group activities into the add-on select with Select2
                    function loadAddonActivities(templateId, groupId, selectedIds, callback) {
                        $.get('{{ url("/group_activities") }}/' + groupId, function(activities) {
                            const sel = $(`#activity_addon_select_${templateId}`);
                            // Destroy existing Select2 instance before clearing options
                            if (sel.hasClass('select2-hidden-accessible')) {
                                sel.select2('destroy');
                            }
                            sel.empty();
                            activities.forEach(function(a) {
                                sel.append($('<option>').val(a.id).text(a.name));
                            });
                            // Init Select2 then apply saved selections
                            sel.select2({
                                placeholder: 'Select activities...',
                                width: '100%',
                                closeOnSelect: false
                            });
                            if (selectedIds && selectedIds.length > 0) {
                                sel.val(selectedIds.map(String)).trigger('change');
                            }
                            if (callback) callback();
                        });
                    }

                    // Show/hide addon select based on group + checkbox state
                    function toggleAddonWrap(templateId) {
                        const groupVal = $(`#group_project_id_${templateId}`).val();
                        const checked = $(`#activity_add_on_${templateId}`).is(':checked');
                        if (groupVal && checked) {
                            $(`#addon_wrap_${templateId}`).removeClass('d-none');
                        } else {
                            $(`#addon_wrap_${templateId}`).addClass('d-none');
                        }
                    }

                    $(`#group_project_id_${template.id}`).on('change', function() {
                        const gid = $(this).val();
                        if (gid && $(`#activity_add_on_${template.id}`).is(':checked')) {
                            loadAddonActivities(template.id, gid, [], function() {
                                $(`#addon_wrap_${template.id}`).removeClass('d-none');
                            });
                        } else {
                            $(`#addon_wrap_${template.id}`).addClass('d-none');
                        }
                    });

                    $(`#activity_add_on_${template.id}`).on('change', function() {
                        const gid = $(`#group_project_id_${template.id}`).val();
                        if ($(this).is(':checked') && gid) {
                            loadAddonActivities(template.id, gid, [], function() {
                                $(`#addon_wrap_${template.id}`).removeClass('d-none');
                            });
                        } else {
                            $(`#addon_wrap_${template.id}`).addClass('d-none');
                        }
                    });

                    // Preload saved addon activity ids when editing
                    if (template.activity_add_on == 1 && actType == 1 && preVal !== "") {
                        const savedIds = template.activity_add_on_activity_ids ? JSON.parse(template.activity_add_on_activity_ids) : [];
                        loadAddonActivities(template.id, preVal, savedIds.map(Number), function() {
                            $(`#addon_wrap_${template.id}`).removeClass('d-none');
                        });
                    }

                    if (template.with_data == 1) {
                        $(`#with_data_${template.id}`).prop("checked", true);
                    }else{
                        $(`#with_data_${template.id}`).prop("checked", false);
                    }
                    if (template.can_edit_data == 1) {
                        $(`#can_edit_data_${template.id}`).prop("checked", true);
                    }else{
                        $(`#can_edit_data_${template.id}`).prop("checked", false);
                    }
                    const ownHeadSelect = $(`#own_template_head${template.id}`);
                    const mainHeaderSelect = $(`#main_header${template.id}`);
                    const subHeaderSelect = $(`#sub_header${template.id}`);
                    //khushboo 02-05-2025
                    const complianceColumnSelect = $(`#compliance_column${template.id}`);
                    //khushboo 02-05-2025
                    mainHeaderSelect.empty();
                    subHeaderSelect.empty();
                    ownHeadSelect.empty();
                    //khushboo 02-05-2025
                    complianceColumnSelect.empty();
                    //khushboo 02-05-2025
                    // const render_not = ['key_code', 'key_name'];
                    const render_not = ['latitude', 'longitude', 'Lat', 'Long', 'Latitude', 'Longitude', 'lat', 'long'];
                    const templateHeads = template.get_template.get_template_heads;
                    ownHeadSelect.append($("<option>").text('Select Template Head').val('').prop('selected',
                        true));
                    mainHeaderSelect.append($("<option>").text('Select Main Header').val('').prop(
                        'selected', true));
                    subHeaderSelect.append($("<option>").text('Select Sub Header').val('').prop('selected',
                        true));
                    //khushboo 02-05-2025
                    complianceColumnSelect.append($("<option>").text('Select Column').val('').prop(
                        'selected',
                        true));
                    //khushboo 02-05-2025
                    $.each(templateHeads, function(index, head) {
                        const cleanValue = head.template_head_name.trim().replace(/\s+/g, '');
                        if ($.inArray(cleanValue, render_not) === -1) {
                            if (template.own_reference_head_id == head.id) {
                                ownHeadSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id).prop('selected', true))
                            } else {
                                ownHeadSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id))
                            }
                            if (template.main_header == head.id) {
                                mainHeaderSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id).prop('selected', true))
                            } else {
                                mainHeaderSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id))
                            }
                            if (template.sub_header == head.id) {
                                subHeaderSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id).prop('selected', true))
                            } else {
                                subHeaderSelect.append($("<option>").text(head.template_head_name)
                                    .val(head.id))
                            }

                            if (isNonComplianceApplicable) {
                                if (template.sub_header == head.id) {
                                    complianceColumnSelect.append($("<option>").text(head
                                            .template_head_name)
                                        .val(head.id).prop('selected', true))
                                } else {
                                    complianceColumnSelect.append($("<option>").text(head
                                            .template_head_name)
                                        .val(head.id))
                                }

                            }
                        }
                    })
                    const masterHeadSelect = $(`#master_template_head${template.id}`);
                    masterHeadSelect.empty();
                    masterHeadSelect.append($("<option>").text('Select Master Head').val('').prop(
                        'selected', true));
                    $.each(masterHeads, function(index, masterHead) {
                        if ($.inArray(masterHead.template_head_name, render_not) === -1) {
                            if (template.master_head_id == masterHead.id) {
                                masterHeadSelect.append($("<option>").text(masterHead
                                    .template_head_name).val(masterHead.id).prop("selected",
                                    true));
                            } else {
                                masterHeadSelect.append($("<option>").text(masterHead
                                    .template_head_name).val(masterHead.id));
                            }
                        }
                    })
                    if (is_master_row) {
                        masterHeadSelect.closest('div').remove();
                        ownHeadSelect.closest('div').remove();
                    }
                    $(`#completion_type_${template.id}`).val(template.completion_type);
                    $(`#min_completion_${template.id}`).val(template.min_completion);
                });
                // Add event listeners to the newly added select elements
                $(".activity-select").change(function() {
                    const groupActivitySelect = $(this).closest('.row').find('.group-activity-select');
                    groupActivitySelect.val('').prop('selected', false); // Unselect the group activity
                });
                $(".group-activity-select").change(function() {
                    const activitySelect = $(this).closest('.row').find('.activity-select');
                    activitySelect.val('').prop('selected', false); // Unselect the activity
                });

            }

            function render_templates_master(projectTemplates) {
                const master_template_select = $("#master_template_id");
                master_template_select.empty();
                $.each(projectTemplates, function(index, value) {
                    master_template_select.append($('<option>').text(value.get_template.template_name).val(
                        value.get_template.id))
                })
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
                            // console.log(response);
                            if (response.message == "Success") {

                                if (response.projectMasterInfo) {
                                    render_templates(response.projectTemplateNames, response
                                        .projectMasterInfo, response.isNonComplianceApplicable);
                                    //khushboo 05-04-2025
                                    if (response.otpProjectActivities) {
                                        let maincontainer = document.getElementsByClassName(
                                            'activitOtpId');
                                        if (maincontainer.length > 0) {
                                            Array.from(maincontainer).forEach((el) => {
                                                el.classList.remove('d-none');
                                            });
                                        }
                                    } else {
                                        let maincontainer = document.getElementsByClassName(
                                            'activitOtpId');
                                        if (maincontainer.length > 0) {
                                            Array.from(maincontainer).forEach((el) => {
                                                el.classList.add('d-none');
                                            });
                                        }
                                    }
                                    //khushboo 05-04-2025
                                    $("#master_template_id").empty();
                                    $("#master_temp_con").addClass('d-none');
                                    $("#master_temp_btn_con").addClass('d-none');
                                } else {
                                    $("#master_temp_con").removeClass('d-none');
                                    $("#master_temp_btn_con").removeClass('d-none');
                                    $(".mapping_con").empty();
                                    render_templates_master(response.projectTemplateNames);
                                }
                            }
                        }
                    })
                }
            })
            $("#map_btn").click(function() {
                const p_id = $("#project_id").val();
                if (p_id) {
                    const projectTemplateActivityMapper = [];
                    $(".mapping_con .row").each(function() {
                        const row = $(this);
                        const templateNameId = row.find(".template_name-select").val();
                        const completionType = row.find(".complete_type_select").val() ? row.find(
                            ".complete_type_select").val() : null;
                        const min_completion = row.find(".min_completion").val() ? row.find(
                            ".min_completion").val() : null;
                        const activityId = row.find(".activity-select").val();
                        //khushboo 05-04-2025
                        const otpactivityId = row.find(".otp-Activity-select").val();
                        // console.log($(".otp-Activity-select"));
                        //khushboo 05-04-2025
                        const activity_groupId = row.find(".group-activity-select").val();
                        const master_headId = row.find(".master_template_head").val() ? row.find(
                            ".master_template_head").val() : null;
                        const own_headId = row.find(".own_template_head").val() ? row.find(
                            ".own_template_head").val() : null;
                        const sub_headerId = row.find(".sub_header").val() ? row.find(".sub_header")
                            .val() : null;
                        const main_headerId = row.find(".main_header").val() ? row.find(
                            ".main_header").val() : null;
                        const is_data_add_on = row.find(".data_add_on").is(":checked") ? 1 : 0;
                        const is_activity_add_on = row.find(".activity_add_on").is(":checked") ? 1 : 0;
                        const addon_activity_ids = row.find(".activity-addon-select").val() || [];
                        const is_with_data = row.find(".with_data").is(":checked") ? 1 : 0;
                        const is_can_edit_data = row.find(".can_edit_data").is(":checked") ? 1 : 0;

                        //khushboo 02-05-2025
                        const compliance_column_Id = row.find(".compliance_column").val() ? row
                            .find(
                                ".compliance_column").val() : null;
                        //khushboo 02-05-2025
                        console.log(row.find(".compliance_column"), row.find(".compliance_column")
                            .val());

                        projectTemplateActivityMapper.push({
                            'templateNameId': templateNameId,
                            'activityId': activityId,
                            'activity_groupId': activity_groupId,
                            'completion_type': completionType,
                            'min_completion': min_completion,
                            'data_add_on': is_data_add_on,
                            'activity_add_on': is_activity_add_on,
                            'activity_add_on_activity_ids': addon_activity_ids,
                            'with_data': is_with_data,
                            'can_edit_data': is_can_edit_data,
                            'master_head': master_headId,
                            'own_head': own_headId,
                            'sub_header': sub_headerId,
                            'main_header': main_headerId,
                            'otpactivityId': otpactivityId,
                            'complianceColumnId': compliance_column_Id,
                        })
                    });
                    // console.log(projectTemplateActivityMapper);
                    $.ajax({
                        url: "{{ route('project.data.activityGroup.map') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ @csrf_token() }}",
                            "project_id": p_id,
                            "mapper_data": projectTemplateActivityMapper
                        },
                        success: function(response) {
                            if (response == "success") {
                                Swal.fire({
                                    title: 'Success!',
                                    text: 'Mapped Successfully.',
                                    icon: 'success',
                                    timer: 2000, // Reload automatically after 2 seconds
                                    timerProgressBar: true, // Show a progress bar for the timer
                                    showConfirmButton: false, // Hide the confirmation button
                                    allowOutsideClick: false // Prevent dismissal by clicking outside the modal
                                }).then(() => {
                                    location.reload(); // Reload the page
                                });
                            }
                        }
                    })
                } else {
                    alert('Please select project');
                }
            })
            $("#make_ptm").click(function() {
                const p_id = $("#project_id").val();
                const mt = $("#master_template_id").val();
                if (p_id) {
                    if (mt) {
                        $.ajax({
                            url: "{{ route('project.master_template.make') }}",
                            type: "POST",
                            data: {
                                "_token": '{{ csrf_token() }}',
                                "p_id": p_id,
                                "mt_id": mt
                            },
                            success: function(response) {
                                if (response.message == "Success") {
                                    Swal.fire({
                                        position: "top-center",
                                        icon: "success",
                                        title: "Project Master Template Created Successfully",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    if (response.projectMasterInfo) {
                                        render_templates(response.projectTemplateNames, response
                                            .projectMasterInfo, response
                                            .isNonComplianceApplicable);
                                        $("#master_template_id").empty();
                                        $("#master_temp_con").addClass('d-none');
                                        $("#master_temp_btn_con").addClass('d-none')
                                    } else {
                                        $("#master_temp_con").removeClass('d-none');
                                        $("#master_temp_btn_con").removeClass('d-none');
                                        $(".mapping_con").empty();
                                        render_templates_master(response.projectTemplateNames);
                                    }
                                    //khushboo 05-04-2025
                                    if (response.otpProjectActivities) {
                                        let maincontainer = document.getElementsByClassName(
                                            'activitOtpId');
                                        if (maincontainer.length > 0) {
                                            Array.from(maincontainer).forEach((el) => {
                                                el.classList.remove('d-none');
                                            });
                                        }
                                    } else {
                                        let maincontainer = document.getElementsByClassName(
                                            'activitOtpId');
                                        if (maincontainer.length > 0) {
                                            Array.from(maincontainer).forEach((el) => {
                                                el.classList.add('d-none');
                                            });
                                        }
                                    }
                                    //khushboo 05-04-2025
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error(error);
                            }

                        })
                    } else {
                        $("#error_message").text('Select Template Name to make master');
                        $("#errorMessage_modal").modal('show');
                    }
                } else {
                    $("#error_message").text('Select Project');
                    $("#errorMessage_modal").modal('show');
                }
            })
        })
    </script>
@endsection
