<?php

namespace App\Http\Controllers\API\Compras;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Compras\Compra;
use App\Models\Compras\DetalleCompra;
use App\Models\Inventarios\Inventario;
use Illuminate\Support\Facades\Validator;
use App\Models\Productos\Producto;
use App\Models\Productos\UnidadMedida;
use Illuminate\Support\Facades\DB;
use App\Helpers\StringsHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ComprasController extends Controller
{

    public function index(Request $request)
    {
        // Reglas de validación para los parámetros de consulta
        $rules = [
            'search' => ['nullable', 'max:250'],
            'perPage' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable'],
            'sort.key' => ['nullable', Rule::in(['id', 'proveedor_id', 'total', 'proveedores.nombre'])],
            'sort.order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];

        $messages = [
            'search.max' => 'El criterio de búsqueda enviado excede la cantidad máxima permitida.',
            'perPage.integer' => 'Solicitud de cantidad de registros por página con formato irreconocible.',
            'perPage.min' => 'La cantidad de registros por página no puede ser menor a 1.',
            'sort.key.in' => 'El valor de clave de ordenamiento es inválido.',
            'sort.order.in' => 'El valor de ordenamiento es inválido.',
        ];

        // Validar los parámetros de consulta
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener los parámetros de consulta
        $search = $request->query('search', '');
        $perPage = $request->query('perPage', 5);

        $sort = json_decode($request->input('sort'), true);
        $orderBy = isset($sort['key']) && !empty($sort['key']) ? $sort['key'] : 'id';
        $orderDirection = isset($sort['order']) && !empty($sort['order']) ? $sort['order'] : 'asc';

        // Obtener las compras con filtrado y ordenamiento
        $compras = Compra::with('proveedor')
            ->where(function (Builder $query) use ($search) {
                $query->whereHas('proveedor', function (Builder $q) use ($search) {
                    $q->where('nombre', 'like', '%' . $search . '%');
                });
            });

        $compras = $compras->orderBy($orderBy, $orderDirection);
        $compras = $compras->paginate($perPage);

        // Preparar la respuesta en formato JSON
        $response = [
            'message' => 'lista de compras',
            'data' => $compras->items(),
            'search' => $search,
            'sort' => [
                'orderBy' => $orderBy,
                'orderDirection' => $orderDirection,
            ],
            'pagination' => [
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'per_page' => $compras->perPage(),
                'total' => $compras->total(),
            ]
        ];

        return response()->json($response, 200);
    }

    //Funcion para actualizar una compra
    public function update(Request $request, $id)
    {
        // Validación de los campos de entrada
        $validator = Validator::make($request->all(), [
            'fecha' => 'required',
            'numeroCCF' => 'required',
            'ivaCompra' => 'required',
            'totalCompra' => 'required',
            'proveedor_id' => 'required',
            'comprasExentas' => 'required',
            'comprasGravadas' => 'required',
            'ivaPercibido' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $compra = Compra::find($id);
        if (!$compra) {
            return response()->json([
                'message' => 'Compra no encontrada.',
            ], 404);
        }

        // Empezar una transacción
        DB::beginTransaction();
        try {
            // Obtener los detalles de compra actuales
            $detalleActual = DetalleCompra::where('compra_id', $compra->id)->get();

            // Disminuir las existencias de los detalles de compra actuales
            foreach ($detalleActual as $detalle) {
                $unidadSeleccionada = Inventario::where('producto_id', $detalle->producto_id)
                    ->where('unidad_medida_id', $detalle->unidad_medida_id)
                    ->first();

                if ($unidadSeleccionada) {
                    // Disminuir las existencias de la unidad de medida seleccionada
                    $unidadSeleccionada->existencias -= $detalle->cantidad;
                    $unidadSeleccionada->save();

                    // Actualizar las existencias de otras unidades de medida del mismo producto
                    $unidadesProducto = Inventario::where('producto_id', $detalle['producto_id'])->get();
                    foreach ($unidadesProducto as $unidad) {
                        if ($unidad->unidad_medida_id != $unidadSeleccionada->unidad_medida_id) {
                            if ($unidadSeleccionada->equivalencia > 1) {
                                $unidad->existencias = $unidadSeleccionada->existencias / $unidadSeleccionada->equivalencia * $unidad->equivalencia;
                            } else {
                                $unidad->existencias = $unidadSeleccionada->existencias * $unidad->equivalencia;
                            }
                            $unidad->save();
                        }
                    }
                }
            }

            // Eliminar los detalles de la compra que se están modificando
            DetalleCompra::where('compra_id', $compra->id)->delete();

            // Actualizar la compra
            $compra->update([
                'fecha' => $request->fecha,
                'numeroCCF' => $request->numeroCCF,
                'comprasExentas' => $request->comprasExentas,
                'comprasGravadas' => $request->comprasGravadas,
                'ivaCompra' => $request->ivaCompra,
                'ivaPercibido' => $request->ivaPercibido,
                'totalCompra' => $request->totalCompra,
                'proveedor_id' => $request->proveedor_id
            ]);

            $detallesNuevos = [];

            // Iterar sobre los productos y crear los registros de detalle de compra
            foreach ($request->productos as $producto) {
                $unidadSeleccionada = Inventario::where('producto_id', $producto['producto_id'])
                    ->where('unidad_medida_id', $producto['unidad_medida_id'])
                    ->first();

                if (!$unidadSeleccionada) {
                    // Revertir la transacción en caso de error
                    DB::rollback();
                    return response()->json([
                        'message' => 'No se encontró el inventario para el producto y unidad de medida proporcionados.',
                        'producto_id' => $producto['producto_id'],
                        'unidad_medida_id' => $producto['unidad_medida_id']
                    ], 400);
                }

                // Crear los nuevos detalles de compra
                $detalleCompra = DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto['producto_id'],
                    'unidad_medida_id' => $producto['unidad_medida_id'],
                    'costo' => $producto['costo'],
                    'ivaCompra' => $producto['iva'],
                    'total' => $producto['total'],
                    'cantidad' => $producto['cantidad']
                ]);

                $detallesNuevos[] = $detalleCompra;

                // Incrementar las existencias con los nuevos detalles
                if ($unidadSeleccionada) {
                    // Aumentar las existencias de la unidad de medida seleccionada
                    $unidadSeleccionada->existencias += $detalleCompra->cantidad;
                    $unidadSeleccionada->save();

                    // Actualizar las existencias de otras unidades de medida del mismo producto
                    $unidadesProducto = Inventario::where('producto_id', $producto['producto_id'])->get();
                    foreach ($unidadesProducto as $unidad) {
                        if ($unidad->unidad_medida_id != $unidadSeleccionada->unidad_medida_id) {
                            if ($unidadSeleccionada->equivalencia > 1) {
                                $unidad->existencias = $unidadSeleccionada->existencias / $unidadSeleccionada->equivalencia * $unidad->equivalencia;
                            } else {
                                $unidad->existencias = $unidadSeleccionada->existencias * $unidad->equivalencia;
                            }
                            $unidad->save();
                        }
                    }
                }
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Compra actualizada exitosamente',
                'compra' => $compra,
                'detalles' => $detallesNuevos
            ], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar la compra',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function detalleCompra($numero)
    {
        // Obtener la compra con el número dado
        $compra = Compra::where('numeroCCF', $numero)->first();

        if (!$compra) {
            return response()->json([
                'message' => 'Compra no encontrada',
            ], 404);
        }

        // Obtener los detalles de la compra
        $detalle = DetalleCompra::with('producto', 'unidad')
            ->where('compra_id', $compra->id)
            ->get();


        // Devolver la respuesta en formato JSON con un mensaje y los datos
        return response()->json([
            'message' => 'Detalle de compra',
            'data' => [
                'compra' => $compra,
                'detalles' => $detalle,
            ],
        ], 200);
    }


    //Agregar compra
    public function store(Request $request)
    {
        // Validación de los campos de entrada
        $validator = Validator::make($request->all(), [
            'fecha' => 'required',
            'numeroCCF' => 'required',
            'ivaCompra' => 'required',
            'totalCompra' => 'required',
            'proveedor_id' => 'required',
            'comprasExentas' => 'required',
            'comprasGravadas' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verificar si ya existe una compra con el mismo numeroCCF
        $compraExistente = Compra::where('numeroCCF', $request->numeroCCF)->first();
        if ($compraExistente) {
            return response()->json([
                'message' => 'La compra con este numeroCCF ya ha sido registrada',
                'compra' => $compraExistente
            ], 409);
        }

        // Empezar una transacción
        DB::beginTransaction();
        try {
            // Crear la compra
            $compra = Compra::create([
                'fecha' => $request->fecha,
                'numeroCCF' => $request->numeroCCF,
                'comprasExentas' => $request->comprasExentas,
                'comprasGravadas' => $request->comprasGravadas,
                'ivaCompra' => $request->ivaCompra,
                'ivaPercibido' => $request->ivaPercibido,
                'totalCompra' => $request->totalCompra,
                'proveedor_id' => $request->proveedor_id
            ]);

            $detallesCompra = [];

            // Iterar sobre los productos y crear los registros de detalle de compra
            foreach ($request->productos as $producto) {
                $detalleCompra = DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto['producto_id'],
                    'unidad_medida_id' => $producto['unidad_medida_id'],
                    'costo' => $producto['costo'],
                    'ivaCompra' => $producto['iva'],
                    'total' => $producto['total'],
                    'cantidad' => $producto['cantidad']
                ]);

                // Obtener el inventario de la unidad de medida seleccionada
                $unidadSeleccionada = Inventario::where('producto_id', $producto['producto_id'])
                    ->where('unidad_medida_id', $producto['unidad_medida_id'])
                    ->first();

                if ($unidadSeleccionada) {
                    // Aumentar las existencias de la unidad de medida seleccionada
                    $unidadSeleccionada->existencias += $detalleCompra->cantidad;
                    $unidadSeleccionada->save();

                    // Actualizar las existencias de otras unidades de medida del mismo producto
                    $unidadesProducto = Inventario::where('producto_id', $producto['producto_id'])->get();
                    foreach ($unidadesProducto as $unidad) {
                        if ($unidad->unidad_medida_id != $unidadSeleccionada->unidad_medida_id) {
                            if ($unidadSeleccionada->equivalencia > 1) {
                                $unidad->existencias = $unidadSeleccionada->existencias / $unidadSeleccionada->equivalencia * $unidad->equivalencia;
                            } else {
                                $unidad->existencias = $unidadSeleccionada->existencias * $unidad->equivalencia;
                            }
                            $unidad->save();
                        }
                    }

                    $detallesCompra[] = $detalleCompra;
                } else {
                    // Revertir la transacción en caso de error
                    DB::rollback();
                    return response()->json([
                        'message' => 'Error: No se encontró el inventario para el producto y unidad de medida proporcionados.',
                        'producto_id' => $producto['producto_id'],
                        'unidad_medida_id' => $producto['unidad_medida_id']
                    ], 400);
                }
            }

            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Compra registrada exitosamente',
                'compra' => $compra,
                'detalles' => $detallesCompra
            ], 201);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json([
                'message' => 'Error al registrar la compra',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete($id)
    {
        // Empezar una transacción
        DB::beginTransaction();
        try {
            // Obtener los detalles de compra para la compra especificada
            $detallesCompra = DetalleCompra::where('compra_id', $id)->get();

            // Disminuir las existencias en el inventario según los detalles de compra
            foreach ($detallesCompra as $detalle) {
                $unidadSeleccionada = Inventario::where('producto_id', $detalle->producto_id)
                    ->where('unidad_medida_id', $detalle->unidad_medida_id)
                    ->first();

                if ($unidadSeleccionada) {
                    // Disminuir las existencias de la unidad de medida seleccionada
                    $unidadSeleccionada->existencias -= $detalle->cantidad;
                    $unidadSeleccionada->save();

                    // Actualizar las existencias de otras unidades de medida del mismo producto
                    $unidadesProducto = Inventario::where('producto_id', $detalle->producto_id)->get();
                    foreach ($unidadesProducto as $unidad) {
                        if ($unidad->unidad_medida_id != $unidadSeleccionada->unidad_medida_id) {
                            if ($unidadSeleccionada->equivalencia > 1) {
                                $unidad->existencias = $unidadSeleccionada->existencias / $unidadSeleccionada->equivalencia * $unidad->equivalencia;
                            } else {
                                $unidad->existencias = $unidadSeleccionada->existencias * $unidad->equivalencia;
                            }
                            $unidad->save();
                        }
                    }
                }
            }

            // Eliminar los detalles de la compra
            DetalleCompra::where('compra_id', $id)->delete();

            // Eliminar la compra
            Compra::where('id', $id)->delete();

            // Confirmar la transacción
            DB::commit();

            return response()->json([
                'message' => 'Compra eliminada exitosamente',
            ], 200);
        } catch (\Exception $e) {
            // Revertir la transacción en caso de error
            DB::rollback();
            return response()->json([
                'message' => 'Error al eliminar la compra',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
