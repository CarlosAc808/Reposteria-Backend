<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Costeo;
use App\Models\CosteoDetalle;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Services\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CosteoController extends Controller
{
    /**
     * Calcular en tiempo real el costo de una lista de ingredientes sin tocar inventario.
     */
    public function postApiSimularCosto(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => true,
                'items_calculados' => [],
                'costo_total'      => 0
            ]);
        }

        $itemsCalculados = [];
        $costoTotal = 0;

        foreach ($items as $item) {
            if (empty($item['ingrediente_id']) || empty($item['cantidad'])) {
                continue;
            }

            $ingrediente = Ingrediente::find($item['ingrediente_id']);
            if (!$ingrediente) {
                continue;
            }

            $cantidad = (float)$item['cantidad'];
            $unidad = $item['unidad'] ?? $ingrediente->unidad_base;

            try {
                // Convertir la cantidad a la unidad base del ingrediente
                $cantidadBase = UnitConverter::convert($cantidad, $unidad, $ingrediente->unidad_base);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => "Error en '{$ingrediente->nombre}': " . $e->getMessage()
                ], 422);
            }

            // Costo = cantidad en unidad base * costo de referencia por unidad base
            $costoSubtotal = round($cantidadBase * $ingrediente->costo_unitario, 2);
            $costoTotal += $costoSubtotal;

            $itemsCalculados[] = [
                'ingrediente_id'          => $ingrediente->id,
                'nombre'                  => $ingrediente->nombre,
                'unidad_base'             => $ingrediente->unidad_base,
                'stock_actual'            => $ingrediente->stock_actual,
                'costo_unitario_base'     => $ingrediente->costo_unitario,
                'cantidad_ingresada'      => $cantidad,
                'unidad_ingresada'        => $unidad,
                'cantidad_base'           => $cantidadBase,
                'costo_calculado'         => $costoSubtotal,
                'stock_suficiente'        => ($ingrediente->stock_actual >= $cantidadBase)
            ];
        }

        return response()->json([
            'success'          => true,
            'items_calculados' => $itemsCalculados,
            'costo_total'      => round($costoTotal, 2)
        ]);
    }

    /**
     * Botón "Guardar y descontar inventario":
     * Guarda el costeo con precios congelados y descuenta el inventario atómicamente.
     */
    public function postApiGuardarYDescontar(Request $request)
    {
        $data = $request->all();
        $items = $data['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe agregar al menos un ingrediente para realizar el costeo.'
            ], 422);
        }

        // Ejecutar todo en una transacción de base de datos
        DB::beginTransaction();

        try {
            // Paso 1: Validar existencias y preparar cálculos
            $detallesParaGuardar = [];
            $costoTotal = 0;

            foreach ($items as $item) {
                if (empty($item['ingrediente_id']) || empty($item['cantidad']) || (float)$item['cantidad'] <= 0) {
                    continue;
                }

                // Bloqueo de fila para evitar condiciones de carrera (concurrency)
                $ingrediente = Ingrediente::where('id', $item['ingrediente_id'])->lockForUpdate()->first();

                if (!$ingrediente) {
                    throw new \Exception("El ingrediente ID {$item['ingrediente_id']} no existe en el catálogo.");
                }

                $cantidad = (float)$item['cantidad'];
                $unidad = $item['unidad'] ?? $ingrediente->unidad_base;

                // Conversión controlada
                $cantidadBase = UnitConverter::convert($cantidad, $unidad, $ingrediente->unidad_base);

                // Regla obligatoria: No permitir existencias negativas
                if ($ingrediente->stock_actual < $cantidadBase) {
                    throw new \Exception("Stock insuficiente para '{$ingrediente->nombre}'. Disponible: {$ingrediente->stock_actual} {$ingrediente->unidad_base}, requerido: {$cantidadBase} {$ingrediente->unidad_base}. Operación cancelada, el inventario no fue modificado.");
                }

                // Costo snapshot
                $subtotal = round($cantidadBase * $ingrediente->costo_unitario, 2);
                $costoTotal += $subtotal;

                $detallesParaGuardar[] = [
                    'ingrediente'   => $ingrediente,
                    'cantidad'      => $cantidad,
                    'unidad'        => $unidad,
                    'cantidad_base' => $cantidadBase,
                    'costo_unitario'=> $ingrediente->costo_unitario,
                    'subtotal'      => $subtotal,
                ];
            }

            if (empty($detallesParaGuardar)) {
                throw new \Exception('No se encontraron ingredientes válidos en la lista.');
            }

            // Paso 2: Crear el registro de Costeo (Cabecera)
            $folio = 'CST-' . date('Ymd') . '-' . str_pad(Costeo::count() + 1, 3, '0', STR_PAD_LEFT);
            $porciones = isset($data['porciones']) && (int)$data['porciones'] > 0 ? (int)$data['porciones'] : 1;

            $costeo = new Costeo();
            $costeo->folio              = $folio;
            $costeo->nombre_preparacion = $data['nombre_preparacion'] ?? 'Preparación Libre';
            $costeo->user_id            = $request->user() ? $request->user()->id : null;
            $costeo->costo_total        = round($costoTotal, 2);
            $costeo->porciones          = $porciones;
            $costeo->costo_por_porcion  = round($costoTotal / $porciones, 2);
            $costeo->estado             = 'confirmado';
            $costeo->observaciones      = $data['observaciones'] ?? '';
            $costeo->save();

            // Paso 3: Guardar detalles y descontar inventario registrando movimientos
            foreach ($detallesParaGuardar as $det) {
                $ing = $det['ingrediente'];
                $stockAnterior = $ing->stock_actual;
                $stockPosterior = $stockAnterior - $det['cantidad_base'];

                // Descontar inventario
                $ing->stock_actual = $stockPosterior;
                $ing->save();

                // Guardar detalle con snapshot del costo
                $costeoDetalle = new CosteoDetalle();
                $costeoDetalle->costeo_id               = $costeo->id;
                $costeoDetalle->ingrediente_id          = $ing->id;
                $costeoDetalle->cantidad                = $det['cantidad'];
                $costeoDetalle->unidad                  = $det['unidad'];
                $costeoDetalle->cantidad_base           = $det['cantidad_base'];
                $costeoDetalle->costo_unitario_snapshot = $det['costo_unitario'];
                $costeoDetalle->costo_subtotal          = $det['subtotal'];
                $costeoDetalle->save();

                // Registrar movimiento en el historial
                $mov = new MovimientoInventario();
                $mov->ingrediente_id       = $ing->id;
                $mov->tipo_movimiento      = 'salida_costeo';
                $mov->cantidad             = $det['cantidad_base'];
                $mov->unidad               = $ing->unidad_base;
                $mov->existencia_anterior  = $stockAnterior;
                $mov->existencia_posterior = $stockPosterior;
                $mov->motivo               = "Consumo por costeo #{$costeo->folio} ({$costeo->nombre_preparacion})";
                $mov->referencia_tipo      = 'costeo';
                $mov->referencia_id        = $costeo->id;
                $mov->user_id              = $request->user() ? $request->user()->id : null;
                $mov->save();
            }

            DB::commit();

            // Cargar relaciones para la respuesta
            $costeo->load('detalles.ingrediente');

            return response()->json([
                'success' => true,
                'message' => "Costeo guardado exitosamente. Folio: {$costeo->folio}. Se descontaron {$costeo->detalles->count()} ingredientes del inventario.",
                'data'    => $costeo
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Listar historial de costeos guardados.
     */
    public function getApiHistorialCosteos(Request $request)
    {
        $query = Costeo::with(['user', 'detalles.ingrediente']);

        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('folio', 'like', "%{$buscar}%")
                  ->orWhere('nombre_preparacion', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $costeos = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $costeos
        ]);
    }

    /**
     * Ver el detalle completo de un costeo histórico.
     */
    public function getApiCosteoDetalle($id)
    {
        $costeo = Costeo::with(['user', 'detalles.ingrediente', 'movimientos'])->find($id);

        if (!$costeo) {
            return response()->json([
                'success' => false,
                'message' => 'Costeo no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $costeo
        ]);
    }

    /**
     * Reversión autorizada de un costeo confirmado:
     * Devuelve los ingredientes al inventario y cambia el estado a revertido.
     */
    public function postApiRevertirCosteo(Request $request, $id)
    {
        $costeo = Costeo::with('detalles.ingrediente')->find($id);

        if (!$costeo) {
            return response()->json([
                'success' => false,
                'message' => 'Costeo no encontrado.'
            ], 404);
        }

        if ($costeo->estado === 'revertido') {
            return response()->json([
                'success' => false,
                'message' => 'Este costeo ya se encuentra revertido anteriormente.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($costeo->detalles as $detalle) {
                $ing = Ingrediente::where('id', $detalle->ingrediente_id)->lockForUpdate()->first();
                if ($ing) {
                    $stockAnterior = $ing->stock_actual;
                    $stockPosterior = $stockAnterior + $detalle->cantidad_base;

                    $ing->stock_actual = $stockPosterior;
                    $ing->save();

                    // Registrar movimiento de reversión
                    $mov = new MovimientoInventario();
                    $mov->ingrediente_id       = $ing->id;
                    $mov->tipo_movimiento      = 'reversion_costeo';
                    $mov->cantidad             = $detalle->cantidad_base;
                    $mov->unidad               = $ing->unidad_base;
                    $mov->existencia_anterior  = $stockAnterior;
                    $mov->existencia_posterior = $stockPosterior;
                    $mov->motivo               = "Reversión autorizada del costeo #{$costeo->folio}";
                    $mov->referencia_tipo      = 'costeo';
                    $mov->referencia_id        = $costeo->id;
                    $mov->user_id              = $request->user() ? $request->user()->id : null;
                    $mov->save();
                }
            }

            $costeo->estado = 'revertido';
            $costeo->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Costeo #{$costeo->folio} revertido con éxito. Los ingredientes fueron reingresados al inventario."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al revertir el costeo: ' . $e->getMessage()
            ], 500);
        }
    }
}
