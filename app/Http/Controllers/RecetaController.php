<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receta;
use App\Models\RecetaIngrediente;

class RecetaController extends Controller
{
    /**
     * Listar recetas con búsqueda por nombre.
     */
    public function getApiRecetas(Request $request)
    {
        $query = Receta::with('ingredientes');

        if ($request->filled('buscar')) {
            $buscar = $request->input('buscar');
            $query->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('sabor', 'like', "%{$buscar}%");
        }

        $recetas = $query->orderBy('nombre', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $recetas
        ]);
    }

    /**
     * Obtener el detalle de una receta específica.
     */
    public function getApiRecetaDetalle($id)
    {
        $receta = Receta::with('ingredientes')->find($id);

        if (!$receta) {
            return response()->json([
                'success' => false,
                'message' => 'Receta no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $receta
        ]);
    }

    /**
     * Crear una nueva receta con sus ingredientes.
     */
    public function postApiCrearReceta(Request $request)
    {
        $data = $request->all();

        if (empty($data['nombre'])) {
            return response()->json([
                'success' => false,
                'message' => 'El nombre de la receta es obligatorio.'
            ], 422);
        }

        // Instanciamos el objeto Receta
        $receta = new Receta();
        $receta->nombre             = $data['nombre'];
        $receta->fotografia         = $data['fotografia'] ?? null;
        $receta->descripcion        = $data['descripcion'] ?? '';
        $receta->sabor              = $data['sabor'] ?? '';
        $receta->tamano_rendimiento = $data['tamano_rendimiento'] ?? '';
        $receta->tiempo_preparacion = isset($data['tiempo_preparacion']) ? (int)$data['tiempo_preparacion'] : 0;
        $receta->tiempo_horneado    = isset($data['tiempo_horneado']) ? (int)$data['tiempo_horneado'] : 0;
        $receta->instrucciones      = $data['instrucciones'] ?? '';
        $receta->save();

        // Guardar los ingredientes si vienen en la petición
        if (!empty($data['ingredientes']) && is_array($data['ingredientes'])) {
            foreach ($data['ingredientes'] as $item) {
                if (!empty($item['nombre_ingrediente']) && !empty($item['cantidad'])) {
                    $ingrediente = new RecetaIngrediente();
                    $ingrediente->receta_id          = $receta->id;
                    $ingrediente->ingrediente_id     = $item['ingrediente_id'] ?? null;
                    $ingrediente->nombre_ingrediente = $item['nombre_ingrediente'];
                    $ingrediente->cantidad           = (float)$item['cantidad'];
                    $ingrediente->unidad             = $item['unidad'] ?? 'g';
                    $ingrediente->save();
                }
            }
        }

        // Cargar ingredientes para devolver la receta completa
        $receta->load('ingredientes');

        return response()->json([
            'success' => true,
            'message' => 'Receta creada exitosamente.',
            'data'    => $receta
        ], 201);
    }

    /**
     * Actualizar una receta existente y sus ingredientes.
     */
    public function putApiEditarReceta(Request $request, $id)
    {
        $receta = Receta::find($id);

        if (!$receta) {
            return response()->json([
                'success' => false,
                'message' => 'Receta no encontrada.'
            ], 404);
        }

        $data = $request->all();

        if (isset($data['nombre']))             $receta->nombre             = $data['nombre'];
        if (isset($data['fotografia']))         $receta->fotografia         = $data['fotografia'];
        if (isset($data['descripcion']))        $receta->descripcion        = $data['descripcion'];
        if (isset($data['sabor']))              $receta->sabor              = $data['sabor'];
        if (isset($data['tamano_rendimiento'])) $receta->tamano_rendimiento = $data['tamano_rendimiento'];
        if (isset($data['tiempo_preparacion'])) $receta->tiempo_preparacion = (int)$data['tiempo_preparacion'];
        if (isset($data['tiempo_horneado']))    $receta->tiempo_horneado    = (int)$data['tiempo_horneado'];
        if (isset($data['instrucciones']))      $receta->instrucciones      = $data['instrucciones'];
        $receta->save();

        // Si se enviaron ingredientes, actualizamos la lista
        if (isset($data['ingredientes']) && is_array($data['ingredientes'])) {
            // Eliminamos los anteriores
            RecetaIngrediente::where('receta_id', $receta->id)->delete();

            // Guardamos los nuevos
            foreach ($data['ingredientes'] as $item) {
                if (!empty($item['nombre_ingrediente']) && !empty($item['cantidad'])) {
                    $ing = new RecetaIngrediente();
                    $ing->receta_id          = $receta->id;
                    $ing->ingrediente_id     = $item['ingrediente_id'] ?? null;
                    $ing->nombre_ingrediente = $item['nombre_ingrediente'];
                    $ing->cantidad           = (float)$item['cantidad'];
                    $ing->unidad             = $item['unidad'] ?? 'g';
                    $ing->save();
                }
            }
        }

        $receta->load('ingredientes');

        return response()->json([
            'success' => true,
            'message' => 'Receta actualizada con éxito.',
            'data'    => $receta
        ]);
    }

    /**
     * Eliminar una receta.
     */
    public function deleteApiReceta($id)
    {
        $receta = Receta::find($id);

        if (!$receta) {
            return response()->json([
                'success' => false,
                'message' => 'Receta no encontrada.'
            ], 404);
        }

        $receta->delete();

        return response()->json([
            'success' => true,
            'message' => 'Receta eliminada correctamente.'
        ]);
    }
}
