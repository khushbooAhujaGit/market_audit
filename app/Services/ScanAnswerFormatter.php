<?php

namespace App\Services;

/**
 * Shared Barcode/QR Code/RFID answer formatting — builds the structured JSON
 * answer ({format, raw_value, parsed, expected_headers, missing_headers,
 * extra_headers, all_expected_present}) that TaskController::saveAnswer()
 * (mobile API) and TaskHandlerController::autoSaveAnswer() (web form) both
 * need to produce identically, so a scan answers the same way regardless of
 * which client submitted it — and so ReportController's scan-answer columns
 * can rely on one consistent shape.
 */
class ScanAnswerFormatter
{
    /**
     * Builds the JSON-encoded answer for a scanned value. $expectedHeaders is
     * the question's currently-configured list (QuestionDropdown options) —
     * used to split the scan's data into matched vs. extra headers.
     */
    public static function buildAnswerJson(string $rawScan, array $expectedHeaders): string
    {
        $decoded = json_decode($rawScan, true);
        $isJsonObject = is_array($decoded) && json_last_error() === JSON_ERROR_NONE
            && array_keys($decoded) !== range(0, count($decoded) - 1); // associative, not a plain list

        if ($isJsonObject) {
            $format = 'json';
            $parsed = $decoded;
        } else {
            $vcard = self::parseVCard($rawScan);
            if ($vcard !== null) {
                $format = 'vcard';
                $parsed = $vcard;
            } else {
                $format = 'raw_text';
                $parsed = null;
            }
        }

        if ($parsed !== null) {
            $foundHeaders   = array_keys($parsed);
            $missingHeaders = array_values(array_diff($expectedHeaders, $foundHeaders));
            $extraHeaders   = array_values(array_diff($foundHeaders, $expectedHeaders));

            $scanAnswer = [
                'format'               => $format,
                'raw_value'            => $rawScan,
                'parsed'               => $parsed,
                'expected_headers'     => $expectedHeaders,
                'missing_headers'      => $missingHeaders,
                'extra_headers'        => $extraHeaders,
                'all_expected_present' => empty($missingHeaders),
            ];
        } else {
            $scanAnswer = [
                'format'               => 'raw_text',
                'raw_value'            => $rawScan,
                'parsed'               => null,
                'expected_headers'     => $expectedHeaders,
                'missing_headers'      => $expectedHeaders,
                'extra_headers'        => [],
                'all_expected_present' => empty($expectedHeaders),
            ];
        }

        return json_encode($scanAnswer);
    }

    /**
     * Parses a vCard-formatted scan (BEGIN:VCARD ... END:VCARD) into a flat
     * head:value map. Returns null if $raw isn't a vCard. See
     * ScanAnswerFormatter::buildAnswerJson() for how this feeds into the
     * final answer shape.
     */
    public static function parseVCard(string $raw): ?array
    {
        if (stripos(ltrim($raw), 'BEGIN:VCARD') !== 0) {
            return null;
        }

        $result = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'BEGIN:') === 0 || stripos($line, 'END:') === 0 || stripos($line, 'VERSION:') === 0) {
                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;

            $propPart = substr($line, 0, $colonPos);
            $value    = substr($line, $colonPos + 1);
            $propBits = explode(';', $propPart);
            $key      = strtoupper(array_shift($propBits));

            foreach ($propBits as $param) {
                if (stripos($param, 'TYPE=') === 0) {
                    $key .= '_' . strtoupper(substr($param, 5));
                    break;
                }
            }

            // Guard against genuine duplicate keys (same property, same/no type)
            if (isset($result[$key])) {
                $i = 2;
                while (isset($result[$key . '_' . $i])) $i++;
                $key .= '_' . $i;
            }

            $result[$key] = $value;
        }

        return empty($result) ? null : $result;
    }
}
