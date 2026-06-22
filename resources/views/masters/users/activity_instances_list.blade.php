@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 style="color:#fff !important; margin-top: 40px !important;">
                            {{ optional($project)->project_name }}
                            &nbsp;— {{ $activity->activity_name }}
                            {{-- Close button: validates all instances submitted then marks activity closed --}}
                            <a href="javascript:void(0)" id="closeInstancesBtn"
                                class="btn float-end ms-2"
                                style="background:#28a745 !important; color:#fff !important; -webkit-text-fill-color:#fff !important; font-size:11px; font-weight:700; border-radius:20px; padding:5px 12px; border:none; white-space:nowrap;"
                                data-row_id="{{ $row_id }}"
                                data-activity_id="{{ $activity->id }}"
                                title="Close all instances">
                                Close
                            </a>
                            <a href="javascript:void(0)" class="btn add-instance-btn float-end ms-2"
                                style="background:#fff !important; color:#111 !important; -webkit-text-fill-color:#111 !important; font-size:14px; font-weight:700; border-radius:50%; width:32px; height:32px; padding:0; line-height:32px; border:none; text-align:center;"
                                data-row_id="{{ $row_id }}"
                                data-activity_id="{{ $activity->id }}"
                                data-group_id="{{ $group_info }}"
                                title="Add another instance">
                                <i class="icon-plus"></i>
                            </a>
                        </h4>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="activity_instances_table">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th>Instance</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($instances as $index => $instance)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $instance['label'] }}</td>
                                            <td>
                                                @if ($instance['submitted'])
                                                    <span class="badge bg-success">Submitted</span>
                                                @elseif ($instance['sent_back'])
                                                    <span class="badge bg-danger">Sent Back</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                <ul class="action">
                                                    @if ($instance['submitted'])
                                                        <li>
                                                            <i class="icon-check text-white fs-5 bg-success p-2"></i>
                                                        </li>
                                                    @elseif ($instance['sent_back'])
                                                        <li class="view">
                                                            <a class="bg-danger"
                                                                href="{{ route('user.project.row_id.activity', ['row_id' => $row_id, 'activity' => $activity->id, 'group_info' => $group_info, 'activity_sequence' => $instance['activity_sequence']]) }}">
                                                                <i class="icon-reload text-white fs-5 bg-dark p-2"></i>
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li class="view">
                                                            <a href="{{ route('user.project.row_id.activity', ['row_id' => $row_id, 'activity' => $activity->id, 'group_info' => $group_info, 'activity_sequence' => $instance['activity_sequence']]) }}">
                                                                <i class="icon-eye text-white fs-5 bg-dark p-2"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                    @if (!$instance['submitted'] && !$instance['is_original'] && $instance['user_id'] == Auth::id())
                                                        <li class="remove-instance">
                                                            <a href="javascript:void(0)" class="delete-instance-btn"
                                                                data-instance_id="{{ $instance['instance_id'] }}"
                                                                title="Remove Instance">
                                                                <i class="icon-trash text-white fs-5 bg-danger p-2"></i>
                                                            </a>
                                                        </li>
                                                    @endif
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Instance Modal --}}
    <div class="modal fade" id="addInstanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="addInstanceForm" method="post" action="{{ route('user.activity.add_instance') }}">
                @csrf
                <input type="hidden" name="row_id" id="add_instance_row_id">
                <input type="hidden" name="activity_id" id="add_instance_activity_id">
                <input type="hidden" name="group_id" id="add_instance_group_id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Instance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="instance_label" class="form-label">Instance Label</label>
                        <input type="text" class="form-control" id="instance_label" name="instance_label"
                            placeholder="e.g. Visit 2" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Delete Instance Form --}}
    <form id="deleteInstanceForm" method="post" action="{{ route('user.activity.delete_instance') }}" class="d-none">
        @csrf
        <input type="hidden" name="instance_id" id="delete_instance_id">
    </form>

    <nav class="mobile-bottom-nav d-md-none">
        <a href="{{ route('user.projects') }}" class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
            <i class="icon-folder"></i>
            Projects
        </a>
        <a href="javascript:history.back()">
            <i class="icon-arrow-left"></i>
            Back
        </a>
    </nav>
@endsection
@section('scripts')
    <script>
        $(document).ready(function() {
            $('#activity_instances_table').DataTable({
                paging: false,
                searching: false,
                info: false,
                ordering: false,
            });

            $('#closeInstancesBtn').on('click', function() {
                const rowId = $(this).data('row_id');
                const activityId = $(this).data('activity_id');
                Swal.fire({
                    title: 'Close this activity?',
                    text: 'All instances must be submitted. Once closed, you cannot add or edit instances until the verifier sends it back.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, Close It'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('user.activity.close_instances') }}',
                            type: 'POST',
                            data: {
                                '_token': '{{ csrf_token() }}',
                                'row_id': rowId,
                                'activity_id': activityId
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'Closed!',
                                        text: 'Activity instances have been closed.',
                                        icon: 'success',
                                        timer: 1800,
                                        showConfirmButton: false
                                    }).then(() => {
                                        history.back();
                                    });
                                } else {
                                    Swal.fire('Cannot Close', response.message, 'warning');
                                }
                            }
                        });
                    }
                });
            });

            $(document).on('click', '.add-instance-btn', function(event) {
                event.preventDefault();
                $('#add_instance_row_id').val($(this).data('row_id'));
                $('#add_instance_activity_id').val($(this).data('activity_id'));
                $('#add_instance_group_id').val($(this).data('group_id'));
                $('#instance_label').val('');
                var modal = new bootstrap.Modal(document.getElementById('addInstanceModal'));
                modal.show();
            });

            $(document).on('click', '.delete-instance-btn', function(event) {
                event.preventDefault();
                const instanceId = $(this).data('instance_id');
                Swal.fire({
                    title: 'Remove this instance?',
                    text: 'This will permanently delete this instance and any answers saved for it.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, remove it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#delete_instance_id').val(instanceId);
                        $('#deleteInstanceForm').submit();
                    }
                });
            });
        });
    </script>
@endsection
