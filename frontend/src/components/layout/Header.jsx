import React from 'react';
import { Plus, Bell, UserCircle, Search } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';

export default function Header() {
  const openModal = useAppStore((state) => state.openModal);

  return (
    <header className="sticky top-0 z-30 w-full bg-white/80 backdrop-blur-md border-b border-slate-200 shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-16">
          {/* Logo & Search */}
          <div className="flex items-center gap-8">
            <div className="flex-shrink-0 flex items-center gap-2">
              <div className="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-xl">G</span>
              </div>
              <span className="text-xl font-bold text-slate-800 tracking-tight">Control-G</span>
            </div>
            
            <div className="hidden md:flex relative group">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Search className="h-4 w-4 text-slate-400 group-focus-within:text-primary-500 transition-colors" />
              </div>
              <input
                type="text"
                placeholder="Buscar contacto, operación..."
                className="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-xl leading-5 bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition-all sm:text-sm w-64 lg:w-96"
              />
            </div>
          </div>

          {/* Right Actions */}
          <div className="flex items-center gap-4">
            <button className="p-2 text-slate-400 hover:text-slate-600 transition-colors relative">
              <Bell className="h-5 w-5" />
              <span className="absolute top-1.5 right-1.5 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white" />
            </button>
            
            <div className="h-8 w-px bg-slate-200 mx-1"></div>
            
            <button 
              onClick={() => openModal('operation')}
              className="btn btn-primary gap-2 animate-fade-in shadow-lg shadow-primary-500/30"
            >
              <Plus className="h-5 w-5" />
              <span className="hidden sm:inline">NUEVO MOVIMIENTO</span>
            </button>
            
            <a href="/contacts" className="flex items-center gap-2 p-1 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors ml-2" title="Contactos">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-users"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </a>

            <a href="/categories" className="flex items-center gap-2 p-1 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors ml-2" title="Categorías">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-tags"><path d="M9 5H2v7l6.29 6.29c.94.94 2.48.94 3.42 0l8.29-8.29c.94-.94.94-2.48 0-3.42L11 5Z"/><path d="M6 9.01V9"/><path d="m15 5 6.3 6.3a2.4 2.4 0 0 1 0 3.4L17 19"/></svg>
            </a>

            <a href="/settings" className="flex items-center gap-2 p-1 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors ml-2" title="Configuración">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="lucide lucide-settings"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            </a>
            
            <button className="flex items-center gap-2 p-1 rounded-full hover:bg-slate-100 transition-colors">
              <UserCircle className="h-8 w-8 text-slate-400" />
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
