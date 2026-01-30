<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class KeycloakAuth
{
    public function handle(Request $request, Closure $next)
    {
        // Se Keycloak estiver desativado, apenas pega o usuário do teste
        if (!env('KEYCLOAK_ENABLED', true)) {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token não informado'], 401);
            }

            // Decodifica o token base64 para extrair o email
            try {
                $decoded = base64_decode($token, true);
                if ($decoded === false) {
                    $email = $token; // Se não for base64 válido, trata como email
                } else {
                    $parts = explode(':', $decoded);
                    $email = $parts[0] ?? $token;
                }
            } catch (\Exception $e) {
                $email = $token;
            }

            $user = User::where('email', $email)->first();

            if (!$user || $user->status != 1) {
                return response()->json(['error' => 'Usuário inválido'], 403);
            }

            $request->attributes->set('user', $user);
            return $next($request);
        }

        // Modo Keycloak normal
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token não informado'], 401);
        }

        try {
            $keycloakUrl = env('KEYCLOAK_URL', 'http://localhost:8080');
            $keycloakRealm = env('KEYCLOAK_REALM', 'SITT');
            $jwksUrl = "{$keycloakUrl}/realms/{$keycloakRealm}/protocol/openid-connect/certs";
            
            $jwks = json_decode(file_get_contents($jwksUrl), true);
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));

            $email = $decoded->email ?? $decoded->preferred_username ?? null;

            if (!$email) {
                return response()->json(['error' => 'Email não encontrado no token'], 401);
            }

            $user = User::where('email', $email)->first();
            if (!$user || $user->status != 1) {
                return response()->json(['error' => 'Usuário inválido'], 403);
            }

            $request->attributes->set('user', $user);
            return $next($request);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Token inválido', 'message' => $e->getMessage()], 401);
        }
    }
}
