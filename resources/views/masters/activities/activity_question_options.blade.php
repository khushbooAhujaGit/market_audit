@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">

        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>  <button class = "btn btn-primary float-end" id="add_option" data-id="{{$question_info->id}}">Add Option</button></h4>
                        <h4>Activity :  {{ optional($question_info->activity)->activity_name}}</h4>
                        <h4>Question :  {{ optional($question_info)->question}}</h4>

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="dropdownOptionsTable">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Option</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($question_info->getOptions as $question_option)
                                    <tr>
                                        <td>{{$loop->iteration}}</td>
                                        <td>{{$question_option->option}}</td>
                                        <td>
                                            <ul class="action">
                                                <li class="edit" data-id="{{$question_option->id}}" data-question_id = "{{$question_option->question_id}}" data-option_val="{{$question_option->option}}" ><i class="icon-pencil-alt"></i></li>
                                                <li class="delete" data-id="{{$question_option->id}}"><i class="icon-trash"></i></li>
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
                                <div class="modal_container">
                                    <div class="modal fade" id="dropdownEditModal" tabindex="-1" role="dialog" aria-labelledby="questionEditModal" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <div class="modal-body">
                                                    <div class="modal-toggle-wrapper">

                                                        <h4 class="text-center pb-2" id="model_heading"></h4>
                                                        <form>
                                                            <input class="form-control" id="question_dropdown_id"  name="question_dropdown_id" type="hidden" placeholder="Enter question_dropdown_id" required="">
                                                            <input class="form-control" id="question_id"  name="question_id" type="hidden" placeholder="Enter question_id" required="">
                                                            <div class="col-xl-12 col-sm-12">
                                                                <label class="form-label" for="option">Option Value<span class="txt-danger">*</span></label>
                                                                <input class="form-control" id="option"  name="option" type="text" placeholder="Enter Option Value" required="">
                                                            </div>

                                                        </form>
                                                        <div class="d-flex justify-content-between mt-4">
                                                            <button class="btn btn-secondary d-flex m-auto" type="button" data-bs-dismiss="modal">Close</button>
                                                            <button class="btn btn-secondary d-flex m-auto" id="update_option_btn" type="button" data-bs-dismiss="modal">Update</button>
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
            var dropdown_options_table = $("#dropdownOptionsTable").DataTable({
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

            $("#dropdownOptionsTable").on("click", ".delete", function(event) {
                const option_id=$(this).data('id');
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
                            url:'{{route('activity.question.option.destroy')}}',
                            type:"POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id":option_id
                            },
                            success:function (response){
                                if(response=="Success"){
                                    dropdown_options_table.row(tar_row).remove().draw();
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
            $("#dropdownOptionsTable").on("click", ".edit", function (event){
                const drop_down_id = $(this).data('id');
                const question_id = $(this).data('question_id');
                const option_val = $(this).data('option_val');
                $("#question_dropdown_id").val(drop_down_id);
                $("#question_id").val(question_id);
                $("#option").val(option_val);
                $("#model_heading").html("Edit Option");
                $("#update_option_btn").html("Update")

                $("#dropdownEditModal").modal("show")
            });

            $("#update_option_btn").click(function (){
                if($("#option").val() !==""){
                    let formData = {
                        'question_dropdown_id' : $("#question_dropdown_id").val(),
                        'question_id' : $("#question_id").val(),
                        'option_val' : $("#option").val()
                    };
                    $.ajax({
                        url: "{{route('dropDown.UpdateOrCreate')}}",
                        type : "POST",
                        data: {
                            "_token" : "{{csrf_token()}}",
                            "form_data": formData
                        },
                        success: function (response){
                            let show_message ='';
                            if(response.message == "success"){
                                console.log(response)
                                if(response.message_info =="Created"){
                                    show_message = "Option Created Successfully."
                                }else{
                                    show_message = "Option Update Successfully."
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
                    $("#error_message").html("Option Value can not be blank or empty")
                    $("#errorMessage_modal").modal('show');
                }
            });

            $("#add_option").click(function (){
                $("#model_heading").html("Add Option");
                const question_id = $(this).data('id');
                $("#question_dropdown_id").val('');
                $("#option").val('')
                $("#question_id").val(question_id);
                $("#dropdownEditModal").modal('show');
                $("#update_option_btn").html("Add Now")
            })
        });
    </script>
@endsection
