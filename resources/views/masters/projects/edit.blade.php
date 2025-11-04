@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Update Project <a href="{{ route('project.list') }}"><button
                                        class = "btn btn-primary float-end">View Projects</button></a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                aria-labelledby="wizard-info-tab">
                                                <form method="POST"
                                                    action="{{ route('project.update', ['id' => $project->id]) }}"
                                                    enctype="multipart/form-data" class="row g-3 needs-validation"
                                                    novalidate="">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="company_id">Select Company</label>
                                                        <select class="form-select" id="company_id" name="company_id"
                                                            required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($companies as $company)
                                                                <option value="{{ $company->id }}"
                                                                    @if ($project->company_id == $company->id) selected @endif>
                                                                    {{ $company->company_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('company_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="zone_id">Select Zone</label>
                                                        <select class="form-select" id="zone_id" name="zone_id"
                                                            required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($zones as $zone)
                                                                <option value="{{ $zone->id }}"
                                                                    @if ($project->zone_id == $zone->id) selected @endif>
                                                                    {{ $zone->zone_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('zone_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="unit_id">Select Unit</label>
                                                        <select class="form-select" id="unit_id" name="unit_id"
                                                            required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($units as $unit)
                                                                <option value="{{ $unit->id }}"
                                                                    @if ($project->unit_id == $unit->id) selected @endif>
                                                                    {{ $unit->unit_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('unit_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="project_type_id">Select Project
                                                            Type</label>
                                                        <select class="form-select" id="project_type_id"
                                                            name="project_type_id" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($project_types as $project_type)
                                                                <option value="{{ $project_type->id }}"
                                                                    @if ($project_type->id == $project->project_type_id) selected @endif>
                                                                    {{ $project_type->project_type_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('project_type_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>

                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="project_name">Project Name<span
                                                                class="txt-danger">*</span></label>
                                                        <input class="form-control" id="project_name"
                                                            value="{{ $project->project_name }}" name="project_name"
                                                            type="text" placeholder="Enter project name" required="">
                                                        @error('project_name')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="template_name_id">Select Heads
                                                            Template</label>
                                                        <select class="form-select" id="template_name_id"
                                                            name="template_name_id[]" multiple="" required="">
                                                            <option selected="" disabled="" value="">Choose...
                                                            </option>
                                                            @foreach ($template_names as $template_name)
                                                                <option value="{{ $template_name->id }}"
                                                                    @if (in_array($template_name->id, $project->getProjectTemplates->pluck('template_name_id')->toArray())) selected @endif>
                                                                    {{ $template_name->template_name }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('template_name_id')
                                                            <p class="text-red-500 text-xs mt-1">
                                                                {{ $message }}
                                                            </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0 ">
                                                            <input class="form-check-input" name="with_data" id="with_data"
                                                                type="checkbox"
                                                                @if ($project->with_data == 1) checked="" @endif>
                                                            <label class="form-check-label" for="with_data">With Data OR
                                                                Not</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input"
                                                                name="is_application_applicable"
                                                                id="is_application_applicable" type="checkbox"
                                                                @if ($project->is_application_applicable == 1) checked="" @endif>
                                                            <label class="form-check-label"
                                                                for="is_application_applicable">AP Applicable </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="recurring"
                                                                id="recurring" type="checkbox"
                                                                @if ($project->recurring == 1) checked="" @endif>
                                                            <label class="form-check-label" for="recurring">Recurring
                                                                Project or Not</label>
                                                        </div>
                                                    </div>
                                                    {{-- khushboo 01-04-2025 --}}

                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="is_otp_required"
                                                                id="is_otp_required" type="checkbox"
                                                                @if ($project->is_otp_required == 1) checked="" @endif>
                                                            <label class="form-check-label" for="is_otp_required">Is OTP
                                                                Required</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 d-none"
                                                        @if ($project->is_otp_duplication_allowed == 1) @else id="otpDuplicationAllowed" @endif>
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input"
                                                                name="is_otp_duplication_allowed"
                                                                id="is_otp_duplication_allowed" type="checkbox">
                                                            <label class="form-check-label"
                                                                for="is_otp_duplication_allowed"
                                                                @if ($project->is_otp_duplication_allowed == 1) checked="" @endif>Is OTP
                                                                Duplication Allowed</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0"
                                                            @if ($project->isComplianceApplicable == 1) @else  id="isComplianceApplicableDiv" @endif >
                                                            <input class="form-check-input" name="isComplianceApplicable"
                                                                id="isComplianceApplicable" type="checkbox"
                                                                @if ($project->isComplianceApplicable == 1) checked="" @endif>
                                                            <label class="form-check-label"
                                                                for="isComplianceApplicable">Is Non Compliance
                                                                Applicable</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input" name="isCompanyApplicable"
                                                                id="isCompanyApplicable" type="checkbox"
                                                                @if ($project->isCompanyApplicable == 1) checked="" @endif>
                                                            <label class="form-check-label" for="isCompanyApplicable">Is
                                                                Company User Applicable</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-3 col-sm-3 @if ($project->companyVerificationRequired == 1) @else d-none @endif"
                                                        id="companyVerificationDiv">
                                                        <div class="form-check checkbox checkbox-primary mb-0">
                                                            <input class="form-check-input"
                                                                name="companyVerificationRequired"
                                                                id="companyVerificationRequired" type="checkbox"
                                                                @if ($project->companyVerificationRequired == 1) checked="" @endif>
                                                            <label class="form-check-label"
                                                                for="companyVerificationRequired">Company Verification
                                                                Required</label>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="complianceRepeationStartDate"
                                                        id="complianceRepeationStartDate">
                                                    <input type="hidden" name="complianceRepeationEndDate"
                                                        id="complianceRepeationEndDate">
                                                    {{-- khushboo 01-04-2025 --}}
                                                    <div class="col-12 text-end">
                                                        <button class="btn btn-primary">Update Now</button>
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
                                                                    <li> <img
                                                                            src="{{ url('assets/images/gif/danger.gif') }}"
                                                                            alt="error"></li>
                                                                </ul>
                                                                <h4 class="text-center pb-2">Ohh! Something went wrong!
                                                                </h4>
                                                                <p class="text-center" id="error_message"></p>
                                                                <button class="btn btn-secondary d-flex m-auto"
                                                                    type="button" data-bs-dismiss="modal">Close</button>
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

    {{-- Compliance Period Modal --}}
    <div class="modal" tabindex="-1" id="ComplianceRepeationPeriod">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Set Compliance Repeation Period</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 ">
                            <label class="form-label" for="">Start Date</label>
                            <input class="form-control" type="date" placeholder="Start Date" name="startDate"
                                id="startDate" min="{{date('Y-m-d')}}" >
                        </div>
                        <div class="col-md-6 ">
                            <label class="form-label" for="">End Date</label>
                            <input class="form-control" type="date" placeholder="End Date" name="endDate"
                                id="endDate">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="submitCompliancePeriod">Submit</button>
                </div>
            </div>
        </div>
    </div>
    {{-- Compliance Period Modal --}}
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {

            //khushboo 24-04-25
            $('#isCompanyApplicable').on('change', function() {
                console.log($(this).is(':checked'));
                if ($(this).is(':checked')) {
                    $('#companyVerificationDiv').removeClass('d-none');
                } else {
                    $('#companyVerificationDiv').addClass('d-none');
                }
            })
            $('#is_otp_required').on('change', function() {
                console.log($(this).is(':checked'));
                if ($(this).is(':checked')) {
                    $('#otpDuplicationAllowed').removeClass('d-none');
                } else {
                    $('#otpDuplicationAllowed').addClass('d-none');
                }
            })
            //khushboo 24-04-25

            let previous_selected_heads = @json($project->common_heads);
            var changingSelect = false;
            const templateName = $("#template_name_id").select2();
            const common_heads = $("#common_heads").select2();

            // this is for the activityGroup and activity
            $("#activity_group_name_id").change(function() {
                if (!changingSelect) {
                    changingSelect = true;
                    $("#activity_id").val('').trigger('change');
                    changingSelect = false;
                }
            });
            // When template select changes
            $("#activity_id").change(function() {
                if (!changingSelect) {
                    changingSelect = true;
                    $("#activity_group_name_id").val('').trigger('change');
                    changingSelect = false;
                }
            });

            function render_zones(zones) {
                let zone_select = $("#zone_id");
                zone_select.empty();
                zone_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected',
                    'selected').val(''));
                $.each(zones, function(index, value) {
                    zone_select.append($('<option>').text(value.zone_name).val(value.id));
                })
            }

            function render_units(units) {
                let unit_select = $("#unit_id");
                unit_select.empty();
                unit_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected',
                    'selected').val(''));
                $.each(units, function(index, value) {
                    unit_select.append($('<option>').text(value.unit_name).val(value.id));
                })
            }
            $("#company_id").change(function(e) {
                const selectedCompanyId = $(this).val();
                if (selectedCompanyId) {
                    $.ajax({
                        url: '{{ route('get_company_zones') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedCompanyId
                        },
                        success: function(response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_zones(response.related_zones);
                            }
                        }
                    })
                }
            })
            $("#zone_id").change(function(e) {
                const selectedZoneId = $(this).val();
                if (selectedZoneId) {
                    $.ajax({
                        url: '{{ route('get_zones_units') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedZoneId
                        },
                        success: function(response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_units(response.related_units);
                            }
                        }
                    })
                }
            })

            function findCommonTemplateHeads(templateArr) {
                // 1. Extract all template_head_name values into a single set
                const allHeads = new Set();
                for (const arr of templateArr) {
                    for (const obj of arr) {
                        allHeads.add(obj.template_head_name);
                    }
                }

                // 2. Iterate through each array and check for presence in all others
                const commonHeads = [];
                for (const head of allHeads) {
                    let isCommon = true;
                    for (const arr of templateArr) {
                        if (!arr.some(obj => obj.template_head_name === head)) {
                            isCommon = false;
                            break;
                        }
                    }
                    if (isCommon) {
                        commonHeads.push(head);
                    }
                }
                return commonHeads;
            }

            function render_common_heads(common_heads_arr) {
                let common_head_select = $("#common_heads");
                common_head_select.empty();
                common_head_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr(
                    'selected', 'selected').val(''));
                $.each(common_heads_arr, function(index, value) {
                    common_head_select.append($('<option>').text(value).val(value));
                })
            }
            // this to get the templateName heads when the template name is selected

            $("#template_name_id").change(function(e) {
                const selected_templates = $(this).val();
                if (selected_templates.length > 1) {
                    $('#isComplianceApplicableDiv').removeClass('d-none');
                }

                if (selected_templates.length > 0) {
                    $.ajax({
                        url: "{{ route('template_name.getHeads') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            'template_names': selected_templates
                        },
                        success: function(response) {
                            const commonTemplateHeads = findCommonTemplateHeads(response
                                .templateArr);
                            if (commonTemplateHeads.length > 0) {
                                render_common_heads(commonTemplateHeads);
                            } else {
                                $("#error_message").html(
                                    "Templates selected Doesn't have nothing in common")
                                $("#errorMessage_modal").modal('show');
                            }
                        }
                    })

                }

            })
            $("#template_name_id").trigger('change');
            // function already_heads_render(previous_selected_heads){
            //
            //   let commonSelect =   $("#common_heads");
            //   commonSelect.empty();
            // $.each(previous_selected_heads, function (index, common_head){
            //     console.log(common_head.head_value)
            //     commonSelect.append($('<option>').text(common_head.head_value).val(common_head.head_value));
            // })
            //     commonSelect.trigger('change');
            // }
            function already_heads_render(previous_selected_heads) {
                let commonSelect = $("#common_heads");
                commonSelect.empty();

                let selectedValues = [];

                $.each(previous_selected_heads, function(index, common_head) {
                    let option = $('<option>').text(common_head.head_value).val(common_head.head_value);
                    option.prop('selected', true);
                    commonSelect.append(option);
                    selectedValues.push(common_head.head_value);
                });

                setTimeout(function() {
                    commonSelect.val(selectedValues).trigger('change');
                }, 1000); // 1000 milliseconds delay
            }
            already_heads_render(previous_selected_heads);


            $('#isComplianceApplicable').on('change', function() {
                console.log($(this).is(':checked'));
                if ($(this).is(':checked')) {
                    $('#ComplianceRepeationPeriod').modal('show');
                } else {

                }
            });

            $('#submitCompliancePeriod').on('click', function() {
                $('#complianceRepeationEndDate').val($('#startDate').val());
                $('#complianceRepeationStartDate').val($('#endDate').val());
            });


        })
    </script>
@endsection
