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
  const [validationAttempted, setValidationAttempted] = useState(false);

  const fetchUserData = (authToken) => {
    if (!authToken || validationAttempted) {
      setLoading(false);
      return;
    }

    setValidationAttempted(true);

    fetch(API_ME_ENDPOINT, {
      headers: { Authorization: `Bearer ${authToken}` },
    })
      .then((res) => {
        if (res.status === 403 || res.status === 401) {
          
          localStorage.removeItem("userEmail");
          localStorage.removeItem("userToken");
          setAuthorized(false);
          setAuthenticated(false);
          setLoading(false);
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
        setLoading(false);
      })
      .catch((err) => {
        console.log("Erro ao acessar dados do usuário:", err);
        setAuthorized(false);
        setAuthenticated(false);
        localStorage.removeItem("userEmail");
        localStorage.removeItem("userToken");
        setLoading(false);
      });
  };

  useEffect(() => {
    if (useKeycloak) {
      initKeycloak()
        .then((auth) => {
          if (!auth) {
            setLoading(false);
            return;
          }

          setAuthenticated(true);

          const tokenInterval = setInterval(() => {
            keycloak.updateToken(60).catch(() => console.log("Falha ao atualizar token"));
          }, 60000);

          fetchUserData(keycloak.token);

          return () => clearInterval(tokenInterval);
        })
        .catch((err) => {
          console.log("Erro ao inicializar Keycloak:", err);
          setLoading(false);
        });
    } else {
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
    setValidationAttempted(false);
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
