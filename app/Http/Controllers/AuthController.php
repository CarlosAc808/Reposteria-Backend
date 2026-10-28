<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Iniciar sesión con correo y contraseña.
     */
    public function postApiLogin(Request $request)
    {
        $data = $request->all();

        // Validar datos básicos de entrada
        if (empty($data['email']) || empty($data['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico y la contraseña son requeridos.'
            ], 422);
        }

        // Buscar el usuario por correo electrónico
        $user = User::where('email', $data['email'])->first();

        // Verificar credenciales
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas. Verifique su correo y contraseña.'
            ], 401);
        }

        // Crear token de acceso seguro
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Sesión iniciada correctamente.',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'rol'   => $user->rol ?? 'pastelero',
            ]
        ]);
    }

    /**
     * Cerrar sesión y revocar tokens.
     */
    public function postApiLogout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente.'
        ]);
    }

    /**
     * Obtener el perfil del usuario autenticado.
     */
    public function getApiProfile(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'rol'   => $user->rol ?? 'pastelero',
            ]
        ]);
    }

    /**
     * Listar usuarios registrados.
     */
    public function getApiUsers()
    {
        $users = User::select('id', 'name', 'email', 'rol', 'created_at')->orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    /**
     * Crear un nuevo usuario en el sistema.
     */
    public function postApiCreateUser(Request $request)
    {
        $data = $request->all();

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Nombre, correo y contraseña son obligatorios.'
            ], 422);
        }

        // Verificar si el correo ya existe
        $existe = User::where('email', $data['email'])->first();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'El correo electrónico ya está registrado en el sistema.'
            ], 422);
        }

        $user = new User();
        $user->name     = $data['name'];
        $user->email    = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->rol      = $data['rol'] ?? 'pastelero';
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente.',
            'data'    => $user
        ]);
    }

    /**
     * Eliminar usuario.
     */
    public function deleteApiUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        // Evitar eliminar al último administrador
        if ($user->rol === 'admin' && User::where('rol', 'admin')->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'No es posible eliminar al único administrador del sistema.'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente.'
        ]);
    }
}
