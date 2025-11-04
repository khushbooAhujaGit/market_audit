<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\TemplateName;
use App\Models\TemplateNameHead;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DynamicTableExport;

class TemplateNameController extends Controller
{
    function __construct()
    {
        return $this->middleware("auth");
    }
    public function index()
    {
        $template_names = TemplateName::paginate(10);
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
                TemplateNameHead::create([
                    'template_name_id' => $newTemplate->id,
                    'template_head_name' => $template_head[1]
                ]);
            }
            return redirect(route('templateName.list'))->with('message', "Template Created Successfully");
        }
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
//        $templateHeads = TemplateNameHead::where('template_name_id', $templateName->id)->pluck('template_head_name')->toArray();
        $templateHeads = $templateName->getTemplateHeads->pluck('template_head_name')->toArray();
        $data = [$templateHeads]; // Wrap the headers in an array
        $fileName = $templateName->template_name . '.xlsx';
        return Excel::download(new class($data) implements FromArray {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }
        }, $fileName);
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
