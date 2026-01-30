import React, { createContext, useState, useEffect } from "react";
import keycloak, { initKeycloak } from "./keycloak";
import { API_ME_ENDPOINT } from "./services/api";

export const AuthContext = createContext();

const useKeycloak = import.meta.env.VITE_USE_KEYCLOAK === "true";

export const AuthProvider = ({ children }) => {
  const [loading, setLoading] = useState(true);       
  const [authenticated, setAuthenticated] = useState(false);
  const [authorized, setAuthorized] = useState(false);
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);

  // Função para buscar dados do usuário
  const fetchUserData = (authToken) => {
    fetch(API_ME_ENDPOINT, {
      headers: { Authorization: `Bearer ${authToken}` },
    })
      .then((res) => {
        if (res.status === 403) {
          setAuthorized(false);
          return null;
        }
        if (!res.ok) throw new Error("Erro ao buscar dados do usuário");
        return res.json();
      })
      .then((data) => {
        if (data) {
          setUser(data);  
          setAuthorized(true);
          setToken(authToken);
        }
      })
      .catch((err) => {
        console.log("Erro ao acessar dados do usuário:", err);
        setAuthorized(false);
      })
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    if (useKeycloak) {
      // Modo Keycloak
      initKeycloak()
        .then((auth) => {
          if (!auth) {
            setLoading(false);
            return;
          }

          setAuthenticated(true);

          // Atualizar token a cada 60 segundos
          setInterval(() => {
            keycloak.updateToken(60).catch(() => console.log("Falha ao atualizar token"));
          }, 60000);

          fetchUserData(keycloak.token);
        })
        .catch((err) => {
          console.log("Erro ao inicializar Keycloak:", err);
          setLoading(false);
        });
    } else {
      // Modo autenticação simples (email)
      const storedEmail = localStorage.getItem("userEmail");
      const storedToken = localStorage.getItem("userToken");
      
      if (storedEmail && storedToken) {
        setAuthenticated(true);
        fetchUserData(storedToken);
      } else {
        setLoading(false);
      }
    }
  }, []);

  const logout = () => {
    setAuthenticated(false);
    setAuthorized(false);
    setUser(null);
    setToken(null);
    localStorage.removeItem("userEmail");
    localStorage.removeItem("userToken");
    
    if (useKeycloak && keycloak.logout) {
      keycloak.logout();
    }
  };

  return (
    <AuthContext.Provider value={{ loading, authenticated, authorized, user, token, logout }}>
      {children}
    </AuthContext.Provider>
  );
};
