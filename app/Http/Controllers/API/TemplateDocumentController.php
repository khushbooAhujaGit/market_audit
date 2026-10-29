<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TemplateDocument;
use App\Services\TemplateDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Files of template file columns, for the app (Bearer token). */
class TemplateDocumentController extends Controller
{
    /**
     * GET /api/rowDocuments/{row_id}
     * The file columns of a row: column, file name, status (available / awaiting_file / queued /
     * failed / invalid), kind (image / pdf / excel / word / file) and, when available, a url
     * (a download link that needs no token and works for 24 hours).
     */
    public function rowDocuments($rowId)
    {
        $row = DB::table('project_template_name_values_new')->where('id', $rowId)->first();
        if (!$row) {
            return response()->json(['status' => 404, 'message' => 'Row not found.'], 404);
        }

        $documents = TemplateDocuments::forRowsApi([$row->id])[$row->id] ?? [];

        return response()->json(['status' => 200, 'row_id' => (int) $rowId, 'documents' => $documents]);
    }

    /**
     * GET /api/documentDownload/{id}/{expires}/{signature} (no login): the file as a download, from the
     * "url" the app APIs give (TemplateDocuments::downloadLink). ?view=1 shows it in the browser instead.
     */
    public function download(Request $request, $id, $expires, $signature)
    {
        if (!ctype_digit((string) $id) || !ctype_digit((string) $expires)
            || !hash_equals(TemplateDocuments::linkSignature((int) $id, (int) $expires), (string) $signature)) {
            return response()->json(['status' => 403, 'message' => 'This download link is not valid.'], 403);
        }
        // 0: a report's link, which does not expire (see TemplateDocuments::reportLink)
        if ((int) $expires !== 0 && (int) $expires < time()) {
            return response()->json(['status' => 410, 'message' => 'This download link has expired. Open the row again in the app to get a new one.'], 410);
        }

        $doc = TemplateDocument::find((int) $id);
        if (!$doc || $doc->status !== 'available' || !$doc->stored_path || !Storage::disk('local')->exists($doc->stored_path)) {
            return response()->json(['status' => 404, 'message' => 'File not found.'], 404);
        }

        $name = $doc->original_name ?: $doc->file_name;
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.' . pathinfo($doc->stored_path, PATHINFO_EXTENSION);
        return Storage::disk('local')->response($doc->stored_path, $name, ['Content-Type' => $doc->mime ?: 'application/octet-stream'],
            $request->boolean('view') ? 'inline' : 'attachment');
    }

    /** GET /api/templateDocument/{id}: the file itself (?download=1 to save it). */
    public function file(Request $request, $id)
    {
        $doc = TemplateDocument::find((int) $id);
        if (!$doc || $doc->status !== 'available' || !$doc->stored_path || !Storage::disk('local')->exists($doc->stored_path)) {
            return response()->json(['status' => 404, 'message' => 'File not found.'], 404);
        }
        if (!TemplateDocuments::canView($request->user(), $doc)) {
            return response()->json(['status' => 403, 'message' => 'This file is not assigned to you.'], 403);
        }

        $name = $doc->original_name ?: $doc->file_name;
        if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.' . pathinfo($doc->stored_path, PATHINFO_EXTENSION);
        return Storage::disk('local')->response($doc->stored_path, $name, ['Content-Type' => $doc->mime ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline');
    }
}
