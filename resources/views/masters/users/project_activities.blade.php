@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">

                    <div class="card-header">
                        <h4 style="color:#fff !important; margin-top: 40px !important;">
                            {{ optional($project)->project_name }}
                            @if (isset($projectTemplateInfo))
                                &nbsp;&#8212; {{ $projectTemplateInfo->getTemplate->template_name }}
                            @endif

                            @if ($status == 1 || $with_data_check == 0)
                                <a class="btn float-end"
                                    style="background:#fff !important; color:#111 !important; -webkit-text-fill-color:#111 !important; font-size:11px; font-weight:700; border-radius:20px; padding:5px 12px; border:none; margin-left:auto; white-space:nowrap; flex-shrink:0;"
                                    href="{{ route('user.project.distributor.outlets', ['row_id' => $row_id, 'distributor_value' => $distributor_value]) }}">
                                    View Outlets
                                </a>
                            @endif
                        </h4>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive theme-scrollbar">
                            <table class="display" id="project_activities">
                                <thead>
                                    <tr>
                                        <th>Sno</th>
                                        <th class="d-none">Template Name</th>
                                        <th class="d-none">Group Name</th>
                                        <th>Activity</th>
                                        <th class="d-none">Sequence</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $previousDone = true; @endphp
                                    @foreach ($userAssignedActivities as $userAssignedActivity)
                                        @php
                                            $allowRpt       = $userAssignedActivity['allow_repeat'] ?? false;
                                            $isClosed       = $userAssignedActivity['is_fully_closed'] ?? false;
                                            $isSentBack     = $userAssignedActivity['is_sent_back'] ?? false;
                                            $hasAnySentBack = $userAssignedActivity['has_any_sent_back'] ?? false;
                                            $answered   = $userAssignedActivity['answer_submitted'];
                                            $otpOk      = $userAssignedActivity['otpVerificationDone'];
                                            $otpNeeded  = $userAssignedActivity['project_data']['is_otp_required'] == 1;
                                            $instancesUrl = route('user.activity.instances_list', ['row_id' => $row_id, 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']]);
                                            $questionsUrl = route('user.project.row_id.activity', ['row_id' => $row_id, 'activity' => $userAssignedActivity['activity_id'], 'group_info' => $userAssignedActivity['group_id']]);

                                            // Determine if this activity is fully completed
                                            if ($allowRpt) {
                                                $thisDone = $isClosed && !$isSentBack;
                                            } elseif ($otpNeeded) {
                                                $thisDone = $answered && $otpOk;
                                            } else {
                                                $thisDone = $answered && !$isSentBack;
                                            }

                                            // Lock only if the previous is not done AND this activity
                                            // has no submitted answers and was not sent back.
                                            // Activities already answered or sent back are always accessible.
                                            $isLocked = !$previousDone && !$isSentBack && !$answered;
                                        @endphp
                                        <tr @if ($userAssignedActivity['is_master']) class="bg-success" @endif>
                                            <td @if ($userAssignedActivity['is_master']) class="bg-success" @endif>
                                                {{ $loop->iteration }}
                                            </td>
                                            <td class="d-none">{{ $userAssignedActivity['template_name'] }}</td>
                                            <td class="d-none">
                                                @if (isset($userAssignedActivity['group_name']))
                                                    {{ $userAssignedActivity['group_name'] }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>{{ $userAssignedActivity['activity_name'] }}</td>
                                            <td class="d-none">
                                                @if (isset($userAssignedActivity['sequence']))
                                                    {{ $userAssignedActivity['sequence'] }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <ul class="action">
                                                    @if ($isLocked)
                                                        {{-- Previous activity not yet submitted — show lock --}}
                                                        <li>
                                                            <i class="icon-lock text-white fs-5 bg-secondary p-2"
                                                               title="Complete the previous activity first"></i>
                                                        </li>
                                                    @elseif ($allowRpt)
                                                        {{-- Activity-add-on flow --}}
                                                        @if ($isClosed && !$isSentBack)
                                                            <li><i class="icon-check text-white fs-5 bg-success p-2"></i></li>
                                                        @elseif ($isSentBack)
                                                            <li class="view">
                                                                <a href="{{ $questionsUrl }}">
                                                                    <i class="icon-reload text-white fs-5 bg-danger p-2" title="Sent back — re-submit required"></i>
                                                                </a>
                                                            </li>
                                                        @elseif ($hasAnySentBack)
                                                            <li class="view">
                                                                <a href="{{ $questionsUrl }}">
                                                                    <i class="icon-eye text-white fs-5 p-2" style="background:#f97316;border-radius:4px;" title="Some instances sent back"></i>
                                                                </a>
                                                            </li>
                                                        @else
                                                            <li class="view">
                                                                <a href="{{ $questionsUrl }}">
                                                                    <i class="icon-eye text-white fs-5 bg-dark p-2"></i>
                                                                </a>
                                                            </li>
                                                        @endif
                                                    @else
                                                        {{-- Standard flow --}}
                                                        @if ($isSentBack)
                                                            <li class="view">
                                                                <a href="{{ $questionsUrl }}">
                                                                    <i class="icon-reload text-white fs-5 bg-danger p-2" title="Sent back — re-submit required"></i>
                                                                </a>
                                                            </li>
                                                        @elseif ($otpNeeded)
                                                            @if ($answered && $otpOk)
                                                                <li><i class="icon-check text-white fs-5 bg-success p-2"></i></li>
                                                            @elseif ($answered && !$otpOk)
                                                                <a class="text-white"
                                                                    href="{{ route('otp_verification_page', ['row_id' => $row_id, 'activity' => $userAssignedActivity['activity_id'], 'project_id' => $project->id]) }}">
                                                                    OTP Verification Pending
                                                                </a>
                                                            @else
                                                                <li class="view">
                                                                    <a href="{{ $questionsUrl }}">
                                                                        <i class="icon-eye text-white fs-5 bg-dark p-2"></i>
                                                                    </a>
                                                                </li>
                                                            @endif
                                                        @else
                                                            @if ($answered)
                                                                <li><i class="icon-check text-white fs-5 bg-success p-2"></i></li>
                                                            @elseif ($hasAnySentBack)
                                                                <li class="view">
                                                                    <a href="{{ $questionsUrl }}">
                                                                        <i class="icon-eye text-white fs-5 p-2" style="background:#f97316;border-radius:4px;" title="Some instances sent back"></i>
                                                                    </a>
                                                                </li>
                                                            @else
                                                                <li class="view">
                                                                    <a href="{{ $questionsUrl }}">
                                                                        <i class="icon-eye text-white fs-5 bg-dark p-2"></i>
                                                                    </a>
                                                                </li>
                                                            @endif
                                                        @endif
                                                    @endif
                                                </ul>
                                            </td>
                                        </tr>
                                        @php
                                            // Only propagate incomplete state if this activity was never answered.
                                            // Sent-back or in-progress activities don't block already-answered ones.
                                            if (!$thisDone && !$answered && !$isSentBack) $previousDone = false;
                                        @endphp
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
    
    // &#9472;&#9472; Universal mobile keyboard suppression &#9472;&#9472;
// Runs on every page, only on mobile browsers
if (window.innerWidth <= 767 || /iPhone|iPad|iPod|Android/i.test(navigator.userAgent)) {
    
    // 1. Intercept DataTables BEFORE it focuses anything
    if (typeof $.fn.DataTable !== 'undefined') {
        var _originalDataTable = $.fn.DataTable;
        $.fn.DataTable = function(options) {
            var result = _originalDataTable.apply(this, arguments);
            // After DT init, immediately kill focus on search
            var container = this.closest('.dataTables_wrapper') || 
                            $('#' + this.attr('id') + '_wrapper');
            setTimeout(function() {
                $(container).find('input[type="search"]')
                    .attr('inputmode', 'none')
                    .attr('readonly', 'readonly')
                    .css('font-size', '16px')
                    .off('focus.dtmobile')
                    .on('focus.dtmobile touchstart.dtmobile click.dtmobile', function() {
                        $(this)
                            .attr('inputmode', 'text')
                            .removeAttr('readonly');
                        $(this).off('focus.dtmobile touchstart.dtmobile click.dtmobile');
                    });
                // Kill any active focus
                if (document.activeElement && 
                    document.activeElement.tagName !== 'BODY') {
                    document.activeElement.blur();
                }
            }, 0);
            return result;
        };
        // Copy all DataTable properties over
        $.extend($.fn.DataTable, _originalDataTable);
    }

    // 2. Catch any input that gets focused on page load
    var blurCount = 0;
    var blurInterval = setInterval(function() {
        if (document.activeElement && 
            document.activeElement !== document.body &&
            document.activeElement.tagName !== 'BUTTON' &&
            document.activeElement.tagName !== 'A') {
            document.activeElement.blur();
        }
        blurCount++;
        if (blurCount >= 10) clearInterval(blurInterval); // Stop after 1 second
    }, 100);
}

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
            
            // &#9472;&#9472; Universal: prevent keyboard popup on every page load (mobile only) &#9472;&#9472;
if (window.innerWidth <= 767) {
    // Mark ALL inputs as readonly before DOM is interactive
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not([type="checkbox"]):not([type="radio"]), textarea').forEach(function(el) {
            if (!el.readOnly && !el.disabled) {
                el.setAttribute('readonly', 'readonly');
                el.addEventListener('touchstart', function() {
                    this.removeAttribute('readonly');
                }, { once: true });
                el.addEventListener('click', function() {
                    this.removeAttribute('readonly');
                }, { once: true });
            }
        });

        // Final safety blur after everything loads
        setTimeout(function() {
            if (document.activeElement && document.activeElement.tagName !== 'BODY') {
                document.activeElement.blur();
            }
        }, 200);
    });
}
            
            
            $("#project_activities").on("click", ".delete", function(event) {
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
                            url: '{{ route('unit.destroy') }}',
                            type: "POST",
                            data: {
                                "_token": "{{ csrf_token() }}", // Add the CSRF token to the data
                                "id": unit_id
                            },
                            success: function(response) {
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
