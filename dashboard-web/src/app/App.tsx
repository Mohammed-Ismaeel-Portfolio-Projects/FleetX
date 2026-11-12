import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import AdminLayout from '../layouts/AdminLayout';
import DispatcherLayout from '../layouts/DispatcherLayout';
import OverviewPage from '../components/dashboard/overview/OverviewPage';
import AdminPricingPage from '../features/admin/pricing/pages/PricingPage';
import NotificationsPage from '../components/dashboard/notifications/NotificationsPage';
import SecurityPage from '../components/dashboard/security/SecurityPage';
import DispatcherDashboard from '../components/dashboard/dispatch/DispatcherDashboard';
import Login from '../pages/auth/Login';
import Unauthorized from '../pages/auth/Unauthorized';
import { ProtectedRoute, AuthProvider } from '../core/auth';
import ShipmentsPage from '../features/dispatcher/shipments/pages/ShipmentsPage';
import ShipmentDetailsPage from '../features/dispatcher/shipments/pages/ShipmentDetailsPage';
import RequestsPage from '../features/dispatcher/requests/pages/RequestsPage';
import RequestDetailsPage from '../features/dispatcher/requests/pages/RequestDetailsPage';
import ActiveTrackingPage from '../features/dispatcher/tracking/pages/ActiveTrackingPage';
import DriverManagementPage from '../features/dispatcher/drivers/pages/DriverManagementPage';
import FleetManagementPage from '../features/dispatcher/fleet/pages/FleetManagementPage';
import DispatcherNotificationsPage from '../features/dispatcher/notifications/pages/NotificationsPage';
import AnalyticsPage from '../features/dispatcher/analytics/pages/AnalyticsPage';
import CommunicationCenterPage from '../features/dispatcher/communication/pages/CommunicationCenterPage';
import CompaniesPage from '../features/admin/companies/pages/CompaniesPage';
import PartnerCompaniesPage from '../features/admin/companies/pages/PartnerCompaniesPage';
import TrailersPage from '../features/admin/trailers/pages/TrailersPage';
import DriversPage from '../features/admin/drivers/pages/DriversPage';
import AdminShipmentsPage from '../features/admin/shipments/pages/ShipmentsPage';
import TrackingPage from '../features/admin/tracking/pages/TrackingPage';
import TrackingDashboard from '../features/admin/tracking-dashboard/pages/TrackingDashboard';
import MonthlyInvoicesPage from '../features/admin/invoices/pages/MonthlyInvoicesPage';
import { ToastProvider } from '../shared/components/toast';

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <BrowserRouter>
          <Routes>
          {/* Public Routes */}
          <Route path="/" element={<Navigate to="/login" replace />} />
          <Route path="/login" element={<Login />} />
          <Route path="/unauthorized" element={<Unauthorized />} />

          {/* Protected Admin Routes */}
          <Route path="/admin/*" element={<ProtectedRoute allowedRoles={['admin']} />}>
            <Route
              path="*"
              element={
                <AdminLayout>
                  <Routes>
                    <Route path="" element={<OverviewPage />} />
                    <Route path="companies" element={<CompaniesPage />} />
                    <Route path="partner-companies" element={<PartnerCompaniesPage />} />
                    <Route path="fleet" element={<FleetManagementPage />} />
                    <Route path="trailers" element={<TrailersPage />} />
                    <Route path="drivers" element={<DriversPage />} />
                    <Route path="shipments" element={<AdminShipmentsPage />} />
                    <Route path="tracking" element={<TrackingDashboard />} />
                    <Route path="tracking/logs" element={<TrackingPage />} />
                    <Route path="pricing" element={<AdminPricingPage />} />
                    <Route path="monthly-invoices" element={<MonthlyInvoicesPage />} />
                    <Route path="analytics" element={<AnalyticsPage />} />
                    <Route path="notifications" element={<NotificationsPage />} />
                    <Route path="security" element={<SecurityPage />} />
                    <Route path="settings" element={<Navigate to="/admin" replace />} />
                  </Routes>
                </AdminLayout>
              }
            />
          </Route>

          {/* Protected Dispatcher Routes */}
          <Route path="/dispatcher/*" element={<ProtectedRoute allowedRoles={['dispatcher']} />}>
            <Route
              path="*"
              element={
                <DispatcherLayout>
                  <Routes>
                    <Route path="" element={<DispatcherDashboard />} />
                    <Route path="requests" element={<RequestsPage />} />
                    <Route path="requests/:id" element={<RequestDetailsPage />} />
                    <Route path="fleet" element={<FleetManagementPage />} />
                    <Route path="shipments" element={<ShipmentsPage />} />
                    <Route path="shipments/:id" element={<ShipmentDetailsPage />} />
                    <Route path="tracking" element={<ActiveTrackingPage />} />
                    <Route path="drivers" element={<DriverManagementPage />} />
                    <Route path="notifications" element={<DispatcherNotificationsPage />} />
                    <Route path="analytics" element={<AnalyticsPage />} />
                    <Route path="communications" element={<CommunicationCenterPage />} />
                    <Route path="settings" element={<Navigate to="/dispatcher" replace />} />
                  </Routes>
                </DispatcherLayout>
              }
            />
          </Route>

          {/* Fallback route */}
          <Route path="*" element={<Navigate to="/login" replace />} />
          </Routes>
        </BrowserRouter>
      </ToastProvider>
    </AuthProvider>
  );
}
