@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>  <button class = "btn btn-primary float-end" id="add_subject_option" data-subject="{{$subjectInfo->subject}}" data-subject_id="{{$subjectInfo->id}}">Add Subject Options</button></h4>
                        <h4>Activity :  {{ optional($subjectInfo->questionInfo->activity)->activity_name}}</h4>
                        <h4>Question :  {{ optional($subjectInfo->questionInfo)->question}}</h4>
                        <h4>Subject :  {{$subjectInfo->subject}}</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="subjectsTable">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Option</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($subjectInfo->getOptions as $subject_option)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$subject_option->option}}</td>
                                        <td>
                                            <ul class="action">
                                                @if($subject_option->answer_type =="Dropdown" || $subject_option->answer_type == "Multi select")
                                                    <li class="dropdown_questions"> <a href="{{route('getQuestion.subject.dropdown', ["id"=>$subject_option->id])}}"><i class="icon-eye text-secondary fs-5"></i></a></li>
                                                @endif
                                                    <li class="edit" data-id="{{$subject_option->id}}" data-subject_id = "{{$subject_option->subject_id}}" data-subject_option="{{$subject_option->option}}" data-subject_answer_type="{{$subject_option->answer_type}}" ><i class="icon-pencil-alt"></i></li>
                                                <li class="delete" data-id="{{$subject_option->id}}"><i class="icon-trash"></i></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
                                <div class="modal_container">
                                    <div class="modal fade" id="subjectEditModal" tabindex="-1" role="dialog" aria-labelledby="questionEditModal" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-body">
                                                    <div class="modal-toggle-wrapper">

                                                        <h4 class="text-center pb-2" id="model_heading"></h4>
                                                        <form>
                                                            <input class="form-control" id="subjective_id"  name="subjective_id" type="hidden" placeholder="Enter subjective_id" required="">
                                                            <input class="form-control" id="sub_option_id"  name="sub_option_id" type="hidden" placeholder="Enter option id">
                                                            <div class="col-xl-12 col-sm-12">
                                                                <label class="form-label" for="subject_option">Subject Option Value<span class="txt-danger">*</span></label>
                                                                <input class="form-control" id="subject_option"  name="subject_option" type="text" placeholder="Enter Subject Option" required="">
                                                            </div>

                                                        </form>
                                                        <div class="d-flex justify-content-between mt-4">
                                                            <button class="btn btn-primary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
                                                            <button class="btn btn-primary d-flex m-auto" id="update_subject_btn" type="button" data-bs-dismiss="modal">Update</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal fade" id="errorMessage_modal" tabindex="-1" role="dialog" aria-labelledby="errorMessage_modal" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-body">
                                                    <div class="modal-toggle-wrapper">
                                                        <ul class="modal-img">
                                                            <li> <img src="{{url('assets/images/gif/danger.gif')}}" alt="error"></li>
                                                        </ul>
                                                        <h4 class="text-center pb-2">Ohh! Something went wrong!</h4>
                                                        <p class="text-center" id="error_message"></p>
                                                        <button class="btn btn-primary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
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
            <!-- Zero Configuration  Ends-->
        </div>
    </div>

@endsection

@section('scripts')
    <script>
        $(document).ready(function (){
            // Initialize DataTable
            var question_subjects_table = $("#subjectsTable").DataTable({
                paging: false
            });
            @if(session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            $("#subjectsTable").on("click", ".delete", function(event) {
                const subject_dropdown_id=$(this).data('id');
                const tar_row=$(this).closest('tr');
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
                        $.ajax({
                            url:'{{route('subjectOptionDropdown.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":subject_dropdown_id
                            },
                            success:function (response){
                                console.log(response)
                                if(response=="Success"){
                                    question_subjects_table.row(tar_row).remove().draw();
                                    Swal.fire(
                                        'Deleted!',
                                        'Record has been deleted.',
                                        'success'
                                    )
                                }
                            }
                        })
                    }
                })
            })
            $("#subjectsTable").on("click", ".edit", function (event){
                const subject_id = $(this).data('subject_id');
                const id = $(this).data('id');
                const subject_option = $(this).data('subject_option');
                const subject_answer_type = $(this).data('subject_answer_type');
                $("#subjective_id").val(subject_id);
                $("#sub_option_id").val(id);
                $("#subject_option").val(subject_option)
                $("#subjective_answer_type").val(subject_answer_type);
                // Set focus on the input field within the modal when it's shown
                $("#subjectEditModal").on('shown.bs.modal', function() {
                    $("#subject").focus();
                });
                $("#model_heading").html("Edit Option");
                $("#update_subject_btn").html("Update")

                $("#subjectEditModal").modal("show")
            });

            $("#update_subject_btn").click(function (){
                if($("#option").val() !==""){
                    let formData = {
                        'subject_id' : $("#subjective_id").val(),
                        'sub_option_id' : $("#sub_option_id").val(),
                        'subject_option' : $("#subject_option").val(),
                        'subjective_answer_type' : $('#subjective_answer_type').val()  // khushboo 11-06-2025
                    };
                    console.log(formData)
                    $.ajax({
                        url: "{{route('subject_option.UpdateOrCreate')}}",
                        type : "POST",
                        data: {
                            "_token" : "{{csrf_token()}}",
                            "form_data": formData
                        },
                        success: function (response){
                            console.log(response)
                            let show_message ='';
                            if(response.message == "success"){
                                if(response.message_info =="Created"){
                                    show_message = "Subject Option Created Successfully."
                                }else{
                                    show_message = "Subject Option Update Successfully."
                                }
                                Swal.fire({
                                    title: 'Success!',
                                    text: show_message,
                                    icon: 'success',
                                    timer: 2000, // Reload automatically after 2 seconds
                                    timerProgressBar: true, // Show a progress bar for the timer
                                    showConfirmButton: false, // Hide the confirmation button
                                    allowOutsideClick: false // Prevent dismissal by clicking outside the modal
                                }).then(() => {
                                    // Perform any additional actions after the timer expires (optional)
                                    location.reload(); // Reload the page
                                });
                            }
                        }
                    })


                }
                else{
                    $("#error_message").html("Subject Value can not be blank or empty")
                    $("#errorMessage_modal").modal('show');
                }
            });

            $("#add_subject_option").click(function (){
            const sub =$(this).data('subject');
                const subject_id = $(this).data('subject_id');
                $("#model_heading").html(`Add option for ${sub}`);
                $("#subjective_id").val(subject_id);
                $("#sub_option_id").val('');
                $("#subject_option").val('')
                // $("#question_id").val(subject_id);
                // Set focus on the input field within the modal when it's shown
                $("#subjectEditModal").on('shown.bs.modal', function() {
                    $("#subject").focus();
                });
                $("#subjectEditModal").modal('show');
                $("#update_subject_btn").html("Add Now")
            })
        });
    </script>
@endsection
