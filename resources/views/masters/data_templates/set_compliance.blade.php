@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Set Compliance <a><button class = "btn btn-primary float-end" id="add_more_card">Add
                                        More</button></a></h4>
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
                                                </div>
                                                <div class="compliance-cards-container mt-4 ">
                                                    <div class="row mb-4 p-2 bg-secondary">
                                                        <div class="col-md-4 col-sm-4">
                                                            <label class="form-label" for="activity_id">Select
                                                                Activity</label>
                                                            <select class="form-select activity-select" id="activity_id"
                                                                name="activity_id" required>
                                                                <option selected disabled value="">Choose...</option>
                                                            </select>
                                                            @error('project_id')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4 col-sm-4">
                                                            <label class="form-label" for="question_id">Select
                                                                Questions</label>
                                                            <select class="form-select " id="question_id" name="question_id"
                                                                required>
                                                                <option selected disabled value="">Choose...</option>

                                                            </select>
                                                            @error('question_id')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4 col-sm-4">
                                                            <label class="form-label" for="non_compliance_value">Non
                                                                Compliance</label>
                                                            <select class="form-select non_compliance_value"
                                                                id="non_compliance_value" name="non_compliance_value"
                                                                required>
                                                                <option selected disabled value="">Choose...</option>
                                                                <option value="yes">Yes</option>
                                                                <option value="no">No</option>
                                                            </select>
                                                            @error('non_compliance_value')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4 col-sm-4 d-none mt-3"
                                                            id="subjective_dropdown_wrapper">
                                                            <label class="form-label"
                                                                for="subjective_followup_dropdown">Subject
                                                                Dropdown</label>
                                                            <select class="form-select " id="subjective_followup_dropdown"
                                                                name="subjective_followup_dropdown">
                                                                <option selected disabled value="">Choose...
                                                                </option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4 col-sm-4 mt-3">
                                                            <label class="form-label" for="non_compliance_threshold">Non
                                                                Compliance Threshold (Number/ Percent)</label>
                                                            <input class="form-control" type="number" step="any"
                                                                id="non_compliance_threshold"
                                                                name="non_compliance_threshold">
                                                            @error('non_compliance_threshold')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror
                                                        </div>
                                                        <div class="col-md-4 col-sm-4 mt-3">
                                                            <label class="form-label"
                                                                for="non_compliance_threshold_remark">Non
                                                                Compliance Remark</label>
                                                            <input class="form-control" type="text"
                                                                id="non_compliance_threshold_remark"
                                                                name="non_compliance_threshold_remark">
                                                            @error('non_compliance_threshold_remark')
                                                                <p class="text-red-500 text-xs mt-1">
                                                                    {{ $message }}
                                                                </p>
                                                            @enderror

                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary" id="map_compliance">Save</button>
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
        let complianceData = [];
        let cardIndex = 0;


        document.addEventListener("DOMContentLoaded", function() {
            const initialCard = document.querySelector('.compliance-cards-container .row');
            initialCard.setAttribute("data-index", cardIndex);

            complianceData.push({
                id: cardIndex,
                activity_id: "",
                question_id: "",
                non_compliance_value: "",
                non_compliance_threshold: "",
                non_compliance_remark: ""
            });

            console.log("Initial complianceData:", complianceData);
        });

        // Fetch questions and update question select box
        function updateQuestionsDropdown(card, activityId, excludeQuestionIds = []) {
            console.log(activityId);
            console.log(card);
            const getQuestionsBaseUrl = "{{ url('/get-questions') }}";
            return fetch(`${getQuestionsBaseUrl}/${activityId}`)
                .then(res => res.json())
                .then(response => {
                    console.log(response);
                    const questionSelect = card.querySelector("#question_id");
                    questionSelect.innerHTML = '<option selected disabled value="">Choose...</option>';

                    const questions = response.data;
                    let firstValidOptionId = null;

                    // console.log(excludeQuestionIds);
                    questions.forEach(q => {
                        if (!excludeQuestionIds.includes(String(q.id))) {
                            const opt = document.createElement("option");
                            opt.value = q.id;
                            opt.textContent = q.question;
                            questionSelect.appendChild(opt);
                            // console.log(q.id);

                            if (!firstValidOptionId) {
                                firstValidOptionId = q.id;
                            }
                        } else {
                            // console.log(card.remove());
                        }
                    });

                    // console.log(firstValidOptionId);
                    // ✅ Auto-select the first valid question and trigger change
                    if (firstValidOptionId) {
                        questionSelect.value = firstValidOptionId;
                        const event = new Event('change', {
                            bubbles: true,
                            cancelable: true
                        });
                        questionSelect.dispatchEvent(event);
                    }

                });
        }

        function refreshAllQuestionsDropdowns() {
            const allCards = document.querySelectorAll(".compliance-cards-container .row");

            allCards.forEach(card => {
                const cardId = card.getAttribute("data-index");
                const activityId = card.querySelector("#activity_id")?.value;

                if (!activityId) return;

                // Get the selected question for this card
                const currentCard = complianceData.find(item => item.id == cardId);
                const currentSelectedQuestionId = currentCard?.question_id || "";

                // Build list of used question IDs from other cards
                const usedQuestionIds = complianceData
                    .filter(item => item.id != cardId)
                    .map(item => item.question_id)
                    .filter(qid => qid !== "");

                const getQuestionsBaseUrl = "{{ url('/get-questions') }}";
                let firstValidOptionId = null;

                fetch(`${getQuestionsBaseUrl}/${activityId}`)
                    .then(res => res.json())
                    .then(response => {
                        const questionSelect = card.querySelector("#question_id");
                        const currentValue = questionSelect.value;

                        questionSelect.innerHTML = '<option disabled value="">Choose...</option>';

                        response.data.forEach(q => {
                            // Allow:
                            // - Questions not used elsewhere
                            // - OR the question already selected in this card
                            if (!usedQuestionIds.includes(String(q.id)) || String(q.id) ===
                                currentSelectedQuestionId) {
                                const opt = document.createElement("option");
                                opt.value = q.id;
                                opt.textContent = q.question;

                                if (String(q.id) === currentValue) {
                                    opt.selected = true;
                                }

                                questionSelect.appendChild(opt);
                                if (!firstValidOptionId) {
                                    firstValidOptionId = q.id;
                                }
                            }
                        });

                        // if (firstValidOptionId) {
                        //     questionSelect.value = firstValidOptionId;
                        //     const event = new Event('change', {
                        //         bubbles: true,
                        //         cancelable: true
                        //     });
                        //     questionSelect.dispatchEvent(event);
                        // }

                        // In case the selected value was removed due to fetch delay or mismatch, preserve it manually
                        if (currentValue && !Array.from(questionSelect.options).some(opt => opt.value ===
                                currentValue)) {
                            // const fallbackOption = document.createElement("option");
                            // fallbackOption.value = currentValue;
                            // fallbackOption.textContent = currentValue;
                            // // fallbackOption.selected = true;
                            // questionSelect.appendChild(fallbackOption);
                        }
                    });
            });
        }

        document.getElementById("add_more_card").addEventListener("click", function() {
            const container = document.querySelector('.compliance-cards-container');
            const lastCard = container.querySelectorAll('.row')[container.querySelectorAll('.row').length - 1];

            const activitySelect = lastCard.querySelector("#activity_id");
            const compliancevalueSelect = lastCard.querySelector("#non_compliance_value");
            // compliancevalueSelect.innerHTML = '';
            const activityId = activitySelect?.value;

            if (!activityId) {
                alert("Please select an activity before adding a new card.");
                return;
            }

            const getQuestionsBaseUrl = "{{ url('/get-questions') }}";

            fetch(`${getQuestionsBaseUrl}/${activityId}`)
                .then(res => res.json())
                .then(response => {
                    const allQuestions = response.data.map(q => String(q.id));
                    // const selectedQuestions = complianceData
                    //     .filter(item => item.activity_id === activityId)
                    //     .map(item => String(item.question_id))
                    //     .filter(q => q !== "");

                    // const remainingQuestions = allQuestions.filter(qid => !selectedQuestions.includes(qid));

                    // Get selected questions for the same activity from all cards in DOM
                    const allCards = container.querySelectorAll('.row');
                    const selectedQuestions = Array.from(allCards).map(card => {
                        const cardActivityId = card.querySelector("#activity_id")?.value;
                        const cardQuestionId = card.querySelector("#question_id")?.value;
                        return cardActivityId === activityId && cardQuestionId ? String(
                            cardQuestionId) : null;
                    }).filter(q => q !== null);

                    const remainingQuestions = allQuestions.filter(qid => !selectedQuestions.includes(qid));

                    console.log(allQuestions, remainingQuestions);
                    if (remainingQuestions.length === 0) {
                        alert("All questions for this activity have been used. Cannot add more cards.");
                        return;
                    }

                    // Update current last card's data
                    const questionId = lastCard.querySelector("#question_id")?.value || "";
                    const nonComplianceValue = lastCard.querySelector("#non_compliance_value")?.value || "";
                    const nonComplianceThreshold = lastCard.querySelector("#non_compliance_threshold")?.value ||
                        "";
                    const nonComplianceRemark = lastCard.querySelector("#non_compliance_threshold_remark")
                        ?.value || "";

                    const existingId = lastCard.getAttribute("data-index");

                    complianceData = complianceData.map(item => {
                        if (item.id == existingId) {
                            return {
                                ...item,
                                activity_id: activityId,
                                question_id: questionId,
                                non_compliance_value: nonComplianceValue,
                                non_compliance_threshold: nonComplianceThreshold,
                                non_compliance_remark: nonComplianceRemark
                            };
                        }
                        return item;
                    });

                    // ✅ Clone new card
                    const newCard = lastCard.cloneNode(true);
                    cardIndex++;
                    newCard.setAttribute("data-index", cardIndex);

                    // Reset input values
                    newCard.querySelectorAll("input").forEach(el => el.value = "");
                    newCard.querySelector("#question_id").innerHTML =
                        '<option selected disabled value="">Choose...</option>';

                    // If only one activity exists, auto-select it
                    const originalActivityOptions = Array.from(activitySelect.options).filter(opt => opt
                        .value !== "");
                    const autoSetActivity = originalActivityOptions.length === 1;
                    if (autoSetActivity) {
                        newCard.querySelector("#activity_id").value = activityId;
                    } else {
                        newCard.querySelector("#activity_id").value = "";
                    }

                    // Add remove button if not present
                    if (!newCard.querySelector(".remove-card-btn")) {
                        const btnDiv = document.createElement("div");
                        btnDiv.className = "col-12 text-end";
                        btnDiv.innerHTML =
                            `<button type="button" class="btn btn-danger remove-card-btn">Remove</button>`;
                        newCard.appendChild(btnDiv);
                    }

                    // Push temp complianceData
                    complianceData.push({
                        id: cardIndex,
                        activity_id: autoSetActivity ? activityId : "",
                        question_id: "",
                        non_compliance_value: "",
                        non_compliance_threshold: "",
                        non_compliance_remark: ""
                    });

                    container.appendChild(newCard);

                    // ✅ Fetch dropdown questions for new card
                    if (autoSetActivity) {
                        updateQuestionsDropdown(newCard, activityId).then(() => {
                            const questionSelect = newCard.querySelector("#question_id");

                            if (questionSelect.options.length <= 1) {
                                // No usable questions — remove card & data
                                container.removeChild(newCard);
                                complianceData = complianceData.filter(item => item.id != cardIndex);
                                alert(
                                    "No questions available for this activity. The new card was removed."
                                );
                                return;
                            }

                            refreshAllQuestionsDropdowns();
                        });
                    }

                    console.log("After Add:", complianceData);
                });
        });


        document.querySelector(".compliance-cards-container").addEventListener("click", function(e) {
            if (e.target.classList.contains("remove-card-btn")) {
                const card = e.target.closest(".row");
                const cardId = card.getAttribute("data-index");

                // complianceData = complianceData.filter(item => item.id != cardId);
                // card.remove();

                // console.log("After Remove:", complianceData);
                const cardData = complianceData.find(item => item.id == cardId);
                const removedActivityId = cardData?.activity_id;
                const removedQuestionId = cardData?.question_id;

                card.remove();
                complianceData = complianceData.filter(item => item.id != cardId);

                // Try to assign the removed question to a remaining card with same activity and empty question_id
                if (removedActivityId && removedQuestionId) {
                    // Find the last card with same activity_id and empty question_id
                    const matchingCard = [...complianceData].reverse().find(item =>
                        item.activity_id === removedActivityId && item.question_id === ""
                    );

                    if (matchingCard) {
                        matchingCard.question_id = removedQuestionId;

                        // Find the actual select element in DOM and update it
                        const targetCardEl = document.querySelector(
                            `.compliance-cards-container .row[data-index="${matchingCard.id}"]`);
                        if (targetCardEl) {
                            const questionSelect = targetCardEl.querySelector("#question_id");
                            const opt = document.createElement("option");
                            opt.value = removedQuestionId;
                            opt.textContent = "Recovered Question";
                            opt.selected = true;
                            questionSelect.appendChild(opt);
                        }
                    }
                }

                refreshAllQuestionsDropdowns();
                console.log("After Remove:", complianceData);

            }
        });

        // Handle change of activity_id and question_id
        document.querySelector(".compliance-cards-container").addEventListener("change", function(e) {
            const card = e.target.closest(".row");
            const cardId = card.getAttribute("data-index");

            if (e.target.id === "activity_id") {
                const selectedActivity = e.target.value;
                complianceData = complianceData.map(item => {
                    if (item.id == cardId) {
                        return {
                            ...item,
                            activity_id: selectedActivity,
                            question_id: ""
                        };
                    }
                    return item;
                });

                // Reset question_id and fetch new list
                const questionSelect = card.querySelector("#question_id");
                questionSelect.innerHTML = '<option selected disabled value="">Choose...</option>';
                // updateQuestionsDropdown(card, selectedActivity);
                updateQuestionsDropdown(card, selectedActivity).then(() => {
                    refreshAllQuestionsDropdowns(); // after fetch
                });

            } else if (e.target.id === "question_id") {
                // const selectedQuestion = e.target.value;    
                const card = e.target.closest(".row");
                const selectedQuestion = card.querySelector("#question_id").value;
                const cardId = card.getAttribute("data-index");

                complianceData = complianceData.map(item => {
                    if (item.id == cardId) {
                        return {
                            ...item,
                            question_id: selectedQuestion
                        };
                    }
                    return item;
                });

                console.log("Selected question updated:", complianceData);

                // // Check for duplicate question_id with same activity_id
                // const currentCard = complianceData.find(item => item.id == cardId);
                // const duplicates = complianceData.filter(item =>
                //     item.id != cardId &&
                //     item.activity_id === currentCard.activity_id &&
                //     item.question_id === currentCard.question_id
                // );

                const currentActivityId = card.querySelector("#activity_id")?.value || "";
                const currentQuestionId = selectedQuestion;

                const duplicates = complianceData.filter(item =>
                    item.id != cardId &&
                    item.activity_id === currentActivityId &&
                    item.question_id === currentQuestionId
                );

                if (duplicates.length > 0) {
                    // alert("This question is already selected for the same activity in another card!");
                    complianceData = complianceData.map(item => {
                        if (item.id == cardId) {
                            return {
                                ...item,
                                question_id: ""
                            };
                        }
                        return item;
                    });
                    e.target.value = ""; // reset dropdown
                    refreshAllQuestionsDropdowns();
                    // return;
                }
                console.log(selectedQuestion);

                if (selectedQuestion) {
                    // 🔥 New: Fetch question type and non_compliance_value options
                    const getQuestionsTypeBaseUrl = "{{ url('/get-question-details') }}";
                    fetch(`${getQuestionsTypeBaseUrl}/${selectedQuestion}`)
                        .then(res => res.json())
                        .then(response => {
                            console.log('selected question');
                            const card = e.target.closest(".row");
                            const nonComplianceValueSelect = card.querySelector("#non_compliance_value");

                            nonComplianceValueSelect.innerHTML =
                                '<option disabled selected value="">Choose...</option>';

                            if (response.question_type === 'Yes / No') {
                                console.log('yes/no');
                                response.data.forEach(item => {
                                    const value = typeof item === 'string' ? item : item
                                        .value; // Adjust if custom structure
                                    const opt = document.createElement("option");
                                    opt.value = value;
                                    opt.textContent = value;
                                    nonComplianceValueSelect.appendChild(opt);
                                });
                            }

                            if (response.question_type === 'Dropdown') {
                                console.log('dropdown');
                                response.data.forEach(item => {
                                    const opt = document.createElement("option");
                                    opt.value = item.id;
                                    opt.textContent = item.option;
                                    nonComplianceValueSelect.appendChild(opt);
                                });
                            }

                            if (response.question_type === 'Subjective') {
                                console.log('subjective');
                                response.data.forEach(subjectiveItem => {
                                    const opt = document.createElement("option");
                                    opt.value = subjectiveItem.id;
                                    opt.textContent = subjectiveItem
                                        .subject; // Adjust as per actual column
                                    nonComplianceValueSelect.appendChild(opt);
                                });

                                // 🔁 Add listener for follow-up fetch when value is chosen
                                nonComplianceValueSelect.addEventListener("change", function() {
                                    const selectedValue = this.value;
                                    if (selectedValue) {
                                        console.log('subjective dropdown');
                                        const getQuestionsDropdownUrl =
                                            "{{ url('/get-subjective-dropdown') }}";
                                        fetch(`${getQuestionsDropdownUrl}/${selectedValue}`)
                                            .then(res => res.json())
                                            .then(subjectiveResponse => {
                                                console.log(subjectiveResponse);
                                                const secondaryDropdown = card.querySelector(
                                                    "#subjective_followup_dropdown");
                                                console.log(card);
                                                console.log(secondaryDropdown);
                                                if (secondaryDropdown) {
                                                    console.log(secondaryDropdown);
                                                    secondaryDropdown.innerHTML =
                                                        '<option disabled selected value="">Choose...</option>';
                                                    subjectiveResponse.data.forEach(item => {
                                                        const opt = document.createElement(
                                                            "option");
                                                        opt.value = item.id;
                                                        opt.textContent = item.option;
                                                        secondaryDropdown.appendChild(opt);
                                                    });
                                                    const secondaryDropdownwrapper = card
                                                        .querySelector(
                                                            "#subjective_dropdown_wrapper");
                                                    if (secondaryDropdownwrapper) {
                                                        secondaryDropdownwrapper.classList.remove(
                                                            "d-none");
                                                    }
                                                    // console.log(secondaryDropdown);
                                                }
                                            });
                                    }
                                });
                            }
                        });
                    refreshAllQuestionsDropdowns();
                }
            }

        });

        $(document).ready(function() {

            $("#project_id").change(function(e) {
                const selectedProjectId = $(this).val();
                if (selectedProjectId) {
                    $.ajax({
                        url: '{{ route('get_project_activities') }}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}",
                            "id": selectedProjectId
                        },
                        success: function(response) {
                            const $activitySelect = $('#activity_id');
                            $('#question_id').html('');
                            $('#non_compliance_value').html('');
                            const firstCard = $(".compliance-cards-container .row").first();

                            // 🔥 Remove all other cards except the first one
                            $(".compliance-cards-container .row").not(":first").remove();

                            if (response.status && Array.isArray(response.data)) {
                                const activities = response.data;

                                $activitySelect.html(
                                    '<option disabled selected value="">Choose...</option>');

                                activities.forEach(activity => {
                                    $activitySelect.append(
                                        `<option value="${activity.id}">${activity.activity_name}</option>`
                                    );
                                });

                                // if (activities.length === 1) {
                                const onlyActivity = activities[0];
                                const onlyActivityId = onlyActivity.id;

                                // Auto-select the single activity in dropdown
                                $activitySelect.val(onlyActivityId);

                                // Set it in the first card too
                                const cardActivitySelect = firstCard.find("#activity_id");
                                if (cardActivitySelect.length) {
                                    cardActivitySelect.val(onlyActivityId);
                                }

                                const cardId = firstCard.attr("data-index");

                                // Update complianceData
                                complianceData = complianceData.map(item => {
                                    if (item.id == cardId) {
                                        return {
                                            ...item,
                                            activity_id: onlyActivityId,
                                            question_id: ""
                                        };
                                    }
                                    return item;
                                });

                                updateQuestionsDropdown(firstCard[0], onlyActivityId).then(
                                    () => {
                                        refreshAllQuestionsDropdowns();
                                    });

                                $(".compliance-cards-container").removeClass('d-none');
                            } else {
                                $activitySelect.html('');
                                $('#question_id').html('');
                                $(".compliance-cards-container").addClass('d-none');
                                $("#error_message").text(
                                    'Child Activity Not Found for Selected Project');
                                $("#errorMessage_modal").modal('show');
                            }
                        }
                    });
                }
            });

        })
    </script>
@endsection
