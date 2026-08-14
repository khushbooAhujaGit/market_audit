<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\TemplateName;
use App\Models\TemplateNameHead;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DynamicTableExport;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class TemplateNameController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    public function index()
    {
        $template_names = TemplateName::all();
        return view('masters.data_templates.index', compact('template_names'));
    }

    public function create()
    {
        return view('masters.data_templates.add');
    }

       public function store(Request $request)
    {
        $formFields = $request->validate([
            'template_name' => ['required', 'unique:template_names'],
            'heads_excel' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Ensure the file is an XLSX
            ],
        ]);
        $newTemplate = TemplateName::create($formFields);
        if ($request->hasFile('heads_excel')) {
            $file = $request->file('heads_excel');
            $data = Excel::toCollection(Excel::class, $file)->first();
            $dataArray = $data->toArray();
            // Remove the first element from the array
            array_shift($dataArray);
            //khushboo 12-05-25
            // Create the two new arrays
            // $newArray1 = ['add_key1', 'key_code'];
            // $newArray2 = ['add_key2', 'key_name'];

            // Prepend the new arrays to $dataArray
            // $dataArray = array_merge([$newArray1, $newArray2], $dataArray);
            //khushboo 12-05-25

            // //khushboo 17-05-25
            // // Create the two new arrays
            // $newArray1 = ['add_key1', 'latitude'];
            // $newArray2 = ['add_key2', 'longitude'];

            // // Prepend the new arrays to $dataArray
            // $dataArray = array_merge([$newArray1, $newArray2], $dataArray);
            // //khushboo 17-05-25
            
            foreach ($dataArray as $template_head) {
                // Columns 2 (Type), 3 (Options), and 4 (Required) are all optional — older-format
                // uploads with only Sr/Name still work unchanged: free text, no options, not required.
                $valueType  = $this->normalizeHeadValueType($template_head[2] ?? null);
                $isRequired = $this->normalizeHeadRequired($template_head[4] ?? null);

                $head = TemplateNameHead::create([
                    'template_name_id'   => $newTemplate->id,
                    'template_head_name' => $template_head[1],
                    'value_type'         => $valueType,
                    'is_required'        => $isRequired,
                ]);

                if ($valueType === 'dropdown') {
                    foreach (explode('|', (string) ($template_head[3] ?? '')) as $option) {
                        $option = trim($option);
                        if ($option === '') continue;
                        \App\Models\TemplateNameHeadOption::create([
                            'template_name_head_id' => $head->id,
                            'option'                => $option,
                        ]);
                    }
                }
            }
            return redirect(route('templateName.list'))->with('message', "Template Created Successfully");
        }
    }


    /**
     * Normalize the free-text "Type" column from the heads-upload Excel into a stable
     * value_type: 'dropdown', 'yes_no', or null (free text — also the default when the
     * column is blank/absent, so older-format uploads are unaffected).
     */
    private function normalizeHeadValueType($rawType): ?string
    {
        $t = strtolower(trim((string) $rawType));
        if ($t === '') return null;
        if (str_contains($t, 'drop')) return 'dropdown';
        if (str_contains($t, 'yes') && str_contains($t, 'no')) return 'yes_no';
        return null;
    }

    /**
     * Normalize the free-text "Required" column from the heads-upload Excel into a boolean.
     * Blank/absent defaults to false (not required) — matches today's behavior for older uploads.
     */
    private function normalizeHeadRequired($rawRequired): bool
    {
        $t = strtolower(trim((string) $rawRequired));
        return in_array($t, ['yes', 'y', 'true', '1', 'required'], true);
    }

    public function edit($id)
    {
        $templateName = TemplateName::findOrFail($id);
        return view('masters.data_templates.edit', compact('templateName'));
    }

    public function update(Request $request, $id)
    {
        $formFields = $request->validate([
            'template_name' => ['required'],
            'heads_excel' => [
                'nullable',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Ensure the file is an XLSX
            ],
        ]);
        $templateName = TemplateName::find($id);
        $templateName->update($formFields);
        return redirect(route('templateName.list'))->with('message', 'Template Updated');
    }

    public function destroy(Request $request)
    {
        $template_name = TemplateName::findOrFail($request->id);
        $template_questions = TemplateNameHead::where('template_name_id', $template_name->id)->get();
//        foreach ($template_questions as $question) {
//            UserQuestion::where('question_id', $question->id)->delete();
//        }
        TemplateNameHead::where('template_name_id', $template_name->id)->delete();
        $template_name->delete();
        return "Success";
    }
    public function downloadExcel($id)
    {
        $templateName = TemplateName::findOrFail($id);
        $heads = $templateName->getTemplateHeads()->orderBy('id')->with('getOptions')->get();

        // Raw PhpSpreadsheet (not Maatwebsite's FromArray) so we can attach per-column data
        // validation dropdowns — same technique already used in
        // ExcelFormatsController::userUploadTemplateDownload().
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($heads as $ci => $head) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue($col . '1', $head->template_head_name);

            if (!in_array($head->value_type, ['dropdown', 'yes_no'])) {
                continue;
            }

            $options = $head->value_type === 'yes_no'
                ? ['Yes', 'No']
                : $head->getOptions->pluck('option')->toArray();
            $optionList = implode(',', $options);

            // Excel's inline list formula is capped at 255 chars — skip validation (leave the
            // column as free text) rather than fail the whole download for one long option list.
            if ($optionList === '' || strlen($optionList) >= 255) {
                continue;
            }

            for ($row = 2; $row <= 500; $row++) {
                $validation = $sheet->getCell($col . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $optionList . '"');
            }
        }

        $fileName = $templateName->template_name . '.xlsx';
        $tmpPath  = tempnam(sys_get_temp_dir(), 'tmpl_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, $fileName)->deleteFileAfterSend(true);
    }

    function getTemplateHeads(Request $request){


        $templateArr = [];
        foreach($request->template_names as $template_name){
            $template_name = TemplateName::findOrFail($template_name);

            $templateArr[] = $template_name->getTemplateHeads;
        }


        return response()->json(['message' => 'success', 'templateArr' => $templateArr]);
    }


    //khushboo 13-05-2025
    public function exportTemplateData()
    {
        $templates = TemplateName::with('getTemplateHeads')->get();

        $data = $templates->map(function ($template) {
            $templateHeads = $template->getTemplateHeads->pluck('template_head_name')->implode(', ');

            return [
                $template->template_name,
                $templateHeads,
            ];
        });

        $headings = ['Template Name', 'Template Heads'];

        return Excel::download(new DynamicTableExport($data, $headings), 'template.xlsx');
    }
    //khushboo 13-05-2025

}
