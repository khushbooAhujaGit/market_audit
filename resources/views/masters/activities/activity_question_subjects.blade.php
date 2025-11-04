@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>  <button class = "btn btn-primary float-end" id="add_subject" data-id="{{$question_info->id}}">Add Subjects</button></h4>
                        <h4>Activity :  {{ optional($question_info->activity)->activity_name}}</h4>
                        <h4>Question :  {{ optional($question_info)->question}}</h4>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="subjectsTable">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Option</th>
                                    <th>Answer Type</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($question_info->getSubjects as $subject)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$subject->subject}}</td>
                                        <td>{{$subject->answer_type??'NA'}}</td>
                                        <td>
                                            <ul class="action">
                                                @if($subject->answer_type == 'Dropdown' || $subject->answer_type == 'Multi Select' || $subject->answer_type == 'Yes / No')
                                                <li class="subject_options"> <a href="{{route('subject.options', ["id"=>$subject->id])}}">View Options</a></li>
                                                @endif
                                                <li class="edit" data-id="{{$subject->id}}" data-question_id = "{{$subject->question_id}}" data-subject_val="{{$subject->subject}}" data-subjective_answer_type="{{$subject->answer_type}}" ><i class="icon-pencil-alt"></i></li>
                                                <li class="delete" data-id="{{$subject->id}}"><i class="icon-trash"></i></li>
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
                                                            <input class="form-control" id="question_id"  name="question_id" type="hidden" placeholder="Enter question_id" required="">
                                                            <div class="col-xl-12 col-sm-12">
                                                                <label class="form-label" for="subject">Subject Value<span class="txt-danger">*</span></label>
                                                                <input class="form-control" id="subject"  name="subject" type="text" placeholder="Enter Subject" required="">
                                                            </div>
                                                            {{-- khushboo 11-06-2025 --}}
                                                            <div class="col-xl-12 col-sm-12 mt-2">
                                                                <label class="form-label" for="subjective_answer_type" >Add Answer Type</label>
                                                                <select class="form-select" id="subjective_answer_type"  name="subjective_answer_type" >
                                                                    <option value="" disabled="" selected >Choose...</option>
                                                                    <option value="Audio" >Audio</option>
                                                                    <option value="Yes / No" >Yes / No</option>
                                                                    <option value="Date" >Date</option>
                                                                    <option value="Date & Time" >Date & Time</option>
                                                                    <option value="Dropdown" >Dropdown</option>
                                                                    <option value="File Upload" >File Upload</option>
                                                                    <option value="Free Text" >Free Text</option>
                                                                    <option value="Image" >Image</option>
                                                                    <option value="Location" >Location</option>
                                                                    <option value="Multi select" >Multi select</option>
                                                                    <option value="Video" >Video</option>
                                                                </select>

                                                            </div>
                                                            {{-- khushboo 11-06-2025 --}}
                                                        </form>
                                                        <div class="d-flex justify-content-between mt-4">
                                                            <button class="btn btn-secondary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
                                                            <button class="btn btn-secondary d-flex m-auto" id="update_subject_btn" type="button" data-bs-dismiss="modal">Update</button>
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
                                                        <button class="btn btn-secondary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
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
                const sub_ques_id=$(this).data('id');
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
                            url:'{{route('subjectQuestion.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":sub_ques_id
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
                const subject_id = $(this).data('id');
                const question_id = $(this).data('question_id');
                const subject_val = $(this).data('subject_val');
                const subjective_answer_type = $(this).data('subjective_answer_type');
                $("#subjective_id").val(subject_id);
                $("#question_id").val(question_id);
                $("#subject").val(subject_val);
                $("#subjective_answer_type").val(subjective_answer_type);
                // Set focus on the input field within the modal when it's shown
                $("#subjectEditModal").on('shown.bs.modal', function() {
                    $("#subject").focus();
                });
                $("#model_heading").html("Edit Option");
                $("#update_subject_btn").html("Update")

                $("#subjectEditModal").modal("show")
            });

            $("#update_subject_btn").click(function (){
                if($("#subject").val() !==""){
                    let formData = {
                        'subjective_id' : $("#subjective_id").val(),
                        'question_id' : $("#question_id").val(),
                        'subject_val' : $("#subject").val(),
                        'subjective_answer_type' : $("#subjective_answer_type").val()
                    };
                    $.ajax({
                        url: "{{route('subject.UpdateOrCreate')}}",
                        type : "POST",
                        data: {
                            "_token" : "{{csrf_token()}}",
                            "form_data": formData
                        },
                        success: function (response){
                            let show_message ='';
                            if(response.message == "success"){
                                if(response.message_info =="Created"){
                                    show_message = "Subject Created Successfully."
                                }else{
                                    show_message = "Subject Update Successfully."
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

            $("#add_subject").click(function (){
                $("#model_heading").html("Add Subject");
                const question_id = $(this).data('id');
                $("#subjective_id").val('');
                $("#subject").val('')
                $("#question_id").val(question_id);
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
