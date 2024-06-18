<?php

namespace App\Http\Controllers\API\Attendance;

use App\Http\Controllers\Controller;

use App\Models\Attendance\Holiday;
use App\Models\General\GralFile;


use Carbon\Carbon;
use Carbon\CarbonPeriod;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use Exception;
use Storage;



class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $holidays = Holiday::orderBy('date_start','desc')->with(['file'])->get();
        foreach ($holidays as $key => $holiday) {
            $file = $holiday->file;
            $image = null;
            try {
                $image = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path('app/'.$file->route.'/'.$file->name)));
            } catch (\Throwable $th) {}
            $holiday->file_image = $image;
        }
        
        return response()->json($holidays, 200);
    }

    public function indexBetweenDates($dateIni,$dateEnd)
    {
        $data = [];
        $code = 200;
        try {
            $data = Holiday::betweenDates($dateIni,$dateEnd)->get();
        } catch (Exception $e) {
            $data = json_decode([ 'message' => $e->getMessage(), 'line' => $e->getLine() ]);
            $code = 500;
        }
        return response()->json($data,$code);
    }

    public function datesWithHolidays($dateIni,$dateEnd)
    {
        $data = [];
        $code = 200;

        try {

            $dateIni = Carbon::parse($dateIni);
            $dateEnd = Carbon::parse($dateEnd);

            $period = CarbonPeriod::create($dateIni,$dateEnd);
            
            foreach ($period as $key => $value) {
                $date = $value->format('Y-m-d');
                $data[$key]['date'] = $date;
                $data[$key]['holidays'] = Holiday::actual($value)->get();
            }

        } catch (Exception $e) {
            $data = json_decode([ 'message' => $e->getMessage(), 'line' => $e->getLine() ]);
            $code = 500;
        }

        return response()->json($data,$code);
    }

    public function indexSimple()
    {
        try {
            $rules = [
                'perPage' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'max:250'],
                'orderBy' => ['nullable', Rule::in(['id', 'name', 'description', 'type', 'date_start', 'time_start', 'date_end', 'time_end', 'permanent'])],
                'orderDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'orderBy.in' => 'Valor de ordenamiento fuera de las opciones aceptables.',
                'orderDirection.in' => 'Valor de dirección de orden fuera de las opciones aceptables.',
            ];

            $request->validate($rules, $messages);

            $perPage = $request->query('perPage', 10);
            $search = $request->query('search', '');
            $orderBy = $request->query('orderBy', 'id');
            $orderDirection = $request->query('orderDirection', 'asc');

            $filterDateRegex = ['options' => ['regexp' => '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/']];

            if (filter_var($search, FILTER_VALIDATE_REGEXP, $filterDateRegex)) {
                $holidays = Holiday::whereBetween($search, ['att_holidays.date_start', 'att_holidays.date_end'])
                    ->orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            } else {
                $holidays = Holiday::where('att_holidays.name', 'like', '%' . $search . '%')
                    ->orWhere('att_holidays.description', 'like', '%' . $search . '%')
                    ->orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            }

            $response = $holidays->toArray();
            $response['search'] = $request->query('search', '');
            $response['orderBy'] = $orderBy;
            $response['orderDirection'] = $orderDirection;

            return response()->json($response, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store2(Request $request)
    {
        try {
            $rules = [
                'name' => ['required', 'max:250', Rule::unique('att_holidays', 'name')->where(function ($query) use ($request) {
                    $query->where('name', $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['nullable', 'max:1000'],
                'type' => ['required', 'integer', Rule::in([1, 2])],
                'date_start' => ['required', 'date', 'before:date_end', 'date_format:Y-m-d'],
                'time_start' => ['nullable', 'date', 'before:time_end', 'date_format:H:i:s'],
                'date_end' => ['nullable', 'date', 'after:date_start', 'date_format:Y-m-d'],
                'time_end' => ['nullable', 'date', 'after:time_start', 'date_format:H:i:s'],
                'permanent' => ['nullable', 'boolean'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre del Día Festivo.',
                'name.max' => 'Se ha excedido la longitud máxima del Nombre del Día Festivo.',
                'name.unique' => 'El Nombre del Día Festivo, ya está siendo utilizado.',
                'description.max' => 'Se ha excedido la longitud máxima de la Descripción del Día Festivo.',
                'type.required' => 'Falta el Tipo del Día Festivo.',
                'type.integer' => 'El formato del Tipo de Día Festivo es irreconocible.',
                'type.in' => 'El Tipo de Día Festivo está fuera del rango aceptable.',
                'date_start.required' => 'Falta la Fecha de Inicio.',
                'date_start.date' => 'La Fecha de Inicio es irreconocible.',
                'date_start.before' => 'La Fecha de Inicio es mayor que la Fecha de Finalización.',
                'date_start.date_format' => 'El Formato de la Fecha de Inicio es irreconocible.',
                'time_start.date' => 'La Hora de Inicio es irreconocible.',
                'time_start.before' => 'La Hora de Inicio es Mayor que la Hora de Finalización.',
                'time_start.date_format' => 'El Formato de la Hora de Inicio es irreconocible.',
                'date_end.date' => 'La Fecha de Finalización es irreconocible.',
                'date_end.after' => 'La Fecha de Finalización es Menor a la Fecha de Inicio.',
                'date_end.date_format' => 'El Formato de la Fecha de Finalización es irreconocible.',
                'time_end.date' => 'Falta la Hora de Finalización.',
                'time_end.after' => 'La Hora de Finalización es menor a la Hora de Inicio.',
                'time_end.date_format' => 'El Formato de la Hora de Finalización es irreconocible.',
                'permanent.boolean' => 'La Clasificación de si es Permanente o no es irreconocible.',
            ];

            $request->validate($rules, $messages);

            $newData = [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'type' => $request->type,
                'date_start' => $request->date_start,
                'time_start' => $request->time_start,
                'date_end' => $request->date_end ? $request->date_end : $request->date_start,
                'time_end' => $request->time_end,
                'permanent' => $request->permanent,
            ];

            $newHoliday = null;

            DB::transaction(function () use ($newData, &$newHoliday) {
                $newHoliday = Holiday::create($newData);
            });

            return response()->json($newHoliday, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
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
            
            $holiday = Holiday::updateOrCreate(
                [
                    'id'=>$request['id']
                ],
                [
                    'name'=>$request['name'],
                    'description'=>$request['description'],
                    'type'=>$request['type'],
                    'date_start'=>$request['date_start'],
                    'date_end'=>$request['date_end'],
                    'time_start'=>$request['time_start'],
                    'time_end'=>$request['time_end'],

                    'permanent'=>$request['permanent'],
                    'vacation'=>$request['vacation'],
                ]
            );

            if ( $request->file('file') ) {
                $mandatoryAttachment = $request->file('file');
                $mandatoryAttachmentName = $mandatoryAttachment->getClientOriginalName();
                $mandatoryAttachmentExtn = $mandatoryAttachment->getClientOriginalExtension();
                $mandatoryAttachmentRoute = 'public/attendances/holidays/'.$holiday->id;
                $fileName   = strtolower($holiday->id.'_'.$mandatoryAttachmentName.'.'.$mandatoryAttachmentExtn);
                Storage::putFileAs($mandatoryAttachmentRoute, $mandatoryAttachment, $fileName);
                $gralFile = GralFile::updateOrCreate(
                    [ 'id'=>$request['file_id'] ],
                    [
                        'name' => $fileName,
                        'original_name' => $mandatoryAttachmentName,
                        'route' => $mandatoryAttachmentRoute
                    ]
                );
                $holiday->gral_file_id = $gralFile->id;
                $holiday->save();
            }

        } else {
            $errors['message']     = $validator->errors();
            $httpCode   = 400;
            $response = $errors;
        }
        return response()->json($response, $httpCode);

    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_holidays,id']],
                [
                    'id.required' => 'Falta identificador de Tipo de Permiso.',
                    'id.integer' => 'Identificador de Tipo de Permiso irreconocible.',
                    'id.exists' => 'Tipo de Permiso solicitado sin coincidencia.',
                ]
            )->validate();

            $holiday = Holiday::findOrFail($validatedData['id']);

            return response()->json($holiday, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_holidays,id']],
                [
                    'id.required' => 'Falta identificador de Tipo de Permiso.',
                    'id.integer' => 'Identificador de Tipo de Permiso irreconocible.',
                    'id.exists' => 'Tipo de Permiso solicitado sin coincidencia.',
                ]
            )->validate();

            $rules = [
                'name' => ['required', 'max:250', Rule::unique('att_holidays', 'name')->where(function ($query) use ($request) {
                    $query->where('name', $request->input('name'))->whereNull('deleted_at');
                })],
                'description' => ['nullable', 'max:1000'],
                'type' => ['required', 'integer', Rule::in([1, 2])],
                'date_start' => ['required', 'date', 'before:date_end', 'date_format:Y-m-d'],
                'time_start' => ['nullable', 'date', 'before:time_end', 'date_format:H:i:s'],
                'date_end' => ['nullable', 'date', 'after:date_start', 'date_format:Y-m-d'],
                'time_end' => ['nullable', 'date', 'after:time_start', 'date_format:H:i:s'],
                'permanent' => ['nullable', 'boolean'],
            ];

            $messages = [
                'name.required' => 'Falta el Nombre del Día Festivo.',
                'name.max' => 'Se ha excedido la longitud máxima del Nombre del Día Festivo.',
                'name.unique' => 'El Nombre del Día Festivo, ya está siendo utilizado.',
                'description.max' => 'Se ha excedido la longitud máxima de la Descripción del Día Festivo.',
                'type.required' => 'Falta el Tipo del Día Festivo.',
                'type.integer' => 'El formato del Tipo de Día Festivo es irreconocible.',
                'type.in' => 'El Tipo de Día Festivo está fuera del rango aceptable.',
                'date_start.required' => 'Falta la Fecha de Inicio.',
                'date_start.date' => 'La Fecha de Inicio es irreconocible.',
                'date_start.before' => 'La Fecha de Inicio es mayor que la Fecha de Finalización.',
                'date_start.date_format' => 'El Formato de la Fecha de Inicio es irreconocible.',
                'time_start.date' => 'La Hora de Inicio es irreconocible.',
                'time_start.before' => 'La Hora de Inicio es Mayor que la Hora de Finalización.',
                'time_start.date_format' => 'El Formato de la Hora de Inicio es irreconocible.',
                'date_end.date' => 'La Fecha de Finalización es irreconocible.',
                'date_end.after' => 'La Fecha de Finalización es Menor a la Fecha de Inicio.',
                'date_end.date_format' => 'El Formato de la Fecha de Finalización es irreconocible.',
                'time_end.date' => 'Falta la Hora de Finalización.',
                'time_end.after' => 'La Hora de Finalización es menor a la Hora de Inicio.',
                'time_end.date_format' => 'El Formato de la Hora de Finalización es irreconocible.',
                'permanent.boolean' => 'La Clasificación de si es Permanente o no es irreconocible.',
            ];

            $request->validate($rules, $messages);

            $updateData = [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'type' => $request->type,
                'date_start' => $request->date_start,
                'time_start' => $request->time_start,
                'date_end' => $request->date_end ? $request->date_end : $request->date_start,
                'time_end' => $request->time_end,
                'permanent' => $request->permanent,
            ];

            $holidayUpdated = null;

            DB::transaction(function () use ($validatedData, $updateData, &$holidayUpdated) {
                $holidayUpdated = Holiday::findOrFail($validatedData['id']);

                $holidayUpdated->update($updateData);
            });

            return response()->json($holidayUpdated, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: id: ' . json_encode($id) . ' | Request: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:att_holidays,id']],
                [
                    'id.required' => 'Falta identificador de Día Festivo.',
                    'id.integer' => 'Identificador de Día Festivo irreconocible.',
                    'id.exists' => 'Día Festivo solicitado sin coincidencia.',
                ]
            )->validate();

            $holiday = null;

            DB::transaction(function () use ($validatedData, &$holiday) {
                $holiday = Holiday::findOrFail($validatedData['id']);
                $holiday->delete();
                $holiday['status'] = 'deleted';
            });

            return response()->json($holiday, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function storeValidator(Request $request)
    {
        $rules = [
            'name' => [ 'required', 'string', 'max:255' ],
            'description' => [ 'string' ],
            'type' => ['required','integer'],
            'date_start' => ['required','date_format:Y-m-d'],
            'date_end' => ['date_format:Y-m-d','after_or_equal:date_start'],

            'time_start' => ['date_format:H:i'],
            'time_end' => ['date_format:H:i'],

            'permanent' => ['required','boolean'],
            'vacation' => ['required','boolean'],

            'gral_file_id' => [ 'integer','exists:gral_files,id']
        ];
        
        $messages = [
            'name.required' => 'Nombre debe ser ingresado',
            'name.string' => 'Nombre debe ser una cadena de caracteres válida',
            'name.max' => 'Nombre debe poseer máximo 255 caracteres',

            'description.string' => 'Descripción debe ser una cadena de caracteres válida',

            'type.required' => 'Tipo debe ser ingresado',
            'type.string' => 'Tipo debe ser un valor entero válido',

            'date_start.required' => 'Fecha inicial debe ser ingresada',
            'date_start.date_format' => 'Fecha inicial debe cumplir con el formato Y-m-d',

            'date_end.date_format' => 'Fecha final debe cumplir con el formato Y-m-d',
            'date_end.after_or_equal' => 'Fecha final debe ser mayor a fecha inicial',

            'time_start.date_format' => 'Hora inicial debe cumplir con el formato H:i',
            'time_end.date_format' => 'Hora final debe cumplir con el formato H:i',

            'permanent.required' => 'Permanente debe ser ingresado',
            'permanent.boolean' => 'Permanente debe ser un valor booleano',

            'vacation.required' => 'Vacación debe ser ingresado',
            'vacation.boolean' => 'Vacación debe ser un valor booleano',

            
            'gral_file_id.integer' => 'Foranea de documento debe ser un valor entero válido',
            'gral_file_id.exists' => 'Foranea de documento debe ser un valor existente',
        ];

        return Validator::make($request->all(),$rules,$messages);
    }
}
