<?php

namespace App\Http\Controllers\API\Administration;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\SendPaymentVoucherEmailJob;
use App\Mail\SendPaymentVoucherEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Administration\PaymentVoucher;
use Illuminate\Validation\ValidationException;
use App\Models\Administration\PaymentVoucherFile;
use Illuminate\Http\File;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class PaymentVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $rules = [
                'perPage' => ['nullable', 'integer', 'min:1'],
                'search' => ['nullable', 'max:250'],
                'sort' => ['nullable'],
                'sort.order' => ['nullable', Rule::in(['id', 'year', 'month'])],
                'sort.key' => ['nullable', Rule::in(['asc', 'desc'])],
            ];

            $messages = [
                'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
                'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
                'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
                'sort.order.in' => 'El valor de ordenamiento es inválido.',
                'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
            ];

            $request->validate($rules, $messages);

            $perPage = $request->query('perPage', 10);
            $search = $request->query('search', date_format(date_create(now()), 'Y'));

            $sort = json_decode($request->input('sort'), true);
            $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'month';
            $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'desc';

            if (isset($request->search) && filter_var($request->query('search'), FILTER_VALIDATE_INT)) {
                $search = intval($request->query('search'));
                $paymentVouchers = PaymentVoucher::select(['id', 'year', 'month', 'finished'])
                    ->where('year', '=', $search)
                    ->orWhere('month', '=', $search)
                    ->orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            } else {
                $search = $request->query('search', '');
                $paymentVouchers = PaymentVoucher::select(['id', 'year', 'month', 'finished'])
                    ->where('description', 'like', '%' . $search . '%')
                    ->orWhereNull('description')
                    ->orderBy($orderBy, $orderDirection)
                    ->paginate($perPage);
            }

            if ($paymentVouchers->count() > 0) {
                foreach ($paymentVouchers as $idx => $paymentVoucher) {
                    $paymentVouchers[$idx]['totalFiles'] = $paymentVoucher->paymentVoucherFiles->count();
                    $paymentVoucher->makeHidden(['paymentVoucherFiles']);
                }
            }

            $result = $paymentVouchers->toArray();
            $result['search'] = $search;
            $result['sort'] = [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
            ];

            $url = URL::current() . '?page=1&perPage=' . $perPage . '&sort=' . urlencode(json_encode($result['sort'])) . '&search=' . urlencode($search);
            $result['url'] = $url;

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => implode(', ', $e->validator->errors()->all())], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

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
    public function store(Request $request)
    {
        try {
            $rules = [
                'year' => ['required', 'integer', 'min:2020', 'max:2050'],
                'month' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12])],
                'description' => ['nullable', 'max:1000'],
                'files' => [
                    'required',
                    'filled',
                    function ($attribute, $value, $fail) {
                        $maxTotalSize = 300 * 1024 * 1024; // 30MB en kilobytes. Si se modifica, modificar el controlador PaymentVoucherController::store en el Back-End
                        $totalSize = 0;

                        foreach ($value as $file) {
                            $totalSize += $file->getSize();
                        }

                        if ($totalSize > $maxTotalSize) {
                            $fail("La suma del peso total de los archivos no debe exceder los " . $maxTotalSize/ 1024 / 1024 . "MB.");
                        }
                    },
                ],
                'files.*' => ['required', 'file', function ($attribute, $value, $fail) {
                    $allowedMimes = ['application/pdf', 'application/zip'];
                    $allowedExtensions = ['pdf', 'zip'];
                    $maxSizes = [
                        'pdf' => 1048576, // 1024 KB
                        'zip' => 314572800, // 20480 KB
                    ];

                    $extension = strtolower($value->getClientOriginalExtension());

                    if (!in_array($value->getMimeType(), $allowedMimes) || !in_array($extension, $allowedExtensions)) {
                        $fail('La extensión ' . $value->getClientOriginalExtension() . ' o el tipo de archivo ' . $value->getMimeType() . ' no son válidos.');
                    }

                    if (array_key_exists($extension, $maxSizes) && $value->getSize() > $maxSizes[$extension] * 1024) {
                        $fail("Se ha excedido el tamaño máximo permitido para el archivo " . $value->getClientOriginalName() . ".");
                    }
                }],
            ];

            $messages = [
                'year.required' => 'Falta el :attribute.',
                'year.integer' => 'El formato del :attribute enviado es irreconocible.',
                'year.min' => 'El :attribute enviado está por debajo del rango mínimo permitido.',
                'year.max' => 'El :attribute enviado está por encima del rango máximo permitido.',
                'month.required' => 'Falta el identificador de :attribute.',
                'month.integer' => 'El formato del identificador de :attribute es incorrecto.',
                'month.in' => 'El identificador del :attribute, está fuera del rango permitido.',
                'description.max' => 'La longitud máxima de la :attribute ha sido excedida.',
                'files.required' => 'Al menos un :attribute es necesario enviar.',
                'files.array' => 'El formato del :attribute enviado es irreconocible.',
                'files.filled' => 'Se debe enviar al menos un :attribute.',
                'files.*.required' => 'Es necesario al menos un :attribute.',
                'files.*.file' => 'La información enviada sin coincidencia con la información solicitada.',
            ];

            $attributes = [
                'year' => 'año',
                'month' => 'mes',
                'description' => 'descripción',
                'files' => 'archivo(s)',
            ];

            $request->validate($rules, $messages, $attributes);

            $voucher = null;

                $basePath = "paymentVouchers/" . $request->year . "/" . $request->month . "/";
                $fullPath = storage_path('app/' . $basePath);

                Storage::makeDirectory($fullPath, 0777, true);

                $voucher = PaymentVoucher::updateOrCreate([
                    'year' => $request->year,
                    'month' => $request->month,
                ], [
                    'description' => $request->description ? DB::raw("CONCAT(IFNULL(description, ''), '\n\n" . date_format(date_create(now()), 'd-m-Y H:s') . "\n {$request->description}')") : DB::raw("CONCAT(IFNULL(description, ''), '')"),
                ]);

                foreach ($request->file('files') as $idx => $file) {
                    if ($file->getClientOriginalExtension() === 'pdf') {

                        $fileName = $file->getClientOriginalName();

                        /* $numericPart = preg_split("/\D+/", $fileName);
                        if (count($numericPart) > 2) {
                            $firstPart = explode($numericPart[1], $fileName);
                        } else {
                            $firstPart[0] = '';
                        }

                        $search = $firstPart[0] . implode("", $numericPart);*/

                        /** */
                        preg_match('/^([a-zA-Z]*\d+)(.*)$/', $fileName, $matches);
	                    $search = $matches[1];
                        /** */

                        $employee = DB::table('adm_document_type_adm_employee')
                            ->select('adm_document_type_adm_employee.id as document_id', 'adm_employees.id as employee_id', 'adm_employees.name', 'adm_employees.lastname', 'adm_employees.email')
                            ->join('adm_employees', 'adm_document_type_adm_employee.adm_employee_id', '=', 'adm_employees.id')
                            ->where('value', $search)
                            ->first();

                        if ($employee) {

                            $email = $employee->email;
                            $filePath = 'app/' . $basePath . $fileName;

                            $voucherFile = PaymentVoucherFile::updateOrCreate([
                                'file_name' => $fileName,
                                'adm_employee_id' => $employee->employee_id,
                                'adm_payment_voucher_id' => $voucher->id,
                            ], [
                                'document_id' => $employee->document_id,
                                'email' => $employee->email,
                                'file_type' => $file->getClientMimetype(),
                                'file_size' => $file->getSize(),
                                'file_path' => $filePath,
                            ]);

                            $file->move($fullPath, $fileName);

                            SendPaymentVoucherEmailJob::dispatch($email, $filePath, $voucher->id, $request->year, $request->month, $voucherFile->id, $employee->employee_id)->delay(now()->addSeconds(10));
                        } else {
                            Log::error('Error obteniendo correo electrónico para: ' .  $file->getClientOriginalName() . ' con extracto: ' . json_encode($search, true));
                        }

                    } elseif ($file->getClientOriginalExtension() === 'zip') {
                        $zip = new ZipArchive();

                        if ($zip->open($file->path()) === true) {
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $filename = $zip->getNameIndex($i);
                                $contents = $zip->getFromIndex($i);

                                if (pathinfo($filename, PATHINFO_EXTENSION) === 'pdf') {
                                    $fileName = pathinfo($filename, PATHINFO_FILENAME) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
                                    $filePath = 'app/' . $basePath . $fileName;
                                    file_put_contents(storage_path($filePath), $contents);

                                    /*$numericPart = preg_split("/\D+/", $fileName);
                                    if (count($numericPart) > 2) {
                                        $firstPart = explode($numericPart[1], $fileName);
                                    } else {
                                        $firstPart[0] = '';
                                    }

                                    $search = $firstPart[0] . implode("", $numericPart);*/

                                    /** */
                                    preg_match('/^([a-zA-Z]*\d+)(.*)$/', $fileName, $matches);
	                                $search = $matches[1];
                                    /** */

                                    $employee = DB::table('adm_document_type_adm_employee')
                                        ->select('adm_document_type_adm_employee.id as document_id', 'adm_employees.id as employee_id', 'adm_employees.name', 'adm_employees.lastname', 'adm_employees.email')
                                        ->join('adm_employees', 'adm_document_type_adm_employee.adm_employee_id', '=', 'adm_employees.id')
                                        ->where('value', $search)
                                        ->first();

                                    if ($employee) {
                                        $email = $employee->email;

                                        $voucherFile = PaymentVoucherFile::updateOrCreate([
                                            'file_name' => $fileName,
                                            'adm_employee_id' => $employee->employee_id,
                                            'adm_payment_voucher_id' => $voucher->id,
                                        ], [
                                            'document_id' => $employee->document_id,
                                            'email' => $employee->email,
                                            'file_type' => 'application/pdf',
                                            'file_size' => strlen($contents),
                                            'file_path' => $filePath,
                                        ]);

                                        SendPaymentVoucherEmailJob::dispatch($email, $filePath, $voucher->id, $request->year, $request->month, $voucherFile->id, $employee->employee_id)->delay(now()->addSeconds(10));
                                    } else {
                                        Log::error('Error obteniendo correo electrónico para: ' .  $fileName . ' con extracto: ' . json_encode($search, true));

                                        $voucher->paymentVoucherErrors()->create(
                                            [
                                                'user_id' => Auth::user()->id,
                                                'error' => 'Información de empleado no encontrada para "' . json_encode($search, true) . '"',
                                                'file' => $file->getClientOriginalName() . ' / ' . pathinfo($filename, PATHINFO_FILENAME) . '.' . pathinfo($filename, PATHINFO_EXTENSION),
                                            ]
                                        );
                                    }
                                } else {
                                    Log::error('Extensión de archivo "' . pathinfo($filename, PATHINFO_EXTENSION) . '" sin soporte para boleta de pago (' . pathinfo($filename, PATHINFO_FILENAME) . '). Por: ' . Auth::user()->id);
                                    $voucher->paymentVoucherErrors()->create(
                                        [
                                            'user_id' => Auth::user()->id,
                                            'error' => 'Archivo con extensión "' . pathinfo($filename, PATHINFO_EXTENSION) . '", no permitida',
                                            'file' => $file->getClientOriginalName() . ' / ' . pathinfo($filename, PATHINFO_FILENAME) . '.' . pathinfo($filename, PATHINFO_EXTENSION),
                                        ]
                                    );
                                }
                            }
                            $zip->close();
                        } else {
                            Log::error('Error abriendo el archivo ' . $file->getClientOriginalName() . ' enviado. Por: ' . Auth::user()->id);

                            $voucher->paymentVoucherErrors()->create(
                                [
                                    'user_id' => Auth::user()->id,
                                    'error' => 'El archivo ' . $file->getClientOriginalName() . ', imposible de abrir.',
                                    'file' => $file->getClientOriginalName(),
                                ]
                            );
                        }
                    } else {
                        Log::error('El archivo "' . $file->getClientOriginalExtension() . '", enviado posee otro formato a los permitidos (PDF o ZIP). '  . $file->getClientOriginalName() . ' | ' . $file->getSize() . ' | ' . $file->getClientMimetype() . ' | Por usuario: ' . Auth::user()->name . ' (' . Auth::user()->id) . ')';

                        $voucher->paymentVoucherErrors()->create(
                            [
                                'user_id' => Auth::user()->id,
                                'error' => 'Archivo con extensión "' . $file->getClientOriginalExtension() . '", no permitida',
                                'file' => $file->getClientOriginalName() . '.' . $file->getClientOriginalExtension(),
                            ]
                        );

                        return response()->json('Archivo erróneo, enviado con formato "' . $file->getClientOriginalExtension() . '" del archivo ' . $file->getClientOriginalName(), 400);
                    }
                }

            return response()->json($voucher, 200);

        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => implode(', ', $e->validator->errors()->all())], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => $e->getMessage(), 'data sent' => json_encode($request->all())], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $validateData = Validator::make(
                ['id' => $id],
                ['id' => ['required', 'integer', 'exists:adm_payment_vouchers,id']],
                [
                    'id.required' => 'Falta identificador de Recurso.',
                    'id.integer' => 'Identificador de Recurso irreconocible.',
                    'id.exists' => 'Recurso solicitado sin coincidencia.',
                ]
            )->validate();

            $voucher = PaymentVoucher::with(
                [
                    'paymentVoucherFiles:id,file_name,file_size,file_path,sent,adm_employee_id,adm_payment_voucher_id,updated_at',
                    'paymentVoucherErrors',
                    'paymentVoucherFiles.employee:id,name,lastname,email,adm_gender_id',
                    'paymentVoucherErrors.user:id,name,lastname'
                ])->findOrFail($validateData['id']);

            $uploadedFiles = Storage::files('/paymentVouchers/' . $voucher->year . '/' . $voucher->month . '/');

            $files = [];
            $errors = [];

            if ($uploadedFiles) {
                foreach ($uploadedFiles as $idx => $file) {
                    $files[$idx]['fullPath'] = 'app/' . $file;
                    $files[$idx]['name'] = pathinfo($file, PATHINFO_FILENAME);
                    $files[$idx]['extension'] = pathinfo($file, PATHINFO_EXTENSION);
                    $files[$idx]['fullName'] = $files[$idx]['name'] . '.' . $files[$idx]['extension'];

                    $file_sent = PaymentVoucherFile::where('file_name', $files[$idx]['fullName'])
                        ->whereHas('paymentVoucher', function ($query) use ($voucher) {
                            $query->where('year', $voucher->year)
                                ->where('month', $voucher->month);
                        })
                        ->first();

                    if (!$file_sent) {

                        preg_match_all('/\d+/', $files[$idx]['fullName'], $matches);
                        $numericPart = implode('', $matches[0]);

                        $employee = DB::table('adm_document_type_adm_employee')
                            ->select('adm_document_type_adm_employee.id as document_id', 'adm_employees.id as employee_id', 'adm_employees.name', 'adm_employees.lastname', 'adm_employees.email')
                            ->join('adm_employees', 'adm_document_type_adm_employee.adm_employee_id', '=', 'adm_employees.id')
                            ->whereRaw("REGEXP_REPLACE(`value`, '[^0-9]+', '') = ?", [$numericPart])
                            ->first();

                        $errors[$idx]['file'] = $files[$idx]['fullName'];
                        $errors[$idx]['employee'] = $employee ?? null;
                    }
                }

                $voucher['errors'] = $errors;
            }

            return response()->json($voucher, 200);
        } catch (ValidationException $e) {
            Log::error(json_encode($e->validator->errors()->getMessages()) . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => implode(', ', $e->validator->errors()->all())], 422);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' En Archivo: ' . $e->getFile() . ' En línea: ' . $e->getLine() . ' - Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($id));

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentVoucher $paymentVoucher)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaymentVoucher $paymentVoucher)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentVoucher $paymentVoucher)
    {
        //
    }
}
