@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Sub Questions</h4>
                            <p class="mb-0 text-muted">
                                Parent Question: <strong>{{ $question->question }}</strong>
                                <span class="badge bg-light-primary ms-2">{{ $question->question_type }}</span>
                            </p>
                        </div>
                        <a href="{{ route('activities.question', ['activity_id' => $activity->id]) }}">
                            <button class="btn btn-primary">Back to Questions</button>
                        </a>
                    </div>
                    <div class="card-body">

                        @if (session('message'))
                            <div class="alert alert-success">{{ session('message') }}</div>
                        @endif
                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        {{-- Linked Sub-Questions Table --}}
                        <h5 class="mb-3">Linked Sub Questions</h5>
                        @if ($question->subQuestions->count() > 0)
                            <div class="table-responsive mb-4">
                                <table class="table table-bordered" id="sub_questions_table">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:120px;">Sequence</th>
                                            <th>Question</th>
                                            <th>Type</th>
                                            <th>Answer Type</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($question->subQuestions as $link)
                                            <tr data-link-id="{{ $link->id }}">
                                                {{-- Sequence cell with inline edit --}}
                                                <td>
                                                    <div class="d-flex align-items-center gap-1">
                                                        <span class="seq-display">{{ $link->sequence }}</span>
                                                        <input type="number" class="form-control form-control-sm seq-input d-none"
                                                            value="{{ $link->sequence }}" min="1" style="width:70px;">
                                                        <button class="btn btn-xs btn-outline-secondary seq-edit-btn" title="Edit sequence">
                                                            <i class="icon-pencil-alt" style="font-size:11px;"></i>
                                                        </button>
                                                        <button class="btn btn-xs btn-success seq-save-btn d-none" title="Save sequence">
                                                            <i class="icon-check" style="font-size:11px;"></i>
                                                        </button>
                                                    </div>
                                                </td>

                                                {{-- Question info --}}
                                                <td>
                                                    {{ $link->childQuestion->question }}
                                                    @if ($link->childQuestion->question_type === 'Multi Response')
                                                        <span class="badge bg-info ms-1" title="This question is a Multi Response — it groups further sub-questions">
                                                            Multi Response
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>{{ $link->childQuestion->question_type }}</td>
                                                <td>
                                                    @if ($link->childQuestion->answer_type)
                                                        <span class="badge bg-danger">Required</span>
                                                    @else
                                                        <span class="badge bg-secondary">Optional</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-wrap">
                                                        {{-- If child is Outlet type → allow managing its own sub-questions --}}
                                                        @if ($link->childQuestion->question_type === 'Multi Response')
                                                            <a href="{{ route('activity.question.sub_questions', ['question_id' => $link->childQuestion->id]) }}"
                                                                class="btn btn-sm btn-outline-success" title="Manage sub-questions of this Multi Response">
                                                                <i class="icon-layers"></i> Sub-Q's
                                                            </a>
                                                        @endif
                                                        <button class="btn btn-sm btn-danger delete-sub-link"
                                                            data-id="{{ $link->id }}">
                                                            <i class="icon-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="alert alert-info mb-4">No sub-questions linked yet.</div>
                        @endif

                        {{-- Add Sub-Question Form --}}
                        <h5 class="mb-3">Link a Sub Question</h5>
                        @if ($available_questions->count() > 0)
                            <form action="{{ route('activity.question.sub_question.store') }}" method="POST">
                                @csrf
                                <input type="hidden" name="parent_question_id" value="{{ $question->id }}">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label">Select Question <span class="text-danger">*</span></label>
                                        <select name="child_question_id" class="form-select" required>
                                            <option value="">-- Select a question --</option>
                                            @foreach ($available_questions as $aq)
                                                <option value="{{ $aq->id }}">
                                                    [{{ $aq->question_type }}]
                                                    {{ $aq->question }}
                                                    ({{ $aq->answer_type ? 'Required' : 'Optional' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Sequence <span class="text-danger">*</span></label>
                                        <input type="number" name="sequence" class="form-control"
                                            value="{{ $question->subQuestions->count() + 1 }}" min="1" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="icon-plus"></i> Link Sub Question
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="alert alert-warning">All available child questions are already linked.</div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('scripts')
    <script>
        $(document).ready(function () {

            // ── Sequence inline edit ──────────────────────────────────────
            $(document).on('click', '.seq-edit-btn', function () {
                const $row = $(this).closest('tr');
                $row.find('.seq-display').addClass('d-none');
                $row.find('.seq-input').removeClass('d-none').focus();
                $(this).addClass('d-none');
                $row.find('.seq-save-btn').removeClass('d-none');
            });

            $(document).on('click', '.seq-save-btn', function () {
                const $row = $(this).closest('tr');
                const linkId = $row.data('link-id');
                const newSeq = parseInt($row.find('.seq-input').val());

                $.ajax({
                    url: '{{ route('activity.question.sub_question.update_sequence') }}',
                    type: 'POST',
                    data: { '_token': '{{ csrf_token() }}', 'id': linkId, 'sequence': newSeq },
                    success: function (response) {
                        if (response.status === 'Success') {
                            // Update this row's displayed sequence
                            $row.find('.seq-display').text(newSeq).removeClass('d-none');
                            $row.find('.seq-input').val(newSeq).addClass('d-none');
                            $row.find('.seq-edit-btn').removeClass('d-none');
                            $row.find('.seq-save-btn').addClass('d-none');

                            // If a swap happened, update the other row's displayed sequence too
                            if (response.swapped_id) {
                                const $swapRow = $('tr[data-link-id="' + response.swapped_id + '"]');
                                $swapRow.find('.seq-display').text(response.swapped_seq);
                                $swapRow.find('.seq-input').val(response.swapped_seq);
                            }

                            Swal.fire({ icon: 'success', title: 'Sequence updated', timer: 1000, showConfirmButton: false });
                        }
                    }
                });
            });

            // ── Remove sub-question link ──────────────────────────────────
            $(document).on('click', '.delete-sub-link', function () {
                const linkId = $(this).data('id');
                const $btn = $(this);
                Swal.fire({
                    title: 'Remove Sub Question?',
                    text: 'This will unlink the sub-question from the parent.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, remove it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '{{ route('activity.question.sub_question.destroy') }}',
                            type: 'POST',
                            data: { '_token': '{{ csrf_token() }}', 'id': linkId },
                            success: function (response) {
                                if (response.status === 'Success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Removed!',
                                        text: 'Sub-question unlinked.',
                                        timer: 1200,
                                        showConfirmButton: false
                                    }).then(function () {
                                        window.location.reload();
                                    });
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
