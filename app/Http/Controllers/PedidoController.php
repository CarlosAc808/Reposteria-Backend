<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use Carbon\Carbon;

class PedidoController extends Controller
{
    /**
     * Listar todos los pedidos con filtros y búsqueda.
     */
    public function getApiPedidos(Request $request)
    {
        $query = Pedido::query();

        // Búsqueda por cliente o folio
        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('cliente_nombre', 'like', "%{$buscar}%")
                  ->orWhere('folio', 'like', "%{$buscar}%")
                  ->orWhere('cliente_telefono', 'like', "%{$buscar}%");
            });
        }

        // Filtro por estado
        if ($request->filled('estado') && $request->input('estado') !== 'todos') {
            $query->where('estado', $request->input('estado'));
        }

        // Filtro por fecha de entrega desde
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_entrega', '>=', $request->input('fecha_desde'));
        }

        // Filtro por fecha de entrega hasta
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_entrega', '<=', $request->input('fecha_hasta'));
        }

        // Ordenar por fecha y hora de entrega
        $pedidos = $query->orderBy('fecha_entrega', 'asc')
                         ->orderBy('hora_entrega', 'asc')
                         ->get();

        return response()->json([
            'success' => true,
            'data'    => $pedidos
        ]);
    }

    /**
     * Obtener el detalle de un pedido por ID.
     */
    public function getApiPedidoDetalle($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $pedido
        ]);
    }

    /**
     * Crear un nuevo pedido.
     */
    public function postApiCrearPedido(Request $request)
    {
        $data = $request->all();

        // Validaciones requeridas
        if (empty($data['cliente_nombre']) || empty($data['fecha_entrega']) || empty($data['hora_entrega'])) {
            return response()->json([
                'success' => false,
                'message' => 'El nombre del cliente, fecha y hora de entrega son obligatorios.'
            ], 422);
        }

        // Validación de fechas lógicas
        $fechaEntrega = Carbon::parse($data['fecha_entrega']);
        $fechaRegistro = !empty($data['fecha_registro']) ? Carbon::parse($data['fecha_registro']) : Carbon::today();

        if ($fechaEntrega->lt($fechaRegistro->subDay())) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha de entrega no puede ser anterior a la fecha de registro.'
            ], 422);
        }

        // Generar folio único si no viene en los datos
        $folio = !empty($data['folio']) ? $data['folio'] : 'PED-' . date('Ymd') . '-' . str_pad(Pedido::count() + 1, 3, '0', STR_PAD_LEFT);

        // Instanciamos el objeto Pedido
        $pedido = new Pedido();

        // Se asignan los parámetros de la petición al objeto
        $pedido->folio            = $folio;
        $pedido->cliente_nombre   = $data['cliente_nombre'];
        $pedido->cliente_telefono = $data['cliente_telefono'] ?? '';
        $pedido->fecha_registro   = $data['fecha_registro'] ?? Carbon::today()->toDateString();
        $pedido->fecha_entrega    = $data['fecha_entrega'];
        $pedido->hora_entrega     = $data['hora_entrega'];
        $pedido->descripcion      = $data['descripcion'] ?? '';
        $pedido->sabor            = $data['sabor'] ?? 'Vainilla Clásica';
        $pedido->tamano           = $data['tamano'] ?? 'Mediano (15-20 personas)';
        $pedido->relleno          = $data['relleno'] ?? '';
        $pedido->decoracion_tipo  = $data['decoracion_tipo'] ?? '';
        $pedido->dedicatoria      = $data['dedicatoria'] ?? '';
        $pedido->foto_referencia  = $data['foto_referencia'] ?? null;
        $pedido->cantidad         = isset($data['cantidad']) ? (int)$data['cantidad'] : 1;
        $pedido->precio_sugerido  = $data['precio_sugerido'] ?? null;
        $pedido->observaciones    = $data['observaciones'] ?? '';
        $pedido->estado           = $data['estado'] ?? 'pendiente';

        // Se ejecuta el método save para agregar el registro
        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido registrado exitosamente.',
            'data'    => $pedido
        ], 201);
    }

    /**
     * Editar información de un pedido existente.
     */
    public function putApiEditarPedido(Request $request, $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.'
            ], 404);
        }

        $data = $request->all();

        // Actualizamos los campos recibidos
        if (isset($data['cliente_nombre']))   $pedido->cliente_nombre   = $data['cliente_nombre'];
        if (isset($data['cliente_telefono'])) $pedido->cliente_telefono = $data['cliente_telefono'];
        if (isset($data['fecha_entrega']))    $pedido->fecha_entrega    = $data['fecha_entrega'];
        if (isset($data['hora_entrega']))     $pedido->hora_entrega     = $data['hora_entrega'];
        if (isset($data['descripcion']))      $pedido->descripcion      = $data['descripcion'];
        if (isset($data['sabor']))            $pedido->sabor            = $data['sabor'];
        if (isset($data['tamano']))           $pedido->tamano           = $data['tamano'];
        if (isset($data['relleno']))          $pedido->relleno          = $data['relleno'];
        if (isset($data['decoracion_tipo']))  $pedido->decoracion_tipo  = $data['decoracion_tipo'];
        if (isset($data['dedicatoria']))      $pedido->dedicatoria      = $data['dedicatoria'];
        if (isset($data['foto_referencia']))  $pedido->foto_referencia  = $data['foto_referencia'];
        if (isset($data['cantidad']))         $pedido->cantidad         = (int)$data['cantidad'];
        if (isset($data['precio_sugerido']))  $pedido->precio_sugerido  = $data['precio_sugerido'];
        if (isset($data['observaciones']))    $pedido->observaciones    = $data['observaciones'];

        // Cambio de estado
        if (isset($data['estado'])) {
            $nuevoEstado = $data['estado'];
            $pedido->estado = $nuevoEstado;

            // Al pasar a 'entregado', registrar la fecha y hora de entrega real
            if ($nuevoEstado === 'entregado' && empty($pedido->fecha_entrega_real)) {
                $pedido->fecha_entrega_real = Carbon::now();
            }
        }

        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido actualizado con éxito.',
            'data'    => $pedido
        ]);
    }

    /**
     * Cambiar de forma rápida el estado de un pedido.
     */
    public function patchApiCambiarEstado(Request $request, $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.'
            ], 404);
        }

        $nuevoEstado = $request->input('estado');
        $estadosPermitidos = ['pendiente', 'en_preparacion', 'listo', 'entregado', 'cancelado'];

        if (!in_array($nuevoEstado, $estadosPermitidos)) {
            return response()->json([
                'success' => false,
                'message' => 'Estado no válido.'
            ], 422);
        }

        $pedido->estado = $nuevoEstado;

        // Registrar hora real si es entregado, o limpiar si se revierte de estado
        if ($nuevoEstado === 'entregado') {
            $pedido->fecha_entrega_real = Carbon::now();
        } else {
            $pedido->fecha_entrega_real = null;
        }

        $pedido->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del pedido actualizado a: ' . $nuevoEstado,
            'data'    => $pedido
        ]);
    }

    /**
     * Obtener pedidos en formato para vista de calendario y alertas de entregas próximas.
     */
    public function getApiCalendarioAlertas()
    {
        $hoy = Carbon::today()->toDateString();
        $ahora = Carbon::now();
        $enDosHoras = Carbon::now()->addHours(2);

        // Entregas para hoy
        $pedidosHoy = Pedido::where('fecha_entrega', $hoy)
                            ->where('estado', '!=', 'cancelado')
                            ->orderBy('hora_entrega', 'asc')
                            ->get();

        // Entregas en las próximas 2 horas (urgentes)
        $alertasProximas = Pedido::where('fecha_entrega', $hoy)
                                 ->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])
                                 ->whereBetween('hora_entrega', [
                                     $ahora->format('H:i:s'),
                                     $enDosHoras->format('H:i:s')
                                 ])
                                 ->get();

        // Todos los eventos para el calendario mensual
        $eventos = Pedido::select('id', 'folio', 'cliente_nombre', 'fecha_entrega', 'hora_entrega', 'sabor', 'tamano', 'estado')
                         ->orderBy('fecha_entrega', 'asc')
                         ->get();

        return response()->json([
            'success' => true,
            'pedidos_hoy'      => $pedidosHoy,
            'alertas_urgentes' => $alertasProximas,
            'eventos'          => $eventos
        ]);
    }

    /**
     * Eliminar un pedido.
     */
    public function deleteApiPedido($id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado.'
            ], 404);
        }

        $pedido->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pedido eliminado correctamente.'
        ]);
    }
}
