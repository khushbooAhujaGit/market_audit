@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Add Dropdown Option <a href="{{route('dropdown.list')}}">
                                <button class="btn btn-primary float-end">View Activities</button>
                            </a></h4>
                    </div>
                    <div class="card-body">
                        <div class="horizontal-wizard-wrapper">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="tab-content dark-field" id="horizontal-wizard-tabContent">
                                        <div class="tab-pane fade show active" id="wizard-info" role="tabpanel"
                                             aria-labelledby="wizard-info-tab">
{{--                                            <form>--}}
{{--                                            <form method="POST" action="{{route('template.store')}}"--}}
{{--                                                  enctype="multipart/form-data" class="row g-3 needs-validation"--}}
{{--                                                  novalidate="">--}}
                                                @csrf
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="activity_id">Select Activity</label>
                                                    <select class="form-select" id="activity_id" name="activity_id"
                                                            required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                        @foreach($activities as $activity)
                                                            <option value="{{$activity->id}}"
                                                                    @if(old('activity_id') == $activity->id) selected @endif>{{$activity->activity_name}}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('activity_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="col-xl-6 col-sm-6">
                                                    <label class="form-label" for="question_id">Select Question</label>
                                                    <select class="form-select" id="question_id" name="question_id"
                                                            required="">
                                                        <option selected="" disabled="" value="">Choose...</option>
                                                    </select>
                                                    @error('question_id')
                                                    <p class="text-red-500 text-xs mt-1">
                                                        {{$message}}
                                                    </p>
                                                    @enderror
                                                </div>
                                                <div class="main_option">
                                                    <div class="row ">
                                                        <div class="col-md-8">
                                                            <label class="form-label" for="inputEmail4">Dropdown Option
                                                                Value</label>
                                                            <input class="form-control dropdown_val" type="text"
                                                                   placeholder="Enter dropdown option">

                                                        </div>
                                                        <div class="col-md-4">
                                                            <button id="add_more" class="btn btn-primary btn-sm mt-4 fs-6">+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button class="btn btn-primary" id="submit_options">Add Now</button>
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
    <script>
        $(document).ready(function () {
            function render_questions(questions) {
                let question_select = $("#question_id");
                question_select.empty();
                question_select.append($('<option>').text('Choose...').attr('disabled', 'disabled').attr('selected', 'selected').val(''));
                $.each(questions, function (index, value) {
                    question_select.append($('<option>').text(value.question).val(value.id));
                })
            }

            $("#activity_id").change(function (e) {
                const selectedTemplateId = $(this).val();
                if (selectedTemplateId) {
                    $.ajax({
                        url: '{{route('get_activity_dropdown_questions')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "id": selectedTemplateId
                        },
                        success: function (response) {
                            console.log(response)
                            if (response.message == "Success") {
                                render_questions(response.related_questions);
                            }
                        }
                    })
                }
            })

            $('#add_more').on('click', function() {
                var newRow = $(`<div class="row mt-3">
                            <div class="col-md-8">
                                <input class="form-control dropdown_val" type="text" placeholder="Enter dropdown option">
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-danger btn-sm fs-6 delete_row"><i class="icon-trash"></i></button>
                            </div>
                        </div>`);
                $('.main_option').append(newRow);
            });
            // Function to remove a row
            $('.main_option').on('click', '.delete_row', function() {
                $(this).closest('.row').remove();
            });


            $("#submit_options").click(function (){
                let activity_val = $("#activity_id").val();
                let question_val = $("#question_id").val();
                let can = 1;
                if(!question_val){
                    Swal.fire({
                        position: "top-center",
                        icon: "error",
                        title: "Select Question",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    can=0;
                }
               let options_val = [];
                $(".dropdown_val").each(function() {
                    if($(this).val() !==""){
                    options_val.push($(this).val());
                    }
                });
                if(options_val.length==0){
                    Swal.fire({
                        position: "top-center",
                        icon: "error",
                        title: "Enter Dropdown Options value",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    can=0;
                }

                if(can==1) {
                    $.ajax({
                        url: '{{route('dropdown.store')}}',
                        type: "POST",
                        data: {
                            "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                            "question_id": question_val,
                            "dropdown_options":options_val
                        },
                        success: function (response) {
                            console.log(response)
                            if(response.message== "Success"){
                                Swal.fire({
                                    position: "top-center",
                                    icon: "success",
                                    title: "Options Added Succfully",
                                    showConfirmButton: false,
                                    timer: 1000
                                });
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);

                            }
                        }
                    })
                }else{
                    alert('j')
                }
            })

        })
    </script>
@endsection
