<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\RecetaController;
use App\Http\Controllers\IngredienteController;
use App\Http\Controllers\CosteoController;
use App\Http\Controllers\CompraController;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (Autenticación)
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'postApiLogin']);

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren Token de Acceso Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación y Usuarios
    Route::post('/logout', [AuthController::class, 'postApiLogout']);
    Route::get('/profile', [AuthController::class, 'getApiProfile']);
    Route::get('/users', [AuthController::class, 'getApiUsers']);
    Route::post('/users', [AuthController::class, 'postApiCreateUser']);
    Route::delete('/users/{id}', [AuthController::class, 'deleteApiUser']);

    // Módulo 5: Dashboard Operativo
    Route::get('/dashboard/metrics', [DashboardController::class, 'getApiDashboardMetrics']);
    Route::get('/dashboard/export', [DashboardController::class, 'getApiExportData']);

    // Módulo 6: Calendario y Pedidos
    Route::get('/pedidos', [PedidoController::class, 'getApiPedidos']);
    Route::get('/pedidos/calendario-alertas', [PedidoController::class, 'getApiCalendarioAlertas']);
    Route::get('/pedidos/{id}', [PedidoController::class, 'getApiPedidoDetalle']);
    Route::post('/pedidos', [PedidoController::class, 'postApiCrearPedido']);
    Route::put('/pedidos/{id}', [PedidoController::class, 'putApiEditarPedido']);
    Route::patch('/pedidos/{id}/estado', [PedidoController::class, 'patchApiCambiarEstado']);
    Route::delete('/pedidos/{id}', [PedidoController::class, 'deleteApiPedido']);

    // Módulo 7: Recetario Digital
    Route::get('/recetas', [RecetaController::class, 'getApiRecetas']);
    Route::get('/recetas/{id}', [RecetaController::class, 'getApiRecetaDetalle']);
    Route::post('/recetas', [RecetaController::class, 'postApiCrearReceta']);
    Route::put('/recetas/{id}', [RecetaController::class, 'putApiEditarReceta']);
    Route::delete('/recetas/{id}', [RecetaController::class, 'deleteApiReceta']);

    // Módulo 8: Inventario de Insumos y Materiales
    Route::get('/ingredientes', [IngredienteController::class, 'getApiIngredientes']);
    Route::get('/ingredientes/{id}', [IngredienteController::class, 'getApiIngredienteDetalle']);
    Route::post('/ingredientes', [IngredienteController::class, 'postApiCrearIngrediente']);
    Route::put('/ingredientes/{id}', [IngredienteController::class, 'putApiEditarIngrediente']);
    Route::post('/ingredientes/{id}/merma', [IngredienteController::class, 'postApiRegistrarMerma']);
    Route::post('/ingredientes/{id}/ajuste', [IngredienteController::class, 'postApiAjusteInventario']);
    Route::delete('/ingredientes/{id}', [IngredienteController::class, 'deleteApiIngrediente']);
    Route::get('/movimientos', [IngredienteController::class, 'getApiMovimientos']);

    // Módulo 9: Costeo Libre de Pasteles
    Route::post('/costeo/simular', [CosteoController::class, 'postApiSimularCosto']);
    Route::post('/costeo/guardar-descontar', [CosteoController::class, 'postApiGuardarYDescontar']);
    Route::get('/costeo/historial', [CosteoController::class, 'getApiHistorialCosteos']);
    Route::get('/costeo/{id}', [CosteoController::class, 'getApiCosteoDetalle']);
    Route::post('/costeo/{id}/revertir', [CosteoController::class, 'postApiRevertirCosteo']);

    // Módulo 10: Compras de Insumos
    Route::get('/compras', [CompraController::class, 'getApiCompras']);
    Route::get('/compras/{id}', [CompraController::class, 'getApiCompraDetalle']);
    Route::post('/compras', [CompraController::class, 'postApiRegistrarCompra']);
});
