@extends('template.layouts.simple.master')
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Distributors
                            @if ($isAuditClosed)
                                <span class="badge badge-danger ">Audit Closed</span>
                            @endif

                        </h4>

                        <h3>Activity : {{ $activity->activity_name }}</h3>

                        @if ($showCloseAuditButton)
                            <div class="text-end">
                                <button class=" btn btn-primary" id="closeAuditButton"
                                    onclick="closeAudit('{{ $activity->id }}', '{{ $project->id }}', '{{ $template->id }}', {{ json_encode($distributors_arr) }}, {{ $group_session_id }})">Close
                                    Audit</button>
                            </div>
                        @endif

                    </div>

                    <div class="card-body">
                        <div class="row g-sm-4 g-3">
                            @forelse($paginatedDistributors as $distributor_data)
                                <div class="col-xl-4 col-md-6">
                                    @php
                                        $completed = 0;
                                        if ($distributor_data['completion_type'] == 'Percentage') {
                                            if ($distributor_data['total_outlets'] > 0) {
                                                $dist_percent =
                                                    ($distributor_data['total_outlet_answered'] /
                                                        $distributor_data['total_outlets']) *
                                                    100;
                                                if ($dist_percent >= $distributor_data['min_completion']) {
                                                    $completed = 1;
                                                }
                                            }
                                        } else {
                                            if (
                                                $distributor_data['total_outlet_answered'] >=
                                                $distributor_data['min_completion']
                                            ) {
                                                $completed = 1;
                                            }
                                        }
                                    @endphp

                                    <div
                                        class="prooduct-details-box row_data_con @if ($completed) disabled @endif">
                                        <div class="d-flex">
                                            {{--                                    <img class="align-self-center img-fluid img-60" src="../assets/images/ecommerce/product-table-6.png" alt="#"> --}}
                                            <div class="flex-grow-1 ms-3">
                                                <div class="product-name">
                                                    <h6>
                                                        @if (isset($distributor_data['head_name']))
                                                            {{ $distributor_data['head_name'] }}
                                                        @else
                                                            {{ $distributor_data['main_header'] }}
                                                        @endif
                                                        <span class="show-details float-end"
                                                            data-val="{{ $distributor_data['value'] }}"><i
                                                                class="fs-4 icofont icofont-exclamation-circle"></i></span>
                                                        <span class="float-end fs-5">
                                                            {{ $distributor_data['total_outlet_answered'] }}/{{ $distributor_data['total_outlets'] }}</span>

                                                    </h6>
                                                </div>
                                                <div class="price d-flex border-0 p-0">
                                                    <div class="text-muted me-2">
                                                        @if (isset($distributor_data['sub_header']))
                                                            {{ $distributor_data['sub_header'] }}
                                                        @else
                                                            {{ $distributor_data['value'] }}
                                                        @endif
                                                    </div>
                                                    <div class="text-muted" style="margin-top: 10%;">
                                                        @php
                                                        $type = ($currentUserROle == 'Company User') ? 1 : 0;
                                                        @endphp
                                                        @if (!$completed)
                                                            <a class="btn btn-primary btn-xs"
                                                                href="{{ route('user.project.distributor.outlets', ['projectTemplate' => $distributor_data['project_template_id'], 'activity' => $distributor_data['activity_id'], 'distributor_value' => $distributor_data['value'],'type'=>$type, 'group_info' => $group_session_id ? $group_session_id : null]) }}">
                                                                View Outlets
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="avaiabilty">
                                                </div>
                                                <div class="border">
                                                    <div class="show-con d-none">
                                                        <span>
                                                            {{ $distributor_data['getDataOfRows']->template_head_name??'' }}
                                                            {{ $distributor_data['getDataOfRows']->value??'' }}
                                                        </span>
                                                         <br>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p>No Data Found</p>
                            @endforelse

                        </div>
                    </div>
                    {{ $paginatedDistributors->links() }}
                </div>
            </div>
            <!-- Zero Configuration  Ends-->
            <div class="modal_con">
                <div class="modal fade" id="row_detail_modal" tabindex="-1" role="dialog"
                    aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="row_data_title"></h5>
                                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div id="main-content"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        function closeAudit(activityId, projectId, templateId, distributorData, group_session_id) {
            // console.log(distributorData);
            $.ajax({
                url: "{{ route('closeAuditData') }}",
                method: "POST",
                data: {
                    "_token": "{{ csrf_token() }}",
                    'activity_id': activityId,
                    'project_id': projectId,
                    'template_name_id': templateId,
                    'distributorData': distributorData,
                    'group_session_id': group_session_id,
                },
                success: function(response) {
                    console.log(response);
                    if (response.status) {
                        Swal.fire({
                            position: "center",
                            icon: "success",
                            title: "Audit Closed Successfully",
                            timer: 3000, // 3 seconds
                            timerProgressBar: true,
                            didClose: () => {
                                location.reload(); // Reload the page after the alert closes
                            }
                        });
                    } else {
                        Swal.fire({
                            position: "center",
                            icon: "warning",
                            title: "Something Went Wrong!",
                            timer: 3000, // 3 seconds
                            timerProgressBar: true,
                            didClose: () => {
                                location.reload();
                            }
                        });
                    }
                }
            });
        }

        $(document).ready(function() {
            @if (session()->has('message'))
                Swal.fire({
                    position: "top-center",
                    icon: "success",
                    title: "{{ session('message') }}",
                    showConfirmButton: false,
                    timer: 1500
                });
            @endif

            $(".show-details").click(function(event) {
                const data_val = $(this).data('val');
                const row_con = $(this).closest('.row_data_con');
                const row_data = row_con.find('.show-con').html();
                $("#main-content").html(row_data)
                $("#row_data_title").html(data_val)
                $("#row_detail_modal").modal('show');
            })


        })
    </script>
@endsection
