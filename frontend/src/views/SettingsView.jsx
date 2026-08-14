import React, { useState, useEffect } from 'react';
import { Save, AlertCircle, Loader2, Plus, X } from 'lucide-react';
import api from '../api/axios';
import { useAppStore } from '../store/useAppStore';

export default function SettingsView() {
  const [methods, setMethods] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(null);
  const [error, setError] = useState('');

  // Create state
  const [isCreating, setIsCreating] = useState(false);
  const [newMethod, setNewMethod] = useState({ nombre: '', banco: '', tipo: 'CREDITO' });

  useEffect(() => {
    fetchMethods();
  }, []);

  const fetchMethods = async () => {
    try {
      setLoading(true);
      const res = await api.get('/payment-methods');
      setMethods(res.data);
    } catch (err) {
      setError('Error al cargar tarjetas.');
    } finally {
      setLoading(false);
    }
  };

  const handleUpdate = async (id, data) => {
    try {
      setSaving(id);
      setError('');
      await api.put(`/payment-methods/${id}`, data);
      
      setMethods(prev => prev.map(m => m.id === id ? { ...m, ...data } : m));
    } catch (err) {
      setError(err.response?.data?.message || 'Error al guardar.');
    } finally {
      setSaving(null);
    }
  };

  const handleCreate = async () => {
    if (!newMethod.nombre || !newMethod.tipo) {
      setError('Nombre y tipo son obligatorios');
      return;
    }
    try {
      setSaving('new');
      setError('');
      const res = await api.post('/payment-methods', newMethod);
      setMethods([...methods, res.data.payment_method]);
      setIsCreating(false);
      setNewMethod({ nombre: '', banco: '', tipo: 'CREDITO' });
    } catch (err) {
      setError(err.response?.data?.message || 'Error al crear tarjeta.');
    } finally {
      setSaving(null);
    }
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Configuración de Tarjetas</h1>
          <p className="text-slate-500 mt-1">Configura los días de facturación y límite de crédito de tus tarjetas.</p>
        </div>
        <button onClick={() => setIsCreating(true)} className="btn btn-primary gap-2">
          <Plus className="w-5 h-5" /> Agregar Medio de Pago
        </button>
      </div>

      {error && (
        <div className="bg-red-50 p-4 rounded-xl flex items-center text-red-600 gap-2">
          <AlertCircle className="w-5 h-5" />
          <span>{error}</span>
        </div>
      )}

      {isCreating && (
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex gap-4 items-end animate-slide-up">
          <div className="flex-1">
            <label className="label-base">Nombre / Alias</label>
            <input type="text" className="input-base" value={newMethod.nombre} onChange={e => setNewMethod({...newMethod, nombre: e.target.value})} placeholder="Ej. Visa Oro" />
          </div>
          <div className="flex-1">
            <label className="label-base">Banco</label>
            <input type="text" className="input-base" value={newMethod.banco} onChange={e => setNewMethod({...newMethod, banco: e.target.value})} placeholder="Ej. BCP" />
          </div>
          <div className="flex-1">
            <label className="label-base">Tipo</label>
            <select className="input-base" value={newMethod.tipo} onChange={e => setNewMethod({...newMethod, tipo: e.target.value})}>
              <option value="CREDITO">Crédito</option>
              <option value="DEBITO">Débito</option>
              <option value="EFECTIVO">Efectivo</option>
              <option value="BILLETERA_DIGITAL">Billetera Digital</option>
            </select>
          </div>
          <div className="flex gap-2">
            <button onClick={handleCreate} disabled={saving === 'new'} className="btn btn-primary">Guardar</button>
            <button onClick={() => setIsCreating(false)} className="btn btn-secondary px-3"><X className="w-5 h-5" /></button>
          </div>
        </div>
      )}

      {loading ? (
        <div className="flex justify-center p-12">
          <Loader2 className="w-8 h-8 animate-spin text-primary-500" />
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <table className="w-full text-left">
            <thead className="bg-slate-50 border-b border-slate-100 text-sm font-medium text-slate-500">
              <tr>
                <th className="px-6 py-4">Medio de Pago</th>
                <th className="px-6 py-4">Tipo</th>
                <th className="px-6 py-4">Línea Total</th>
                <th className="px-6 py-4">Día de Corte</th>
                <th className="px-6 py-4">Día de Pago</th>
                <th className="px-6 py-4 text-right">Acción</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {methods.map(method => (
                <MethodRow 
                  key={method.id} 
                  method={method} 
                  onSave={handleUpdate} 
                  isSaving={saving === method.id} 
                />
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function MethodRow({ method, onSave, isSaving }) {
  const [corte, setCorte] = useState(method.dia_corte || '');
  const [pago, setPago] = useState(method.dia_pago || '');
  const [linea, setLinea] = useState(method.linea_total || '');
  const isCredit = method.tipo === 'CREDITO';

  const hasChanges = String(corte) !== String(method.dia_corte || '') || 
                     String(pago) !== String(method.dia_pago || '') ||
                     String(linea) !== String(method.linea_total || '');

  return (
    <tr className="hover:bg-slate-50/50 transition-colors">
      <td className="px-6 py-4">
        <div className="font-medium text-slate-800">{method.nombre}</div>
        <div className="text-xs text-slate-500">{method.banco || 'S/N Banco'}</div>
      </td>
      <td className="px-6 py-4">
        <span className="px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs font-medium">
          {method.tipo}
        </span>
      </td>
      <td className="px-6 py-4">
        {isCredit ? (
          <div className="relative w-32">
            <span className="absolute left-3 top-2 text-slate-400">$</span>
            <input 
              type="number" 
              className="input-base pl-7 py-1 text-sm h-9" 
              value={linea}
              onChange={e => setLinea(e.target.value)}
              placeholder="0.00"
            />
          </div>
        ) : (
          <span className="text-slate-400 text-sm">-</span>
        )}
      </td>
      <td className="px-6 py-4">
        {isCredit ? (
          <input 
            type="number" 
            min="1" max="31"
            className="input-base w-20 py-1 text-sm h-9 text-center" 
            value={corte}
            onChange={e => setCorte(e.target.value)}
            placeholder="Día"
          />
        ) : (
          <span className="text-slate-400 text-sm">-</span>
        )}
      </td>
      <td className="px-6 py-4">
        {isCredit ? (
          <input 
            type="number" 
            min="1" max="31"
            className="input-base w-20 py-1 text-sm h-9 text-center" 
            value={pago}
            onChange={e => setPago(e.target.value)}
            placeholder="Día"
          />
        ) : (
          <span className="text-slate-400 text-sm">-</span>
        )}
      </td>
      <td className="px-6 py-4 text-right">
        {isCredit && hasChanges && (
          <button 
            onClick={() => onSave(method.id, { dia_corte: corte, dia_pago: pago, linea_total: linea })}
            disabled={isSaving}
            className="btn btn-primary px-3 py-1.5 text-sm h-9"
          >
            {isSaving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
          </button>
        )}
      </td>
    </tr>
  );
}
