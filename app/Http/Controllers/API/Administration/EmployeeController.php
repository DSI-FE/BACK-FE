<?php
namespace App\Http\Controllers\API\Administration;

use App\Helpers\ImageProcessing;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

use App\Helpers\StringsHelper;
use App\Helpers\PaginationHelper;
use App\Jobs\SendNewEmployeeNotificationJob;
use App\Jobs\SendUnsubscribeEmployeeEmailJob;
use App\Models\Administration\Address;
use App\Models\Administration\DocumentType;
use App\Models\Administration\Employee;
use App\Models\Administration\EmployeeRequest;
use App\Models\Administration\FunctionalPosition;
use App\Models\Attendance\PermissionType;
use App\Models\Attendance\Schedule;
use App\Models\General\GralConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $employees = [];

            $rules = [
                'search' => ['nullable', 'max:250'],
                'perPage' => ['nullable', 'integer', 'min:1'],
                'sort' => ['nullable'],
                'sort.order' => ['nullable', Rule::in(['id', 'name', 'lastname', 'email'])],
                'sort.key' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'sort.order.in' => 'El valor de ordenamiento es inválido.',
                'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
            ];

            $request->validate($rules, $messages);

            $search = StringsHelper::normalizarTexto($request->query('search', ''));
            $perPage = $request->query('perPage', 10);

            $sort = json_decode($request->input('sort'), true);
            $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
            $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

            $employees = Employee::with([
                'functionalPositions' => function ($query) {
                    $query->select('adm_functional_positions.id', 'adm_functional_positions.name', 'adm_functional_positions.adm_organizational_unit_id')
                        ->leftJoin('adm_organizational_units', 'adm_organizational_units.id', '=', 'adm_functional_positions.adm_organizational_unit_id')
                        ->select('adm_functional_positions.id', 'adm_functional_positions.name', 'adm_organizational_units.id as unit_id', 'adm_organizational_units.name as unit_name', 'adm_employee_adm_functional_position.date_start', 'adm_employee_adm_functional_position.date_end');
                },
                'user:id,name,lastname,email,status',
                'gender:id,name',
                'maritalStatus:id,name'
            ])
            ->select('adm_employees.id', 'adm_employees.name', 'adm_employees.lastname', 'adm_employees.email', 'adm_employees.phone', 'adm_employees.photo_route', 'adm_employees.photo_route_sm', 'adm_employees.user_id', 'adm_employees.adm_gender_id', 'adm_employees.adm_marital_status_id')
            ->where(function (Builder $query) use ($search) {
                return $query->where('adm_employees.name', 'like', '%' . $search . '%')
                    ->orWhere('adm_employees.lastname', 'like', '%' . $search . '%')
                    ->orWhere('adm_employees.email', 'like', '%' . $search . '%')
                    ->orWhere('adm_employees.email_personal', 'like', '%' . $search . '%');
            })
            ->where('status', 1)
            ->orderBy($orderBy, $orderDirection)
            ->paginate($perPage);

            foreach ($employees as $idx => $employee) {
                if ($employee->photo_route_sm && file_exists(storage_path($employee->photo_route))) {
                    $employee->photo_route_sm = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                } else {
                    $employee->photo_route_sm = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path('app/public/nopic.jpg')));
                }

                $employees[$idx]['request_resume'] = DB::table('adm_requests')
                    ->select('adm_request_type_id', 'status', DB::raw('count(*) as total'))
                    ->where('employee_id_affected', $employee->id)
                    ->groupBy('adm_request_type_id', 'status')
                    ->get();
            }

            $response = $employees->toArray();
            $response['search'] = $request->query('search', '');
            $response['sort'] = [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
        }
    }

    public function indexActive()
    {
        try{
            $employeesRaw = Employee::where('active',1)->get();
            return response()->json($employeesRaw, 200);
        }
        catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id );
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number: '.$e->getLine()], 500);
        }
    }

    public function indexDirectory(Request $request)
    {
        try {
            $perPage = $request->query('paginate');

            $requestSort = json_decode($request->sort);
            $sortOrder = $requestSort && $requestSort->order != '' ? $requestSort->order : 'asc';
            $sortKey = $requestSort && $requestSort->key && $requestSort->key ? $requestSort->key : 'name';

            $search = htmlentities(StringsHelper::normalizarTexto($request->query('search', '')), ENT_QUOTES);
            $organizationalUnitId = $request->query('organizationalUnitId') ? intval($request->query('organizationalUnitId')) : null;

            $employeesRaw = Employee::activesWithPrincipalFunctionalPositions(Carbon::now())->get();

            $employees = collect();

            foreach ($employeesRaw as $key => $employee) {

                $functionalPosition = $employee->functionalPositions->first();
                $organizationalUnit = $functionalPosition ? $functionalPosition->organizationalUnit : null;
                $photo = null;

                if(!$organizationalUnitId || ($organizationalUnitId && intval($organizationalUnitId) == $organizationalUnit->id)) {

                    try {
                        $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                    } catch (\Throwable $th) {}

                    $employeeArr = [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'lastname' => $employee->lastname,
                        'photo' => $employee->photo,
                        'phone' => $employee->phone,
                        'email' => $employee->email,
                        'functional_position' => $functionalPosition ? $functionalPosition->name : null,
                        'organizational_unit' => $organizationalUnit ? $organizationalUnit->name : null,
                        'organizational_unit_id' => $organizationalUnit ? $organizationalUnit->id : null
                    ];

                    if ($search) {
                        $itemTemp = $employeeArr;
                        $emp = StringsHelper::normalizarTexto( implode(' ',$itemTemp));
                        $sea = StringsHelper::normalizarTexto($search);
                        if (str_contains($emp,$sea)) {
                            $employeeArr['photo'] = $photo;
                            $employees->push($employeeArr);
                        }
                    } else {
                        $employeeArr['photo'] = $photo;
                        $employees->push($employeeArr);
                    }
                }
            }
            $employees = $employees->sortBy([[$sortKey,$sortOrder]]);
            $response = PaginationHelper::paginate($employees,$perPage);
            return response()->json($response, 200);
        }
        catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number: '.$e->getLine()], 500);
        }
    }

    public function indexMarkingRequired()
    {
        try{
            return response()->json(Employee::activeMarkingRequired()->get(), 200);
        }
        catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id );
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.','errors'=>$e->getMessage().' Line Number: '.$e->getLine()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_employees,id']],
                [
                    'id.required' => 'Falta identificador de Colaborador.',
                    'id.integer' => 'Identificador de Colaborador irreconocible.',
                    'id.exists' => 'Colaborador solicitado sin coincidencia.',
                ]
            )->validate();

            $employee = Employee::with([
                'user',
                'gender',
                'maritalStatus',
                'address.municipality.department',
                'functionalPositions' => function ($query) {
                    $query->with('organizationalUnit');
                },
                'schedules',
                'employeeRequests',
                'documents'
            ])->findOrFail($validatedData['id']);

            if ($employee->photo_route_sm && file_exists(storage_path($employee->photo_route_sm))) {
                $employee->photo_route_sm = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
            } else {
                $employee->photo_route_sm = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path('app/public/nopic.jpg')));
            }

            if ($employee->photo_route && file_exists(storage_path($employee->photo_route))) {
                $employee->photo_route = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path($employee->photo_route)));
            } else {
                $employee->photo_route = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path('app/public/nopic.jpg')));
            }

            // $employee->makeHidden(['photo_route']);

            return response()->json($employee, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id);

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.'], 500);
        }
    }

    public function store(Request $request){
        try{
            $rules = [
                'adm_municipality_id' => ['required', 'integer', 'exists:adm_municipalities,id'],
                'urbanization' => ['required', 'max:250'],
                'street' => ['required', 'max:250'],
                'number' => ['required', 'max:250'],
                'complement' => ['nullable', 'max:1000'],

                'dui' => ['required', 'regex:/^\d{8}-\d$/'/*, 'unique:adm_document_type_adm_employee,value'*/],
                'nit' => ['required', 'regex:/^\d{8}-\d$|^\d{4}-\d{6}-\d{3}-\d$/'/*, 'unique:adm_document_type_adm_employee,value'*/],
                /** -> */'nup' => ['nullable', /*'regex:/^d{1-12}$/',*/ 'unique:adm_document_type_adm_employee,value'],
                /** -> */'isss' => ['nullable', /*'regex:/^d{1-9}$/',*/ 'unique:adm_document_type_adm_employee,value'],
                /** -> */'mh' => ['nullable'/*, 'regex:/^[a-zA-Z0-9]{1,14}$/', 'unique:adm_document_type_adm_employee,value'*/],
                /** -> */'dsi' => ['nullable', /*'regex:/^d{1-7}$/',*/ 'unique:adm_document_type_adm_employee,value'],

                'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],

                'name' => ['required'],
                'lastname' => ['required'],
                'phone_personal' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
                'email_personal' => ['nullable', 'email:dns'],
                'birthday' => ['required', 'date_format:Y-m-d'],
                'marking_required' => ['required', 'in:true,false'],
                'adm_gender_id' => ['required', 'integer', 'exists:adm_genders,id'],
                'adm_marital_status_id' => ['required', 'integer', 'exists:adm_marital_statuses,id'],
                'children' => ['required', 'in:true,false'],
                'external' => ['required', 'in:true,false'],
                'viatic' => ['required', 'in:true,false'],

                /** -> */'vehicle' => ['required', 'in:true,false'],
                /** -> */'adhonorem' => ['required', 'in:true,false'],
                /** -> */'parking' => ['required', 'in:true,false'],
                /** -> */'disabled' => ['required', /*'in:true, false'*/],

                'date_start' => ['required', 'date_format:Y-m-d'],
                'salary' => ['nullable', 'numeric'],
                'adm_functional_position_id' => ['required', 'integer', 'exists:adm_functional_positions,id'],

                'att_schedule_id' => ['required', 'integer', 'exists:att_schedules,id'],

                'applicant' => ['required', 'integer', 'exists:adm_employees,id'],
                'desktop' => ['required', 'in:true,false'],
                'portable' => ['required', 'in:true,false'],
                'mobile' => ['required', 'in:true,false'],
                'requestEmail' => ['required', 'in:true,false'],
                'requestIpPhone' => ['required', 'in:true,false'],

                /** */'car_make_id' => ['nullable', 'integer', 'exists:car_makes,id', 'required_with_all:model,year,license_plate'],
                /** */'car_model_id' => ['nullable', 'integer', 'exists:car_models,id', 'required_with_all:make_id,year,license_plate'],
                /** */'year' => ['nullable', 'regex:/^(19|20)\d{2}$/', 'required_with_all:make_id,model,license_plate'],
                /** */'color' => ['nullable', 'max_250'],
                /** */'license_plate' => ['nullable', 'string', 'alpha_num', 'min:1', 'max:8', 'unique:adm_employee_vehicles', 'required_with_all:brand,model,year'],
            ];

            $messages = [
                'adm_municipality_id.required' => 'El :attribute, es requerido.',
                'adm_municipality_id.integer' => 'El :attribute, enviado es irreconocible.',
                'adm_municipality_id.exists' => 'Ningún registro corresponde al :attribute enviado.',
                'urbanization.required' => 'El :attribute, es requerido.',
                'urbanization.max' => 'El :attribute, excede la longitud máxima permitida.',
                'street.required' => 'El :attribute, es requerido.',
                'street.max' => 'El :attribute, excede la longitud máxima permitida.',
                'number.required' => 'El :attribute, es requerido.',
                'number.max' => 'El :attribute, excede la longitud máxima permitida.',
                'complement.max' => 'El :attribute, excede la longitud máxima permitida.',

                'dui.required' => 'El :attribute, es requerido.',
                /** */'regex' => 'El Formato enviado para :attribute, es irreconocible.',
                /** */'unique' => 'El :attribute ya está asignado a un registro.',

                'photo.image' => 'La :attribute es irreconocible.',
                'photo.mimes' => 'La :attribute sin correspondencia con los tipos permitidos.',
                'photo.max' => 'El :attribute, excede la longitud máxima permitida.',

                'name.required' => 'El :attribute, es requerido.',
                'lastname.required' => 'El :attribute, es requerido.',
                'phone_personal.regex' => 'El Formato enviado para :attribute, es irreconocible.',
                'email_personal.email' => 'El formato de :attribute, es irreconocible.',
                'birthday.required' => 'El :attribute, es requerido.',
                'birthday.date_format' => 'El formato de :attribute, es irreconocible.',
                'marking_required.required' => 'El :attribute, es requerido.',
                'marking_required.in' => 'El :attribute, fuera de los parámetros esperados.',
                'adm_gender_id.required' => 'El :attribute, es requerido.',
                'adm_gender_id.integer' => 'El formato de :attribute, es irreconocible.',
                'adm_gender_id.exists' => 'Ningún registro corresponde al :attribute enviado.',
                'adm_marital_status_id.required' => 'El :attribute, es requerido.',
                'adm_marital_status_id.integer' => 'El formato de :attribute, es irreconocible.',
                'adm_marital_status_id.exists' => 'Ningún registro corresponde al :attribute enviado.',
                'children.required' => 'El :attribute, es requerido.',
                'children.in' => 'El :attribute, fuera de los parámetros esperados.',
                'external.required' => 'El :attribute, es requerido.',
                'external.in' => 'El :attribute, fuera de los parámetros esperados.',
                'viatic.required' => 'El :attribute, es requerido.',
                'viatic.in' => 'El :attribute, fuera de los parámetros esperados.',

                /** */'required' => 'Falta el :attribute.',
                /** */'in' => 'El :attribute, está fuera de los parámetros permitidos.',

                'date_start.required' => 'El :attribute, es requerido.',
                'date_start.date_format' => 'El formato de :attribute, es irreconocible.',
                'salary.numeric' => 'El formato de :attribute, es irreconocible.',
                'adm_functional_position_id.required' => 'El :attribute, es requerido.',
                'adm_functional_position_id.integer' => 'El formato de :attribute, es irreconocible.',
                'adm_functional_position_id.exists' => 'Ningún registro corresponde al :attribute enviado.',

                'att_schedule_id.required' => 'El :attribute, es requerido.',
                'att_schedule_id.integer' => 'El formato de :attribute, es irreconocible.',
                'att_schedule_id.exists' => 'Ningún registro corresponde al :attribute enviado.',

                'applicant.required' => 'El :attribute, es requerido.',
                'applicant.integer' => 'El formato de :attribute, es irreconocible.',
                'applicant.exists' => 'Ningún registro corresponde al :attribute enviado.',
                'desktop.required' => 'El :attribute, es requerido.',
                'desktop.in' => 'El :attribute, fuera de los parámetros esperados.',
                'portable.required' => 'El :attribute, es requerido.',
                'portable.in' => 'El :attribute, fuera de los parámetros esperados.',
                'mobile.required' => 'El :attribute, es requerido.',
                'mobile.in' => 'El :attribute, fuera de los parámetros esperados.',
                'requestEmail.required' => 'El :attribute, es requerido.',
                'requestEmail.in' => 'El :attribute, fuera de los parámetros esperados.',
                'requestIpPhone.required' => 'El :attribute, es requerido.',
                'requestIpPhone.in' => 'El :attribute, fuera de los parámetros esperados.',

                /** */'exists' => 'Ningún registro corresponde al :attribute enviado.',
                /** */'max' => ':attribute ha excedido la longitud máxima asignada.',
                /** */'required_with_all' => 'Falta enviar :attribute, para la información del vehículo.',
                /** */'regex' => 'El formato d:attribute, es irreconocible.',
                /** */'string' => 'El formato d:attribute, es irreconocible.',
                /** */'license_plate.alpha_num' => 'en :attribute solo se pueden enviar caracteres alfa numéricos.',
                /** */'license_plate.min' => ':attribute debe tener la longitud mínima de al menos :min carácter.',
                /** */'license_plate.unique' => ':attribute enviado, ya está asignado a otro vehículo.',
            ];

            $attributes = [
                'employee_id' => 'Identificador de Colaborador',
                'name' => 'Nombres',
                'lastname' => 'Apellidos',
                'phone' => 'Teléfono',
                'email' => 'Email',
                'phone_personal' => 'Teléfono Personal',
                'email_personal' => 'Email Personal',
                'birthday' => 'Fecha de Nacimiento',
                'photo' => 'Imagen',
                'marking_required' => 'Requiere Marcación',
                'adm_gender_id' => 'Identificador de Género',
                'adm_marital_status_id' => 'Identificador de Estado Familiar',
                'external' => 'Colaborador externo/interno',
                'viatic' => 'Derecho a Viáticos',
                'children' => 'Hijos',
                'dui' => 'DUI',
                'nit' => 'NIT',
                /** */'nup' => 'NUP',
                /** */'isss' => 'ISSS',
                /** */'mh' => 'código de Ministerio de Hacienda',
                /** */'dsi' => 'código dsi',
                'adm_municipality_id' => 'Identificador de Municipio',
                'urbanization' => 'Urbanización / Residencial / Colonia',
                'street' => 'Calle / Polígono / Pasaje',
                'number' => '# de Casa',
                'complement' => 'Observaciones y/o notas',
                'adm_functional_position_id' => 'Cargo',
                'salary' => 'Salario',
                /** */'vehicle' => 'requiere vehículo',
                /** */'adhonorem' => 'Ad Honorem',
                /** */'parking' => 'require parqueo',
                /** */'disabled' => 'Capacidades Especiales',
                'date_start' => 'Fecha de Ingreso',
                'date_end' => 'Fecha Final',
                'att_schedule_id' => 'Identificador de Horario',
                /** */'car_make_id' => 'la Marca',
                /** */'car_model_id' => 'el Modelo',
                /** */'year' => 'el Año',
                /** */'color' => 'el Color',
                /** */'license_plate' => 'el Número de Placa'
            ];

            $request->validate($rules, $messages, $attributes);

            $newEmployee = [];

            DB::transaction(function () use ($request, &$newEmployee) {
                //crear dirección
                $newAddress = Address::create([
                    'name' => 'principal',
                    'urbanization' => $request->urbanization,
                    'street' => $request->street,
                    'number' => $request->number,
                    'complement' => $request->complement ? $request->complement : null,
                    'adm_municipality_id' => $request->adm_municipality_id,
                ]);

                $photoSaved = [];

                // si trae imagen
                if ($request->photo) {
                    $photoSaved = ImageProcessing::employeeImage($request->photo, str_replace('-', '', $request->dui));
                }

                $newEmployeeData = [
                    'name' => $request->name,
                    'lastname' => $request->lastname,
                    'email' => $request->email_personal,
                    'email_personal' => $request->email_personal,
                    'phone' => $request->phone_personal,
                    'phone_personal' => $request->phone_personal,
                    // 'photo_name' => $photoSaved['photo_name'],
                    // 'photo_route' => $photoSaved['photo_route'],
                    // 'photo_route_sm' => $photoSaved['photo_route_sm'],
                    'birthday' => $request->birthday,
                    'marking_required' => $request->marking_required == 'true' ? true : false,
                    'status' => true,
                    'active' => true,
                    'adm_gender_id' => $request->adm_gender_id,
                    'adm_marital_status_id' => $request->adm_marital_status_id,
                    'adm_address_id' => $newAddress->id,
                    'remote_mark' => false,
                    'external' => $request->external == 'true' ? true : false,
                    'viatic' => $request->viatic == 'true' ? true : false,
                    /** */'vehicle' => $request->vehicle == 'true' ? true : false,
                    /** */'adhonorem' => $request->adhonorem == 'true' ? true : false,
                    /** */'parking' => $request->parking == 'true' ? true : false,
                    /** */'disabled' => $request->disabled == 'true' ? true : false,
                    'children' => $request->children == 'true' ? true : false,
                ];

                // crear Colaborador
                $newEmployee = Employee::create($newEmployeeData);

                /** Si vehiculo */
                /*
                if ($request->has('vehicle') && $request->vehicle == 'true') {
                    $newEmployee->vehicles()->create([
                        'brand' => $request->vehicle_brand,
                        'model' => $request->vehicle_model,
                        'year' => $request->vehicle_year,
                        'license_plate' => $request->vehicle_license_plate,
                        'color' => $request->color,
                    ]);
                }
                */

                //crear documentos DUI = 1 y NIT = 2 obligatorios, el resto es opcional
                $documentTypeDui = DocumentType::findOrFail(1);
                $newEmployee->documents()->attach($documentTypeDui, ['value' => $request->dui]);

                $documentTypeNit = DocumentType::findOrFail(2);
                $newEmployee->documents()->attach($documentTypeNit, ['value' => $request->nit]);

                if ($request->has('nup') && !empty($request->nup)) {
                    $documentTypeNup = DocumentType::findOrFail(3);
                    $newEmployee->documents()->attach($documentTypeNup, ['value' => $request->nup]);
                }

                if ($request->has('isss') && !empty($request->isss)) {
                    $doucmentTypeIsss = DocumentType::findOrFail(4);
                    $newEmployee->documents()->attach($doucmentTypeIsss, ['value' => $request->isss]);
                }

                if ($request->has('mh') && !empty($request->mh)) {
                    $documentTypeMh = DocumentType::findOrFail(6);
                    $newEmployee->documents()->attach($documentTypeMh, ['value' => $request->mh]);
                }

                if ($request->has('dsi') && !empty($request->dsi)) {
                    $documentTypedsi = DocumentType::findOrFail(7);
                    $newEmployee->documents()->attach($documentTypedsi, ['value' => $request->dsi]);
                }

                // crear relación de Colaborador con functional position
                $functionalPosition = FunctionalPosition::findOrFail($request->adm_functional_position_id);
                $newEmployee->functionalPositions()->attach($functionalPosition, [
                    'date_start' => $request->date_start,
                    'date_end' => null,
                    'principal' => 1,
                    'salary' => $request->salary ? $request->salary : 0,
                    'active' => true,
                ]);

                // crear relación de Colaborador con schedule
                $schedule = Schedule::findOrFail($request->att_schedule_id);
                $newEmployee->schedules()->attach($schedule, [
                    'date_start' => $request->date_start,
                    'date_end' => null,
                    'active' => true,
                ]);

                // crear solicitud de usuario
                $newEmployeeRequest = EmployeeRequest::create([
                    'user_id' => Auth::user()->id,
                    'employee_id_applicant' => $request->applicant,
                    'employee_id_affected' => $newEmployee->id,
                    'employee_id_authorizing' => 78, // 78 = Juan Reina
                    'adm_request_type_id' => 1, // Solicitud de Creación de Usuario
                    'status' => 1,
                ]);

                $newAccessCardRequest = EmployeeRequest::create([
                    'user_id' => Auth::user()->id,
                    'employee_id_applicant' => $request->applicant,
                    'employee_id_affected' => $newEmployee->id,
                    'employee_id_authorizing' => 2,
                    'adm_request_type_id' => 3,
                    'status' => 1,
                ]);

                $requestData = [
                    [
                        'adm_request_id' => $newEmployeeRequest->id,
                        'adm_request_type_element_id' => 1,
                        'value_boolean' => $request->desktop == 'true' ? true : false,
                        'value_string' => false,
                        'field_name' => null,
                    ],
                    [
                        'adm_request_id' => $newEmployeeRequest->id,
                        'adm_request_type_element_id' => 2,
                        'value_boolean' => $request->portable == 'true' ? true : false,
                        'value_string' => false,
                        'field_name' => null,
                    ],
                    [
                        'adm_request_id' => $newEmployeeRequest->id,
                        'adm_request_type_element_id' => 3,
                        'value_boolean' => $request->mobile == 'true' ? true : false,
                        'value_string' => true,
                        'field_name' => 'phone',
                    ],
                    [
                        'adm_request_id' => $newEmployeeRequest->id,
                        'adm_request_type_element_id' => 4,
                        'value_boolean' => $request->requestEmail == 'true' ? true : false,
                        'value_string' => true,
                        'field_name' => 'email',
                    ],
                    [
                        'adm_request_id' => $newEmployeeRequest->id,
                        'adm_request_type_element_id' => 5,
                        'value_boolean' => $request->requestIpPhone == 'true' ? true : false,
                        'value_string' => true,
                        'field_name' => 'ipPhone',
                    ],
                ];

                DB::table('adm_r_t_element_adm_request')->insert($requestData);

                $permissionTypes = PermissionType::all();

                $courtesyTime = GralConfiguration::where('identifier', 'minutos_cortesia_mensual')->first();

                for ($month = 1; $month <= 12; $month++) {
                    $newEmployee->permissionTypes()->attach($permissionTypes->pluck('id'), [
                        'used_hours' => 0,
                        'used_requests' => 0,
                        'month' => $month,
                    ]);

                    $newEmployee->courtesyTime()->create([
                        'month' => $month,
                        'available_minutes' => $courtesyTime->value ?? 0,
                    ]);
                }

                // Notificación que se ha creado un nuevo Colaborador
                // $notifyTo = [78, 186]; // 22 - René, 78 = Juan, 167 = Rodolfo, 186 = Jonathan
                // SendNewEmployeeNotificationJob::dispatch($notifyTo, $newEmployee->id);
            });

            return response()->json($newEmployee, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' | En línea ' . $e->getFile() . '-' . $e->getLine() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage().$e->getLine()], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $rules = [
                'request_id' => ['required', 'integer', 'exists:adm_requests,id'],
                'employee_id' => ['required', 'integer', 'exists:adm_employees,id'],
                'phone' => ['nullable'],
                'ipPhone' => ['nullable'],
                'email' => ['nullable', 'email:dns', Rule::unique('users', 'email')],
            ];

            $messages = [
                'request_id.required' => 'El Identificador de la Solicitud es requerido.',
                'request_id.integer' => 'El Formato del Identificador de la Solicitud es irreconocible.',
                'request_id.exists' => 'Sin concordancia con el Identificador de Solicitud enviado.',

                'employee_id.required' => 'El Identificador de Colaborador es requerido.',
                'employee_id.integer' => 'El Formato del Identificador de Colaborador es irreconocible.',
                'employee_id.exists' => 'Sin coincidencia con el Identificador de Colaborador enviado.',

                'email.unique' => 'El Correo Electrónico esta asignado a un usuario existente.',
                'email.email' => 'El Correo Electrónico enviado es inválido.',
            ];

            $request->validate($rules, $messages);

            if (($request->has('phone') && $request->phone === null) ||
                ($request->has('ipPhone') && $request->ipPhone === null) ||
                ($request->has('email') && $request->email === null)) {

                $required = [];

                if ($request->has('phone') && $request->phone === null) {
                    array_push($required, 'Teléfono Móvil');
                }

                if ($request->has('ipPhone') && $request->ipPhone === null) {
                    array_push($required, 'Teléfono IP');
                }

                if ($request->has('email') && $request->email === null) {
                    array_push($required, 'Correo Institucional');
                }

                throw new Exception('Falta información de ' . implode(', ', $required));
            } else {
                // throw new Exception('Al menos uno de los campos debe de ser enviado.');
            }

            $employee = [];

            DB::transaction(function () use ($request, &$employee) {
                $employee = Employee::findOrFail($request->employee_id);

                /** */
                if ($employee->user_id == NULL) {
                    $passwordDefault = GralConfiguration::where('identifier', 'password_default')->first();

                    if ($request->email) {
                        $email = $request->email;
                    } else {
                        $email = $employee->email;
                    }

                    $emailExploded = explode('@', $email);

                    $newUserData = [
                        'name' => $employee->name,
                        'lastname' => $employee->lastname,
                        'username' => $emailExploded[0],
                        'email' => $email,
                        'password' => Hash::make($passwordDefault->value ? $passwordDefault->value : 'dsi23'),
                        'change_password' => 0,
                        'status' => 1,
                    ];

                    $newUser = User::create($newUserData);
                    $employee->update([
                        'user_id' => $newUser->id,
                    ]);
                }
                /** */

                if ($request->has('phone') && $request->phone !== null && $request->has('ipPhone') && $request->ipPhone !== null) {
                    if ($request->has('phone') && $request->phone !== null && $request->has('ipPhone') && $request->ipPhone !== null) {
                        $phone = $request->ipPhone . ',' . $request->phone;
                    } elseif ($request->has('phone') && $request->phone !== null) {
                        $phone = $request->phone;
                    } elseif ($request->has('ipPhone') && $request->ipPhone !== null) {
                        $phone = $request->ipPhone;
                    } else {
                        $phone = null;
                    }

                    $employee->update([
                        'email' => $email,
                        'phone' => $phone,
                    ]);
                }

                $employee->employeeRequests()
                    ->where('id', $request->request_id)
                    ->update(['status' => 2]);
            });

            return response()->json($employee, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function activeEmployees()
    {
        $employees = Employee::select(['id', DB::raw("CONCAT(name, ' ', lastname) AS name")])
            ->where('active', true)
            ->get();

        return response()->json($employees, 200);
    }

    public function unsubscribeRequestEmployee(Request $request, int $id)
    {
        try {
            $validatedData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_employees,id']],
                [
                    'id.required' => 'Falta identificador de Colaborador.',
                    'id.integer' => 'Identificador de Colaborador irreconocible.',
                    'id.exists' => 'Colaborador solicitado sin coincidencia.',
                ]
            )->validate();

            $rules = [
                'employee_id' => ['required', 'numeric', 'exists:adm_employees,id'],
                'end_date' => ['required', 'date_format:Y-m-d'],
                'unsubscribe_justification' => ['required'],
            ];

            $messages = [
                'employee_id.required' => 'Falta el Identificador del Colaborador.',
                'employee_id.integer' => 'El formato del Identificador del Colaborador es irreconocible.',
                'employee_id.exists' => 'Sin concordancia entre el Identificador enviado y los Colaboradores.',

                'end_date.required' => '',
                'end_date.date_format' => '',

                'unsubscribe_justification.required' => '',
            ];

            if ($id !== $request->employee_id) { throw new Exception('Sin concordancia entre el Identificador del Colaborador y el Identificador solicitado.'); }

            $request->validate($rules, $messages);

            $employee_id_authorizing = 78;

            $employee = [];

            DB::transaction(function () use ($request, $employee_id_authorizing, &$employee) {
                $employee = Employee::findOrFail($request->employee_id);

                $newRequest = $employee->employeeRequests()->create([
                    'user_id' => Auth::user()->id,
                    'employee_id_applicant' => Auth::user()->employee->id ?? throw new Exception('Sin concordancia con el Identificador de Solicitante.'),
                    'employee_id_affected' => $request->employee_id,
                    'employee_id_authorizing' => $employee_id_authorizing,
                    'adm_request_type_id' => 2,
                    'status' => 1
                ]);

                $functionalPositions = $employee->functionalPositions;

                foreach ($functionalPositions as $functionalPosition) {
                    $functionalPosition->pivot->date_end = $request->end_date;
                    $functionalPosition->pivot->save();
                }

                $employee->update([
                    'unsubscribe_justification' => $request->unsubscribe_justification,
                ]);

                $authorizing_employee = Employee::findOrFail($employee_id_authorizing);
                $receiver = $authorizing_employee->email;
                // $receiver = 'rodolfo.barraza@dsi.gob.sv';

                $endDate = date_format(date_create($request->end_date), 'Y-m-d 06:00:00');

                if (now() < $endDate) {
                    $delayInSeconds = now()->diffInSeconds($endDate);
                    SendUnsubscribeEmployeeEmailJob::dispatch($receiver, $employee, $newRequest)->delay($delayInSeconds);
                } else {
                    SendUnsubscribeEmployeeEmailJob::dispatch($receiver, $employee, $newRequest);
                }
            });

            return response()->json($employee, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function showPic($employeeId)
    {
        try {
            $employeeRaw = Employee::with([
                'functionalPositions' => function ($query) {
                    $query->with('organizationalUnit');
                }
            ])->findOrFail($employeeId);

            $functionalPosition = $employeeRaw->functionalPositions->first();
            $organizationalUnit = $functionalPosition ? $functionalPosition->organizationalUnit : null;
            $photo = null;

            try {
                $photo = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employeeRaw->photo_route)));
            } catch (\Throwable $th) {}

            $employee = [
                'id' => $employeeRaw->id,
                'name' => $employeeRaw->name,
                'lastname' => $employeeRaw->lastname,
                'birthday' => $employeeRaw->birthday,
                'photo' => $photo,
                'phone' => $employeeRaw->phone,
                'email' => $employeeRaw->email,
                'functional_position' => $functionalPosition ? $functionalPosition->name : null,
                'organizational_unit' => $organizationalUnit ? $organizationalUnit->name : null,
                'organizational_unit_id' => $organizationalUnit ? $organizationalUnit->id : null
            ];
            return response()->json($employee, 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $employeeId);
            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud: '.$e->getMessage()], 500);
        }
    }

    public function updateEmployeeInfo(Request $request, int $id)
    {
        try {
            if (intval($request->employee_id) !== $id) { throw new Exception('Sin concordancia con identificadores.'); }

            $rules = [
                'employee_id' => ['required', 'integer', 'exists:adm_employees,id'],
                'name' => ['nullable', 'max:250'],
                'lastname' => ['nullable', 'max:250'],
                'phone' => ['nullable', 'max:250'],
                'email' => ['nullable', 'email', 'max:250', Rule::unique('adm_employees', 'email')->ignore($request->employee_id)->whereNull('deleted_at')],
                'phone_personal' => ['nullable', 'max:250'],
                'email_personal' => ['nullable', 'email:dns', 'max:250', Rule::unique('adm_employees', 'email_personal')->ignore($request->employee_id)->whereNull('deleted_at')],
                'birthday' => ['nullable', 'date_format:Y-m-d'],
                'marking_required' => ['nullable', Rule::in(['true', 'false'])],
                'adm_gender_id' => ['nullable', 'integer', 'exists:adm_genders,id'],
                'adm_marital_status_id' => ['nullable', 'integer', 'exists:adm_marital_statuses,id'],
                'external' => ['nullable', Rule::in(['true', 'false'])],
                'viatic' => ['nullable', Rule::in(['true', 'false'])],
                'children' => ['nullable', Rule::in(['true', 'false'])],
                'dui' => ['nullable', 'regex:/^\d{8}-\d$/', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'nit' => ['nullable', 'regex:/^\d{8}-\d$|^\d{4}-\d{6}-\d{3}-\d$/', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'nup' => ['nullable', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'isss' => ['nullable', 'regex:/^d{1-9}$/', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'mh' => ['nullable', 'regex:/^[a-zA-Z0-9]{1,14}$/', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'dsi' => ['nullable', Rule::unique('adm_document_type_adm_employee', 'value')->ignore($request->employee_id, 'adm_employee_id')],
                'adm_municipality_id' => ['nullable', 'integer', 'exists:adm_municipalities,id'],
                'urbanization' => ['nullable', 'max:250'],
                'street' => ['nullable', 'max:250'],
                'number' => ['nullable', 'max:250'],
                'complement' => ['nullable', 'max:1000'],
                'adm_functional_position_id' => ['nullable', 'integer', 'exists:adm_functional_positions,id'],
                'salary' => ['nullable', 'numeric'],
                'date_start' => ['nullable', 'date_format:Y-m-d'],
                'date_end' => ['nullable', 'date_format:Y-m-d'],
                'att_schedule_id' => ['nullable', 'integer', 'exists:att_schedules,id'],
                'vehicle' => ['nullable', 'in:true,false'],
                'adhonorem' => ['nullable', 'in:true,false'],
                'parking' => ['nullable', 'in:true,false'],
                /** */'brand' => ['nullable', 'max:250', 'required_with_all:model,year,license_plate'],
                /** */'model' => ['nullable', 'max:250', 'required_with_all:brand,year,license_plate'],
                /** */'year' => ['nullable', 'regex:/^(19|20)\d{2}$/', 'required_with_all:brand,model,license_plate'],
                /** */'license_plate' => ['nullable', 'string', 'alpha_num', 'min:1', 'max:8', 'required_with_all:brand,model,year', Rule::unique('adm_employee_vehicles')->ignore($request->employee_id)],
            ];

            $messages = [
                'required' => 'El :attribute, es requerido.',
                'integer' => 'El :attribute, enviado es irreconocible.',
                'numeric' => 'El :attribute, enviado es irreconocible.',
                'exists' => 'Ningún registro corresponde al :attribute enviado.',
                'max' => 'El :attribute, excede la longitud máxima permitida.',
                'email' => 'El formato de :attribute, es irreconocible.',
                'date_format' => 'El formato de :attribute, es irreconocible.',
                'unique' => 'El :attribute, se encuentra asignado a otro Colaborador.',
                /** */'regex' => 'El Formato enviado para :attribute, es irreconocible.',
                /** */'unique' => 'El :attribute ya está asignado a un registro.',
                /** */'required' => 'Falta el :attribute.',
                /** */'in' => 'El :attribute, está fuera de los parámetros permitidos.',
                /** */'required_with_all' => 'Falta enviar :attribute, para la información del vehículo.',
                /** */'string' => 'El formato d:attribute, es irreconocible.',
                /** */'license_plate.alpha_num' => 'en :attribute solo se pueden enviar caracteres alfa numéricos.',
                /** */'license_plate.min' => ':attribute debe tener la longitud mínima de al menos :min caracter.',
                /** */'license_plate.unique' => ':attribute enviado, ya está asignado a otro vehículo.',
            ];

            $attributes = [
                'employee_id' => 'Identificador de Colaborador',
                'name' => 'Nombres',
                'lastname' => 'Apellidos',
                'phone' => 'Teléfono',
                'email' => 'Email',
                'phone_personal' => 'Teléfono Personal',
                'email_personal' => 'Email Personal',
                'birthday' => 'Fecha de Nacimiento',
                'marking_required' => 'Requiere Marcación',
                'adm_gender_id' => 'Identificador de Género',
                'adm_marital_status_id' => 'Identificador de Estado Familiar',
                'external' => 'Colaborador externo/interno',
                'viatic' => 'Derecho a Viáticos',
                'children' => 'Hijos',
                'dui' => 'DUI',
                'nit' => 'NIT',
                /** */'nup' => 'NUP',
                /** */'isss' => 'ISSS',
                /** */'mh' => 'código de Ministerio de Hacienda',
                /** */'dsi' => 'código dsi',
                'adm_municipality_id' => 'Identificador de Municipio',
                'urbanization' => 'campo Urbanización / Residencial / Colonia',
                'street' => 'campo Calle / Polígono / Pasaje',
                'number' => '# de Casa',
                'complement' => 'Observaciones y/o notas',
                'adm_functional_position_id' => 'Cargo',
                'salary' => 'Salario',
                /** */'vehicle' => 'requiere vehículo',
                /** */'adhonorem' => 'Ad Honorem',
                /** */'parking' => 'require parqueo',
                'date_start' => 'Fecha de Ingreso',
                'date_end' => 'Fecha Final',
                'att_schedule_id' => 'Identificador de Horario',
                /** */'brand' => 'la Marca',
                /** */'model' => 'el Modelo',
                /** */'year' => 'el Año',
                /** */'license_plate' => 'el Número de Placa'
            ];

            $request->validate($rules, $messages, $attributes);

            $employee = null;

            DB::transaction(function () use ($request, &$employee) {
                $employee = Employee::findOrFail($request->employee_id);

                $newDataEmployee = [];

                if ($request->has('name')) { $newDataEmployee['name'] = $request->name; }
                if ($request->has('lastname')) { $newDataEmployee['lastname'] = $request->lastname; }
                if ($request->has('email')) { $newDataEmployee['email'] = $request->email; }
                if ($request->has('email_personal')) { $newDataEmployee['email_personal'] = $request->email_personal; }
                if ($request->has('phone')) { $newDataEmployee['phone'] = $request->phone; }
                if ($request->has('phone_personal')) { $newDataEmployee['phone_personal'] = $request->phone_personal; }
                if ($request->has('birthday')) { $newDataEmployee['birthday'] = $request->birthday; }
                if ($request->has('marking_required')) { $newDataEmployee['marking_required'] = $request->marking_required == 'true' ? true : false; }
                if ($request->has('adm_gender_id')) { $newDataEmployee['adm_gender_id'] = $request->adm_gender_id; }
                if ($request->has('adm_marital_status_id')) { $newDataEmployee['adm_marital_status_id'] = $request->adm_marital_status_id; }
                if ($request->has('remote_mark')) { $newDataEmployee['remote_mark'] = $request->remote_mark === 'true' ? true : false; }
                if ($request->has('external')) { $newDataEmployee['external'] = $request->external === 'true' ? true : false; }
                if ($request->has('viatic')) { $newDataEmployee['viatic'] = $request->viatic === 'true' ? true : false; }
                if ($request->has('vehicle')) { $newDataEmployee['vehicle'] = $request->vehicle === 'true' ? true : false; }
                if ($request->has('disabled')) { $newDataEmployee['disabled'] = $request->disabled === 'true' ? true : false; }
                if ($request->has('adhonorem')) { $newDataEmployee['adhonorem'] = $request->adhonorem === 'true' ? true : false; }
                if ($request->has('parking')) { $newDataEmployee['parking'] = $request->parking === 'true' ? true : false; }
                if ($request->has('children')) { $newDataEmployee['children'] = $request->children === 'true' ? true : false; }

                $photoSaved = [];

                // si trae imagen
                if ($request->photo) {

                    $photoSaved = ImageProcessing::employeeImage($request->photo, str_replace('-', '', $employee->name));

                    $newDataEmployee['photo_name'] = $photoSaved['photo_name'];
                    $newDataEmployee['photo_route'] = $photoSaved['photo_route'];
                    $newDataEmployee['photo_route_sm'] = $photoSaved['photo_route_sm'];
                }


                $employee->update($newDataEmployee);

                /*if ($request->has('vehicle') && $request->vehicle == 'true') {
                    $employee->vehicles()->delete();
                    $employee->vehicles()->create([
                        'brand' => $request->vehicle_brand,
                        'model' => $request->vehicle_model,
                        'year' => $request->vehicle_year,
                        'license_plate' => $request->vehicle_license_plate,
                    ]);
                } else {
                    if ($employee->vehicles()->exists()) {
                        $employee->vehicles()->delete();
                    }
                }*/

                if ($request->has('dui') && !empty($request->dui)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 1,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->dui,
                            ]
                        );
                }

                if ($request->has('nit') && !empty($request->nit)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 2,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->nit,
                            ]
                        );
                }

                if ($request->has('nup') && !empty($request->nup)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 3,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->nup,
                            ]
                        );
                }

                if ($request->has('isss') && !empty($request->isss)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 4,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->isss,
                            ]
                        );
                }

                if ($request->has('mh') && !empty($request->mh)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 6,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->mh,
                            ]
                        );
                }

                if ($request->has('dsi') && !empty($request->dsi)) {
                    DB::table('adm_document_type_adm_employee')
                        ->updateOrInsert(
                            [
                                'adm_document_type_id' => 7,
                                'adm_employee_id' => $request->employee_id,
                            ],
                            [
                                'value' => $request->dsi,
                            ]
                        );
                }

                $addressFields = ['adm_municipality_id', 'urbanization', 'street', 'number', 'complement'];

                if (!empty(array_intersect_key(array_flip($addressFields), $request->all()))) {
                    $newAddressData = [];

                    $address = $employee->address;

                    if ($request->has('adm_municipality_id')) { $newAddressData['adm_municipality_id'] = $request->adm_municipality_id; }
                    if ($request->has('urbanization')) { $newAddressData['urbanization'] = $request->urbanization; }
                    if ($request->has('street')) { $newAddressData['street'] = $request->street; }
                    if ($request->has('number')) { $newAddressData['number'] = $request->number; }
                    if ($request->has('complement')) { $newAddressData['complement'] = $request->complement; }

                    if (empty($address)) {
                        $newAddressData['name'] = 'principal';
                        $address = Address::create($newAddressData);

                        $employee->update([
                            'adm_address_id' => $address->id,
                        ]);
                    } else {
                        $address->update($newAddressData);
                    }
                }

                $scheduleFields = ['att_schedule_id', 'date_start', 'date_end'];

                if (!empty(array_intersect_key(array_flip($scheduleFields), $request->all()))) {

                    $newScheduleData = [];
                    $latestSchedule = null;

                    $activeSchedules = $employee->schedules()
                        ->wherePivot('active', true)
                        ->wherePivotNull('date_end')
                        ->get();

                    if ($activeSchedules->isNotEmpty()) {
                        $activeSchedules->each(function ($schedule) use ($request) {
                            $employeeSchedule = $schedule->employeeSchedule;
                            $dateEnd = $request->input('date_end') ?: ($request->date_start ? $request->date_start : date_format(date_create(now()), 'Y-m-d'));
                            $employeeSchedule->update([
                                'active' => false,
                                'date_end' => $dateEnd,
                            ]);
                        });

                        $latestSchedule = $activeSchedules->last();
                    }

                    if ($request->has('att_schedule_id')) { $latestSchedule = Schedule::findOrFail($request->att_schedule_id); }
                    if ($request->has('date_start')) { $newScheduleData['date_start'] = $request->date_start; } else { $newScheduleData['date_start'] = date_format(date_create(now()), 'Y-m-d'); }
                    $newScheduleData['active'] = true;

                    if (!isset($newScheduleData['att_schedule_id']) && !$latestSchedule) { throw new Exception('Sin registros previos para el horario.'); }

                    $newSchedule = $employee->schedules()->attach($latestSchedule, $newScheduleData);
                }

                

                $functionalPositionFields = ['adm_functional_position_id', 'date_start', 'date_end', 'salary'];

                if (!empty(array_intersect_key(array_flip($functionalPositionFields), $request->all()))) {

                    $newFunctionalPositionData = [];

                    $activeFunctionalPositions = $employee->functionalPositions()
                        ->wherePivot('active', true)
                        ->wherePivotNull('date_end')
                        ->get();

                    $latestFunctionalPosition = $activeFunctionalPositions->last();

                    if ($activeFunctionalPositions->isNotEmpty()) {
                        $activeFunctionalPositions->each(function ($functionalPosition) use ($request) {
                            $dateEnd = $request->input('date_end') ?: ($request->date_start ? $request->date_start : date_format(date_create(now()), 'Y-m-d'));
                            $functionalPosition->pivot->update([
                                'active' => false,
                                'date_end' => $dateEnd,
                            ]);
                        });
                    }

                    if ($request->has('adm_functional_position_id')) { $latestFunctionalPosition = FunctionalPosition::findOrFail($request->adm_functional_position_id); }
                    if ($request->has('date_start')) { $newFunctionalPositionData['date_start'] = $request->date_start; } else { $newFunctionalPositionData['date_start'] = date_format(date_create(now()), 'Y-m-d'); }
                    if ($request->has('salary')) { $newFunctionalPositionData['salary'] = $request->input('salary', 0); } else { $newFunctionalPositionData['salary'] = 0; }
                    $newFunctionalPositionData['active'] = true;
                    $newFunctionalPositionData['principal'] = 1;

                    if (!isset($newFunctionalPositionData['adm_functional_position_id']) && !$latestFunctionalPosition) { throw new Exception('Sin registros previos para el cargo funcional.'); }

                    $newFunctionalPosition = $employee->functionalPositions()->attach($latestFunctionalPosition, $newFunctionalPositionData);
                }
            });

            return response()->json($employee, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422, [], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function notifications()
    {
        return response()->json(Auth::user()->unreadNotifications,200);
    }

    public function birthdays(int $id = null) {
        try {

            $months = [1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC' ];

            if ($id) {
                $month = $id;
            } else {
                $month = Carbon::now()->month;
            }

            $birthdays = Employee::select('id', 'name', 'lastname', 'birthday', 'email', 'photo_route_sm', 'adm_gender_id')
                ->with(['functionalPositions' => function ($query) {
                    $query->select('adm_functional_positions.id', 'adm_functional_positions.name', 'adm_functional_positions.adm_organizational_unit_id')
                        ->with(['organizationalUnit:id,name']);
                }])
                ->where('active', true)
                ->whereMonth('birthday', $month)
                ->orderBy(DB::raw('DAY(birthday)'), 'asc')
                ->get();

            $birthdays->each(function ($employee) use ($months, $month) {
                $employee->birthday = date_format(date_create($employee->birthday), 'd') . ' - ' . $months[$month];
                if ($employee->photo_route_sm && file_exists(storage_path($employee->photo_route_sm))) {
                    $employee->photo_route_sm = "data:image/jpg;base64," . base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                }

                $employee->functionalPositions->each(function ($position) {
                    $position->makeHidden('pivot');
                });
            });

            return response()->json($birthdays, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id ?? json_encode($id));

            return response()->json(['message' => $e->validator->errors()->getMessages()], 422, [], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . $id ?? json_encode($id));

            return response()->json(['message' => $e->getMessage(), 'line' => $e->getline(), 'month' => $month], 500);
        }
    }

    public function birthdaysBetweenDates($dateIni,$dateEnd) {
        $data = [];
        $code = 200;
        try {
            $employees = Employee::birthdaysBetweenDates($dateIni,$dateEnd)
            ->orderBy(DB::raw('MONTH(birthday)'), 'asc')
            ->orderBy(DB::raw('DAY(birthday)'), 'asc')
            ->get();

            foreach($employees as $key => $employee)
            {
                $data[$key]['id'] = $employee->id;
                $data[$key]['name'] = $employee->name.' '.$employee->lastname;
                $data[$key]['birthday'] = $employee->birthday;
                // $data[$key]['functional_position'] = $employee->functionalPositions[0]->name;
                // $data[$key]['organizational_unit'] = $employee->functionalPositions[0]->organizationalUnit->name;
                try {
                    // $data[$key]['photo'] = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                } catch (\Throwable $th) {}
            }
        } catch (Exception $e) {
            $data = $e->getMessage();
            $code = 500;
        }
        return response()->json($data,$code);
    }

    public function birthdaysInMonth($dateIni) {
        $data = [];
        $code = 200;
        try {
            $employees = Employee::birthdaysInMonth($dateIni)
            ->orderBy(DB::raw('MONTH(birthday)'), 'asc')
            ->orderBy(DB::raw('DAY(birthday)'), 'asc')
            ->get();

            foreach($employees as $key => $employee)
            {
                $data[$key]['id'] = $employee->id;
                $data[$key]['name'] = $employee->name.' '.$employee->lastname;
                $data[$key]['email'] = $employee->email;
                $data[$key]['phone'] = $employee->phone;
                $data[$key]['birthday'] = $employee->birthday;
                $data[$key]['functional_position'] = $employee->functionalPositions[0]->name;
                $data[$key]['organizational_unit'] = $employee->functionalPositions[0]->organizationalUnit->name;
                try {
                    $data[$key]['photo'] = "data:image/jpg;base64,".base64_encode(file_get_contents(storage_path($employee->photo_route_sm)));
                } catch (\Throwable $th) {}
            }
        } catch (Exception $e) {
            $data = $e->getMessage();
            $code = 500;
        }
        return response()->json($data,$code);
    }

}
