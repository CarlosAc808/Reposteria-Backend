<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Ingrediente;
use App\Models\MovimientoInventario;
use App\Services\UnitConverter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    /**
     * Listar compras registradas con búsqueda y filtros.
     */
    public function getApiCompras(Request $request)
    {
        $query = Compra::with(['user', 'detalles.ingrediente']);

        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('establecimiento', 'like', "%{$buscar}%")
                  ->orWhere('folio_ticket', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_compra', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_compra', '<=', $request->input('fecha_hasta'));
        }

        $compras = $query->orderBy('fecha_compra', 'desc')
                         ->orderBy('id', 'desc')
                         ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $compras
        ]);
    }

    /**
     * Obtener el detalle de una compra específica.
     */
    public function getApiCompraDetalle($id)
    {
        $compra = Compra::with(['user', 'detalles.ingrediente'])->find($id);

        if (!$compra) {
            return response()->json([
                'success' => false,
                'message' => 'Compra no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $compra
        ]);
    }

    /**
     * Registrar una nueva compra y actualizar el inventario.
     */
    public function postApiRegistrarCompra(Request $request)
    {
        $data = $request->all();

        // Validaciones básicas
        if (empty($data['establecimiento']) || empty($data['fecha_compra'])) {
            return response()->json([
                'success' => false,
                'message' => 'El establecimiento y la fecha de compra son obligatorios.'
            ], 422);
        }

        $items = $data['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe registrar al menos un producto o ingrediente en la compra.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 1. Instanciamos el objeto Compra
            $compra = new Compra();
            $compra->folio_ticket    = $data['folio_ticket'] ?? null;
            $compra->establecimiento = $data['establecimiento'];
            $compra->fecha_compra    = $data['fecha_compra'];
            $compra->observaciones   = $data['observaciones'] ?? '';
            $compra->user_id         = $request->user() ? $request->user()->id : null;
            $compra->importe_total   = 0; // Se calculará con los detalles
            $compra->save();

            $totalAcumulado = 0;

            // 2. Procesamos cada producto y actualizamos el inventario
            foreach ($items as $item) {
                if (empty($item['ingrediente_id']) || empty($item['cantidad']) || (float)$item['cantidad'] <= 0) {
                    continue;
                }

                $ingrediente = Ingrediente::where('id', $item['ingrediente_id'])->lockForUpdate()->first();
                if (!$ingrediente) {
                    throw new \Exception("Ingrediente ID {$item['ingrediente_id']} no existe en el catálogo.");
                }

                $cantidad = (float)$item['cantidad'];
                $unidad = $item['unidad'] ?? $ingrediente->unidad_base;
                $costoUnitario = isset($item['costo_unitario']) ? (float)$item['costo_unitario'] : $ingrediente->costo_unitario;

                // Subtotal del ítem
                $subtotal = isset($item['subtotal']) && (float)$item['subtotal'] > 0 
                    ? (float)$item['subtotal'] 
                    : round($cantidad * $costoUnitario, 2);

                $totalAcumulado += $subtotal;

                // Normalizar la cantidad a la unidad base del ingrediente
                $cantidadBase = UnitConverter::convert($cantidad, $unidad, $ingrediente->unidad_base);

                // Calcular el nuevo costo por unidad base para actualizar el catálogo
                $costoPorUnidadBase = $cantidadBase > 0 ? round($subtotal / $cantidadBase, 4) : $ingrediente->costo_unitario;

                // Guardar detalle de compra
                $detalle = new CompraDetalle();
                $detalle->compra_id      = $compra->id;
                $detalle->ingrediente_id = $ingrediente->id;
                $detalle->cantidad       = $cantidad;
                $detalle->unidad         = $unidad;
                $detalle->cantidad_base  = $cantidadBase;
                $detalle->costo_unitario = $costoUnitario;
                $detalle->subtotal       = $subtotal;
                $detalle->save();

                // Conectar con el inventario: incrementar stock y actualizar precios/fechas
                $stockAnterior = $ingrediente->stock_actual;
                $stockPosterior = $stockAnterior + $cantidadBase;

                $ingrediente->stock_actual        = $stockPosterior;
                $ingrediente->costo_unitario      = $costoPorUnidadBase;
                $ingrediente->fecha_ultima_compra = $compra->fecha_compra;
                $ingrediente->save();

                // Registrar movimiento en el historial
                $mov = new MovimientoInventario();
                $mov->ingrediente_id       = $ingrediente->id;
                $mov->tipo_movimiento      = 'entrada_compra';
                $mov->cantidad             = $cantidadBase;
                $mov->unidad               = $ingrediente->unidad_base;
                $mov->existencia_anterior  = $stockAnterior;
                $mov->existencia_posterior = $stockPosterior;
                $mov->motivo               = "Compra en {$compra->establecimiento}" . ($compra->folio_ticket ? " (Ticket: {$compra->folio_ticket})" : '');
                $mov->referencia_tipo      = 'compra';
                $mov->referencia_id        = $compra->id;
                $mov->user_id              = $request->user() ? $request->user()->id : null;
                $mov->save();
            }

            // Actualizar importe total final
            $compra->importe_total = isset($data['importe_total']) && (float)$data['importe_total'] > 0
                ? (float)$data['importe_total']
                : round($totalAcumulado, 2);
            $compra->save();

            DB::commit();

            $compra->load('detalles.ingrediente');

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente y stock de inventario incrementado.',
                'data'    => $compra
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la compra: ' . $e->getMessage()
            ], 422);
        }
    }
}
