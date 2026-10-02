{{--
    Read-only display of a Barcode / QR Code / RFID answer for the verifier view.

    The answer is the JSON wrapper built by ScanAnswerFormatter / TaskController::saveAnswer():
    {format, raw_value, parsed, expected_headers, missing_headers, extra_headers, all_expected_present}.
    Headers are matched against the question's *current* options (same as ReportController),
    so this stays correct if the configured headers changed after the scan was saved.
    Anything that isn't that wrapper (legacy / bulk-submitted plain strings) is shown as raw text.

    Required:
      $question — Question model (Barcode / QR Code / RFID)
      $answer   — stored user_answer string
--}}
@php
    $scanDecoded = json_decode((string) $answer, true);
    $isScanWrapper = is_array($scanDecoded) && array_key_exists('raw_value', $scanDecoded) && array_key_exists('format', $scanDecoded);

    $scanRaw     = $isScanWrapper ? (string) $scanDecoded['raw_value'] : (string) $answer;
    $scanFormat  = $isScanWrapper ? $scanDecoded['format'] : 'raw_text';
    $scanParsed  = ($isScanWrapper && is_array($scanDecoded['parsed'] ?? null)) ? $scanDecoded['parsed'] : null;

    $scanHeaders = $question->getOptions->pluck('option')->map('trim')->filter()->values()->all();
    $scanExtra   = $scanParsed ? array_diff_key($scanParsed, array_flip($scanHeaders)) : [];
    $scanMissing = $scanParsed
        ? array_values(array_filter($scanHeaders, fn($h) => !array_key_exists($h, $scanParsed)))
        : $scanHeaders;

    $scanFormatLabels = ['json' => 'JSON', 'vcard' => 'vCard', 'raw_text' => 'Plain text'];
@endphp

<div class="scan-answer-verify">
    <div class="mb-2">
        <span class="badge bg-secondary">{{ $question->question_type }}</span>
        <span class="badge bg-light text-dark border">{{ $scanFormatLabels[$scanFormat] ?? $scanFormat }}</span>
        @if (!empty($scanHeaders))
            @if (empty($scanMissing))
                <span class="badge bg-success">All expected headers present</span>
            @else
                <span class="badge bg-danger">Missing: {{ implode(', ', $scanMissing) }}</span>
            @endif
        @endif
    </div>

    @if ($scanParsed !== null)
        <table class="table table-sm table-bordered mb-2" style="font-size:13px;">
            <tbody>
                @foreach ($scanHeaders as $scanHeader)
                    <tr>
                        <th class="bg-light" style="width:40%;">{{ $scanHeader }}</th>
                        <td>
                            @if (array_key_exists($scanHeader, $scanParsed))
                                {{ is_scalar($scanParsed[$scanHeader]) ? $scanParsed[$scanHeader] : json_encode($scanParsed[$scanHeader]) }}
                            @else
                                <span class="text-danger fst-italic">&#8212; not found in scan &#8212;</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach ($scanExtra as $extraKey => $extraValue)
                    <tr>
                        <th class="bg-light text-muted" style="width:40%;">
                            {{ $extraKey }} <small class="fw-normal">(extra)</small>
                        </th>
                        <td class="text-muted">{{ is_scalar($extraValue) ? $extraValue : json_encode($extraValue) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <details>
            <summary class="small text-muted" style="cursor:pointer;">Raw scanned value</summary>
            <textarea class="form-control mt-1" rows="3" readonly style="font-size:12px;">{{ $scanRaw }}</textarea>
        </details>
    @else
        {{-- Not JSON / vCard — no header matching possible, show the scanned text as-is --}}
        <textarea class="form-control" rows="2" readonly>{{ $scanRaw }}</textarea>
    @endif
</div>
