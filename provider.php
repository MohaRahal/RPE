import React, { useState, useEffect, useContext } from "react";
import { useNavigate } from "react-router-dom";
import { AuthContext } from "../AuthProvider";
import keycloak, { initKeycloak } from "../keycloak";

const useKeycloak = import.meta.env.VITE_USE_KEYCLOAK === "true";

export default function LoginForm() {
  const [email, setEmail] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  const { authenticated, loading: authLoading } = useContext(AuthContext);

  // Se já está autenticado, redireciona para home
  useEffect(() => {
    if (authenticated && !authLoading) {
      navigate("/", { replace: true });
    }
  }, [authenticated, authLoading, navigate]);

  const handleKeycloakLogin = async () => {
    setError(null);
    setLoading(true);
    try {
      const auth = await initKeycloak();
      if (auth) {
        // Keycloak faz a autenticação automaticamente
        window.location.href = "/";
      }
    } catch (err) {
      setError("Erro ao inicializar autenticação com Keycloak");
      setLoading(false);
    }
  };

  const handleSimpleLogin = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      // Valida se é um email válido
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        throw new Error("Por favor, insira um email válido");
      }

      const apiUrl = import.meta.env.VITE_API_URL || "http://192.168.137.23:8000/api";
      const res = await fetch(apiUrl + "/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email }),
      });

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.message || "Email não registrado ou inativo");
      }

      const data = await res.json();
      
      // Armazena email e token no localStorage
      localStorage.setItem("userEmail", email);
      localStorage.setItem("userToken", data.token || email);

      // Aguarda um pouco para garantir que o localStorage foi atualizado
      setTimeout(() => {
        navigate("/", { replace: true });
      }, 100);
    } catch (err) {
      setError(err.message);
      setLoading(false);
    }
  };

  if (authLoading) {
    return (
      <div className="flex items-center justify-center h-screen bg-gray-100">
        <div className="text-center">
          <p className="text-gray-600">Carregando...</p>
        </div>
      </div>
    );
  }

  if (useKeycloak) {
    return (
      <div className="flex items-center justify-center h-screen bg-gray-100">
        <div className="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
          <h1 className="text-2xl font-bold text-center mb-6">Login</h1>
          <p className="text-gray-600 text-center mb-6">
            Você será redirecionado para o Keycloak para autenticação
          </p>

          <button
            onClick={handleKeycloakLogin}
            disabled={loading}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded"
          >
            {loading ? "Autenticando..." : "Entrar com Keycloak"}
          </button>

          {error && (
            <p className="text-red-500 text-center mt-4">{error}</p>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="flex items-center justify-center h-screen bg-gray-100">
      <div className="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
        <h1 className="text-2xl font-bold text-center mb-6">Login</h1>

        <form onSubmit={handleSimpleLogin}>
          <div className="mb-4">
            <label className="block text-gray-700 font-bold mb-2">Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              disabled={loading}
              className="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 disabled:bg-gray-100"
              placeholder="seu@email.com"
            />
          </div>

          {error && (
            <p className="text-red-500 text-sm mb-4">{error}</p>
          )}

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white font-bold py-2 px-4 rounded"
          >
            {loading ? "Autenticando..." : "Entrar"}
          </button>
        </form>

        <p className="text-gray-600 text-center text-sm mt-6">
          Use email corporativo para login.
        </p>
      </div>
    </div>
  );
}
