@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Group Activity <a href="{{route('activityGroup.list')}}">
                                    <button class="btn btn-primary float-end">View Activity Groups</button>
                                </a></h4>
                        </div>
                        <div class="card-body">
                            <div class="horizontal-wizard-wrapper">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                            <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                                 aria-labelledby="wizard-info-tab">
                                                {{--                                            <form method="POST" action="{{route('template.store')}}" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate="">--}}
                                                @csrf
                                                <div class="row">

                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="activity_id">Select
                                                            Activity</label>
                                                        <select class="form-select" id="activity_id" name="activity_id"
                                                                required="">
                                                            <option selected="" disabled="" value="">Choose...</option>
                                                            @foreach($activities as $activity)
                                                                <option value="{{$activity->id}}"
                                                                        @if(old('activity_id') == $activity->id) selected @endif>{{$activity->activity_name}}</option>
                                                            @endforeach
                                                        </select>
                                                        @error('company_id')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                    <div class="col-xl-6 col-sm-6">
                                                        <label class="form-label" for="activity_group_name">Acitivity
                                                            Group Name</label>
                                                        <input type="text" class="form-control" id="activity_group_name"
                                                               name="activity_group_name" required>
                                                        @error('activity_group_name')
                                                        <p class="text-red-500 text-xs mt-1">
                                                            {{$message}}
                                                        </p>
                                                        @enderror
                                                    </div>
                                                </div>
                                                <div id="activites_con" class="activites_con row">


                                                </div>


                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary" id="create_group">Create New Group
                                                    </button>
                                                </div>
                                                {{--                                            </form>--}}
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

        $(document).ready(function () {

            let temp_arr = [];
            let activity_select = $("#activity_id").select2();

            function render_activities(template) {
                let template_info = template;
                if (!temp_arr.includes(template_info.id)) {
                    temp_arr.push(template_info.id);
                    let temp = `
                 <div class="col-md-12 col-xl-12">
                                                        <div class="card-wrapper border rounded-3 checkbox-checked">
                                                            <div class="form-check-size">
                                                                <div class="form-check form-switch form-check-inline">
                                                                    <input class="form-check-input check-size" id="flexSwitchCheckDefault2" type="checkbox" role="switch" checked="" data-id="${template_info.id}">
                                                                </div>
                                                                <div class="template_name_con form-check-inline">
                                                                    ${template_info.activity_name}
                                                                </div>
                                                                <div class="form-check form-switch form-check-inline">
                                                                    <input class="form-control" type="number" placeholder="Enter sequence" value="${temp_arr.length}">
                                                                </div>
                                                                 <div class="text-danger fs-5 delete">
                                                                        <i class="icon-trash delete_template_from_group_now" data-id="${template_info.id}"></i>
                                                                    </div>
                                                            </div>
                                                        </div>
                                                    </div>
                `;
                    $("#activites_con").append(temp)
                    $(".delete_template_from_group_now").click(function () {
                        let delete_temp = $(this).closest('.col-md-12');
                        const group_temp_id = $(this).data('id');
                        if (group_temp_id) {
                            Swal.fire({
                                title: 'Are you sure?',
                                text: "You won't be able to revert this!",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Yes, delete it!'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    let index = temp_arr.indexOf(group_temp_id);
                                    if (index !== -1) {
                                        temp_arr.splice(index, 1);
                                    }
                                    delete_temp.remove();
                                }
                            })
                        }

                    })

                } else {
                    Swal.fire({
                        position: "top-center",
                        icon: "error",
                        title: ` ${template_info.template_name} already selected`,
                        showConfirmButton: false,
                        timer: 1000
                    });
                }


            }

            $("#activity_id").change(function (e) {
                const selectedAct = $(this).val();
                if (selectedAct) {
                    $.ajax({
                        url: '{{route('activity.info')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedAct
                        },
                        success: function (response) {
                            if (response.message == "success") {
                                render_activities(response.info);
                            }
                        }
                    })
                }
            })


            $("#create_group").click(function () {
                let activity_group_name = $("#activity_group_name").val();
                if (activity_group_name) {
                    let selectedCheckboxes = $(".check-size:checked"); // Select all checked checkboxes
                    let selectedCheckboxesData = []; // Array to store objects
                    selectedCheckboxes.each(function () {
                        let checkboxId = $(this).data("id"); // Get the data-id of the checkbox
                        let sequenceValue = $(this).closest('.form-check-size').find('.form-control').val(); // Get the sequence value
                        let templateName = $(this).closest('.form-check-size').find('.template_name_con').text(); // Get the template name
                        selectedCheckboxesData.push({
                            id: checkboxId,
                            sequence: sequenceValue,
                            template_name: templateName
                        }); // Push the id, sequence value, and template name into the array as an object
                    });

                    if (selectedCheckboxesData.length > 0) {
                        $.ajax({
                            url: '{{route('create_activity_group')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "temp_arr": selectedCheckboxesData,
                                "group_name": activity_group_name,
                            },
                            success: function (response) {
                                if (response.message == "success") {
                                    Swal.fire({
                                        position: "top-center",
                                        icon: "success",
                                        title: "Activity Group Created",
                                        showConfirmButton: false,
                                        timer: 1000
                                    });
                                    setTimeout(function () {
                                        location.reload();
                                    }, 1000);
                                } else if (response.message == "already") {
                                    Swal.fire({
                                        position: "top-center",
                                        icon: "error",
                                        title: "Group Already Exits",
                                        showConfirmButton: false,
                                        timer: 1000
                                    });

                                }

                            }
                        })


                    } else {
                        Swal.fire({
                            position: "top-center",
                            icon: "error",
                            title: `No Activity Checked or Added`,
                            showConfirmButton: false,
                            timer: 1000
                        });
                    }


                } else {
                    Swal.fire({
                        position: "top-center",
                        icon: "error",
                        title: `Group Name is required`,
                        showConfirmButton: false,
                        timer: 1000
                    });
                }
            })

        })
    </script>
@endsection
