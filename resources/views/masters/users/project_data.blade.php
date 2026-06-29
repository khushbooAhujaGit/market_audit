@extends('template.layouts.simple.master')
@section('styles')
    <style>
        /* Mobile-First Responsive Design */
        #projectDataTable {
            width: 100%;
            margin-bottom: 0;
        }

        #projectDataTable thead {
            display: none;
            /* Hide header on mobile */
        }

        #projectDataTable tbody tr {
            display: block;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        #projectDataTable tbody td {
            display: block;
            border: none;
            padding: 5px 0;
            text-align: left;
        }

        /* Serial number styling */
        .serial-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 50%;
            font-weight: 600;
        }

        /* Outlet card styling */
        .outlet-card {
            padding: 10px 0;
        }

        .outlet-name {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .outlet-info {
            font-size: 14px;
            color: #6c757d;
        }

        /* Status badge */
        .status-badge .badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Action buttons */
        .action-buttons .btn {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 4px;
            width: 100%;
            max-width: 80px;
            margin: 2px auto;
        }

        .btn-block {
            display: block;
        }

        /* Detail items */
        .detail-item {
            margin-bottom: 5px;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: #6c757d;
            margin-left: 5px;
        }

        /* Show details icon */
        .show-details {
            cursor: pointer;
            color: #0d6efd;
        }

        /* Tablet and larger screens */
        @media (min-width: 768px) {
            #projectDataTable thead {
                display: table-header-group;
            }

            #projectDataTable tbody tr {
                display: table-row;
                border: none;
                border-radius: 0;
                margin-bottom: 0;
                padding: 0;
                box-shadow: none;
            }

            #projectDataTable tbody td {
                display: table-cell;
                padding: 12px 15px;
                border-top: 1px solid #e9ecef;
                text-align: center;
                vertical-align: middle;
            }

            #projectDataTable tbody tr:hover {
                background-color: #f8f9fa;
            }

            .outlet-card {
                padding: 0;
                text-align: left;
            }

            .action-buttons .btn {
                width: auto;
                display: inline-block;
                margin: 2px;
            }

            .btn-block {
                display: inline-block;
            }
        }

        /* Large screens */
        @media (min-width: 992px) {
            .outlet-name {
                font-size: 16px;
            }

            .action-buttons .btn {
                font-size: 12px;
                padding: 6px 12px;
            }
        }

        /* Extra small devices (phones, 320px and up) */
        @media (max-width: 374px) {
            .outlet-name {
                font-size: 14px;
            }

            .outlet-info {
                font-size: 12px;
            }

            .status-badge .badge {
                font-size: 11px;
                padding: 4px 8px;
            }

            .action-buttons .btn {
                font-size: 11px;
                padding: 4px 8px;
                max-width: 70px;
            }

            .serial-number {
                width: 26px;
                height: 26px;
                line-height: 26px;
                font-size: 14px;
            }
        }

        /* Prevent horizontal scroll */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Ensure no horizontal scroll */
        body,
        .page-body,
        .card-body {
            overflow-x: hidden;
        }

        /* Card header responsive */
        .card-header h4 {
            font-size: 18px;
        }

        @media (max-width: 576px) {
            .card-header h4 {
                font-size: 16px;
            }

            .float-end {
                float: right;
                margin-top: 5px;
            }
        }

        .btn-danger .icon-refresh,
        .btn-danger i {
            color: #fff !important;
        }
    </style>
