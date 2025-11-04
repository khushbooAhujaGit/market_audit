@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Project : {{ optional($project)->project_name }}
                            {{--                            <a href="{{route('unit.create')}}"><button class = "btn btn-primary float-end">Create</button></a> --}}
                        </h4>
                        @if (isset($projectTemplateInfo))
                            <h4>{{ $projectTemplateInfo->getTemplate->template_name }}</h4>
                        @endif

                    </div>
                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="project_activities">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th>Template Name</th>
                                        <th>Group Name</th>
                                        <th>Activity</th>
                                        <th>Sequence</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $count=0;
                                    @endphp
                                    @foreach ($userAssignedActivities as $userAssignedActivity)

                                        @php
                                            if ($userAssignedActivity['is_master']) {
                                                continue;
                                            }
                                            $count++;
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $count }}</td>
                                            <td>{{ $userAssignedActivity['template_name'] }}</td>
                                            <td>
                                                @if (isset($userAssignedActivity['group_name']))
                                                    {{ $userAssignedActivity['group_name'] }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $userAssignedActivity['activity_name'] }}</td>
                                            <td>
                                                @if (isset($userAssignedActivity['sequence']))
                                                    {{ $userAssignedActivity['sequence'] }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>

                                                <ul class="action">
                                                    <li class="view">
                                                        <a
                                                            href="{{ route('user.project_child.data', ['type' => 1, 'project' => $project->id, 'template' => $userAssignedActivity['template_name_id'], 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']]) }}"><i
                                                                class="icon-eye text-secondary fs-5"></i>
                                                        </a>
                                                    </li>
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
        $(document).ready(function() {
            const project_activities = $("#project_activities").DataTable({
                paging: false
            });
            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif

        })
    </script>
@endsection
