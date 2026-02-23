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
            max-width: 100% !important;
            height: 500px !important;
            width: auto;
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

                                            @foreach ($related_values->templateNameValues as $r_values)
                                                @php
                                                    $Value = trim($r_values['value']);
                                                @endphp
                                                @if (Str::contains($Value, $latlongArr))
                                                    @php $latlongData[] = $r_values['value']; @endphp
                                                @endif
                                                <tr>
                                                    <td class="fw-medium">
                                                        {{ $r_values['name'] }}</td>
                                                    <td>{{ $r_values['value'] }}</td>
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

                                            <div id="map" class=""
                                                 style="height: 300px; margin-top: 15px; border-radius: 8px;"></div>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-xl-6 col-md-6">
                                    <div class="card">
                                        <div class="table-card">
                                            <h5 class="mt-2 p-2">Activity Questions <span
                                                    class="fs-5 float-end  badge bg-secondary"
                                                    id="edit_answers">Edit</span>
                                            </h5>
                                            <form method="post" action="{{ route('verifier.activityQuestionAnswers') }}"
                                                  enctype="multipart/form-data">
                                                @csrf
                                                <input type="hidden" name="row_id" value="{{ $related_values->id }}">
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
                                                                    if($related_question->question_type == 'Subjective'){
                                                                        $d_values = [];
                                                                    }else{
                                                                        $d_value = '';
                                                                    }

                                                                    foreach ($user_responses as $user_reponse) {

                                                                        if (
                                                                            $user_reponse->question_id ==
                                                                            $related_question->id
                                                                        ) {
                                                                            if($related_question->question_type == 'Subjective'){

                                                                                $d_values[] = $user_reponse->user_answer;

                                                                            }else{
                                                                                $d_value = $user_reponse->user_answer;
                                                                            }
 //
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
                                                                                <img class="imagemodal"
                                                                                     alt="Image"                         src="{{ asset($d_value) }}" data-setval="{{ asset($d_value) }}" >
                                                                            </div>

                                                                        @endif
                                                                        <input name="{{ $related_question->id }}"
                                                                               type="file" class="form-control"
                                                                               disabled>

                                                                        @break

                                                                    @case('Video')
                                                                        @php
                                                                            // Decode URL-encoded characters
                                                                            $decodedValue = urldecode($d_value);

                                                                            // Check if it's already a full URL
                                                                            if (str_starts_with($decodedValue, 'http://') || str_starts_with($decodedValue, 'https://')) {
                                                                                $fileUrl = $decodedValue;
                                                                            } else {
                                                                                $fileUrl = asset($decodedValue);
                                                                            }

                                                                            // Encode for JavaScript
                                                                            $jsFileUrl = str_replace(' ', '%20', $fileUrl);
                                                                        @endphp
                                                                        @if ($d_value)
                                                                            <a class="badge badge-primary mt-2 imagemodal"
                                                                               data-setval="{{ $jsFileUrl }}"
                                                                               style="cursor:pointer;">
                                                                                Click Here
                                                                            </a>
                                                                            <!-- Debug: show actual path -->
                                                                            <small class="d-block text-muted mt-1" title="{{ $fileUrl }}">
                                                                                Path: .../{{ basename($decodedValue) }}
                                                                            </small>
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

                                                                        @foreach ($d_values as $d_value)
                                                                            @php
                                                                                $extension = strtolower(pathinfo($d_value, PATHINFO_EXTENSION));
                                                                                $isUrlOrPath = str_contains($d_value, '/'); // crude check for file path

                                                                                $isDate = false;
                                                                                $isDateTime = false;
                                                                                $parsedDate = null;

                                                                                // Detect Date or DateTime format using Carbon
                                                                                try {
                                                                                    $parsedDate = \Carbon\Carbon::parse($d_value);
                                                                                    $isDate = $parsedDate && $parsedDate->format('Y-m-d') === $d_value;
                                                                                    $isDateTime = $parsedDate && !$isDate;
                                                                                } catch (\Exception $e) {
                                                                                    // Not a valid date/datetime, fallback
                                                                                }

// Decode URL-encoded characters FIRST
            $decodedValue = urldecode($d_value);

 // Check if it's already a full URL
            if (str_starts_with($decodedValue, 'http://') || str_starts_with($decodedValue, 'https://')) {
                $fileUrl = $decodedValue;
            } else {
                $fileUrl = asset($decodedValue);
            }
                                                                            @endphp

                                                                            @if (!$isUrlOrPath)
                                                                                {{-- Plain Text --}}
                                                                                <div class="d-flex flex-column gap-2">
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

                                                                            @elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                                {{-- Image --}}
                                                                                <div class="d-flex flex-column gap-2">
                                                                                    <div class="table-avtar-new mb-3">
                                                                                        <img class="imagemodal"
                                                                                             data-setval="{{ asset($d_value) }}"
                                                                                             alt="Image"
                                                                                             src="{{ asset($d_value) }}" >
                                                                                    </div>
                                                                                </div>

                                                                            @elseif (in_array($extension, ['mp4', 'webm', 'ogg', 'temp']))
                                                                                {{-- Video --}}
                                                                                <div class="mb-2">
                                                                                    <a class="badge badge-primary mt-2 imagemodal"
                                                                                       style="cursor:pointer;"
                                                                                       data-setval="{{ $fileUrl }}">Click Here
                                                                                    </a>
                                                                                </div>
                                                                            @elseif (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt']))
                                                                                {{-- File --}}
                                                                                <div class="mb-2">
                                                                                    <a class="badge badge-primary mt-2"
                                                                                       href="{{ asset($d_value) }}">Click
                                                                                        Here
                                                                                    </a>
                                                                                </div>

                                                                            @elseif ($isDate)
                                                                                <input type="date"
                                                                                       value="{{ $d_value ? \Carbon\Carbon::createFromFormat('d/m/Y', $d_value)->format('Y-m-d') : '' }}"
                                                                                       name="{{ $related_question->id }}"
                                                                                       class="form-control" readonly>

                                                                            @elseif ($isDateTime)
                                                                                @php

                                                                                    $formattedDateTime = \Carbon\Carbon::parse(
                                                                                        $d_value,
                                                                                    )->format('Y-m-d\TH:i');
                                                                                @endphp
                                                                                <input type="datetime-local"
                                                                                       name="{{ $related_question->id }}"
                                                                                       value="{{ $formattedDateTime }}"
                                                                                       class="form-control" readonly>

                                                                            @endif
                                                                        @endforeach

                                                                        @break

                                                                    @case('Dropdown')
                                                                        <select class="form-select"
                                                                                name="{{ $related_question->id }}"
                                                                                disabled>
                                                                            <option value="">Select ..</option>
                                                                            @foreach ($related_question->getOptions as $questionOption)
                                                                                <option
                                                                                    value="{{ $questionOption->option }}"
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
                                                                                <option
                                                                                    value="{{ $questionOption->option }}"
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
                                                                    id="verify_remark" required >
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
        <div class="modal-dialog modal-lg modal-dialog-centered"> <!-- centered modal -->
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
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
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
        // function initMap() {
        //
        //     const origin = { lat: 23.02858631445742, lng: 72.53012544754631 };      // Ahmedabad
        //     const destination = { lat: 23.249656769507297, lng: 72.65856764961387 }; // Mumbai
        //
        //     const map = new google.maps.Map(document.getElementById("map"), {
        //         zoom: 6,
        //         center: origin,
        //     });
        //
        //     const directionsService = new google.maps.DirectionsService();
        //     const directionsRenderer = new google.maps.DirectionsRenderer();
        //     directionsRenderer.setMap(map);
        //
        //     // Render route on map
        //     directionsService.route(
        //         {
        //             origin: origin,
        //             destination: destination,
        //             travelMode: google.maps.TravelMode.DRIVING,
        //         },
        //         (response, status) => {
        //             if (status === "OK") {
        //                 directionsRenderer.setDirections(response);
        //             } else {
        //                 alert("Directions request failed due to " + status);
        //             }
        //         }
        //     );
        //
        //     // Use Distance Matrix to get distance and time
        //     const service = new google.maps.DistanceMatrixService();
        //     service.getDistanceMatrix(
        //         {
        //             origins: [origin],
        //             destinations: [destination],
        //             travelMode: google.maps.TravelMode.DRIVING,
        //             unitSystem: google.maps.UnitSystem.METRIC,
        //         },
        //         (result, status) => {
        //             if (status === "OK") {
        //                 const distance = result.rows[0].elements[0].distance.text;
        //                 const duration = result.rows[0].elements[0].duration.text;
        //                 document.getElementById("output").innerHTML =
        //                     `<b>Distance:</b> ${distance} <br> <b>Duration:</b> ${duration}`;
        //             }
        //         }
        //     );
        // }
        //
        // window.onload = initMap;
    </script>

    <script>

        function haversineDistance(lat1, lon1, lat2, lon2) {

            console.log(lat1, lon1, lat2, lon2);
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;

            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);

            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const distance = R * c;

            return distance.toFixed(2); // Returns distance in km
        }

        function convertToDecimal(coordinate) {
            if (!coordinate || typeof coordinate !== 'string') return null;

            // Split on degree symbol and any surrounding spaces
            const parts = coordinate.trim().split(/[Â°\s]+/);
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

        $('#verify_remark').on('change', function(){
            if ($('#verify_remark').val() == 'other') {
                $('#other_remark').removeClass('d-none');
            } else {
                $('#other_remark').addClass('d-none');
                $('#other_remark').val('');
            }
        })

        // function getOtherRemark() {
        //     if ($('#verify_remark').val() == 'other') {
        //         $('#other_remark').removeClass('d-none');
        //     } else {
        //         $('#other_remark').addClass('d-none');
        //         $('#other_remark').val('');
        //     }
        // }

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

            if (distributorLat && distributorLng && userLat && userLng) {
                const map = L.map('map').setView([distributorLat, distributorLng], 6);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: 'Â© OpenStreetMap contributors'
                }).addTo(map);

                const distributorMarker = L.marker([distributorLat, distributorLng]).addTo(map).bindPopup('Distributor Location').openPopup();
                const userMarker = L.marker([userLat, userLng]).addTo(map).bindPopup('User Location');

                const latlngs = [
                    [distributorLat, distributorLng],
                    [userLat, userLng]
                ];

                const polyline = L.polyline(latlngs, {
                    color: 'blue'
                }).addTo(map);

                map.fitBounds(polyline.getBounds());

                // ðŸŸ¢ Fetch travel/road distance using OSRM
                fetch(`https://router.project-osrm.org/route/v1/driving/${distributorLng},${distributorLat};${userLng},${userLat}?overview=false`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.routes && data.routes.length > 0) {
                            // const distanceInKm = (data.routes[0].distance / 1000).toFixed(2);
                            // console.log(`Road Distance: ${distanceInKm} km`);
                            //
                            // const midLat = (distributorLat + userLat) / 2;
                            // const midLng = (distributorLng + userLng) / 2;
                            //
                            // // âœ… Now safely call L.popup() after map is fully initialized
                            // L.popup()
                            //     .setLatLng([midLat, midLng])
                            //     .setContent(`<strong>Road Distance: ${distanceInKm} km</strong>`)
                            //     .openOn(map);

                            const straightLineDistance = haversineDistance(distributorLat, distributorLng, userLat, userLng);
                            console.log(`Straight-line Distance: ${straightLineDistance} km`);

                            const midLat = (distributorLat + userLat) / 2;
                            const midLng = (distributorLng + userLng) / 2;

                            L.popup()
                                .setLatLng([midLat, midLng])
                                .setContent(`<strong>Distance: ${straightLineDistance} km</strong>`)
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
                    attribution: 'Â© OpenStreetMap contributors'
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
                    attribution: 'Â© OpenStreetMap contributors'
                }).addTo(map);

                L.marker([userLat, userLng]).addTo(map)
                    .bindPopup('User Location')
                    .openPopup();
            }

            // Case 4: Neither location exists â€“ do nothing or show a message
            else {
                console.warn("No location data available.");
            }
        });

        function openImageModelold(filePath) {
            const extension = filePath.split('.').pop().toLowerCase();
            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'mp3', 'webm', 'ogg', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
        <img
            src="${filePath}"
            alt="Uploaded File"
            class=" rounded shadow-sm border"
            style="height: 450px !important; width: auto; object-fit: contain;"
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

        let isVideoLoading = false;
        let isVideoPlaying = false;

        function openImageModel(filePath) {
            console.log('Original file path:', filePath);

            // Reset flags
            isVideoLoading = false;
            isVideoPlaying = false;

            // Ensure the path is properly encoded for URLs
            const encodedFilePath = encodeURI(filePath).replace(/%20/g, ' ');

            const extension = encodedFilePath.split('.').pop().toLowerCase();
            console.log('Encoded file path:', encodedFilePath, 'Extension:', extension);

            let html = '';

            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const videoExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'temp'];

            if (imageExtensions.includes(extension)) {
                html = `
            <img
                src="${encodedFilePath}"
                alt="Uploaded File"
                class="img-fluid rounded shadow-sm border"
                style="height: 450px !important; width: auto; object-fit: contain;"
                onerror="handleImageError(this, '${encodedFilePath}')"
            />
        `;
            } else if (videoExtensions.includes(extension)) {
                // Determine mime type
                let mimeType = 'video/';
                switch(extension) {
                    case 'mp4': mimeType += 'mp4'; break;
                    case 'webm': mimeType += 'webm'; break;
                    case 'ogg': mimeType += 'ogg'; break;
                    case 'mov': mimeType += 'quicktime'; break;
                    case 'avi': mimeType += 'x-msvideo'; break;
                    case 'wmv': mimeType += 'x-ms-wmv'; break;
                    case 'flv': mimeType += 'x-flv'; break;
                    case 'temp': mimeType += 'mp4'; break;
                    default: mimeType += 'mp4';
                }

                // Create a direct download link with proper encoding
                const downloadLink = encodedFilePath.replace(/ /g, '%20');

                html = `
            <div class="video-container">
                <video id="modal-video" controls class="w-100 rounded shadow-sm border"
                       style="max-height: 70vh; object-fit: contain; background: #000;"
                       preload="metadata"
                       crossorigin="anonymous"
                       playsinline>
                    <source src="${encodedFilePath}" type="${mimeType}">
                    <p class="mt-3 p-3 bg-light rounded">
                        <i class="fa fa-exclamation-triangle text-warning"></i>
                        Your browser does not support this video format or the file cannot be loaded.
                        <br>
                        <a href="${downloadLink}" download class="btn btn-sm btn-danger mt-2">
                            <i class="fa fa-download"></i> Download Video
                        </a>
                    </p>
                </video>
                <div class="mt-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" onclick="playVideo()" id="play-btn">
                            <i class="fa fa-play"></i> Play
                        </button>
                        <button type="button" class="btn btn-info" onclick="reloadVideo('${encodedFilePath}')" id="reload-btn">
                            <i class="fa fa-refresh"></i> Reload
                        </button>
                        <a href="${downloadLink}" download class="btn btn-primary">
                            <i class="fa fa-download"></i> Download
                        </a>
                    </div>
                </div>
                <div class="mt-2">
                    <span id="video-status" class="badge bg-secondary">Loading...</span>
                    <small class="text-muted ms-2">Format: .${extension}</small>
                </div>
                <div id="video-error" class="alert alert-danger mt-2 d-none">
                    <i class="fa fa-exclamation-triangle"></i>
                    Video cannot be played. Possible issues:
                    <ul class="mb-0 mt-1">
                        <li>Browser doesn't support .${extension} format</li>
                        <li>File path contains spaces (common issue)</li>
                        <li>Video file might be corrupted</li>
                        <li>Web recordings may have encoding issues</li>
                    </ul>
                    <div class="mt-2">
                        <a href="${downloadLink}" download class="btn btn-sm btn-danger">
                            <i class="fa fa-download"></i> Download and play locally
                        </a>
                        <button type="button" class="btn btn-sm btn-warning ms-2" onclick="forceLoadVideo('${encodedFilePath}')">
                            <i class="fa fa-bolt"></i> Force Load
                        </button>
                    </div>
                </div>
            </div>
        `;
            } else {
                html = `
            <div class="alert alert-warning">
                <p class="mb-0">File type .${extension} not supported for preview.</p>
                <a href="${encodedFilePath}" download class="btn btn-sm btn-primary mt-2">
                    <i class="fa fa-download"></i> Download File
                </a>
            </div>
        `;
            }

            $('#ImageModelBody').html(html);
            const modal = new bootstrap.Modal(document.getElementById('ImageModel'));
            modal.show();

            // Set up video event listeners
            if (videoExtensions.includes(extension)) {
                setTimeout(() => {
                    setupVideoListeners(encodedFilePath);
                }, 500); // Delay to ensure DOM is ready
            }
        }

        function setupVideoListeners(filePath) {
            const video = document.getElementById('modal-video');
            const playBtn = document.getElementById('play-btn');
            const reloadBtn = document.getElementById('reload-btn');
            const status = document.getElementById('video-status');

            if (!video) return;

            // Clear any existing listeners
            video.onerror = null;
            video.oncanplay = null;
            video.onplaying = null;
            video.onwaiting = null;

            video.onerror = function(e) {
                console.error('Video error:', video.error);
                status.textContent = 'Error: ' + getVideoError(video.error);
                status.className = 'badge bg-danger';
                document.getElementById('video-error').classList.remove('d-none');
                isVideoLoading = false;
                playBtn.disabled = false;
                reloadBtn.disabled = false;
            };

            video.oncanplay = function() {
                status.textContent = 'Ready to play';
                status.className = 'badge bg-success';
                isVideoLoading = false;
                playBtn.disabled = false;
                reloadBtn.disabled = false;
            };

            video.onplaying = function() {
                status.textContent = 'Playing';
                status.className = 'badge bg-success';
                isVideoPlaying = true;
                playBtn.innerHTML = '<i class="fa fa-pause"></i> Pause';
                playBtn.onclick = pauseVideo;
            };

            video.onpause = function() {
                isVideoPlaying = false;
                playBtn.innerHTML = '<i class="fa fa-play"></i> Play';
                playBtn.onclick = playVideo;
            };

            video.onwaiting = function() {
                status.textContent = 'Buffering...';
                status.className = 'badge bg-warning';
            };

            video.onloadstart = function() {
                status.textContent = 'Loading...';
                status.className = 'badge bg-warning';
                isVideoLoading = true;
                playBtn.disabled = true;
                reloadBtn.disabled = true;
            };
        }

        function playVideo() {
            if (isVideoLoading) {
                console.log('Video is still loading, please wait...');
                return;
            }

            const video = document.getElementById('modal-video');
            const status = document.getElementById('video-status');
            const playBtn = document.getElementById('play-btn');

            if (!video) return;

            // Disable button while playing
            playBtn.disabled = true;
            isVideoPlaying = true;

            // Small delay to ensure video is ready
            setTimeout(() => {
                video.play().then(() => {
                    console.log('Video playback started successfully');
                    status.textContent = 'Playing';
                    status.className = 'badge bg-success';
                    playBtn.disabled = false;
                    playBtn.innerHTML = '<i class="fa fa-pause"></i> Pause';
                    playBtn.onclick = pauseVideo;
                }).catch(error => {
                    console.error('Error playing video:', error);
                    status.textContent = 'Play failed - try reloading';
                    status.className = 'badge bg-danger';
                    playBtn.disabled = false;
                    isVideoPlaying = false;

                    // Show specific error
                    let errorMsg = 'Unknown error';
                    switch(error.name) {
                        case 'AbortError':
                            errorMsg = 'Playback was interrupted. Try reloading the video.';
                            break;
                        case 'NotAllowedError':
                            errorMsg = 'Autoplay blocked. Click play again or enable autoplay in browser settings.';
                            break;
                        case 'NotSupportedError':
                            errorMsg = 'Video format not supported by browser.';
                            break;
                        case 'NetworkError':
                            errorMsg = 'Network error loading video.';
                            break;
                    }

                    // Update error message
                    const errorDiv = document.getElementById('video-error');
                    const errorList = errorDiv.querySelector('ul');
                    if (errorList) {
                        errorList.innerHTML = `<li>${errorMsg}</li>
                                      <li>Try downloading and playing locally</li>`;
                        errorDiv.classList.remove('d-none');
                    }
                });
            }, 100);
        }

        function pauseVideo() {
            const video = document.getElementById('modal-video');
            const playBtn = document.getElementById('play-btn');

            if (video && isVideoPlaying) {
                video.pause();
                isVideoPlaying = false;
                playBtn.innerHTML = '<i class="fa fa-play"></i> Play';
                playBtn.onclick = playVideo;
            }
        }

        function reloadVideo(filePath) {
            if (isVideoLoading) {
                console.log('Video is already loading, please wait...');
                return;
            }

            const video = document.getElementById('modal-video');
            const source = video.querySelector('source');
            const status = document.getElementById('video-status');
            const playBtn = document.getElementById('play-btn');
            const reloadBtn = document.getElementById('reload-btn');

            // Disable buttons during reload
            playBtn.disabled = true;
            reloadBtn.disabled = true;
            isVideoLoading = true;

            // Reset video
            video.pause();

            // Reset status
            status.textContent = 'Reloading...';
            status.className = 'badge bg-warning';

            // Hide error
            document.getElementById('video-error').classList.add('d-none');

            // Force reload with cache busting
            const newPath = filePath + (filePath.includes('?') ? '&' : '?') + 't=' + new Date().getTime();

            // Change source and load
            source.src = newPath;
            video.load();

            // Re-enable buttons after load
            setTimeout(() => {
                playBtn.disabled = false;
                reloadBtn.disabled = false;
                isVideoLoading = false;

                // Auto-play if previously playing
                if (isVideoPlaying) {
                    setTimeout(() => playVideo(), 500);
                }
            }, 1000);
        }

        function forceLoadVideo(filePath) {
            const video = document.getElementById('modal-video');
            const source = video.querySelector('source');
            const status = document.getElementById('video-status');

            // Create a new source element (clean slate)
            const newSource = document.createElement('source');
            newSource.src = filePath + '?force=' + new Date().getTime();
            newSource.type = source.type;

            // Replace the source
            video.innerHTML = '';
            video.appendChild(newSource);

            // Reset and load
            video.load();

            status.textContent = 'Force loading...';
            status.className = 'badge bg-warning';

            // Hide error
            document.getElementById('video-error').classList.add('d-none');

            // Setup listeners again
            setTimeout(() => {
                setupVideoListeners(filePath);
            }, 300);
        }

        function getVideoError(error) {
            if (!error) return 'Unknown error';

            switch(error.code) {
                case 1: return 'Video loading aborted';
                case 2: return 'Network error';
                case 3: return 'Video decoding error (common with web recordings)';
                case 4: return 'Video format not supported';
                default: return 'Error code: ' + error.code;
            }
        }

        function handleImageError(img, filePath) {
            console.error('Image failed to load:', filePath);
            img.src = '/path/to/placeholder.png';
            img.alt = 'Image failed to load';
            img.style.border = '2px dashed #dc3545';
        }

        $(document).ready(function () {

            //image modal

            $('.imagemodal').on('click', function(){
                let datasetval = $(this).data('setval');
                console.log(datasetval);
                openImageModel(datasetval);
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
