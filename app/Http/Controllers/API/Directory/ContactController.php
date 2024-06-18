<?php

namespace App\Http\Controllers\API\Directory;

use App\Http\Controllers\Controller;

use App\Models\Directory\Contact;
use App\Models\General\GralFile;

use Illuminate\Http\Request;

use Auth;
use Storage;
use Validator;

class ContactController extends Controller
{

    public function index()
    {
        $contacts = Contact::all();
        return response()->json($contacts,200);
    }

    public function indexByDirectoryWithImage($directoryId)
    {
        $data = [];
        $contacts = Contact::where([ 'dir_directory_id'=>$directoryId ])->orderBy('name')->get();
        foreach ($contacts as $key => $contact) {
            $file = $contact->file;
            $image = null;
            try {
                $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
            } catch (\Throwable $th) {}
            $contact->file_image = $image;
        }
        return response()->json($contacts, 200);
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
            $contact = Contact::updateOrCreate(
            [ 'id' => $request['id'] ],
            [
                'name' => $request['name'],
                'lastname' => $request['lastname'],
                'email' => $request['email'],
                'phone' => $request['phone'],
                'mobile' => $request['mobile'],
                'description' => $request['description'],
                'notes' => $request['notes'],
                'active' => $request['active'],
                'dir_directory_id' => $request['dir_directory_id'],
            ]);

            if ( $request->file('file') ) {
                $mandatoryAttachment = $request->file('file');
                $mandatoryAttachmentName = $mandatoryAttachment->getClientOriginalName();
                $mandatoryAttachmentExtn = $mandatoryAttachment->getClientOriginalExtension();
                $mandatoryAttachmentRoute = 'public/directory/directories/'.$contact->dir_directory_id.'/contacts/'.$contact->id;
                $fileName   = strtolower($contact->id.'_'.$mandatoryAttachmentName.'.'.$mandatoryAttachmentExtn);
                Storage::putFileAs($mandatoryAttachmentRoute, $mandatoryAttachment, $fileName);
                $gralFile = GralFile::updateOrCreate(
                [ 'id'=>$request['file_id'] ],
                [
                    'name' => $fileName,
                    'original_name' => $mandatoryAttachmentName,
                    'route' => $mandatoryAttachmentRoute
                ]);
                $contact->gral_file_id = $gralFile->id;
                $contact->save();
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
            'lastname' => [ 'string', 'max:255' ],
            'email' => [ 'string', 'max:255' ],
            'phone' => [ 'max:255' ],
            'mobile' => [ 'max:255' ],
            // 'description' => [ 'string' ],
            // 'notes' => [ 'string' ],
            'active' => [ 'boolean' ],
            'gral_file_id' => [ 'integer', 'exists:gral_files,id'],
            'adm_address_id' => [ 'integer', 'exists:adm_addresses,id'],
            'dir_directory_id' => [ 'required', 'integer', 'exists:dir_directories,id']
        ];
        
        $messages = [
            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe poseer máximo 255 caracteres',
            'lastname.string' => 'Apellido debe ser una cadena de caracteres válida',
            'lastname.max' => 'Apellido debe poseer máximo 255 caracteres',
            'email.string' => 'Correo Electrónico debe ser una cadena de caracteres válida',
            'email.max' => 'Correo Electrónico debe poseer máximo 255 caracteres',
            'phone.max' => 'Teléfono debe poseer máximo 255 caracteres',
            'mobile.max' => 'Teléfono Móvil debe poseer máximo 255 caracteres',
            // 'description.string' => 'Descripción debe ser una cadena de caracteres válida',
            // 'notes.string' => 'Notas debe ser una cadena de caracteres válida',
            'active.boolean' => 'Activo debe ser un valor booleano',
            'gral_file_id.integer' => 'Foranea de documento debe ser un valor entero válido',
            'gral_file_id.exists' => 'Foranea de documento debe ser un valor existente',
            'adm_address_id.integer' => 'Foranea de dirección debe ser un valor entero válido',
            'adm_address_id.exists' => 'Foranea de dirección debe ser un valor existente',
            'dir_directory_id.required' => 'Foranea de directorio debe ser ingresada',
            'dir_directory_id.integer' => 'Foranea de directorio debe ser un valor entero válido',
            'dir_directory_id.exists' => 'Foranea de directorio debe ser un valor existente'
        ];
        return Validator::make($request->all(),$rules,$messages);
    }

    public function show($id)
    {
        $contact = Contact::where('id',$id)->first();
        $file = $contact->file;
        $image = null;
        try {
            $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
        } catch (\Throwable $th) {}
        $contact->file_image = $image;
        return response()->json($contact, 200);
    }
    
    public function destroy($id)
    {
        $contact = Contact::find($id);
        if ($contact) {
            $contact->delete();
        }
        return response()->json($contact, 200);
    }
    
}