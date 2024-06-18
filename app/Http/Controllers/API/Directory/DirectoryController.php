<?php

namespace App\Http\Controllers\API\Directory;

use App\Http\Controllers\Controller;

use App\Models\Directory\Directory;
use App\Models\General\GralFile;

use Illuminate\Http\Request;

use Auth;
use Storage;
use Validator;

class DirectoryController extends Controller
{
    public function index()
    {
        $directories = Directory::
        with([ 'employee', 'file', 'contacts', 'classifications' ])->get();
        return response()->json($directories,200);
    }

    public function indexEmployee()
    {
        $directories = Directory::
        where( 'adm_employee_id', Auth::user()->employee->id )->with(['file'])->get();
        return response()->json($directories,200);
    }

    public function indexPublic()
    {
        $directories = Directory::
        where( 'public', 1 )
        ->with([ 'employee', 'file', 'contacts', 'classifications' ])->get();
        return response()->json($directories,200);

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
            $directory = Directory::updateOrCreate(
            [ 'id' => $request['id'] ],
            [
                'name' => $request['name'],
                'classification_name' => $request['classification_name'],
                'description' => $request['description'],
                'public' => $request['public'],
                'adm_employee_id' => $request['adm_employee_id']
            ]);

            if ( $request->file('file') ) {
                $mandatoryAttachment = $request->file('file');
                $mandatoryAttachmentName = $mandatoryAttachment->getClientOriginalName();
                $mandatoryAttachmentExtn = $mandatoryAttachment->getClientOriginalExtension();
                $mandatoryAttachmentRoute = 'public/directory/directories/'.$directory->id;
                $fileName   = strtolower($directory->id.'_'.$mandatoryAttachmentName.'.'.$mandatoryAttachmentExtn);
                Storage::putFileAs($mandatoryAttachmentRoute, $mandatoryAttachment, $fileName);
                $gralFile = GralFile::updateOrCreate(
                [ 'id'=>$request['file_id'] ],
                [
                    'name' => $fileName,
                    'original_name' => $mandatoryAttachmentName,
                    'route' => $mandatoryAttachmentRoute
                ]);
                $directory->gral_file_id = $gralFile->id;
                $directory->save();
            }
        } else {
            $errors['message'] = $validator->errors();
            $httpCode = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'name' => [ 'required', 'string', 'max:255' ],
            'classification_name' => [ 'string', 'max:255' ],
            'description' => [ 'string' ],
            'public' => [ 'required', 'boolean' ],
            'gral_file_id' => [ 'integer', 'exists:gral_files,id'],
            'adm_employee_id' => ['required','integer','exists:adm_employees,id']
        ];
        
        $messages = [
            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe poseer máximo 255 caracteres',
            'classification_name.string' => 'Nombre de Clasificación debe ser una cadena de caracteres válida',
            'classification_name.max' => 'Nombre de Clasificación debe poseer máximo 255 caracteres',
            'description.string' => 'Descripción debe ser una cadena de caracteres válida',
            'public.required' => 'Publico debe ser ingresado',
            'public.boolean' => 'Público debe ser un valor booleano',
            'adm_employee_id.required' => 'Foranea de empleado debe ser ingresada',
            'adm_employee_id.integer' => 'Foranea de empleado debe ser un valor entero válido',
            'adm_employee_id.exists' => 'Foranea de empleado debe ser un valor existente',
            'gral_file_id.integer' => 'Foranea de documento debe ser un valor entero válido',
            'gral_file_id.exists' => 'Foranea de documento debe ser un valor existente',
        ];

        // if ( !$request['file_id'] ) {
        //     $rules['file'] = [ 'required' ];
        //     $messages['file.required'] = 'Archivo debe ser cargado';
        // }

        return Validator::make($request->all(),$rules,$messages);
    }
    
    public function show($id)
    {
        $directory = Directory::where('id',$id)->with(
            ['employee','file','contacts','classifications']
        )->first();
        return response()->json($directory, 200);
    }

    public function destroy($id)
    {
        $directory = Directory::find($id);
        if ($directory) {
            $directory->delete();
        }
        return response()->json($directory, 200);
    }

}