@endsection
@section('content')
    <div class="page-body">
        <div class="row">
            <!-- Zero Configuration  Starts-->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h4 style="color:#fff !important;  margin-top: 40px !important;">
                            My Project Data
                            @if ($currentUserRole == 'Auditor')
                                <button id="add_data_btn" class="btn float-end"
                                    style="background:#fff !important; color:#111 !important; -webkit-text-fill-color:#111 !important; font-size:11px; font-weight:700; border-radius:20px; padding:5px 12px; border:none; margin-left:auto;">
                                    {{ $add_project_data_title }}
                                </button>
                            @endif
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Table with Card-like Layout -->
                        <!-- Mobile-friendly Responsive Table -->
                        <div class="table-responsive" style="margin-top:90px !important;">
                            <table class="table table-hover" id="projectDataTable">
                                <thead>
                                    <tr>
                                        <th width="50px">#</th>
                                        <th>Outlet Details</th>
                                        <th width="100px">Status</th>
                                        <th width="100px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($project_data_arr as $index => $project_data)
                                        <tr>
                                            <!-- Serial Number -->
                                            <td class="align-middle">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <strong class="serial-number">{{ $index + 1 }}</strong>
                                                </div>
                                            </td>

                                            <!-- Card-style Content -->
                                            <td class="outlet-cell">
                                                <div class="outlet-card">
                                                    <!-- Main Header -->
                                                    <div class="outlet-name mb-1">
                                                        <strong>
                                                            @if (isset($project_data['main_header']))
                                                                {{ $project_data['main_header'] }}
                                                            @else
                                                                {{ $project_data['data_item']['head_name'] }}
                                                            @endif
                                                        </strong>
                                                        <span class="show-details float-end" data-bs-toggle="tooltip"
                                                            title="View Details"
                                                            data-val="{{ $project_data['data_item']['head_value'] }}">
                                                            <i class="icofont icofont-exclamation-circle text-primary"></i>
                                                        </span>
                                                    </div>

                                                    <!-- Sub Header -->
                                                    <div class="outlet-info text-muted small">
                                                        @if (isset($project_data['sub_header']))
                                                            {{ $project_data['sub_header'] }}
                                                        @else
                                                            {{ $project_data['data_item']['head_value'] }}
                                                        @endif
                                                    </div>

                                                    <!-- Hidden Details -->
                                                    <div class="show-con d-none">
                                                        @if (!empty($project_data['header_data']))
                                                            @foreach ($project_data['header_data'] as $head_data)
                                                                <div class="detail-item">
                                                                    <span
                                                                        class="detail-label">{{ $head_data['head_name'] ?? '' }}:</span>
                                                                    <span
                                                                        class="detail-value">{{ $head_data['value'] ?? '' }}</span>
                                                                </div>
                                                            @endforeach
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Status Column -->
                                            <td class="align-middle">
                                                <div class="status-badge text-center">

                                                    @if (
                                                        $project_data['status'] == 'completed' &&
                                                            $project_data['isOtpRequired'] == 1 &&
                                                            $project_data['otpVerificationDone']
                                                    )

                                                        @if (isset($project_data['outletexist']) && $project_data['outletexist'])
                                                            @if ($project_data['existingoutletcompletestatus'] && $project_data['outlet_data_add_on'] == 0)
                                                                <span class="badge bg-success">Completed</span>
                                                            @else
                                                                <span class="badge bg-gray text-dark">Pending</span>
                                                            @endif
                                                        @else
                                                            <span class="badge bg-success">Completed</span>
                                                        @endif
                                                    @elseif(
                                                        $project_data['status'] == 'completed' &&
                                                            $project_data['isOtpRequired'] == 1 &&
                                                            !$project_data['otpVerificationDone']
                                                    )
                                                        <span class="badge bg-gray text-dark">Pending</span>
                                                    @elseif($project_data['status'] == 'completed' && $project_data['isOtpRequired'] == 0)
                                                        <!--<span class="badge bg-success">Completed</span>-->
                                                        @if (isset($project_data['outletexist']) && $project_data['outletexist'])
                                                            @if ($project_data['existingoutletcompletestatus'] && $project_data['outlet_data_add_on'] == 0)
                                                                <span class="badge bg-success">Completed</span>
                                                            @else
                                                                <span class="badge bg-gray text-dark">Pending</span>
                                                            @endif
                                                        @else
                                                            <span class="badge bg-success">Completed</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-gray text-dark ">
                                                            {{ ucfirst($project_data['status']) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>

                                            <!-- Actions Column -->
                                            <td class="align-middle">
                                                <div class="action-buttons">

                                                    @if (
                                                        $project_data['status'] == 'completed' &&
                                                            $project_data['isOtpRequired'] == 1 &&
                                                            $project_data['otpVerificationDone']
                                                    )

                                                        {{-- Completed + OTP Done --}}
                                                        @if (isset($project_data['outletexist']) && $project_data['outletexist'])
                                                            @if ($project_data['existingoutletcompletestatus'] && $project_data['outlet_data_add_on'] == 0)
                                                                @if ($currentUserRole == 'Company User')
                                                                    <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                        onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @else
                                                                    <a class="btn btn-success btn-xs btn-block mb-1 d-none">
                                                                        <i class="icon-check text-white fs-5"></i>
                                                                    </a>
                                                                @endif
                                                            @else
                                                                @if ($currentUserRole == 'Company User')
                                                                    <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                        onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @else
                                                                    <a class="btn btn-warning btn-xs btn-block mb-1"
                                                                        @if ($isParent == 0) href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['id'], 'status' => $isOutletAssigned]) }}">
                                                                    @else
                                                                        href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['row_id'], 'status' => $isOutletAssigned]) }}
                                                                        "> @endif
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        @else
                                                            @if ($currentUserRole == 'Company User')
                                                                <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                    onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                    <i class="icon-eye text-white fs-5"></i>
                                                                </a>
                                                            @else
                                                                <a class="btn btn-success btn-xs btn-block mb-1 d-none">
                                                                    <i class="icon-check text-white fs-5"></i>
                                                                </a>
                                                            @endif
                                                        @endif
                                                    @elseif(
                                                        $project_data['status'] == 'completed' &&
                                                            $project_data['isOtpRequired'] == 1 &&
                                                            !$project_data['otpVerificationDone']
                                                    )
                                                        {{-- Completed + OTP Pending --}}
                                                        @if ($currentUserRole == 'Company User')
                                                            <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                <i class="icon-eye text-white fs-5"></i>
                                                            </a>
                                                        @else
                                                            @php
                                                                $url =
                                                                    $isParent == 0
                                                                        ? route('user.project.assigned_activities', [
                                                                            'row_id' =>
                                                                                $project_data['data_item']['id'],
                                                                            'status' => $isOutletAssigned,
                                                                        ])
                                                                        : route('user.project.assigned_activities', [
                                                                            'row_id' =>
                                                                                $project_data['data_item']['row_id'],
                                                                            'status' => $isOutletAssigned,
                                                                        ]);
                                                            @endphp

                                                            <a href="{{ $url }}"
                                                                class="btn btn-primary btn-xs btn-block mb-1">
                                                                <i class="icon-eye text-white fs-5"></i>
                                                            </a>
                                                        @endif
                                                    @elseif($project_data['status'] == 'completed' && $project_data['isOtpRequired'] == 0)
                                                        @if (isset($project_data['outletexist']) && $project_data['outletexist'])
                                                            @if ($project_data['existingoutletcompletestatus'] && $project_data['outlet_data_add_on'] == 0)
                                                                @if ($currentUserRole == 'Company User')
                                                                    <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                        onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @else
                                                                    <a class="btn btn-success btn-xs btn-block mb-1 ">
                                                                        <i class="icon-check text-white fs-5"></i>
                                                                    </a>
                                                                @endif
                                                            @else
                                                                @if ($currentUserRole == 'Company User')
                                                                    <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                        onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @else
                                                                    <a class="btn btn-primary btn-xs btn-block mb-1"
                                                                        @if ($isParent == 0) href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['id'], 'status' => $isOutletAssigned]) }}">
                                                                    @else
                                                                        href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['row_id'], 'status' => $isOutletAssigned]) }}
                                                                        "> @endif
                                                                        <i class="icon-eye text-white fs-5"></i>
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        @else
                                                            @if ($currentUserRole == 'Company User')
                                                                <a class="btn btn-success btn-xs btn-block mb-1"
                                                                    onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')">
                                                                    <i class="icon-check text-white fs-5"></i>
                                                                </a>
                                                            @else
                                                                <a class="btn btn-success btn-xs btn-block mb-1"
                                                                    @if ($isParent == 0) href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['id'], 'status' => $isOutletAssigned]) }}">
                                                                @else
                                                                    href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['row_id'], 'status' => $isOutletAssigned]) }}
                                                                    "> @endif
                                                                    <i class="icon-check text-white fs-5"></i>
                                                                </a>
                                                            @endif
                                                        @endif
                                                    @else
                                                        {{-- Pending/In Progress --}}
                                                        @if ($project_data['can_edit_data'] == 1)
                                                            <a class="btn btn-info btn-xs btn-block mb-1"
                                                                onclick="showEditModal({{ isset($project_data['data_item']['id']) ? $project_data['data_item']['id'] : $project_data['data_item']['row_id'] }})">
                                                                <i class="icon-pencil text-white fs-5"></i>
                                                            </a>
                                                        @endif
                                                        <a class="btn btn-primary btn-xs btn-block"
                                                            @if ($currentUserRole == 'Company User') onclick="viewQuestionModel('{{ $project_data['data_item']['id'] }}', '{{ $activity->id }}')"
                                                        @else
                                                            @if ($isParent == 0)
                                                                href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['id'], 'status' => $isOutletAssigned]) }}"
                                                            @else
                                                                href="{{ route('user.project.assigned_activities', ['row_id' => $project_data['data_item']['row_id'], 'status' => $isOutletAssigned]) }}" @endif
                                                            @endif
                                                            >
                                                            <i class="icon-eye text-white fs-5"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        @empty
                                            <tr style="width:100% !important;">
                                                <td colspan="4" class="text-center py-4">
                                                    <div class="text-muted justify-content-center"
                                                        style="width:200px !important;">
                                                        <i class="fa fa-inbox fa-2x mb-2"></i>
                                                        <p>No Data Found</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
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
                    {{-- Add Data Modal --}}
                    <div class="modal fade" id="add_data" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" role="dialog" aria-labelledby="questionEditModal" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                            <div class="modal-content">
                                <!-- Modal Header with Close (X) Button -->
                                <div class="modal-header">
                                    <h4 class="modal-title">{{ $add_project_data_title }}</h4>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-toggle-wrapper">
                                        <form id="project_data_add_form">
                                            @if ($project_temp_info->is_master == 1)
                                                {{-- Master template form --}}
                                                <div class="row">
                                                    <input type="hidden" name="project_template_id"
                                                        value="{{ $project_temp_info->id }}">
                                                    @foreach ($projectTemplateHeaders as $projectTemplateHeaderInfo)
                                                        <div class="col-xl-6 col-sm-6 mb-2">
                                                            <label class="form-label"
                                                                for="{{ $projectTemplateHeaderInfo->id }}">{{ $projectTemplateHeaderInfo->template_head_name }}</label>
                                                            <input class="form-control"
                                                                id="{{ $projectTemplateHeaderInfo->id }}"
                                                                name="{{ $projectTemplateHeaderInfo->id }}" type="text"
                                                                placeholder="Enter Data" required="">
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                @foreach ($projectTemplateHeaders as $key => $templateHeadersData)
                                                    @php
                                                        $templateId = $templateHeadersData['id'];
                                                        $templateMapping = $templateMappingData[$templateId] ?? null;
                                                    @endphp

                                                    <div class="template-content" id="template-content-{{ $key }}"
                                                        style="{{ $key == 0 ? '' : 'display:none;' }}"
                                                        data-template-id="{{ $templateId }}">
                                                        <div class="row">
                                                            <h3>{{ $templateHeadersData['template_name'] }}</h3>
                                                            <input type="hidden" name="project_template_id"
                                                                value="{{ $templateId }}">

                                                            @foreach ($templateHeadersData['data'] as $projectTemplateHeaderInfo)
                                                                @php
                                                                    $isMasterHead =
                                                                        $templateMapping &&
                                                                        $projectTemplateHeaderInfo->id ==
                                                                            $templateMapping['master_head_id'];
                                                                    $isOwnReference =
                                                                        $templateMapping &&
                                                                        $projectTemplateHeaderInfo->id ==
                                                                            $templateMapping['own_reference_head_id'];
                                                                    $prefilledValue = null;
                                                                    $isReadonly = false;

                                                                    if (
                                                                        $isOwnReference &&
                                                                        isset($templateMapping['master_head_value'])
                                                                    ) {
                                                                        $prefilledValue =
                                                                            $templateMapping['master_head_value'];
                                                                        $isReadonly = true;
                                                                    }
                                                                @endphp

                                                                <div class="col-xl-6 col-sm-6 mb-2 mt-2">
                                                                    <label class="form-label"
                                                                        for="input-{{ $templateId }}-{{ $projectTemplateHeaderInfo->id }}">
                                                                        {{ $projectTemplateHeaderInfo->template_head_name }}
                                                                        @if ($isMasterHead)
                                                                            <small class="text-muted">(Linked to
                                                                                Distributor)</small>
                                                                        @elseif($isOwnReference)
                                                                            <small class="text-muted">(Reference ID)</small>
                                                                        @endif
                                                                    </label>
                                                                    <input class="form-control template-input"
                                                                        id="input-{{ $templateId }}-{{ $projectTemplateHeaderInfo->id }}"
                                                                        name="{{ $projectTemplateHeaderInfo->id }}"
                                                                        type="text" placeholder="Enter Data"
                                                                        data-template-id="{{ $templateId }}"
                                                                        data-head-id="{{ $projectTemplateHeaderInfo->id }}"
                                                                        value="{{ $prefilledValue ?? '' }}"
                                                                        @if ($isReadonly) readonly
                                                                       style="background-color: #f8f9fa;" @endif
                                                                        @if (!$isMasterHead && !$isOwnReference) required @endif>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach

                                                {{-- Child templates (multiple) --}}
                                                <div class="row mb-3">
                                                    @foreach ($projectTemplateHeaders as $key => $templateHeadersData)
                                                        <button type="button"
                                                            class="btn btn-secondary col-4 me-2 template-btn"
                                                            data-target="template-content-{{ $key }}"
                                                            data-template-id="{{ $templateHeadersData['id'] }}">
                                                            {{ $templateHeadersData['template_name'] }}
                                                        </button>
                                                    @endforeach
                                                </div>

                                                {{-- Hidden container for template mapping data --}}
                                                <div id="template-mapping-data" style="display: none;">
                                                    @foreach ($templateMappingData as $templateId => $mapping)
                                                        <div class="template-mapping" data-template-id="{{ $templateId }}">
                                                            <span
                                                                class="master-head-id">{{ $mapping['master_head_id'] }}</span>
                                                            <span
                                                                class="master-head-value">{{ $mapping['master_head_value'] }}</span>
                                                            <span
                                                                class="own-reference-id">{{ $mapping['own_reference_head_id'] ?? '' }}</span>
                                                            <span
                                                                class="own-reference-value">{{ $mapping['own_reference_value'] ?? '' }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </form>
                                        <div class="mt-4 text-end">
                                            <button class="btn btn-secondary me-2" type="button"
                                                data-bs-dismiss="modal">Close
                                            </button>
                                            <button class="btn btn-primary" id="submit_data_btn" type="button">Add Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="edit_data" data-bs-backdrop="static" data-bs-keyboard="false"
                        tabindex="-1" role="dialog" aria-labelledby="edit_data_modal" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                            <div class="modal-content">
                                <!-- Modal Header with Close (X) Button -->
                                <div class="modal-header">
                                    <h4 class="modal-title">Edit Data</h4>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-toggle-wrapper">
                                        <form id="template_head_data_edit_form">
                                            <div class="row" id="template_head_data">
                                            </div>
                                            <input type="hidden" name="project_template_id" id="project_template_edit_id">
                                            <input type="hidden" name="row_id" id="project_template_row_id">
                                        </form>
                                        <div class="mt-4 text-end">
                                            <button class="btn btn-secondary me-2" type="button"
                                                data-bs-dismiss="modal">Close
                                            </button>
                                            <button class="btn btn-primary" id="submit_edit_data_btn" type="button">Save
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- view question data model --}}
        <div class="modal fade" id="viewQuestionDataModal" tabindex="-1" aria-labelledby="viewQuestionDataModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewQuestionDataModal">Question/Answer Data</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="QuestionModalBodyData">

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- view question data model --}}

        {{-- Bottom Navigation (mobile only, hidden on desktop via d-md-none) --}}
        <nav class="mobile-bottom-nav d-md-none">
            <a href="{{ route('user.projects') }}" class="{{ request()->routeIs('user.projects') ? 'active' : '' }}">
                <i class="icon-folder"></i>
                Projects
            </a>
            <a href="{{ route('user.projects') }}">
                <i class="icon-arrow-left"></i>
                Back
            </a>
        </nav>
    @endsection

    @section('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {

                // &#9472;&#9472; Template tab switching &#9472;&#9472;
                var templateButtons = document.querySelectorAll('.template-btn');
                var templateContents = document.querySelectorAll('.template-content');

                function showTemplate(targetId) {
                    templateContents.forEach(function(content) {
                        var isTarget = (content.id === targetId);
                        content.style.display = isTarget ? 'block' : 'none';
                        content.querySelectorAll('input, select, textarea').forEach(function(el) {
                            el.disabled = !isTarget;
                        });
                    });
                }

                templateButtons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        templateButtons.forEach(function(b) {
                            b.classList.remove('btn-primary');
                            b.classList.add('btn-secondary');
                        });
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-primary');
                        showTemplate(this.getAttribute('data-target'));
                    });
                });

                if (templateContents.length > 0) {
                    showTemplate(templateContents[0].id);
                }

                // &#9472;&#9472; Add button &#9472;&#9472;
                var addBtn = document.getElementById('add_data_btn');
                if (addBtn) {
                    addBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var modalEl = document.getElementById('add_data');
                        if (modalEl) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        }
                    });
                }

                // &#9472;&#9472; Reset modal state on close &#9472;&#9472;
                var addModalEl = document.getElementById('add_data');
                if (addModalEl) {
                    addModalEl.addEventListener('hidden.bs.modal', function() {
                        // Clear validation states
                        document.querySelectorAll('#project_data_add_form .is-invalid')
                            .forEach(function(el) {
                                el.classList.remove('is-invalid');
                            });

                        // Reset to first tab
                        templateContents.forEach(function(content, index) {
                            if (index === 0) {
                                content.style.display = 'block';
                                content.querySelectorAll('input, select, textarea')
                                    .forEach(function(el) {
                                        el.disabled = false;
                                    });
                            } else {
                                content.style.display = 'none';
                                content.querySelectorAll('input, select, textarea')
                                    .forEach(function(el) {
                                        el.disabled = true;
                                    });
                            }
                        });

                        // Reset tab buttons
                        templateButtons.forEach(function(b, index) {
                            b.classList.remove('btn-primary', 'btn-secondary');
                            b.classList.add(index === 0 ? 'btn-primary' : 'btn-secondary');
                        });
                    });
                }

            }); // end DOMContentLoaded
        </script>

        <script>
            function showEditModal(row_id) {
                $('#template_head_data').html('');
                $.ajax({
                    url: "{{ route('getTemplateHeadData') }}",
                    method: "POST",
                    data: {
                        "_token": "{{ @csrf_token() }}",
                        row_id: row_id
                    },
                    success: function(response) {
                        if (response.status) {
                            var html = '';
                            $('#project_template_edit_id').val(response.project_template_id);
                            $('#project_template_row_id').val(row_id);
                            $.each(response.data, function(i, item) {
                                html += `<div class="col-xl-6 col-sm-6 mb-2">
                            <label class="form-label">${item.name}</label>
                            <input class="form-control" id="${item.id}" name="${item.id}"
                                type="text" placeholder="" required
                                value="${item.value == null ? '' : item.value}">
                        </div>`;
                            });
                            $('#template_head_data').append(html);
                            $('#edit_data').modal('show');
                        }
                    }
                });
            }

            function viewQuestionModel(rowId, activityId) {
                $('#QuestionModalBodyData').html('');
                $.ajax({
                    url: "{{ route('getQuestionAnswersofChildTemplate') }}",
                    method: "POST",
                    data: {
                        "_token": "{{ @csrf_token() }}",
                        row_id: rowId,
                        activity_id: activityId
                    },
                    success: function(response) {
                        var data = response.data.activityAnswerData;
                        if (data.length > 0) {
                            var html = '<div class="row mb-3">';
                            var auditor = data[0].get_user;
                            var agency = data[0].get_user.agency_user;
                            var auditDate = new Date(data[0].created_at).toLocaleString();
                            if (agency) {
                                html += `<div class="col-4"><b>Agency Name:</b></div>
                                 <div class="col-7">${agency.name.toUpperCase()}</div>`;
                            }
                            html += `<div class="col-4 mt-2"><b>Auditor Name:</b></div>
                             <div class="col-7 mt-2">${auditor.name.toUpperCase()}</div>
                             <div class="col-4 mt-2"><b>Audit Date-Time:</b></div>
                             <div class="col-7 mt-2">${auditDate}</div>`;
                            var verifier = data[0].get_verifier;
                            if (verifier) {
                                var verifyDate = new Date(data[0].updated_at).toLocaleString();
                                html += `<div class="col-4 mt-2"><b>Verifier Name:</b></div>
                                 <div class="col-7 mt-2">${verifier.name.toUpperCase()}</div>
                                 <div class="col-4 mt-2"><b>Verification Date-Time:</b></div>
                                 <div class="col-7 mt-2">${verifyDate}</div>`;
                            }
                            html += '</div>';
                            var baseUrl = "{{ url('/') }}";
                            data.forEach(function(answerData) {
                                var qType = answerData.get_question_info.question_type;
                                var fileUrl = '';
                                if ((qType == "File Upload" || qType == "Video") && answerData
                                    .user_answer) {
                                    fileUrl = baseUrl + '/' + answerData.user_answer.replaceAll('/company',
                                        '');
                                }
                                html += `<div class="row mb-2">
                            <div class="col-4"><label><b>${answerData.get_question_info.question} :</b></label></div>
                            <div class="col-7">`;
                                if (qType === 'Image' || qType === 'File Upload') {
                                    html +=
                                        `<label><a href="${fileUrl}" target="_blank">View File</a></label>`;
                                } else {
                                    html += `<label>${answerData.user_answer}</label>`;
                                }
                                html += `</div></div>`;
                            });
                            $('#QuestionModalBodyData').html(html);
                            $('#viewQuestionDataModal').modal('show');
                        } else {
                            Swal.fire({
                                position: "center",
                                icon: "warning",
                                title: "Either Answer is not filled Or not verified",
                                timer: 3000,
                                timerProgressBar: true
                            });
                        }
                    }
                });
            }

            // &#9472;&#9472; Shared AJAX submit &#9472;&#9472;
            function submitAddForm(formData) {
                $.ajax({
                    url: "{{ route('projectData.add') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.message == 'success') {
                            $('#add_data').modal('hide');
                            Swal.fire({
                                position: 'top-center',
                                icon: 'success',
                                title: 'New Data added successfully',
                                showConfirmButton: false,
                                timer: 1500
                            });
                            location.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Something went wrong!'
                            });
                        }
                    },
                    error: function(xhr) {
                        console.error('Submit error:', xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to submit. Please try again.'
                        });
                    }
                });
            }

            $(document).ready(function() {

                // &#9472;&#9472; DataTable &#8212; delayed to run after layout auto-init &#9472;&#9472;
                setTimeout(function() {

                    // Destroy whatever the layout may have auto-inited
                    if ($.fn.DataTable.isDataTable('#projectDataTable')) {
                        $('#projectDataTable').DataTable().destroy();
                    }

                    var $table = $('#projectDataTable');

                    // Only init if real data rows exist
                    // (excludes the "No Data Found" colspan row)
                    var hasRealRows = $table.find('tbody tr td:not([colspan])').length > 0;

                    if ($table.length && hasRealRows) {

                        if (window.innerWidth <= 767) {
                            if (!$.fn.dataTable.ext.pager.mobile_3) {
                                $.fn.dataTable.ext.pager.mobile_3 = function(page, pages) {
                                    var buttons = ['previous'];
                                    var start = Math.max(0, Math.min(page - 1, pages - 3));
                                    var end = Math.min(start + 3, pages);
                                    for (var i = start; i < end; i++) {
                                        buttons.push(i);
                                    }
                                    buttons.push('next');
                                    return buttons;
                                };
                            }
                        }

                        $table.DataTable({
                            pagingType: window.innerWidth <= 767 ? 'mobile_3' : 'simple_numbers',
                            language: {
                                paginate: {
                                    previous: '&#8249;',
                                    next: '&#8250;'
                                }
                            },
                            initComplete: function() {
                                if (window.innerWidth <= 767 || /iPhone|iPad|iPod|Android/i.test(
                                        navigator.userAgent)) {
                                    $('#projectDataTable_wrapper input[type="search"]')
                                        .attr('readonly', true)
                                        .css('font-size', '16px')
                                        .on('click focus touchstart', function() {
                                            $(this).removeAttr('readonly');
                                        });
                                }
                            }
                        });

                        if (window.innerWidth <= 767) {
                            setTimeout(function() {
                                var $wrapper = $('#projectDataTable_wrapper');
                                var $filter = $wrapper.find('.dataTables_filter').detach();
                                var $length = $wrapper.find('.dataTables_length').detach();
                                $('#dt-sticky-controls').remove();
                                $('<div id="dt-sticky-controls"></div>')
                                    .append($filter).append($length)
                                    .insertAfter($('.card > .card-header').first());
                            }, 50);
                        }
                    }

                }, 300); // delay so layout auto-init fires first, then we take over

                // &#9472;&#9472; Session flash &#9472;&#9472;
                @if (session()->has('message'))
                    Swal.fire({
                        position: "top-center",
                        icon: "success",
                        title: "{{ session('message') }}",
                        showConfirmButton: false,
                        timer: 1500
                    });
                @endif

                // &#9472;&#9472; Tooltips &#9472;&#9472;
                $('[data-bs-toggle="tooltip"]').tooltip();

                // &#9472;&#9472; Show details modal &#9472;&#9472;
                $(document).on('click', '.show-details', function(e) {
                    e.stopPropagation();
                    var data_val = $(this).data('val');
                    var row_data = $(this).closest('.outlet-card').find('.show-con').html();
                    $('#main-content').html(row_data);
                    $('#row_data_title').html(data_val);
                    $('#row_detail_modal').modal('show');
                });

                // &#9472;&#9472; Submit ADD form &#9472;&#9472;
                $('#submit_data_btn').click(function(e) {
                    e.preventDefault();

                    var isMaster = {{ $project_temp_info->is_master ?? 0 }};

                    if (isMaster == 1) {
                        // &#9472;&#9472; MASTER: all fields always enabled, simple submit &#9472;&#9472;
                        var formData = new FormData($('#project_data_add_form')[0]);
                        formData.append('_token', "{{ csrf_token() }}");

                        var filledFields = 0;
                        formData.forEach(function(v, k) {
                            if (k !== '_token' && k !== 'project_template_id' && v !== '' && v !==
                                null) {
                                filledFields++;
                            }
                        });

                        if (filledFields === 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'You need to add at least one value!'
                            });
                            return;
                        }

                        submitAddForm(formData);

                    } else {
                        // &#9472;&#9472; CHILD TEMPLATE (is_master=0): only submit active tab &#9472;&#9472;

                        var $activeTemplate = $('.template-content:visible');

                        // Fallback if nothing visible
                        if ($activeTemplate.length === 0) {
                            $activeTemplate = $('.template-content').first();
                            $activeTemplate.show();
                            $activeTemplate.find('input, select, textarea').prop('disabled', false);
                        }

                        var activeTemplateId = $activeTemplate.data('template-id');

                        // Ensure active tab inputs are enabled
                        $activeTemplate.find('input, select, textarea').prop('disabled', false);

                        // Validate required fields
                        var hasEmptyRequired = false;
                        $activeTemplate.find('input[required]').each(function() {
                            if ($(this).val().trim() === '') {
                                hasEmptyRequired = true;
                                $(this).addClass('is-invalid');
                            } else {
                                $(this).removeClass('is-invalid');
                            }
                        });

                        if (hasEmptyRequired) {
                            // Re-disable other tabs
                            $('.template-content').not($activeTemplate)
                                .find('input, select, textarea').prop('disabled', true);
                            Swal.fire({
                                icon: 'warning',
                                title: 'Required fields missing',
                                text: 'Please fill all required fields!'
                            });
                            return;
                        }

                        // Build FormData manually from active tab only
                        var formData = new FormData();
                        formData.append('_token', "{{ csrf_token() }}");
                        formData.append('project_template_id', activeTemplateId);

                        var filledFields = 0;
                        $activeTemplate.find('input, select, textarea').each(function() {
                            var $el = $(this);
                            var name = $el.attr('name');
                            var val = $el.val() || '';
                            if (name && name !== 'project_template_id') {
                                formData.append(name, val);
                                if (val.trim() !== '' && $el.attr('type') !== 'hidden') {
                                    filledFields++;
                                }
                            }
                        });

                        if (filledFields === 0) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'You need to add at least one value!'
                            });
                            return;
                        }

                        submitAddForm(formData);
                    }
                });

                // &#9472;&#9472; Submit EDIT form &#9472;&#9472;
                $('#submit_edit_data_btn').click(function(e) {
                    e.preventDefault();
                    var formData = new FormData($('#template_head_data_edit_form')[0]);
                    formData.append('_token', "{{ csrf_token() }}");
                    var filledFields = 0;
                    formData.forEach(function(v, k) {
                        if (v !== '' && v !== null) filledFields++;
                    });
                    if (filledFields > 2) {
                        $.ajax({
                            url: "{{ route('edit_project_data_template') }}",
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (response.status) {
                                    $('#edit_data').modal('hide');
                                    Swal.fire({
                                        position: 'top-center',
                                        icon: 'success',
                                        title: response.message,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                    location.reload();
                                }
                            }
                        });
                    } else {
                        $('#edit_data').modal('hide');
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'You Need to add atleast one value!'
                        });
                    }
                });

            }); // end document.ready
        </script>
    @endsection
