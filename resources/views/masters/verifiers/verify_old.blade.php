@extends('template.layouts.simple.master')
@section('style')
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
          rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>


    <style>
        /* Style the Select2 container */
        .select2-container {
            font-size: 14px;
        }

        .table-avtar-new {
            height: 250px !important;
            width: 250px !important;
            position: relative; /* Needed for absolute positioning */
            overflow: hidden; /* Hide any image overflow */
        }

        .table-avtar-new img {
            border: 2px solid #374462;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 160px !important;
            max-height: 100%;
            max-width: 100%;
        }

        .select2-container--default .select2-selection--multiple {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px;
            min-height: 38px;
        }

        /* Style selected items */
        .select2-container--default .select2-selection--multiple .select2-selection__choice {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 2px 5px;
            margin: 2px;
        }

        /* Style the dropdown */
        .select2-container--default .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        /* Placeholder styling */
        .select2-container--default .select2-selection--multiple .select2-selection__placeholder {
            color: #6c757d;
        }
    </style>
@endsection
@section('content')
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @php
                                    $latlongData = [];
                                    $latlongArr = [
                                        'latitude',
                                        'longitude',
                                        'Lat',
                                        'Long',
                                        'Latitude',
                                        'Longitude',
                                        'lat',
                                        'long',
                                    ];
                                @endphp
                                <div class="col-xl-6 col-md-6">
                                    <div class="table-card">
                                        <h5>Distributor Details</h5>
                                        <table class="table mb-0">
                                            <thead>

                                            </thead>
                                            <tbody>

                                            @foreach ($related_values as $r_values)
                                                @php
                                                    $Value = trim($r_values->getHeadName->template_head_name);

                                                @endphp
                                                @if (Str::contains($Value, $latlongArr))
                                                    @php $latlongData[] = $r_values->value; @endphp

                                                @endif
                                                <tr>
                                                    <td class="fw-medium">
                                                        {{ $r_values->getHeadName->template_head_name }}</td>
                                                    <td>{{ $r_values->value }}</td>
                                                </tr>
                                            @endforeach

                                            </tbody>
                                        </table>
                                        <h5 class="mt-4">Audit Details</h5>
                                        <table class="table mb-0">
                                            <thead>

                                            </thead>
                                            <tbody>

                                            <tr class="mt-2">
                                                <td class="fw-medium">Auditor Name</td>
                                                <td>{{ !empty($auditorData) ? $auditorData->name : '' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Agency Name</td>
                                                <td>{{ !empty($agencyData) ? $agencyData->name : '' }}</td>
                                            </tr>
                                            <tr>
                                                <td class="fw-medium">Audit Date / Time</td>
                                                <td>{{ $auditDateTime??'' }}</td>
                                            </tr>
                                            </tbody>
                                        </table>
                                        <!--end table-->
                                        @if (!empty($latlongData) || !empty($userLatLongData))
                                            <div id="map"
                                                 style="height: 400px; margin-top: 15px; border-radius: 8px;"></div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-xl-6 col-md-6">
                                    <div class="card">
                                        <div class="table-card">
                                            <h5 class="mt-2 p-2">Activity Questions <span
                                                    class="fs-5 float-end  badge bg-secondary" id="edit_answers">Edit</span>
                                            </h5>
                                            <form method="post" action="{{ route('verifier.activityQuestionAnswers') }}"
                                                  enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="row_id" value="{{ $r_values->row_id }}">
                                                <input type="hidden" name="activity_id"
                                                       value="{{ $related_questions[0]->activity_id }}">
                                                @if ($activity_group_info)
                                                    <input type="hidden" name="activity_group_id"
                                                           value="{{ $activity_group_info->id }}">
                                                @endif

                                                <table class="table mb-0">
                                                    <thead>
                                                    </thead>
                                                    <tbody>
                                                    @php
                                                        $selectArrIds = [];
                                                    @endphp
                                                    @foreach ($related_questions as $related_question)
                                                        <tr>
                                                            <td class="col-md-6 fw-medium">
                                                                {{ $related_question->question }}</td>
                                                            <td class="col-md-6">
                                                                @php

                                                                    $d_value = '';
                                                                    foreach ($user_responses as $user_reponse) {
                                                                        if (
                                                                            $user_reponse->question_id ==
                                                                            $related_question->id
                                                                        ) {
                                                                            $d_value = $user_reponse->user_answer;
                                                                            break; // Stop the loop once a match is found
                                                                        }
                                                                    }
                                                                @endphp

                                                                @switch($related_question->question_type)
                                                                    @case('Location')
                                                                        <input name="{{ $related_question->id }}"
                                                                               value="{{ $d_value }}" type="text"
                                                                               class="form-control" disabled>
                                                                        @if ($d_value)
                                                                            <a href="https://www.google.com/maps?q={{ $d_value }}"
                                                                               target="_blank"
                                                                               class="text-underline text-dark">
                                                                                Location
                                                                            </a>
                                                                        @endif
                                                                        @break

                                                                    @case('Image')
                                                                        @if($d_value)
                                                                            <div class="table-avtar-new mb-3">
                                                                                <img class=""
                                                                                     alt="Image"
                                                                                     src="{{ asset($d_value) }}">
                                                                            </div>

                                                                        @endif
                                                                        <input name="{{ $related_question->id }}"
                                                                               type="file" class="form-control"
                                                                               disabled>
                                                                        @if ($d_value)
                                                                            {{-- <img class="img-fluid table-avtar"
                                                                                alt="Image" > --}}
                                                                            {{--                                                                                <a class="badge badge-primary mt-2"--}}
                                                                            {{--                                                                                    style="cursor:pointer;"--}}
                                                                            {{--                                                                                    onclick="openImageModel('{{ asset($d_value) }}')">view--}}
                                                                            {{--                                                                                    image--}}
                                                                            {{--                                                                                </a>--}}
                                                                        @endif
                                                                        @break

                                                                    @case('Video')
                                                                        @if ($d_value)
                                                                            <a class="badge badge-primary mt-2"
                                                                               style="cursor:pointer;"
                                                                               onclick="openImageModel('{{ asset($d_value) }}')">Click
                                                                                Here
                                                                            </a>
                                                                        @endif
                                                                        @break

                                                                    @case('Free Text')
                                                                        <input type="text" value="{{ $d_value }}"
                                                                               name="{{ $related_question->id }}"
                                                                               class="form-control" readonly>
                                                                        @break

                                                                    @case('Yes / No')
                                                                        <select class="form-select"
                                                                                name="{{ $related_question->id }}"
                                                                                disabled>
                                                                            <option value="">Select ..</option>
                                                                            <option value="Yes"
                                                                                    @if ('Yes' == $d_value) selected @endif>
                                                                                Yes
                                                                            </option>
                                                                            <option value="No"
                                                                                    @if ('No' == $d_value) selected @endif>
                                                                                No
                                                                            </option>
                                                                        </select>
                                                                        @break

                                                                    @case('Subjective')
                                                                        <div class="d-flex flex-column gap-2">
                                                                            <select class="form-select subjective_ques d-none"
                                                                                    data-q_id="{{ $related_question->id }}">
                                                                                <option value="">Select Subject ..
                                                                                </option>
                                                                                @foreach ($related_question->getSubjects as $questionSubjects)
                                                                                    <option
                                                                                        value="{{ $questionSubjects->subject }}">
                                                                                        {{ $questionSubjects->subject }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                            <select
                                                                                @if ($related_question->answer_type) required=""
                                                                                @endif
                                                                                class="form-select subjective_ques_option"
                                                                                name="{{ $related_question->id }}"
                                                                                disabled>
                                                                                <option value="{{ $d_value }}">
                                                                                    {{ $d_value }}</option>
                                                                            </select>
                                                                        </div>
                                                                        @break

                                                                    @case('Dropdown')
                                                                        <select class="form-select"
                                                                                name="{{ $related_question->id }}"
                                                                                disabled>
                                                                            <option value="">Select ..</option>
                                                                            @foreach ($related_question->getOptions as $questionOption)
                                                                                <option value="{{ $questionOption->option }}"
                                                                                        @if ($questionOption->option == $d_value) selected @endif>
                                                                                    {{ $questionOption->option }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        @break

                                                                    @case('Multi select')
                                                                        @php
                                                                            $selectArrIds[] =
                                                                                'select' . $related_question->id;
                                                                            $selectedValues = explode(
                                                                                ',',
                                                                                $d_value ?? '',
                                                                            );
                                                                        @endphp
                                                                        <select class="form-select"
                                                                                name="{{ $related_question->id }}[]"
                                                                                id="select{{ $related_question->id }}"
                                                                                multiple
                                                                                disabled>
                                                                            <option value="">Select ..</option>
                                                                            @foreach ($related_question->getOptions as $questionOption)
                                                                                <option value="{{ $questionOption->option }}"
                                                                                        @if (in_array($questionOption->option, $selectedValues)) selected @endif>
                                                                                    {{ $questionOption->option }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        @break

                                                                    @case('Date & Time')
                                                                        @php
                                                                            // Parse the date in the 'd-m-Y H:i:s' format and convert it to 'Y-m-d\TH:i' for the datetime-local input
                                                                            //                                                                            $formattedDateTime = \Carbon\Carbon::createFromFormat('d-m-Y H:i:s', $d_value)->format('Y-m-d\TH:i');
                                                                            $formattedDateTime = \Carbon\Carbon::parse(
                                                                                $d_value,
                                                                            )->format('Y-m-d\TH:i');
                                                                        @endphp
                                                                        <input type="datetime-local"
                                                                               name="{{ $related_question->id }}"
                                                                               value="{{ $formattedDateTime }}"
                                                                               class="form-control" readonly>
                                                                        @break

                                                                    @case('Date')
                                                                        <input type="date"
                                                                               value="{{ $d_value ? \Carbon\Carbon::createFromFormat('d/m/Y', $d_value)->format('Y-m-d') : '' }}"
                                                                               name="{{ $related_question->id }}"
                                                                               class="form-control" readonly>
                                                                        @break

                                                                    @case('File Upload')
                                                                        <input type="file"
                                                                               name="{{ $related_question->id }}"
                                                                               class="form-control" disabled>
                                                                        @if ($d_value)
                                                                            <a class="badge badge-primary mt-2"
                                                                               href="{{ asset($d_value) }}">Click
                                                                                Here
                                                                            </a>
                                                                        @endif
                                                                        @break

                                                                    @case('Audio')
                                                                        <div class="audio-recorder">
                                                                            <button type="button"
                                                                                    class="btn btn-sm btn-primary start-recording"
                                                                                    data-id="{{ $related_question->id }}">
                                                                                Record
                                                                            </button>
                                                                            <button type="button"
                                                                                    class="btn btn-sm btn-danger stop-recording"
                                                                                    data-id="{{ $related_question->id }}"
                                                                                    disabled>Stop
                                                                            </button>
                                                                            <button type="button"
                                                                                    class="btn btn-sm btn-warning delete-audio"
                                                                                    data-id="{{ $related_question->id }}"
                                                                                    @if (!isset($d_value)) disabled @endif>
                                                                                Delete
                                                                            </button>

                                                                            <audio
                                                                                id="audio-player-{{ $related_question->id }}"
                                                                                controls></audio>
                                                                            <input type="hidden"
                                                                                   name="{{ $related_question->id }}"
                                                                                   class="audio-data"
                                                                                   data-id="{{ $related_question->id }}"
                                                                                   value="{{ asset($d_value) }}">
                                                                        </div>
                                                                        @break

                                                                    @default
                                                                        <input type="text" value="{{ $d_value }}"
                                                                               class="form-control"
                                                                               name="{{ $related_question->id }}"
                                                                               readonly>
                                                                @endswitch

                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    <tr>
                                                        <td class="col-md-6 fw-medium">Verifier Remark</td>
                                                        <td class="col-md-6">
                                                            <select class="form-control" name="remark"
                                                                    id="verify_remark" required
                                                                    onchange="getOtherRemark()">
                                                                <option value="">Select Remark</option>
                                                                <option value="other">Other Remark</option>
                                                                @foreach ($remarks as $remark)
                                                                    <option value="{{ $remark->id }}">
                                                                        {{ $remark->remark }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input class="form-control d-none mt-4" type="text"
                                                                   placeholder="Add Other Remark" id="other_remark"
                                                                   name="other_remark">
                                                        </td>
                                                    </tr>


                                                    </tbody>
                                                </table>

                                                <button id="approve" name="approve"
                                                        class="btn btn-primary float-end">Approve
                                                </button>
                                                <button id="reject" name="reject"
                                                        class="btn btn-danger float-end">Reject
                                                </button>
                                            </form>
                                            <!--end table-->
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                {{-- @foreach ($get_draft_values as $values) --}}
                {{--   <pre> {{$values}}</pre> --}}
                {{-- @endforeach --}}
            </div>
        </div>
    </div>

    {{-- khushboo 15-05-25 --}}
    {{-- View Image Modal --}}
    <div class="modal fade" id="ImageModel" tabindex="-1" aria-labelledby="ImageModelLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered"> <!-- centered modal -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ImageModelLabel">Uploaded File Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>
                <div class="modal-body text-center" id="ImageModelBody" style="padding: 15px;">
                    <!-- Image will be injected here -->
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    {{-- End Modal --}}
    {{-- khushboo 15-05-25 --}}
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>

        function convertToDecimal(coordinate) {
            if (!coordinate || typeof coordinate !== 'string') return null;

            // Split on degree symbol and any surrounding spaces
            const parts = coordinate.trim().split(/[&#176;\s]+/);
            let value = parseFloat(parts[0]);

            if (isNaN(value)) return null;

            const direction = parts[1]?.toUpperCase();

            if (direction === 'S' || direction === 'W') {
                value *= -1;
            } else if (direction === 'N' || direction === 'E') {
                value *= 1; // Explicit for clarity (positive)
            } else {
                return null; // Invalid direction
            }

            return value;
        }


        function getOtherRemark() {
            if ($('#verify_remark').val() == 'other') {
                $('#other_remark').removeClass('d-none');
            } else {
                $('#other_remark').addClass('d-none');
                $('#other_remark').val('');
            }
        }

        document.addEventListener("DOMContentLoaded", function () {

            const LatLong = @json($latlongData);

            var distributorLat = LatLong.length > 0 ? convertToDecimal(LatLong[0]) : null;
            var distributorLng = LatLong.length > 0 ? convertToDecimal(LatLong[1]) : null;
            // var distributorLat = '';
            // var distributorLng = '';
            // console.log(distributorLat);

            const userLatLongData = @json($userLatLongData);
            // console.log(userLatLongData, distributorLat, distributorLng);
            // const userLatLongData = [];
            const userLat = userLatLongData.latitude ? parseFloat(userLatLongData.latitude) : null;
            const userLng = userLatLongData.longitude ? parseFloat(userLatLongData.longitude) : null;


            let map;

            // Case 1: Both distributor and user coordinates exist
            // if (distributorLat && distributorLng && userLat && userLng) {
            //
            //     map = L.map('map').setView([distributorLat, distributorLng], 6);
            //
            //     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            //         attribution: '&#169; OpenStreetMap contributors'
            //     }).addTo(map);
            //
            //     const distributorMarker = L.marker([distributorLat, distributorLng]).addTo(map).bindPopup(
            //         'Distributor Location').openPopup();
            //     const userMarker = L.marker([userLat, userLng]).addTo(map).bindPopup('User Location');
            //
            //     const latlngs = [
            //         [distributorLat, distributorLng],
            //         [userLat, userLng]
            //     ];
            //     const polyline = L.polyline(latlngs, {
            //         color: 'blue'
            //     }).addTo(map);
            //     map.fitBounds(polyline.getBounds());
            //
            //     const distributorPoint = L.latLng(distributorLat, distributorLng);
            //     const userPoint = L.latLng(userLat, userLng);
            //     const distanceInKm = (distributorPoint.distanceTo(userPoint) / 1000).toFixed(2);
            //
            //     console.log(distanceInKm);
            //     const midLat = (distributorLat + userLat) / 2;
            //     const midLng = (distributorLng + userLng) / 2;
            //     L.popup()
            //         .setLatLng([midLat, midLng])
            //         .setContent(`<strong>Distance: ${distanceInKm} km</strong>`)
            //         .openOn(map);
            // }

            if (distributorLat && distributorLng && userLat && userLng) {
                const map = L.map('map').setView([distributorLat, distributorLng], 6);

                const blueIcon = new L.Icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                const redIcon = new L.Icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41]
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#169; OpenStreetMap contributors'
                }).addTo(map);

                const distributorMarker = L.marker([distributorLat, distributorLng], { icon: blueIcon })
                    .addTo(map)
                    .bindPopup('Distributor Location')
                    .openPopup();

                const userMarker = L.marker([userLat, userLng], { icon: redIcon })
                    .addTo(map)
                    .bindPopup('User Location');

                const latlngs = [
                    [distributorLat, distributorLng],
                    [userLat, userLng]
                ];

                const polyline = L.polyline(latlngs, { color: 'blue' }).addTo(map);

                // Fit map to bounds of both markers + some padding
                const bounds = L.latLngBounds(latlngs);
                map.fitBounds(bounds, { padding: [50, 50] });

                // &#128994; Fetch travel/road distance using OSRM
                fetch(`https://router.project-osrm.org/route/v1/driving/${distributorLng},${distributorLat};${userLng},${userLat}?overview=false`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.routes && data.routes.length > 0) {
                            const distanceInKm = (data.routes[0].distance / 1000).toFixed(2);
                            console.log(`Road Distance: ${distanceInKm} km`);

                            // &#128260; Get the actual center of the visible map after fitBounds
                            const center = map.getCenter();

                            L.popup({
                                maxWidth: 300,
                                autoPan: true,
                                className: 'distance-popup'
                            })
                                .setLatLng(center)
                                .setContent(`<strong>Road Distance: ${distanceInKm} km</strong>`)
                                .openOn(map);

                        } else {
                            console.warn("No route found.");
                        }
                    })
                    .catch(err => {
                        console.error("OSRM request failed:", err);
                    });
            }


            // Case 2: Only distributor location exists
            else if (distributorLat && distributorLng) {
                map = L.map('map').setView([distributorLat, distributorLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#169; OpenStreetMap contributors'
                }).addTo(map);

                L.marker([distributorLat, distributorLng]).addTo(map)
                    .bindPopup('Distributor Location')
                    .openPopup();
            }

            // Case 3: Only user location exists
            else if (userLat && userLng) {
                console.log(userLat, userLng);
                map = L.map('map').setView([userLat, userLng], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&#169; OpenStreetMap contributors'
                }).addTo(map);

                L.marker([userLat, userLng]).addTo(map)
                    .bindPopup('User Location')
                    .openPopup();
            }

            // Case 4: Neither location exists &#8211; do nothing or show a message
            else {
                console.warn("No location data available.");
            }
        });

        function openImageModel(filePath) {
            const extension = filePath.split('.').pop().toLowerCase();
            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'mp3', 'webm', 'ogg', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
                    <img
                        src="${filePath}"
                        alt="Uploaded File"
                        class="img-fluid rounded shadow-sm border"
                        style="max-height: 400px; object-fit: contain;"
                        onerror="this.onerror=null; this.src='/path/to/placeholder.png';"
                    />
                `;
            } else if (videoExtensions.includes(extension)) {
                // Fix for temp: assume it's mp4
                let mimeType = 'video/' + (extension === 'temp' ? 'mp4' : extension);

                html = `
                    <video controls class="w-100 rounded shadow-sm border" style="max-height: 400px; object-fit: contain;">
                    <source src="${filePath}" type="${mimeType}">
                        Your browser does not support the video tag.
                    </video>
                `;

            } else {
                html = `<p class="text-danger">Unsupported file type.</p>`;
            }

            $('#ImageModelBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('ImageModel'));
            modal.show();
        }

        $(document).ready(function () {
            @if (session()->has('message'))
            Swal.fire({
                position: "top-center",
                icon: "success",
                title: "{{ session('message') }}",
                showConfirmButton: false,
                timer: 1500
            });
            @endif

            const multiSelects = @json($selectArrIds);
            $.each(multiSelects, function (index, select_id) {
                console.log(select_id);
                $(`#${select_id}`).select2({
                    theme: "bootstrap-5",
                    placeholder: "Select options",
                    allowClear: true,
                    closeOnSelect: false,
                    width: '100%',
                });
            })

            $("#edit_answers").click(function () {
                // Enable all text inputs by removing readonly
                $('input[readonly]').removeAttr('readonly');
                // Enable all file and date inputs by removing disabled
                $('input[disabled], select[disabled]').removeAttr('disabled');
                $(".subjective_ques").removeClass('d-none');
            })

            $('#approve, #reject').on('click', function () {
                // Enable all text inputs by removing readonly
                $('input[readonly]').removeAttr('readonly');
                // Enable all file and date inputs by removing disabled
                $('input[disabled], select[disabled]').removeAttr('disabled');
            });
            // this is to add the subjective question implement in the activity
            $(".subjective_ques").change(function (event) {
                let subjectTd = $(this).closest('td');
                const subjectInfo = $(this).val();
                let qid = $(this).data('q_id');
                if (qid !== "" && subjectInfo !== "") {
                    $.ajax({
                        url: "{{ route('getQuestion.subject.options') }}",
                        type: "POST",
                        data: {
                            "_token": "{{ @csrf_token() }}",
                            question_id: qid,
                            subject: subjectInfo
                        },
                        success: function (response) {
                            if (response.message == "success") {
                                let subject_options_select = subjectTd.find(
                                    ".subjective_ques_option");
                                subject_options_select.empty();
                                $.each(response.subjectOption.get_options, function (index,
                                                                                     subjectOptions) {
                                    let subjectOption =
                                        `${subjectInfo} (${subjectOptions.option})`;
                                    subject_options_select.append($('<option>').text(
                                        subjectOptions.option).val(
                                        subjectOption));
                                });
                                // this is to make the subject as the option selected so that it can be answered if the options of subjects are not present
                                if (response.subjectOption.get_options.length == 0) {
                                    // Swal.fire({
                                    //     icon: "error",
                                    //     title: "Oops...",
                                    //     text: "This Subject Doesn't have any Option!",
                                    // });
                                    subject_options_select.append($('<option>').text(
                                        subjectInfo).val(subjectInfo));
                                }
                            } else {
                                //     Something went wrong
                            }
                        }
                    })
                }

            });


            // this is to record the audio message
            let mediaRecorder;
            let audioChunks = {};

            $(".start-recording").on("click", function () {
                let id = $(this).data("id");
                let stopBtn = $(".stop-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio[data-id='" + id + "']");
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $("input[name='" + id + "']");

                navigator.mediaDevices.getUserMedia({
                    audio: true
                })
                    .then(stream => {
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks[id] = [];

                        mediaRecorder.ondataavailable = event => {
                            audioChunks[id].push(event.data);
                        };
                        mediaRecorder.onstop = () => {
                            let audioBlob = new Blob(audioChunks[id], {
                                type: 'audio/wav'
                            });
                            let audioUrl = URL.createObjectURL(audioBlob);
                            audioPlayer.attr("src", audioUrl).removeClass(
                                "d-none"); // Show audio player
                            deleteBtn.prop("disabled", false);
                            // Convert audio to base64 and store in hidden input
                            let reader = new FileReader();
                            reader.readAsDataURL(audioBlob);
                            reader.onloadend = function () {
                                audioInput.val(reader.result);
                            };
                        };
                        mediaRecorder.start();
                        $(this).prop("disabled", true);
                        stopBtn.prop("disabled", false);
                    });
            });
            $(".stop-recording").on("click", function () {
                let id = $(this).data("id");
                let stopBtn = $(this);
                let startBtn = $(".start-recording[data-id='" + id + "']");
                let deleteBtn = $(".delete-audio[data-id='" + id + "']");
                let audioPlayer = $("#audio-player-" + id);
                mediaRecorder.stop();
                mediaRecorder.onstop = function () {
                    let audioBlob = new Blob(audioChunks[id], {
                        type: "audio/wav"
                    });
                    let audioUrl = URL.createObjectURL(audioBlob);

                    audioPlayer.attr("src", audioUrl).removeClass(
                        "d-none"); // Show and set audio source
                    deleteBtn.prop("disabled", false);

                    // Convert audio to base64 and store in hidden input
                    let audioInput = $("input[name='" + id + "']");
                    let reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = function () {
                        audioInput.val(reader.result);
                    };
                };
                stopBtn.prop("disabled", true);
                startBtn.prop("disabled", false);
            });
            $(".delete-audio").on("click", function () {
                let id = $(this).data("id");
                let audioPlayer = $("#audio-player-" + id);
                let audioInput = $("input[name='" + id + "']");
                audioPlayer.addClass("d-none").attr("src", ""); // Hide audio player
                audioInput.val("");
                $(".start-recording[data-id='" + id + "']").prop("disabled", false);
                $(".stop-recording[data-id='" + id + "']").prop("disabled", true);
                $(this).prop("disabled", true);
            });

            $('.audio-data').each(function () {
                var audioFilePath = $(this).val(); // Get the audio file path
                var audioPlayer = '#audio-player-' + $(this).data('id'); // Target the specific audio player
                // Set the audio source dynamically
                if (audioFilePath) {
                    $(audioPlayer).attr('src', audioFilePath);
                }
            });
        })
    </script>
@endsection
