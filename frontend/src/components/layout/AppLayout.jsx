import React from 'react';
import Header from './Header';

export default function AppLayout({ children }) {
  return (
    <div className="min-h-screen flex flex-col bg-background">
      <Header />
      
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-slide-up">
        {children}
      </main>
      
      {/* Footer minimalista */}
      <footer className="w-full py-4 text-center text-sm text-slate-400">
        &copy; {new Date().getFullYear()} Control-G. Todos los derechos reservados.
      </footer>
    </div>
  );
}
