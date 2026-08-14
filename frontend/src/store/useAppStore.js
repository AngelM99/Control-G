import { create } from 'zustand';
import api from '../api/axios';

// Store global simplificado para UI y Modales
export const useAppStore = create((set) => ({
  // Estado de Modales
  modals: {
    operation: false,
    payment: false,
    contactInline: false,
  },
  
  // Datos para los modales
  modalData: {
    payment: null, // Guardará { contact_id, deudas }
  },

  // Datos del Dashboard (data real de la API)
  dashboardData: {
    kpis: null,
    tarjetas: null,
    exigibilidad: null,
    loading: false,
  },

  // Actions
  openModal: (modalName, data = null) => set((state) => ({
    modals: { ...state.modals, [modalName]: true },
    modalData: { ...state.modalData, [modalName]: data }
  })),
  
  closeModal: (modalName) => set((state) => ({
    modals: { ...state.modals, [modalName]: false },
    modalData: { ...state.modalData, [modalName]: null }
  })),

  // Refresca todos los widgets del dashboard desde la API
  fetchDashboardData: async () => {
    set((state) => ({ dashboardData: { ...state.dashboardData, loading: true } }));

    try {
      const [kpisRes, tarjetasRes, exigibilidadRes] = await Promise.all([
        api.get('/dashboard/kpis'),
        api.get('/dashboard/tarjetas'),
        api.get('/dashboard/exigibilidad'),
      ]);

      set({
        dashboardData: {
          kpis: kpisRes.data.data,
          tarjetas: tarjetasRes.data.data || [],
          exigibilidad: exigibilidadRes.data.data,
          loading: false,
        },
      });
    } catch {
      set((state) => ({ dashboardData: { ...state.dashboardData, loading: false } }));
    }
  },
}));
