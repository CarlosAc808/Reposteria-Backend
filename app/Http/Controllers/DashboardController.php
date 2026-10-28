<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Ingrediente;
use App\Models\Compra;
use App\Models\Costeo;
use App\Models\CosteoDetalle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtener métricas y KPIs calculados en tiempo real desde la base de datos.
     */
    public function getApiDashboardMetrics()
    {
        $hoy = Carbon::today()->toDateString();
        $inicioMes = Carbon::now()->startOfMonth()->toDateString();
        $finMes = Carbon::now()->endOfMonth()->toDateString();
        $proximos7Dias = Carbon::today()->addDays(7)->toDateString();

        // 1. Pedidos pendientes
        $pedidosPendientes = Pedido::where('estado', 'pendiente')->count();

        // 2. Pedidos programados para hoy
        $pedidosHoy = Pedido::where('fecha_entrega', $hoy)
            ->where('estado', '!=', 'cancelado')
            ->count();

        // 3. Pasteles en preparación
        $enPreparacion = Pedido::where('estado', 'en_preparacion')->count();

        // 4. Entregas completadas
        $entregasCompletadas = Pedido::where('estado', 'entregado')->count();

        // 5. Ingredientes con existencias bajas
        $existenciasBajas = Ingrediente::where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->count();

        // 6. Ingredientes próximos a caducar (en los próximos 7 días)
        $proximosCaducar = Ingrediente::where('activo', true)
            ->whereNotNull('fecha_caducidad')
            ->whereBetween('fecha_caducidad', [$hoy, $proximos7Dias])
            ->count();

        // 7. Compras registradas durante el mes
        $comprasMesTotal = Compra::whereBetween('fecha_compra', [$inicioMes, $finMes])
            ->sum('importe_total');

        // 8. Costo acumulado de los costeos guardados durante el mes
        $costeosMesTotal = Costeo::where('estado', 'confirmado')
            ->whereBetween('created_at', [
                Carbon::now()->startOfMonth()->toDateTimeString(),
                Carbon::now()->endOfMonth()->toDateTimeString()
            ])
            ->sum('costo_total');

        // Gráfica 1: Pedidos por estado
        $pedidosPorEstado = Pedido::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get();

        // Gráfica 2: Pedidos en los últimos 7 días
        $pedidosUltimosDias = Pedido::select(DB::raw('DATE(fecha_entrega) as fecha'), DB::raw('count(*) as total'))
            ->where('fecha_entrega', '>=', Carbon::today()->subDays(6)->toDateString())
            ->groupBy('fecha')
            ->orderBy('fecha', 'asc')
            ->get();

        // Gráfica 3: Ingredientes más consumidos en costeos
        $ingredientesConsumidos = CosteoDetalle::join('ingredientes', 'costeo_detalles.ingrediente_id', '=', 'ingredientes.id')
            ->select('ingredientes.nombre', DB::raw('SUM(costeo_detalles.cantidad_base) as total_consumido'), 'ingredientes.unidad_base')
            ->groupBy('ingredientes.id', 'ingredientes.nombre', 'ingredientes.unidad_base')
            ->orderBy('total_consumido', 'desc')
            ->limit(5)
            ->get();

        // Gráfica 4: Costos de producción registrados (últimos 6 meses)
        $costosProduccionMeses = Costeo::where('estado', 'confirmado')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as periodo"),
                DB::raw('SUM(costo_total) as total_costo')
            )
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('periodo')
            ->orderBy('periodo', 'asc')
            ->get();

        // Gráfica 5: Evolución de los gastos en compras (últimos 6 meses)
        $gastosComprasMeses = Compra::select(
                DB::raw("DATE_FORMAT(fecha_compra, '%Y-%m') as periodo"),
                DB::raw('SUM(importe_total) as total_gasto')
            )
            ->where('fecha_compra', '>=', Carbon::now()->subMonths(5)->startOfMonth()->toDateString())
            ->groupBy('periodo')
            ->orderBy('periodo', 'asc')
            ->get();

        // Alertas inmediatas para mostrar en el banner del dashboard
        $alertasBajas = Ingrediente::where('activo', true)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->limit(3)
            ->get(['id', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_base']);

        $alertasCaducidad = Ingrediente::where('activo', true)
            ->whereNotNull('fecha_caducidad')
            ->whereBetween('fecha_caducidad', [$hoy, $proximos7Dias])
            ->limit(3)
            ->get(['id', 'nombre', 'fecha_caducidad', 'stock_actual', 'unidad_base']);

        $pedidosUrgentesHoy = Pedido::where('fecha_entrega', $hoy)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])
            ->orderBy('hora_entrega', 'asc')
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'kpis' => [
                'pedidos_pendientes'    => $pedidosPendientes,
                'pedidos_hoy'           => $pedidosHoy,
                'en_preparacion'        => $enPreparacion,
                'entregas_completadas'  => $entregasCompletadas,
                'existencias_bajas'     => $existenciasBajas,
                'proximos_caducar'      => $proximosCaducar,
                'compras_mes'           => round((float)$comprasMesTotal, 2),
                'costeo_mes'            => round((float)$costeosMesTotal, 2),
            ],
            'graficas' => [
                'pedidos_por_estado'    => $pedidosPorEstado,
                'pedidos_ultimos_dias'  => $pedidosUltimosDias,
                'ingredientes_top'      => $ingredientesConsumidos,
                'costos_produccion'     => $costosProduccionMeses,
                'gastos_compras'        => $gastosComprasMeses,
            ],
            'alertas' => [
                'stock_critico'         => $alertasBajas,
                'proximos_caducar'      => $alertasCaducidad,
                'pedidos_hoy'           => $pedidosUrgentesHoy,
            ]
        ]);
    }

    /**
     * Exportar reporte mensual completo en JSON o archivo CSV compatible con Excel.
     */
    public function getApiExportData(Request $request)
    {
        $mes = $request->query('mes', Carbon::now()->format('Y-m'));
        try {
            $carbonMes = Carbon::createFromFormat('Y-m', $mes);
        } catch (\Exception $e) {
            $carbonMes = Carbon::now();
            $mes = $carbonMes->format('Y-m');
        }

        $inicioMes = $carbonMes->copy()->startOfMonth()->toDateString();
        $finMes = $carbonMes->copy()->endOfMonth()->toDateString();

        $compras = Compra::whereBetween('fecha_compra', [$inicioMes, $finMes])
            ->with('detalles.ingrediente')
            ->orderBy('fecha_compra', 'desc')
            ->get();

        $costeos = Costeo::whereBetween('created_at', [$inicioMes . ' 00:00:00', $finMes . ' 23:59:59'])
            ->with('detalles.ingrediente')
            ->orderBy('created_at', 'desc')
            ->get();

        $pedidos = Pedido::whereBetween('fecha_entrega', [$inicioMes, $finMes])
            ->orderBy('fecha_entrega', 'desc')
            ->get();

        $resumen = [
            'periodo' => $mes,
            'inicio' => $inicioMes,
            'fin' => $finMes,
            'total_compras' => round((float)$compras->sum('importe_total'), 2),
            'total_costeos' => round((float)$costeos->sum('costo_total'), 2),
            'total_pedidos' => $pedidos->count(),
            'pedidos_entregados' => $pedidos->where('estado', 'entregado')->count(),
            'pedidos_pendientes' => $pedidos->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])->count(),
        ];

        // Si se solicita descarga directa en CSV para Excel
        if ($request->query('format') === 'csv') {
            $filename = "reporte_atelier_dulce_{$mes}.csv";
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($resumen, $compras, $costeos, $pedidos, $mes) {
                $fh = fopen('php://output', 'w');
                // UTF-8 BOM para que Excel en Windows reconozca caracteres y acentos
                fprintf($fh, chr(0xEF).chr(0xBB).chr(0xBF));

                fputcsv($fh, ['ATELIER DULCE - REPORTE MENSUAL OPERATIVO']);
                fputcsv($fh, ['Periodo:', $mes]);
                fputcsv($fh, ['Generado el:', Carbon::now()->toDateTimeString()]);
                fputcsv($fh, []);

                // Resumen
                fputcsv($fh, ['--- RESUMEN GENERAL ---']);
                fputcsv($fh, ['Concepto', 'Total']);
                fputcsv($fh, ['Total Inversión en Compras ($)', $resumen['total_compras']]);
                fputcsv($fh, ['Total Costos de Producción ($)', $resumen['total_costeos']]);
                fputcsv($fh, ['Total Pedidos Registrados', $resumen['total_pedidos']]);
                fputcsv($fh, ['Pedidos Entregados con Éxito', $resumen['pedidos_entregados']]);
                fputcsv($fh, ['Pedidos Pendientes / En Proceso', $resumen['pedidos_pendientes']]);
                fputcsv($fh, []);

                // Compras
                fputcsv($fh, ['--- COMPRAS DEL MES ---']);
                fputcsv($fh, ['Folio Ticket', 'Establecimiento / Proveedor', 'Fecha', 'Importe Total ($)', 'Observaciones']);
                foreach ($compras as $c) {
                    fputcsv($fh, [
                        $c->folio_ticket,
                        $c->establecimiento,
                        $c->fecha_compra ? $c->fecha_compra->format('Y-m-d') : '',
                        number_format($c->importe_total, 2, '.', ''),
                        $c->observaciones ?? ''
                    ]);
                }
                fputcsv($fh, []);

                // Costeos
                fputcsv($fh, ['--- COSTEOS Y PRODUCCIÓN DEL MES ---']);
                fputcsv($fh, ['Folio', 'Preparación / Pastel', 'Fecha', 'Porciones', 'Costo Total ($)', 'Costo por Porción ($)', 'Estado']);
                foreach ($costeos as $cost) {
                    fputcsv($fh, [
                        $cost->folio,
                        $cost->nombre_preparacion,
                        $cost->created_at ? $cost->created_at->format('Y-m-d H:i') : '',
                        $cost->porciones,
                        number_format($cost->costo_total, 2, '.', ''),
                        number_format($cost->costo_por_porcion, 2, '.', ''),
                        $cost->estado
                    ]);
                }
                fputcsv($fh, []);

                // Pedidos
                fputcsv($fh, ['--- PEDIDOS DEL MES ---']);
                fputcsv($fh, ['Folio', 'Cliente', 'Teléfono', 'Fecha Entrega', 'Hora Entrega', 'Pastel / Descripción', 'Estado', 'Precio Sugerido ($)']);
                foreach ($pedidos as $p) {
                    fputcsv($fh, [
                        $p->folio,
                        $p->cliente_nombre,
                        $p->cliente_telefono ?? '',
                        $p->fecha_entrega ? $p->fecha_entrega->format('Y-m-d') : '',
                        $p->hora_entrega ?? '',
                        $p->descripcion ?? ($p->sabor . ' - ' . $p->tamano),
                        $p->estado,
                        number_format($p->precio_sugerido ?? 0, 2, '.', '')
                    ]);
                }

                fclose($fh);
            };

            return response()->stream($callback, 200, $headers);
        }

        return response()->json([
            'success' => true,
            'resumen' => $resumen,
            'compras' => $compras,
            'costeos' => $costeos,
            'pedidos' => $pedidos,
        ]);
    }
}
