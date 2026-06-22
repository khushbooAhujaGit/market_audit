<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Audit PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            padding: 0;
            margin: 0;
            background-color: #fff;
            color: #000;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 20px;
            color: #333;
            margin: 10px 0 5px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 14px;
            background-color: #374462;
            padding: 8px;
            border: 1px solid #ccc;
            margin-top: 20px;
            margin-bottom: 10px;
            color: floralwhite;
        }

        .info-table-new {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table-new th,
        .info-table-new td {
            border: 1px solid #999;
            padding: 8px;
            vertical-align: top;
        }

        .info-table-new th {
            width: 30%;
            background-color: #374462;
            text-align: left;
            color: floralwhite;
        }

        .info-table-new td {
            width: 70%;
            background-color: #f9f9f9;
            word-wrap: break-word;
        }

        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .checklist-table th,
        .checklist-table td {
            border: 1px solid #999;
            padding: 8px;
            vertical-align: top;
        }

        .checklist-table th {
            background-color: #374462;
            color: floralwhite;
            text-align: left;
        }

        .parent-question-row {
            background-color: #ffffff;
        }

        .child-question-row {
            background-color: #f9f9f9;
        }

        /* Image page styles - fixed to avoid blank pages */
        .image-page {
            page-break-before: always;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* First image should not have page break before if it's the first element after checklist */
        .image-page:first-of-type {
            page-break-before: avoid;
        }

        .image-page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f5f5f5;
            border-bottom: 2px solid #374462;
            width: 100%;
        }

        .image-question-text {
            font-size: 18px;
            font-weight: bold;
            color: #374462;
            margin-bottom: 10px;
        }

        .image-question-type {
            font-size: 14px;
            color: #666;
            font-style: italic;
        }

        .image-container {
            text-align: center;
            padding: 20px;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
        }

        .image-container img {
            max-width: 90%;
            max-height: 80vh;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .file-link {
            display: inline-block;
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }

        .audio-link {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            gap: 5px;
        }

        .audio-link:before {
            content: "🔊";
            font-size: 12px;
        }

        .video-link {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            gap: 5px;
        }

        .video-link:before {
            content: "▶";
            font-size: 12px;
        }

        .download-link {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            gap: 5px;
        }

        .download-link:before {
            content: "⬇";
            font-size: 12px;
        }

        .location-link {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background-color: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            gap: 5px;
        }

        .location-link:before {
            content: "📍";
            font-size: 12px;
        }

        .answer-text {
            line-height: 1.5;
        }

        .inline-media {
            display: inline-block;
            margin: 2px;
        }

        .image-placeholder {
            color: #0066cc;
            font-style: italic;
        }

        /* Ensure no extra blank pages */
        .checklist-table {
            page-break-after: avoid;
        }

        .section-title:last-of-type {
            page-break-after: avoid;
        }
    </style>
</head>

<body>

<div class="header">
    @if(isset($main_header) && isset($sub_header) && !empty($main_header) && !empty($sub_header))
        {{ $main_header . ' - ' . $sub_header }}
    @else
        {{ $value }}
    @endif
</div>

<div class="section-title">Audit Details</div>
<table class="info-table-new">
    <tbody>
    <tr>
        <th>Auditor Name</th>
        <td>{{ $auditorInfo->getUser->name ?? '---' }}</td>
    </tr>
    <tr>
        <th>Date of Audit</th>
        <td>{{ $auditDate ?? '---' }}</td>
    </tr>
    <tr>
        <th>Time of Audit</th>
        <td>{{ $auditTime ?? '---' }}</td>
    </tr>
    </tbody>
</table>

<div class="section-title">Checklist</div>
<table class="checklist-table">
    <thead>
    <tr>
        <th style="width: 8%;">Sr. No</th>
        <th style="width: 52%;">Checklist Point</th>
        <th style="width: 40%;">Response / Remarks</th>
    </tr>
    </thead>
    <tbody>
    @php
        $index = 1;
        $imagePages = []; // Store images to display on separate pages

        // Helper function to check if answer is not empty
        function isAnswerNotEmpty($answer) {
            if (!$answer) {
                return false;
            }

            $answerValue = $answer->user_answer;

            // Check if answer is empty or null
            if (empty($answerValue)) {
                return false;
            }

            // For subjective or multi-select, check if array is empty
            if (is_array($answerValue)) {
                return !empty(array_filter($answerValue));
            }

            // For other types, check if string is not empty
            return trim($answerValue) !== '';
        }

        // Helper function to get answer for a question
        function getAnswerForQuestion($questionId, $userResponses) {
            foreach ($userResponses as $userResponse) {
                if ($userResponse->question_id == $questionId) {
                    return $userResponse;
                }
            }
            return null;
        }

        // Helper function to check if value is an image
        function isImageFile($path) {
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return in_array($extension, $extensions);
        }

        // Helper function to collect images for separate pages
        function collectImage($value, $questionText, $parentQuestionText = null, &$imagePages) {
            $displayText = $questionText;
            if ($parentQuestionText) {
                $displayText = $parentQuestionText . ' - ' . $questionText;
            }

            $fullPath = asset($value);
            $imagePages[] = [
                'title' => $displayText,
                'path' => $fullPath,
                'file_path' => $value
            ];
            return '<span class="image-placeholder">📷 See image on next page: ' . e($displayText) . '</span>';
        }

        // Helper function to format answer
        function formatAnswer($answer, $questionObj, $questionText, &$imagePages, $parentQuestionText = null) {
            if (!$answer) {
                return null;
            }

            $questionType = $questionObj->question_type ?? 'Free Text';
            $d_value = $answer->user_answer;

            // Check if answer is empty
            if (empty($d_value) || (is_array($d_value) && empty(array_filter($d_value)))) {
                return null;
            }

            $displayText = $questionText;
            if ($parentQuestionText) {
                $displayText = $parentQuestionText . ' - ' . $questionText;
            }

            switch($questionType) {
                case 'Location':
                    if ($d_value) {
                        return '<a href="https://www.google.com/maps?q=' . urlencode($d_value) . '" target="_blank" class="location-link">View Location</a>';
                    }
                    return null;

                case 'Video':
                    if ($d_value) {
                        $fullUrl = asset($d_value);
                        return '<a href="' . $fullUrl . '" target="_blank" class="video-link">View Video</a>';
                    }
                    return null;

                case 'Audio':
                    if ($d_value) {
                        $fullUrl = asset($d_value);
                        return '<a href="' . $fullUrl . '" target="_blank" class="audio-link">Play Audio</a>';
                    }
                    return null;

                case 'Free Text':
                case 'Dropdown':
                case 'Yes / No':
                    $textValue = trim($d_value);
                    return !empty($textValue) ? '<div class="answer-text">' . nl2br(e($textValue)) . '</div>' : null;

                case 'Subjective':
                    $output = '';
                    $values = is_array($d_value) ? $d_value : [$d_value];
                    $hasContent = false;

                    foreach ($values as $d_val) {
                        if (empty($d_val)) continue;

                        $extension = strtolower(pathinfo($d_val, PATHINFO_EXTENSION));
                        $isUrlOrPath = str_contains($d_val, '/');

                        if (!$isUrlOrPath) {
                            $output .= '<div class="answer-text">' . nl2br(e($d_val)) . '</div>';
                            $hasContent = true;
                        } elseif (isImageFile($d_val)) {
                            // Collect image for separate page
                            $output .= collectImage($d_val, $questionText, $parentQuestionText, $imagePages);
                            $hasContent = true;
                        } elseif (in_array($extension, ['mp4', 'webm', 'ogg'])) {
                            $fullUrl = asset($d_val);
                            $output .= '<div class="inline-media"><a href="' . $fullUrl . '" target="_blank" class="video-link">View Video</a></div>';
                            $hasContent = true;
                        } elseif (in_array($extension, ['mp3', 'wav', 'webm', 'ogg'])) {
                            $fullUrl = asset($d_val);
                            $output .= '<div class="inline-media"><a href="' . $fullUrl . '" target="_blank" class="audio-link">Play Audio</a></div>';
                            $hasContent = true;
                        } else {
                            $fullUrl = asset($d_val);
                            $output .= '<div class="inline-media"><a href="' . $fullUrl . '" target="_blank" class="download-link">Download File</a></div>';
                            $hasContent = true;
                        }
                    }
                    return $hasContent ? $output : null;

                case 'Multi select':
                    $values = is_array($d_value) ? implode(', ', $d_value) : $d_value;
                    return !empty($values) ? '<div class="answer-text">' . e($values) . '</div>' : null;

                case 'Date & Time':
                    try {
                        if (empty($d_value)) return null;
                        $date = \Carbon\Carbon::parse($d_value);
                        return '<div class="answer-text">' . $date->format('d-m-Y H:i') . '</div>';
                    } catch (\Exception $e) {
                        return !empty($d_value) ? '<div class="answer-text">' . e($d_value) . '</div>' : null;
                    }

                case 'Date':
                    try {
                        if (empty($d_value)) return null;
                        // Handle different date formats
                        if (strpos($d_value, '/') !== false) {
                            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $d_value);
                            return '<div class="answer-text">' . $date->format('d-m-Y') . '</div>';
                        } elseif (strpos($d_value, '-') !== false) {
                            $date = \Carbon\Carbon::parse($d_value);
                            return '<div class="answer-text">' . $date->format('d-m-Y') . '</div>';
                        }
                        return '<div class="answer-text">' . e($d_value) . '</div>';
                    } catch (\Exception $e) {
                        return !empty($d_value) ? '<div class="answer-text">' . e($d_value) . '</div>' : null;
                    }

                case 'File Upload':
                    if ($d_value) {
                        $fullUrl = asset($d_value);
                        if (isImageFile($d_value)) {
                            // Collect image for separate page
                            return collectImage($d_value, $questionText, $parentQuestionText, $imagePages);
                        }
                        return '<a href="' . $fullUrl . '" target="_blank" class="download-link">Download File</a>';
                    }
                    return null;

                case 'Image':
                    if ($d_value) {
                        // Collect image for separate page
                        return collectImage($d_value, $questionText, $parentQuestionText, $imagePages);
                    }
                    return null;

                default:
                    $textValue = trim($d_value);
                    return !empty($textValue) ? '<div class="answer-text">' . nl2br(e($textValue)) . '</div>' : null;
            }
        }
    @endphp

    @foreach ($related_questions as $key => $related_question)
        @php
            // Get the answer for the current question
            $answer = getAnswerForQuestion($related_question->id, $user_responses);

            // Check if parent question has an answer
            $hasParentAnswer = isAnswerNotEmpty($answer);

            // Only process if parent has answer
            if (!$hasParentAnswer) {
                continue;
            }

            // Check if this question has child questions
            $hasChildren = isset($related_question->children) && count($related_question->children) > 0;

            // Format parent answer
            $formattedAnswer = formatAnswer($answer, $related_question, $related_question->question, $imagePages);

            // Only show parent if formatted answer is not null
            if ($formattedAnswer === null) {
                continue;
            }
        @endphp

            <!-- Parent Question Row -->
        <tr class="parent-question-row">
            <td style="width: 8%; vertical-align: top;">{{ $index++ }}</td>
            <td style="width: 52%; font-weight: bold; vertical-align: top;">
                {{ $related_question->question }}
            </td>
            <td style="width: 40%; vertical-align: top;">
                {!! $formattedAnswer !!}
            </td>
        </tr>

        <!-- Child Questions - Only show if they have answers -->
        @if($hasChildren)
            @foreach($related_question->children as $childQuestion)
                @php
                    $childAnswer = getAnswerForQuestion($childQuestion->id, $user_responses);
                    $hasChildAnswer = isAnswerNotEmpty($childAnswer);

                    // Skip child if no answer
                    if (!$hasChildAnswer) {
                        continue;
                    }

                    $childFormattedAnswer = formatAnswer($childAnswer, $childQuestion, $childQuestion->question, $imagePages, $related_question->question);

                    // Skip if formatted answer is null
                    if ($childFormattedAnswer === null) {
                        continue;
                    }
                @endphp
                <tr class="child-question-row">
                    <td style="width: 8%; vertical-align: top;"></td>
                    <td style="width: 52%; vertical-align: top; padding-left: 25px !important;">
                        <span style="font-style: italic; color: #666;">↳ {{ $childQuestion->question }}</span>
                    </td>
                    <td style="width: 40%; vertical-align: top; background-color: #fefefe;">
                        {!! $childFormattedAnswer !!}
                    </td>
                </tr>
            @endforeach
        @endif
    @endforeach
    </tbody>
</table>

<!-- Display all images on separate pages with their questions -->
@if (isset($imagePages) && count($imagePages) > 0)
    @foreach ($imagePages as $index => $image)
        <div class="image-page">
            <div class="image-page-header">
                <div class="image-question-text">{{ $image['title'] }}</div>
                <div class="image-question-type">Image Attachment</div>
            </div>
            <div class="image-container">
                @php
                    // Try to get the absolute path for the image
                    $imagePath = $image['path'];
                    $publicPath = public_path(str_replace(url('/'), '', $imagePath));

                    // Check if file exists
                    $fileExists = file_exists($publicPath);
                @endphp

                @if($fileExists)
                    <img src="{{ $publicPath }}" style="max-width: 90%; max-height: 80vh; width: auto; height: auto; object-fit: contain; display: block; margin: 0 auto;" />
                @else
                    <div style="color: red; text-align: center; padding: 50px;">
                        <p>Image not found: {{ $image['title'] }}</p>
                        <p>Path: {{ $imagePath }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
@endif

</body>

</html>
