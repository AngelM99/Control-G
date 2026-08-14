import React, { useState } from 'react';
import { X, DollarSign, AlertCircle, Info } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';

export default function PaymentModal() {
  const { modals, closeModal, modalData } = useAppStore();
  const isOpen = modals.payment;
  const data = modalData.payment || {}; // { contactName, saldoPendiente, sugerenciaCuota }

  const [monto, setMonto] = useState(data.sugerenciaCuota || '');
  
  if (!isOpen) return null;

  const saldoPendiente = data.saldoPendiente || 1250.50;
  const isOverpayment = Number(monto) > saldoPendiente;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md animate-slide-up">
        {/* Header */}
        <div className="flex justify-between items-center p-6 border-b border-slate-100">
          <h2 className="text-xl font-bold text-slate-800">Registrar Abono</h2>
          <button 
            onClick={() => closeModal('payment')}
            className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Body */}
        <div className="p-6 space-y-6">
          <div className="bg-slate-50 p-4 rounded-xl border border-slate-200">
            <p className="text-sm text-slate-500">Abonando a deuda de:</p>
            <p className="font-bold text-slate-800">{data.contactName || 'Juan Pérez'}</p>
            <div className="mt-2 flex justify-between items-end">
              <span className="text-xs text-slate-500">Saldo pendiente total</span>
              <span className="font-bold text-rose-600">S/ {saldoPendiente.toFixed(2)}</span>
            </div>
          </div>

          <div>
            <label className="label-base">Monto a Abonar</label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <DollarSign className="h-5 w-5 text-slate-400" />
              </div>
              <input 
                type="number" 
                value={monto}
                onChange={(e) => setMonto(e.target.value)}
                placeholder="0.00"
                className={`input-base pl-10 text-lg font-semibold ${isOverpayment ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500' : ''}`}
              />
              <button 
                className="absolute inset-y-0 right-2 flex items-center text-xs font-bold text-primary-600 hover:text-primary-700"
                onClick={() => setMonto(saldoPendiente)}
              >
                PAGAR TODO
              </button>
            </div>
            
            {/* Anti-Sobrepago Alerta */}
            {isOverpayment ? (
              <p className="mt-2 text-sm text-rose-600 flex items-center gap-1 animate-fade-in">
                <AlertCircle className="h-4 w-4" />
                El abono no puede superar el saldo pendiente.
              </p>
            ) : (
              <p className="mt-2 text-xs text-slate-500 flex items-center gap-1">
                <Info className="h-3 w-3" />
                Se aplicará automáticamente a la deuda más antigua (FIFO).
              </p>
            )}
          </div>

          <div>
            <label className="label-base">Medio de Pago Origen/Destino</label>
            <select className="input-base">
              <option>Yape</option>
              <option>Plin</option>
              <option>Efectivo (S/)</option>
            </select>
          </div>
        </div>

        {/* Footer */}
        <div className="p-6 border-t border-slate-100 bg-slate-50 rounded-b-2xl flex justify-end gap-3">
          <button 
            onClick={() => closeModal('payment')}
            className="btn btn-secondary"
          >
            Cancelar
          </button>
          <button 
            className="btn btn-primary"
            disabled={isOverpayment || !monto || Number(monto) <= 0}
          >
            Confirmar Abono
          </button>
        </div>
      </div>
    </div>
  );
}
