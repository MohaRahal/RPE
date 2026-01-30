<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        $useKeycloak = env('KEYCLOAK_ENABLED', false);
        
        if ($useKeycloak) {
            // Modo Keycloak: pega usuário do middleware
            $user = $request->attributes->get('user');
        } else {
            // Modo autenticação simples: pega email do token
            $email = $request->bearerToken();
            if (!$email) {
                return response()->json(['error' => 'Token não fornecido'], 401);
            }

            // Decodifica o token base64 para extrair o email
            try {
                $decoded = base64_decode($email);
                $email = explode(':', $decoded)[0];
            } catch (\Exception $e) {
                $email = $request->bearerToken();
            }

            $user = User::where('email', $email)->first();
        }

        if (!$user || $user->status != 1) {
            return response()->json(['error' => 'Usuário não autorizado ou inativo'], 403);
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'nome' => $user->nome ?? null,
            'email' => $user->email,
            'role' => $user->funcao ?? null,
            'acesso' => $user->permissao ?? null,
        ]);
    }
}
