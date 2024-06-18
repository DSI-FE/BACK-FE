<?php

namespace App\Http\Controllers\API\Institution;

use App\Http\Controllers\Controller;
use App\Models\Institution\Entry;
use App\Models\General\GralFile;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use Storage;

class EntryController extends Controller
{

    public function index()
    {
        return response()->json(Entry::all(), 200);
    }

    public function indexWithFilesByType($type)
    {
        $data = [];
        $entries = Entry::where([ 'type'=>$type ])->orderBy('id','DESC')->get();
        foreach ($entries as $key => $entry) {
            $file = $entry->file;
            $image = null;
            try {
                $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
            } catch (\Throwable $th) {}
            $entry->file_image = $image;
        }
        
        return response()->json($entries, 200);
    }

    public function indexWithFilesByTypeAndSubtype($type,$subtype)
    {
        $data = [];
        $response = null;
        $subtypeNull = ['NULL','UNDEFINED','null','undefined',null,0];
        $subtype = in_array($subtype,$subtypeNull) ? null : $subtype;
        $entries = Entry::where([ 'type'=>$type, 'subtype'=>$subtype ])->orderBy('id','DESC')->get();
        foreach ($entries as $key => $entry) {
            $file = $entry->file;
            $image = null;
            try {
                $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
            } catch (\Throwable $th) {}
            $entry->file_image = $image;
        }
        
        return response()->json($entries, 200);
    }

    public function showActiveWithFilesByTypeAndSubtype($type,$subtype)
    {
        $data = [];
        $response = null;
        $entry = Entry::where([ 'active'=>1, 'type'=>$type, 'subtype'=>$subtype ])->first();
        if ( $entry ) {
            $file = $entry->file;
            $image = null;
            try {
                $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
            } catch (\Throwable $th) {}
            $entry->file_image = $image;
        }
        
        return response()->json($entry, 200);
    }
    
    public function store(Request $request)
    {
        $message = '';
        $errors = null;
        $response = null;
        $httpCode = 200;
        $msg = '';

        $validator = $this->storeValidator($request);
        if(!$validator->fails())
        {

            $deactivatePrevious = $request['deactivate_previous'] && $request['deactivate_previous'] == true && $request['type'] && $request['subtype'] ?? false;
            if ( $deactivatePrevious ) {
                $entriesPrevious = Entry::where([ 'active'=>1, 'type'=>$request['type'], 'subtype'=>$request['subtype'] ])->get();
                foreach ($entriesPrevious as $key => $entry) {
                    $entry->active = 0;
                    $entry->save();
                }
            }
            
            $entry = Entry::updateOrCreate(
                [
                    'id'=>$request['id']
                ],
                [
                    'name'=>$request['name'],
                    'description'=>$request['description'],
                    'content'=>$request['content'],
                    'url'=>$request['url'],
                    'show_in_carousel'=>$request['show_in_carousel'],
                    'type'=>$request['type'],
                    'subtype'=>$request['subtype'],
                    'date_start'=>$request['date_start'],
                    'date_end'=>$request['date_end'],
                    'active'=>$request['active'],
                    'adm_employee_id'=>$request['adm_employee_id']
                ]
            );

            if ( $request->file('file') ) {
                $mandatoryAttachment = $request->file('file');
                $mandatoryAttachmentName = $mandatoryAttachment->getClientOriginalName();
                $mandatoryAttachmentExtn = $mandatoryAttachment->getClientOriginalExtension();
                $mandatoryAttachmentRoute = 'public/institution/entries/'.$entry->id;
                $fileName   = strtolower($entry->id.'_'.$mandatoryAttachmentName.'.'.$mandatoryAttachmentExtn);
                Storage::putFileAs($mandatoryAttachmentRoute, $mandatoryAttachment, $fileName);
                $gralFile = GralFile::updateOrCreate(
                    [ 'id'=>$request['file_id'] ],
                    [
                        'name' => $fileName,
                        'original_name' => $mandatoryAttachmentName,
                        'route' => $mandatoryAttachmentRoute
                    ]
                );
                $entry->gral_file_id = $gralFile->id;
                $entry->save();
            }

        } else {
            $errors['message']     = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);

    }

    public function show(int $id)
    {
        $entry = Entry::where('id',$id)->with(['file'])->first();
        return response()->json($entry, 200);
    }

    public function destroy(int $id)
    {
        $entry = Entry::findOrFail($id);
        $entry->delete();
        return response()->json($entry, 200);
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'name' => [ 'required', 'string', 'max:255' ],
            'description' => [ 'string' ],
            'content' => [ 'string' ],
            'url' => [ 'string', 'max:511' ],
            'show_in_carousel' => [ 'boolean' ],
            'type' => ['required','integer'],
            'subtype' => ['integer'],
            'date_start' => ['date_format:Y-m-d'],
            'date_end' => ['date_format:Y-m-d','after_or_equal:date_start'],
            'active' => ['boolean'],
            'adm_employee_id' => ['required','integer','exists:adm_employees,id'],
            'gral_file_id' => [ 'integer','exists:gral_files,id']
        ];
        
        $messages = [
            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe poseer máximo 255 caracteres',

            'description.string' => 'Descripción debe ser una cadena de caracteres válida',

            'content.string' => 'Contenido debe ser una cadena de caracteres válida',

            'url.string' => 'Enlace debe ser una cadena de caracteres válida',
            'url.max' => 'Enlace debe poseer máximo 255 caracteres',

            'show_in_carousel.boolean' => 'Mostrar en Carrusel debe ser un valor booleano',

            'type.required' => 'Tipo debe ser ingresado',
            'type.string' => 'Tipo debe ser un valor entero válido',

            'subtype.string' => 'Subtipo debe ser un valor entero válido',

            'date_start.date_format' => 'Fecha inicial debe cumplir con el formato Y-m-d',
            'date_end.date_format' => 'Fecha final debe cumplir con el formato Y-m-d',
            'date_end.after_or_equal' => 'Fecha final debe ser mayor a fecha inicial',

            'active.boolean' => 'Activo debe ser un valor booleano',
            
            'adm_employee_id.required' => 'Foranea de empleado debe ser ingresada',
            'adm_employee_id.integer' => 'Foranea de empleado debe ser un valor entero válido',
            'adm_employee_id.exists' => 'Foranea de empleado debe ser un valor existente',

            'gral_file_id.integer' => 'Foranea de documento debe ser un valor entero válido',
            'gral_file_id.exists' => 'Foranea de documento debe ser un valor existente',
        ];

        if ( !$request['file_id'] ) {
            $rules['file'] = [ 'required' ];
            $messages['file.required'] = 'Archivo debe ser cargado';
        }
        
        return Validator::make($request->all(),$rules,$messages);
    }
}
