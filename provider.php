import { useContext } from "react";
import { Navigate } from "react-router-dom";
import { AuthContext } from "../AuthProvider";

export default function ProtectedRoute({ children, allowedRoles }) {

  const { user, loading, authenticated, authorized } = useContext(AuthContext);

  if (loading) {
    return <div className="flex items-center justify-center h-screen">Carregando...</div>;
  }

  // Se não está autenticado, redireciona para login
  if (!authenticated) {
    return <Navigate to="/login" replace />;
  }

  // Se não está autorizado (usuário inativo ou validação falhou)
  if (!authorized || user === null) {
    return <Navigate to="/login" replace />;
  }
  
  // Se não tem permissão para acessar a rota
  if (allowedRoles && !allowedRoles.includes(user.acesso)) {
    return <Navigate to="/error" replace />;
  }

  return children;
}
