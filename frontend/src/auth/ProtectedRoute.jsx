import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from './AuthProvider.jsx';

const ProtectedRoute = ({ allowedRoles }) => {
  const { user } = useAuth();
  const location = useLocation();

  if (!user) {
    return <Navigate to="/masuk" state={{ from: location }} replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    // Redirect to each role's main home
    if (user.role === 'pengelola_gedung') {
      return <Navigate to="/pengelola" replace />;
    } else if (user.role === 'pengelola_sistem') {
      return <Navigate to="/sistem" replace />;
    } else {
      return <Navigate to="/app" replace />;
    }
  }

  return <Outlet />;
};

export default ProtectedRoute;
