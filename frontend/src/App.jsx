import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import Login from './pages/Login';
import DashboardView from './views/DashboardView';
import AppLayout from './components/layout/AppLayout';
import ProtectedRoute from './components/ProtectedRoute';

import SettingsView from './views/SettingsView';
import ContactDetailView from './views/ContactDetailView';
import ContactsView from './views/ContactsView';
import CategoriesView from './views/CategoriesView';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/login" element={<Login />} />
        
        {/* Protected Routes */}
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={
            <AppLayout>
              <DashboardView />
            </AppLayout>
          } />
          
          <Route path="/settings" element={
            <AppLayout>
              <SettingsView />
            </AppLayout>
          } />

          <Route path="/contacts" element={
            <AppLayout>
              <ContactsView />
            </AppLayout>
          } />

          <Route path="/categories" element={
            <AppLayout>
              <CategoriesView />
            </AppLayout>
          } />

          <Route path="/contacts/:id" element={
            <AppLayout>
              <ContactDetailView />
            </AppLayout>
          } />
          {/* Default Route inside protected layout */}
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
        </Route>

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </Router>
  );
}

export default App;
