@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Project : {{optional($project)->project_name}}
                        </h4>
                        @if(isset($projectTemplateInfo))
                            <h4>{{$projectTemplateInfo->getTemplate->template_name}}</h4>
                        @endif

                        @if($status == 1 || $with_data_check == 0)
                            <a class="btn btn-primary float-end"
                               href="{{route('user.project.distributor.outlets', ['row_id'=> $row_id, 'distributor_value' => $distributor_value])}}">
                                View Outlets
                            </a>
                        @endif
                    </div>

                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="project_activities">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th class="d-none">Template Name</th>
                                    <th class="d-none">Group Name</th>
                                    <th >Activity</th>
                                    <th class="d-none">Sequence</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($userAssignedActivities as $userAssignedActivity)
                                    <tr @if($userAssignedActivity['is_master']) class="bg-success" @endif>
                                        <td @if($userAssignedActivity['is_master']) class="bg-success" @endif>{{$loop->iteration}}</td>
                                        <td class="d-none">{{$userAssignedActivity['template_name']}}</td>
                                        <td class="d-none">@if(isset($userAssignedActivity['group_name']))
                                                {{$userAssignedActivity['group_name']}}
                                            @else
                                                -
                                            @endif</td>
                                        <td>{{$userAssignedActivity['activity_name']}}</td>
                                        <td  class="d-none">
                                            @if(isset($userAssignedActivity['sequence']))
                                                {{$userAssignedActivity['sequence']}}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <ul class="action">
                                                @if($userAssignedActivity['is_master'])

                                                    @if($userAssignedActivity['answer_submitted'] && $userAssignedActivity['otpVerificationDone'])
                                                        <a><i class="icon-check text-white fs-5"></i></a>
                                                    @elseif($userAssignedActivity['answer_submitted'] && !$userAssignedActivity['otpVerificationDone'])
                                                        <a class="text-white"
                                                           href="{{route('otp_verification_page', ['row_id'=>$row_id, 'activity' => $userAssignedActivity['activity_id']])}}">
                                                            OTP Verification Pending
                                                        </a>
                                                    @else
                                                        <li class="view">
                                                            <a
                                                                href="{{route('user.project.row_id.activity', ['row_id'=>$row_id, 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']])}}"><i
                                                                    class="icon-eye text-white fs-5"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                @else
                                                    @if($userAssignedActivity['answer_submitted'] && $userAssignedActivity['otpVerificationDone'])
                                                        <a><i class="icon-check text-white fs-5"></i></a>
                                                    @elseif($userAssignedActivity['answer_submitted'] && !$userAssignedActivity['otpVerificationDone'])
                                                        <a
                                                            href="{{route('otp_verification_page', ['row_id'=>$row_id, 'activity' => $userAssignedActivity['activity_id']])}}"><i
                                                                class="icon-eye text-white fs-5"></i></a>
                                                    @else
                                                        <li class="view"><a
                                                                href="{{route('user.project.row_id.activity', [ 'row_id'=>$row_id, 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']])}}"><i
                                                                    class="icon-eye text-white fs-5"></i></a></li>
                                                    @endif
                                                @endif
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div>
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
        $(document).ready(function () {
            const project_activities = $("#project_activities").DataTable({
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
            $("#project_activities").on("click", ".delete", function (event) {
                const unit_id = $(this).data('id');
                const tar_row = $(this).closest('tr');
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
                            url: '{{route('unit.destroy')}}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": unit_id
                            },
                            success: function (response) {
                                if (response == "Success") {
                                    project_activities.row(tar_row).remove().draw();
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
        })
    </script>
@endsection
