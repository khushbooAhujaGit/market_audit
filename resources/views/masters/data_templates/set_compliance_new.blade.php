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
                            <h4>Set Compliance
                                <button class="btn btn-primary float-end" id="openComplianceModal">Add New</button>
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="project_id" class="form-label">Select Project</label>
                                    <select id="project_id" class="form-select" onchange="getProjectTemplate()">
                                        <option disabled selected>Choose Project</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}">{{ $project->project_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="template_name_id">Select Data Template</label>
                                    <select class="form-select template_name_id" id="template_name_id"
                                        name="template_name_id" required="">
                                        <option selected="" disabled="" value="">Choose...
                                        </option>

                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">

                            </div>

                            <div id="complianceCards" class="row g-3"></div>
                            <div class="row text-end d-none" id="buttonDiv">
                                <button class="btn btn-primary" id="submitButton" onclick="submitData()">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="complianceModal" tabindex="-1" aria-labelledby="complianceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="complianceModalLabel">Add/Edit Compliance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="complianceForm">
                        <input type="hidden" id="editIndex">
                        <div class="mb-3">
                            <label for="activity_id" class="form-label">Select Activity</label>
                            <select id="activity_id" name="activity_id" class="form-select" required ></select>
                        </div>
                        <div class="mb-3">
                            <label for="question_id" class="form-label">Select Question</label>
                            <select id="question_id" name="question_id" class="form-select" required ></select>
                        </div>
                        <div class="mb-3">
                            <label for="compliance_value" class="form-label">Compliance Value</label>
                            <select id="compliance_value" name="compliance_value" class="form-select" required></select>
                        </div>
                        <div class="mb-3 d-none" id="subjectiveContainer">
                            <label for="subjective_value" class="form-label">Subjective Value</label>
                            <select id="subjective_value" name="subjective_value" class="form-select"></select>
                        </div>
                        <div class="mb-3">
                            <label for="threshold" class="form-label">Select Compliance Threshold Type</label>
                            <select class="form-select" name="threshold_type" id="threshold_type" required>
                                <option value="percent">Percent</option>
                                <option value="number">Number</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="threshold" class="form-label">Non Compliance Threshold</label>
                            <input type="number" class="form-control" id="threshold" name="threshold"
                                placeholder="e.g. 50%" >
                        </div>
                        <div class="mb-3">
                            <label for="remark" class="form-label">Non Compliance Remark</label>
                            <input type="text" class="form-control" id="remark" name="remark"
                                placeholder="e.g. Branding not properly done">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveCompliance">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // $("#template_name_id").select2();
        // $("#project_id").select2();
        $('.loader-backdrop').addClass('d-none');
        let complianceData = [];
        let cachedActivities = {};

        function getProjectTemplate() {
            let projectId = $('#project_id').val();
            // const getQuestionsBaseUrl = "{{ url('/get-project-templates') }}";
            $.ajax({
                url: "{{ route('getProjectTemplate') }}",
                method: "POST",
                data: {
                    'projectId': projectId,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function(response) {
                    console.log(response);
                    $('#template_name_id').html('');
                    if (response.status) {
                        let data = response.data;
                        let options = '<option selected="" disabled="" value="" >Choose...</option>';
                        response.data.forEach(val => {
                            options +=
                                `<option value="${val.get_template.id}">${val.get_template.template_name}</option>`;
                        });
                        $('#template_name_id').append(options);
                    } else {
                        Swal.fire({
                            position: "center",
                            icon: "warning",
                            title: "Project Mapping is Not Done",
                            timer: 3000,
                            timerProgressBar: true
                        });
                    }
                }
            });
        }

        $(document).ready(function() {
            $('.loader-backdrop').addClass('d-none');
            $('#openComplianceModal').click(function() {

                const projectId = $('#project_id').val();
                if (!projectId) {
                    alert('Please select a project first.');
                    return;
                }

                const projectTemplateId = $('#template_name_id').val();
                if (!projectTemplateId) {
                    alert('Please select a project Template.');
                    return;
                }

                resetModal();
                $('#complianceModal').modal('show');

                if (cachedActivities[projectId] && cachedActivities[projectId].length > 0) {
                    populateActivityDropdown(projectId);
                } else {
                    fetchActivities(projectId, projectTemplateId, function() {
                        populateActivityDropdown(projectId);
                    });
                }
                // if(cachedActivities[complianceId] && cachedActivities[complianceId].length > 0){
                //     populateComplianceData(complianceId);
                // }
            });

            $('#template_name_id').change(function() {
                const projectId = $('#project_id').val();
                const projectTemplateId = $(this).val();
                resetModal();
                $('#complianceModal').modal('show');

                // Always fetch new data when project changes
                fetchActivities(projectId, projectTemplateId, function() {
                    populateActivityDropdown(projectId);

                });
            });

            $('#activity_id').change(function() {
                fetchQuestions($('#project_id').val(), $(this).val(), '', $('#template_name_id').val());
            });

            $('#question_id').change(function() {
                handleQuestionType($(this).val());
            });

            $('#compliance_value').change(function() {
                if ($('#compliance_value option:selected').data('has-subjective')) {
                    $('#subjectiveContainer').removeClass('d-none');
                    fetchSubjectiveOptions();
                } else {
                    $('#subjectiveContainer').addClass('d-none');
                }
            });

            $('#saveCompliance').click(function() {
                saveCompliance();
            });

        });

        function resetModal() {
            $('#editIndex').val('');
            $('#activity_id').html('<option selected disabled>Loading...</option>');
            $('#question_id').html('<option selected disabled>Select Activity First</option>');
            $('#compliance_value').html('<option selected disabled>Select Question First</option>');
            $('#subjective_value').html('');
            $('#threshold').val('');
            $('#remark').val('');
            $('#subjectiveContainer').addClass('d-none');
        }


        let complianceIndex = 0;

        function fetchActivities(projectId, projectTemplateId, callback = () => {}) {
            console.log(projectId, projectTemplateId);
            $.ajax({
                url: '{{ route('get_project_activities') }}',
                method: 'POST',
                data: {
                    "_token": "{{ csrf_token() }}",
                    "id": projectId,
                    "template_id": projectTemplateId
                },
                success: function(response) {
                    console.log("Compliance Response:", response);

                    if (!response.status || !response.data || !response.data.length) {
                        alert(`${response.message || 'No activities found.'}`);
                        $('#activity_id').html('<option selected disabled>No Activity Found</option>');
                        return;
                    }

                    cachedActivities[projectId] = response.data;
                    console.log(response.complianceData.length, complianceData.length);

                    // If compliance data exists, load it into UI
                    if (Array.isArray(response.complianceData) && response.complianceData.length > 0) {
                        const container = $('#complianceCards');
                        container.empty(); // clear old

                        if (response.complianceData.length > complianceData.length) {

                            complianceData = []; // reset
                            complianceIndex = 0;

                            response.complianceData.forEach((item, index) => {
                                // console.log(item);
                                const cardData = {
                                    project_id: item.project_id,
                                    template_id: item.project_template_id,
                                    template_name: item.template_data.template_name,
                                    activity_id: item.activity_id,
                                    question_id: item.question_id,
                                    compliance_value: item.user_answer,
                                    threshold_value: item.compliance_threshold,
                                    threshold_type: item.threshold_type,
                                    remark: item.compliance_remark,
                                    activity_name: item.activity_data?.activity_name || '',
                                    question_text: item.question_data?.question || '',
                                    subjective_value: item.subjective_value || null,
                                    subjective_value_name: item.subjective_value_name || ''
                                };

                                complianceData.push(cardData); // store it

                                container.append(`
                        <div class="row mb-4 mt-3 p-4 bg-secondary border rounded shadow-sm position-relative" style="min-height: 250px; border-left: 5px solid #0d6efd;">
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Template</label>
                                <input type="text" class="form-control" value="${cardData.template_name}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Activity</label>
                                <input type="text" class="form-control" value="${cardData.activity_name}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Question</label>
                                <input type="text" class="form-control" value="${cardData.question_text}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance</label>
                                <input type="text" class="form-control" value="${cardData.compliance_value}" readonly>
                            </div>
                            ${cardData.subjective_value ? `
                                                                            <div class="col-md-4 col-sm-6 mb-3">
                                                                                <label class="form-label fw-bold text-white">Subjective Dropdown</label>
                                                                                <input type="text" class="form-control" value="${cardData.subjective_value_name}" readonly>
                                                                            </div>` : ''}
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Threshold Type</label>
                                <input type="text" class="form-control" value="${cardData.threshold_type}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Threshold</label>
                                <input type="text" class="form-control" value="${cardData.threshold_value}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Remark</label>
                                <input type="text" class="form-control" value="${cardData.remark}" readonly>
                            </div>

                            <div class="position-absolute d-flex gap-2 justify-content-end p-2" style="bottom: 0; right: 0;">
                                <button type="button" class="btn btn-sm btn-outline-white" onclick="editCompliance(${index})">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-white" onclick="deleteCompliance(${index})">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </div>
                        </div>
                    `);
                            });

                        } else {

                            complianceData.forEach((item, index) => {
                                // console.log(item);

                                container.append(`
                        <div class="row mb-4 mt-3 p-4 bg-secondary border rounded shadow-sm position-relative" style="min-height: 250px; border-left: 5px solid #0d6efd;">
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Template</label>
                                <input type="text" class="form-control" value="${item.template_name}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Activity</label>
                                <input type="text" class="form-control" value="${item.activity_name}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Selected Question</label>
                                <input type="text" class="form-control" value="${item.question_text}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance</label>
                                <input type="text" class="form-control" value="${item.compliance_value}" readonly>
                            </div>
                            ${item.subjective_value ? `
                                                                        <div class="col-md-4 col-sm-6 mb-3">
                                                                            <label class="form-label fw-bold text-white">Subjective Dropdown</label>
                                                                            <input type="text" class="form-control" value="${item.subjective_value_name}" readonly>
                                                                        </div>` : ''}
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Threshold Type</label>
                                <input type="text" class="form-control" value="${item.threshold_type}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Threshold</label>
                                <input type="text" class="form-control" value="${item.threshold_value}" readonly>
                            </div>
                            <div class="col-md-4 col-sm-6 mb-3">
                                <label class="form-label fw-bold text-white">Non Compliance Remark</label>
                                <input type="text" class="form-control" value="${item.remark}" readonly>
                            </div>

                            <div class="position-absolute d-flex gap-2 justify-content-end p-2" style="bottom: 0; right: 0;">
                                <button type="button" class="btn btn-sm btn-outline-white" onclick="editCompliance(${index})">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-white" onclick="deleteCompliance(${index})">
                                    <i class="bi bi-trash3"></i> Delete
                                </button>
                            </div>
                        </div>
                    `);
                            });
                        }


                    }

                    if (complianceData.length == 0) {
                        $('#buttonDiv').addClass('d-none');
                    } else {
                        $('#buttonDiv').removeClass('d-none');
                    }

                    callback(); // execute callback
                },
                error: function(xhr) {
                    console.error("AJAX Error:", xhr.responseText);
                }
            });
        }


        function populateActivityDropdown(projectId) {
            const activities = cachedActivities[projectId] || [];
            let options = '<option selected disabled>Select Activity</option>';
            activities.forEach(activity => {
                options += `<option value="${activity.id}" >${activity.activity_name}</option>`;
            });
            $('#activity_id').html(options);
        }

        function fetchQuestions(projectId, activityId, questionId = null, templateId) {
            const getQuestionsBaseUrl = "{{ url('/get-questions') }}";

            $.ajax({
                url: "{{ route('get-questions') }}",
                method: 'POST',
                data: {
                    'activity_id': activityId,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function(response) {
                    if (!response.status || !response.data || !response.data.length) {
                        alert('No questions found for the selected activity.');
                        $('#question_id').html('<option selected disabled>No Question Found</option>');
                        return;
                    }

                    let options = '<option selected disabled>Select Question</option>';

                    response.data.forEach(question => {
                        const alreadyAdded = complianceData.some((item, index) =>
                            item.template_id == templateId &&
                            item.activity_id == activityId &&
                            item.question_id == question.id &&
                            item.question_id !=
                            questionId // exclude only if it's not the one we're editing
                        );

                        if (!alreadyAdded || question.id == questionId) {
                            options += `<option value="${question.id}">${question.question}</option>`;
                        }
                    });

                    $('#question_id').html(options);

                    if (questionId) {
                        $('#question_id').val(questionId).trigger('change');
                    }
                }
            });
        }


        function handleQuestionType(questionId) {
            // const getQuestionsTypeBaseUrl = "{{ url('/get-question-details') }}";

            $.ajax({
                url: "{{ route('get-question-details') }}",
                method: 'POST',
                data: {
                    'questionId': questionId,
                    "_token": "{{ @csrf_token() }}",
                },
                success: function(response) {
                    console.log(response);

                    let options = '<option selected disabled>Select Compliance</option>';
                    $('#subjective_value').hide(); // hide initially

                    if (!response.status) {
                        alert('Failed to fetch compliance values.');
                        $('#compliance_value').html('<option selected disabled>No Compliance Found</option>');
                        return;
                    }

                    const type = response.question_type;

                    if (type === 'Yes / No') {
                        options += `<option value="Yes">Yes</option>`;
                        options += `<option value="No">No</option>`;
                    } else if (type === 'Dropdown') {
                        if (!response.data || !response.data.length) {
                            alert('No compliance values found for this question.');
                            $('#compliance_value').html(
                                '<option selected disabled>No Compliance Found</option>');
                            return;
                        }
                        response.data.forEach(val => {
                            options += `<option value="${val.option}">${val.option}</option>`;
                        });
                    } else if (type === 'Subjective') {
                        if (!response.data || !response.data.length) {
                            alert('No compliance values found for this question.');
                            $('#compliance_value').html(
                                '<option selected disabled>No Compliance Found</option>');
                            return;
                        }

                        response.data.forEach(val => {
                            options += `<option value="${val.id}">${val.subject}</option>`;
                        });

                        // 👇 Show and attach change event for subjective
                        $('#compliance_value').off('change').on('change', function() {
                            fetchSubjectiveOptions(questionId, $(this).val());
                        });

                        $('#subjective_value').show(); // show subjective dropdown
                    }

                    $('#compliance_value').html(options);
                },
                error: function(xhr) {
                    console.error(xhr);
                    alert('Something went wrong while fetching question details.');
                }
            });
        }

        function fetchSubjectiveOptions(questionId, selectedSubject) {
            console.log(selectedSubject);
            // const getQuestionsDropdownUrl = "{{ url('/get-subjective-dropdown') }}";

            $.ajax({
                url: "{{ route('get-subjective-dropdown') }}",
                method: 'POST',
                data: {
                    'subjectId': selectedSubject,
                    "_token": "{{ @csrf_token() }}"
                },
                success: function(response) {
                    console.log(response);

                    if (!response.status || !response.data || !response.data.length) {
                        alert('No subjective options found for the selected question.');
                        $('#subjective_value').html('<option selected disabled>No Subjective Found</option>');
                        $('#subjectiveContainer').addClass('d-none');
                        return;
                    }

                    let html = '<option selected disabled>Select Subjective</option>';
                    response.data.forEach(option => {
                        html += `<option value="${option.id}">${option.option}</option>`;
                    });
                    $('#subjective_value').html(html);
                    $('#subjectiveContainer').removeClass('d-none');
                }
            });
        }

        function saveCompliance() {
            let index = $('#editIndex').val();
            let templateName = $('#template_name_id option:selected').text();
            let activityId = $('#activity_id').val();
            let questionId = $('#question_id').val();
            let complianceVal = $('#compliance_value').val();
            let activityName = $('#activity_id option:selected').text();
            let questionText = $('#question_id option:selected').text();
            let thresholdType = $('#threshold_type').val();
            let threshold = $('#threshold').val();
            let remark = $('#remark').val();

            // 🔒 Check for required dropdown data
            if (!activityId || $('#activity_id option').length <= 1) {
                alert('Please select a valid Activity. No activity data available.');
                return;
            }

            if (!questionId || $('#question_id option').length <= 1) {
                alert('Please select a valid Question. No question data available.');
                return;
            }

            if (!complianceVal || $('#compliance_value option').length <= 1) {
                alert('Please select a valid Compliance Value. No question type data available.');
                return;
            }

            if (threshold == '') {
                alert('Please enter Threshold Value.');
                return;
            }

            if (thresholdType == '') {
                alert('Please select Threshold type.');
                return;
            }



            let data = {
                project_id: $('#project_id').val(),
                template_id: $('#template_name_id').val(),
                template_name: templateName,
                activity_id: activityId,
                question_id: questionId,
                compliance_value: complianceVal,
                activity_name: activityName,
                question_text: questionText,
                threshold_type: thresholdType,
                threshold_value: threshold,
                remark: remark,
                subjective_value: $('#subjective_value option:selected').val() || null,
                subjective_value_name: $('#subjective_value option:selected').text() || null
            };

            const duplicate = complianceData.find((item, i) => i != index && item.template_id == data.template_id && item
                .activity_id == data.activity_id && item
                .question_id == data.question_id);
            if (duplicate) {
                alert('This question is already added for the selected activity.');
                return;
            }

            if (index) {
                complianceData[index] = data;
            } else {
                complianceData.push(data);
            }
            // console.log(complianceData);
            $('#complianceModal').modal('hide');
            renderComplianceCards();
        }

        function renderComplianceCards() {
            let container = $('#complianceCards');
            container.empty();

            complianceData.forEach((item, index) => {
                container.append(`
            <div class="row mb-4 mt-3 p-4 bg-secondary border rounded shadow-sm position-relative" style="min-height: 250px; border-left: 5px solid #0d6efd;">
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Selected Template</label>
                    <input type="text" class="form-control" value="${item.template_name || 'N/A'}" readonly>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Selected Activity</label>
                    <input type="text" class="form-control" value="${item.activity_name || 'N/A'}" readonly>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Selected Question</label>
                    <input type="text" class="form-control" value="${item.question_text || 'N/A'}" readonly>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Non Compliance</label>
                    <input type="text" class="form-control" value="${item.compliance_value}" readonly>
                </div>
                ${item.subjective_value ? `
                                                                                                                            <div class="col-md-4 col-sm-6 mb-3">
                                                                                                                                <label class="form-label fw-bold text-white">Subjective Dropdown</label>
                                                                                                                                <input type="text" class="form-control" value="${item.subjective_value_name}" readonly>
                                                                                                                            </div>` : ''}
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Non Compliance Threshold Type</label>
                    <input type="text" class="form-control" value="${item.threshold_type||'percent'}" readonly>
                </div>                                                                            
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Non Compliance Threshold</label>
                    <input type="text" class="form-control" value="${item.threshold_value || ''}" readonly>
                </div>
                <div class="col-md-4 col-sm-6 mb-3">
                    <label class="form-label fw-bold text-white">Non Compliance Remark</label>
                    <input type="text" class="form-control" value="${item.remark || ''}" readonly>
                </div>

                <div class="position-absolute d-flex gap-2 justify-content-end p-2" style="bottom: 0; right: 0;">
                    <button type="button" class="btn btn-sm btn-outline-white" onclick="editCompliance(${index})">
                        <i class="bi bi-pencil-square"></i> Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-white" onclick="deleteCompliance(${index})">
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                </div>
            </div>
        `);
            });
            if (complianceData.length == 0) {
                $('#buttonDiv').addClass('d-none');
            } else {
                $('#buttonDiv').removeClass('d-none');
            }
            // $('#buttonDiv').removeClass('d-none');
        }


        function editCompliance(index) {

            const item = complianceData[index];
            // console.log(item);
            $('#editIndex').val(index);
            $('#project_id').val(item.project_id);

            // const projectTemplateId = $('#template_name_id').val();
            fetchActivities(item.project_id, item.template_id);
            setTimeout(() => {
                $('#activity_id').val(item.activity_id);
                fetchQuestions(item.project_id, item.activity_id, item.question_id, item.template_id);
                setTimeout(() => {
                    $('#question_id').val(item.question_id);
                    handleQuestionType(item.question_id);
                    setTimeout(() => {
                        $('#compliance_value').val(item.compliance_value).change();
                        if (item.subjective_value) {
                            setTimeout(() => {
                                $('#subjective_value').val(item.subjective_value);
                            }, 300);
                        }
                    }, 300);
                }, 300);
            }, 300);

            // ✅ Set after everything is done
            $('#threshold').val(item.threshold_value || '');
            $('#remark').val(item.remark || '');

            $('#complianceModal').modal('show');
        }

        function deleteCompliance(index) {
            complianceData.splice(index, 1);
            console.log(complianceData);
            if (complianceData.length === 0) {
                $('#buttonDiv').addClass('d-none');
            }
            renderComplianceCards();
        }

 
        function submitData() {
            console.log(complianceData);
            if (complianceData.length == 0) {
                // alert('Data')
            } else {
                let projectId = $('project_id').val();
                $('.loader-backdrop').removeClass('d-none');
                $.ajax({
                    url: "{{ route('compliance.store') }}",
                    method: "POST",
                    data: {
                        '_token': '{{ csrf_token() }}',
                        'projectId': projectId,
                        'complianceData': complianceData,
                    },
                    success: function(response) {
                        console.log(response);
                        $('.loader-backdrop').addClass('d-none');
                        if (response.status) {
                            Swal.fire({
                                position: "center",
                                icon: "success",
                                title: `${response.message}`,
                                timer: 3000, // 3 seconds
                                timerProgressBar: true,
                                didClose: () => {
                                    location.reload(); // Reload the page after the alert closes
                                }
                            });
                        } else {
                            Swal.fire({
                                position: "center",
                                icon: "warning",
                                title: "Something Went Wrong!",
                                timer: 3000, // 3 seconds
                                timerProgressBar: true,
                                didClose: () => {
                                    location.reload();
                                }
                            });
                        }
                    }
                })
            }
        }
    </script>
@endsection
