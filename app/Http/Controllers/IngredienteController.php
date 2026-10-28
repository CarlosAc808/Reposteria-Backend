<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Services\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IngredienteController extends Controller
{
    /**
     * Listar ingredientes con filtros de categoría, búsqueda, existencias bajas y próximos a caducar.
     */
    public function getApiIngredientes(Request $request)
    {
        $query = Ingrediente::query();

        // Búsqueda por nombre o código
        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('codigo', 'like', "%{$buscar}%")
                  ->orWhere('categoria', 'like', "%{$buscar}%");
            });
        }

        // Filtro por categoría
        if ($request->filled('categoria') && $request->input('categoria') !== 'all') {
            $query->where('categoria', $request->input('categoria'));
        }

        // Filtro por estado activo/inactivo
        if ($request->has('activo')) {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filtro especial: solo existencias bajas
        if ($request->filled('solo_bajas') && $request->input('solo_bajas') == '1') {
            $query->whereColumn('stock_actual', '<=', 'stock_minimo');
        }

        // Filtro especial: próximos a caducar
        if ($request->filled('solo_caducar') && $request->input('solo_caducar') == '1') {
            $hoy = Carbon::today()->toDateString();
            $proximos7Dias = Carbon::today()->addDays(7)->toDateString();
            $query->whereNotNull('fecha_caducidad')
                  ->whereBetween('fecha_caducidad', [$hoy, $proximos7Dias]);
        }

        $ingredientes = $query->orderBy('nombre', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $ingredientes
        ]);
    }

    /**
     * Obtener el detalle de un ingrediente.
     */
    public function getApiIngredienteDetalle($id)
    {
        $ingrediente = Ingrediente::with(['movimientos' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }])->find($id);

        if (!$ingrediente) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado.'
            ], 404);
        }

        // Retornar unidades compatibles con su unidad base
        $unidadesCompatibles = UnitConverter::getCompatibleUnits($ingrediente->unidad_base);

        return response()->json([
            'success' => true,
            'data'    => $ingrediente,
            'unidades_compatibles' => $unidadesCompatibles
        ]);
    }

    /**
     * Registrar un nuevo ingrediente.
     */
    public function postApiCrearIngrediente(Request $request)
    {
        $data = $request->all();

        if (empty($data['nombre']) || empty($data['unidad_base'])) {
            return response()->json([
                'success' => false,
                'message' => 'El nombre y la unidad base son obligatorios.'
            ], 422);
        }

        $ingrediente = new Ingrediente();
        $ingrediente->codigo              = $data['codigo'] ?? null;
        $ingrediente->nombre              = $data['nombre'];
        $ingrediente->categoria           = $data['categoria'] ?? 'Varios';
        $ingrediente->descripcion         = $data['descripcion'] ?? '';
        $ingrediente->unidad_base         = $data['unidad_base'];
        $ingrediente->stock_actual        = isset($data['stock_actual']) ? (float)$data['stock_actual'] : 0;
        $ingrediente->stock_minimo        = isset($data['stock_minimo']) ? (float)$data['stock_minimo'] : 0;
        $ingrediente->costo_unitario      = isset($data['costo_unitario']) ? (float)$data['costo_unitario'] : 0;
        $ingrediente->fecha_ultima_compra = $data['fecha_ultima_compra'] ?? null;
        $ingrediente->fecha_caducidad     = $data['fecha_caducidad'] ?? null;
        $ingrediente->activo              = isset($data['activo']) ? (bool)$data['activo'] : true;
        $ingrediente->imagen              = $data['imagen'] ?? null;
        $ingrediente->save();

        // Si se registró con stock inicial mayor a 0, registrar movimiento de ajuste inicial
        if ($ingrediente->stock_actual > 0) {
            $mov = new MovimientoInventario();
            $mov->ingrediente_id       = $ingrediente->id;
            $mov->tipo_movimiento      = 'ajuste_positivo';
            $mov->cantidad             = $ingrediente->stock_actual;
            $mov->unidad               = $ingrediente->unidad_base;
            $mov->existencia_anterior  = 0;
            $mov->existencia_posterior = $ingrediente->stock_actual;
            $mov->motivo               = 'Inventario inicial al dar de alta el ingrediente.';
            $mov->referencia_tipo      = 'ajuste';
            $mov->referencia_id        = $ingrediente->id;
            $mov->user_id              = $request->user() ? $request->user()->id : null;
            $mov->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Ingrediente registrado exitosamente.',
            'data'    => $ingrediente
        ], 201);
    }

    /**
     * Editar datos de un ingrediente.
     */
    public function putApiEditarIngrediente(Request $request, $id)
    {
        $ingrediente = Ingrediente::find($id);

        if (!$ingrediente) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado.'
            ], 404);
        }

        $data = $request->all();

        if (isset($data['codigo']))              $ingrediente->codigo              = $data['codigo'];
        if (isset($data['nombre']))              $ingrediente->nombre              = $data['nombre'];
        if (isset($data['categoria']))           $ingrediente->categoria           = $data['categoria'];
        if (isset($data['descripcion']))         $ingrediente->descripcion         = $data['descripcion'];
        if (isset($data['unidad_base']))         $ingrediente->unidad_base         = $data['unidad_base'];
        if (isset($data['stock_minimo']))        $ingrediente->stock_minimo        = (float)$data['stock_minimo'];
        if (isset($data['costo_unitario']))      $ingrediente->costo_unitario      = (float)$data['costo_unitario'];
        if (isset($data['fecha_ultima_compra'])) $ingrediente->fecha_ultima_compra = $data['fecha_ultima_compra'];
        if (isset($data['fecha_caducidad']))     $ingrediente->fecha_caducidad     = $data['fecha_caducidad'];
        if (isset($data['activo']))              $ingrediente->activo              = (bool)$data['activo'];
        if (isset($data['imagen']))              $ingrediente->imagen              = $data['imagen'];

        $ingrediente->save();

        return response()->json([
            'success' => true,
            'message' => 'Ingrediente actualizado con éxito.',
            'data'    => $ingrediente
        ]);
    }

    /**
     * Registrar una merma de ingrediente (desperdicio, caducidad, daño).
     */
    public function postApiRegistrarMerma(Request $request, $id)
    {
        $ingrediente = Ingrediente::find($id);

        if (!$ingrediente) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado.'
            ], 404);
        }

        $data = $request->all();

        if (empty($data['cantidad']) || (float)$data['cantidad'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar una cantidad válida mayor a cero.'
            ], 422);
        }

        $unidad = $data['unidad'] ?? $ingrediente->unidad_base;
        $cantidadOriginal = (float)$data['cantidad'];

        // Convertir cantidad a unidad base
        try {
            $cantidadBase = UnitConverter::convert($cantidadOriginal, $unidad, $ingrediente->unidad_base);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        // Validación de no existencia negativa
        if ($ingrediente->stock_actual < $cantidadBase) {
            return response()->json([
                'success' => false,
                'message' => "Existencia insuficiente. Stock actual: {$ingrediente->stock_actual} {$ingrediente->unidad_base}, merma solicitada: {$cantidadBase} {$ingrediente->unidad_base}."
            ], 422);
        }

        $stockAnterior = $ingrediente->stock_actual;
        $stockPosterior = $stockAnterior - $cantidadBase;

        // Descontar inventario
        $ingrediente->stock_actual = $stockPosterior;
        $ingrediente->save();

        // Registrar movimiento
        $mov = new MovimientoInventario();
        $mov->ingrediente_id       = $ingrediente->id;
        $mov->tipo_movimiento      = 'merma';
        $mov->cantidad             = $cantidadBase;
        $mov->unidad               = $ingrediente->unidad_base;
        $mov->existencia_anterior  = $stockAnterior;
        $mov->existencia_posterior = $stockPosterior;
        $mov->motivo               = $data['motivo'] ?? 'Merma reportada en cocina.';
        $mov->referencia_tipo      = 'merma';
        $mov->referencia_id        = null;
        $mov->user_id              = $request->user() ? $request->user()->id : null;
        $mov->save();

        return response()->json([
            'success' => true,
            'message' => 'Merma registrada exitosamente.',
            'stock_actual' => $stockPosterior
        ]);
    }

    /**
     * Registrar un ajuste manual autorizado de inventario (físico o corrección).
     */
    public function postApiAjusteInventario(Request $request, $id)
    {
        $ingrediente = Ingrediente::find($id);

        if (!$ingrediente) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado.'
            ], 404);
        }

        $data = $request->all();

        if (!isset($data['nueva_existencia']) || (float)$data['nueva_existencia'] < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe indicar la nueva existencia válida (no se permiten existencias negativas).'
            ], 422);
        }

        $nuevaExistencia = (float)$data['nueva_existencia'];
        $stockAnterior = $ingrediente->stock_actual;
        $diferencia = $nuevaExistencia - $stockAnterior;

        if ($diferencia == 0) {
            return response()->json([
                'success' => true,
                'message' => 'La existencia ingresada es idéntica a la actual. No se requirió movimiento.'
            ]);
        }

        $tipoMovimiento = $diferencia > 0 ? 'ajuste_positivo' : 'ajuste_negativo';

        // Actualizar stock
        $ingrediente->stock_actual = $nuevaExistencia;
        $ingrediente->save();

        // Registrar movimiento en el historial
        $mov = new MovimientoInventario();
        $mov->ingrediente_id       = $ingrediente->id;
        $mov->tipo_movimiento      = $tipoMovimiento;
        $mov->cantidad             = abs($diferencia);
        $mov->unidad               = $ingrediente->unidad_base;
        $mov->existencia_anterior  = $stockAnterior;
        $mov->existencia_posterior = $nuevaExistencia;
        $mov->motivo               = $data['motivo'] ?? 'Ajuste de inventario físico autorizado.';
        $mov->referencia_tipo      = 'ajuste';
        $mov->referencia_id        = null;
        $mov->user_id              = $request->user() ? $request->user()->id : null;
        $mov->save();

        return response()->json([
            'success' => true,
            'message' => 'Ajuste de inventario aplicado correctamente.',
            'stock_actual' => $nuevaExistencia
        ]);
    }

    /**
     * Consultar historial global o filtrado de movimientos de inventario.
     */
    public function getApiMovimientos(Request $request)
    {
        $query = MovimientoInventario::with(['ingrediente', 'user']);

        if ($request->filled('ingrediente_id')) {
            $query->where('ingrediente_id', $request->input('ingrediente_id'));
        }

        if ($request->filled('tipo_movimiento')) {
            $query->where('tipo_movimiento', $request->input('tipo_movimiento'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $movimientos = $query->orderBy('id', 'desc')->paginate(30);

        return response()->json([
            'success' => true,
            'data'    => $movimientos
        ]);
    }

    /**
     * Desactivar o archivar un ingrediente (no se borra físicamente para preservar el historial).
     */
    public function deleteApiIngrediente($id)
    {
        $ingrediente = Ingrediente::find($id);

        if (!$ingrediente) {
            return response()->json([
                'success' => false,
                'message' => 'Ingrediente no encontrado.'
            ], 404);
        }

        // Si tiene movimientos, desactivarlo para preservar la integridad del historial
        $tieneMovimientos = MovimientoInventario::where('ingrediente_id', $id)->exists();

        if ($tieneMovimientos) {
            $ingrediente->activo = false;
            $ingrediente->save();

            return response()->json([
                'success' => true,
                'message' => 'El ingrediente tiene movimientos históricos. Ha sido desactivado del catálogo para preservar los registros.'
            ]);
        }

        // Si no tiene ningún movimiento, puede borrarse
        $ingrediente->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ingrediente eliminado del catálogo.'
        ]);
    }
}
