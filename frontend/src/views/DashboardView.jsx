import React, { useEffect } from 'react';
import KPICards from '../components/dashboard/KPICards';
import CreditCardWidget from '../components/dashboard/CreditCardWidget';
import ContactsWidget from '../components/dashboard/ContactsWidget';
import OperationModal from '../components/modals/OperationModal';
import { useAppStore } from '../store/useAppStore';

export default function DashboardView() {
  const fetchDashboardData = useAppStore((state) => state.fetchDashboardData);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-end mb-8">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Visión General</h1>
          <p className="text-slate-500 mt-1">Tu resumen financiero del mes actual.</p>
        </div>
      </div>

      {/* Row 1: KPIs */}
      <KPICards />

      {/* Row 2: Widgets & Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        
        {/* Left Column (Tarjetas) */}
        <div className="lg:col-span-2">
          <CreditCardWidget />
        </div>
        
        {/* Right Column (Buscar Deuda) */}
        <div className="lg:col-span-1 space-y-6">
          <ContactsWidget />
        </div>
        
      </div>

      {/* Modals Globales de la Vista */}
      <OperationModal />
    </div>
  );
}
