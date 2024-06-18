<?php

namespace App\Http\Controllers\API\Administration;

use App\Helpers\StringsHelper;
use App\Http\Controllers\Controller;
use App\Models\Administration\Gender;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GenderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $genders = [];

            $rules = [
                'search' => ['nullable', 'max:250'],
                'perPage' => ['nullable', 'integer', 'min:1'],
                'sort' => ['nullable'],
                'sort.order' => ['nullable', Rule::in(['id', 'name', 'active'])],
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

            $genders = Gender::where(function (Builder $query) use ($search) {
                return $query->where('adm_genders.name', 'like', '%' . $search . '%');;
            })
            ->orderBy($orderBy, $orderDirection)
            ->paginate($perPage);

            $response = $genders->toArray();
            $response['search'] = $request->query('search', '');
            $response['sort'] = [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection
            ];

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::error($e->getMessage() . ' Por Usuario: ' . Auth::user()->id . '. Información enviada: ' . json_encode($request->all()));

            return response()->json(['message' => 'Ha ocurrido un error al procesar la solicitud.', 'errors' => $e->getMessage()], 500);
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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Gender $Genders)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Gender $Genders)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Gender $Genders)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Gender $Genders)
    {
        //
    }

    public function activeGenders() {
        $genders = Gender::select('id', 'name')->where('active', true)->get();

        return response()->json($genders, 200);
    }
}
