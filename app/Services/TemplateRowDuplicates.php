<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Duplicate check for project data uploads: a row is a duplicate when every column matches
 * (ignoring case and extra spaces) a row already in the template or earlier in the same file.
 */
class TemplateRowDuplicates
{
    /** Comparison key for one row: its values in template column order. */
    public static function key(array $valuesByHeadId, array $headIds): string
    {
        $parts = [];
        foreach ($headIds as $headId) {
            $v = (string) ($valuesByHeadId[$headId] ?? '');
            $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
            $parts[] = mb_strtolower(trim($v));
        }
        return md5(implode("\x1F", $parts));
    }

    /** Keys of the rows already stored for a project template. */
    public static function existingKeys(int $projectTemplateId, array $headIds): array
    {
        $keys = [];
        DB::table('project_template_name_values_new')
            ->where('project_template_id', $projectTemplateId)
            ->select('id', 'template_data_json')
            ->orderBy('id')
            ->chunkById(5000, function ($rows) use (&$keys, $headIds) {
                foreach ($rows as $row) {
                    $json = json_decode($row->template_data_json, true);
                    if (is_array($json)) $keys[self::key($json, $headIds)] = true;
                }
            });
        return $keys;
    }
}
