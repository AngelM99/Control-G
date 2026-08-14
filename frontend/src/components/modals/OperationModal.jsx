import React, { useState, useEffect } from 'react';
import { X, Calendar, DollarSign, Tag, Users, Wallet, Loader2 } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';
import api from '../../api/axios';
import clsx from 'clsx';

const parseDate = (iso) => {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
};

const diaDelMes = (base, dia) => {
  const d = new Date(base.getFullYear(), base.getMonth(), dia);
  return d.getMonth() === base.getMonth() ? d : new Date(base.getFullYear(), base.getMonth() + 1, 0);
};

// Alinea los vencimientos de las cuotas al ciclo de facturación de la tarjeta:
// si la compra cae en o antes del día de corte entra en la factura de ese mes
// (primera cuota vence el día de pago del mes siguiente); si cae después del
// corte, entra en la factura del mes siguiente. Retorna null si la tarjeta no
// tiene día de corte/pago configurado.
const fechasVencimientoCuotas = (method, fechaIso, numCuotas) => {
  const diaCorte = Number(method?.dia_corte);
  const diaPago = Number(method?.dia_pago);
  if (!diaCorte || !diaPago || !fechaIso || !numCuotas) return null;

  const operacion = parseDate(fechaIso);
  const corteMes = diaDelMes(operacion, diaCorte);
  const enEsteMes = operacion.getTime() <= corteMes.getTime();

  const mesFactura = new Date(operacion.getFullYear(), operacion.getMonth(), 1);
  if (!enEsteMes) mesFactura.setMonth(mesFactura.getMonth() + 1);

  const primera = diaDelMes(new Date(mesFactura.getFullYear(), mesFactura.getMonth() + 1, 1), diaPago);

  const fechas = [];
  for (let i = 0; i < numCuotas; i++) {
    const base = new Date(primera.getFullYear(), primera.getMonth() + i, 1);
    fechas.push(diaDelMes(base, diaPago));
  }
  return fechas;
};

const formatDate = (d) =>
  d.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });

export default function OperationModal() {
  const { modals, closeModal, fetchDashboardData } = useAppStore();
  const isOpen = modals.operation;

  // Masters
  const [contacts, setContacts] = useState([]);
  const [methods, setMethods] = useState([]);
  const [categories, setCategories] = useState([]);

  // Form State
  const [tipo, setTipo] = useState('GASTO_PERSONAL');
  const [esDiferida, setEsDiferida] = useState(false);
  const [cuotas, setCuotas] = useState(2);
  const [monto, setMonto] = useState('');
  const [fecha, setFecha] = useState(new Date().toISOString().split('T')[0]);
  const [contactoId, setContactoId] = useState('');
  const [metodoId, setMetodoId] = useState('');
  const [categoriaId, setCategoriaId] = useState('');
  const [descripcion, setDescripcion] = useState('');

  // Inline Contact Creation
  const [isCreatingContact, setIsCreatingContact] = useState(false);
  
  // Submit State
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [newContactData, setNewContactData] = useState({ dni: '', nombre: '', telefono: '' });

  useEffect(() => {
    if (isOpen) {
      fetchMasters();
      resetForm();
    }
  }, [isOpen]);

  const fetchMasters = async () => {
    try {
      const [cRes, mRes, catRes] = await Promise.all([
        api.get('/contacts'),
        api.get('/payment-methods'),
        api.get('/categories')
      ]);
      setContacts(cRes.data.data || cRes.data);
      setMethods(mRes.data);
      setCategories(catRes.data);
      
      if (mRes.data.length > 0) setMetodoId(mRes.data[0].id);
      if (catRes.data.length > 0) setCategoriaId(catRes.data[0].id);
    } catch (err) {
      console.error(err);
    }
  };

  const resetForm = () => {
    setTipo('GASTO_PERSONAL');
    setEsDiferida(false);
    setCuotas(2);
    setMonto('');
    setFecha(new Date().toISOString().split('T')[0]);
    setContactoId('');
    setDescripcion('');
    setIsCreatingContact(false);
    setNewContactData({ dni: '', nombre: '', telefono: '' });
    setError('');
  };

  const handleCreateContact = async () => {
    try {
      setLoading(true);
      const res = await api.post('/contacts', { 
        ...newContactData,
        alias: newContactData.nombre, 
        tipo_contacto: 'DEUDOR' 
      });
      const newContact = res.data.data || res.data;
      setContacts([...contacts, newContact]);
      setContactoId(newContact.id);
      setIsCreatingContact(false);
      setNewContactData({ dni: '', nombre: '', telefono: '' });
    } catch (err) {
      setError('Error al crear contacto');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async () => {
    if (!monto || !metodoId || !categoriaId) {
      setError('Faltan campos obligatorios');
      return;
    }
    
    if (['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'].includes(tipo) && !contactoId) {
      setError('Debe seleccionar un contacto para este tipo de operación');
      return;
    }

    try {
      setLoading(true);
      setError('');
      
      const payload = {
        tipo_operacion: tipo,
        monto_original: parseFloat(monto),
        moneda_original: 'PEN',
        fecha_operacion: fecha,
        payment_method_id: parseInt(metodoId),
        category_id: parseInt(categoriaId),
        contact_id: ['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'].includes(tipo) ? parseInt(contactoId) : null,
        es_diferida: esDiferida,
        numero_cuotas: esDiferida ? parseInt(cuotas) : 1,
        descripcion: descripcion || 'Operación registrada'
      };

      await api.post('/operations', payload);
      fetchDashboardData();
      closeModal('operation');
    } catch (err) {
      setError(err.response?.data?.message || 'Error al guardar operación');
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

  const selectedMethod = methods.find((m) => String(m.id) === String(metodoId));
  const vencimientos = esDiferida
    ? fechasVencimientoCuotas(selectedMethod, fecha, parseInt(cuotas, 10) || 2)
    : null;
  const montoCuotaAprox = monto && cuotas > 0 ? (parseFloat(monto) / parseInt(cuotas, 10)).toFixed(2) : '0.00';

  const tipos = [
    { id: 'GASTO_PERSONAL', label: 'Gasto' },
    { id: 'INGRESO_PERSONAL', label: 'Ingreso' },
    { id: 'COMPRA_TERCERO', label: 'Compra Tercero' },
    { id: 'PRESTAMO_OTORGADO', label: 'Préstamo' },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fade-in">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto animate-slide-up">
        <div className="flex justify-between items-center p-6 border-b border-slate-100 sticky top-0 bg-white z-10">
          <h2 className="text-xl font-bold text-slate-800">Registrar Movimiento</h2>
          <button onClick={() => closeModal('operation')} className="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6 space-y-6">
          {error && (
            <div className="p-3 bg-red-50 text-red-600 rounded-xl text-sm font-medium">
              {error}
            </div>
          )}
          
          <div>
            <label className="label-base">Tipo de Operación</label>
            <div className="flex flex-wrap gap-2 bg-slate-100 p-1 rounded-xl">
              {tipos.map((t) => (
                <button
                  key={t.id}
                  onClick={() => setTipo(t.id)}
                  className={clsx(
                    "flex-1 py-2 px-2 min-w-[120px] text-sm font-medium rounded-lg transition-all",
                    tipo === t.id ? "bg-white text-primary-600 shadow-sm" : "text-slate-500 hover:text-slate-700"
                  )}
                >
                  {t.label}
                </button>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="label-base">Monto Total (PEN)</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <DollarSign className="h-5 w-5 text-slate-400" />
                </div>
                <input type="number" value={monto} onChange={(e) => setMonto(e.target.value)} placeholder="0.00" className="input-base pl-10 text-lg font-semibold" />
              </div>
            </div>

            <div>
              <label className="label-base">Fecha de Operación</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Calendar className="h-5 w-5 text-slate-400" />
                </div>
                <input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} className="input-base pl-10" />
              </div>
            </div>
          </div>

          {['COMPRA_TERCERO', 'PRESTAMO_OTORGADO'].includes(tipo) && (
            <div className="animate-fade-in">
              <label className="label-base flex justify-between">
                <span>Contacto (Deudor)</span>
                {!isCreatingContact && (
                  <button onClick={() => setIsCreatingContact(true)} className="text-xs text-primary-600 font-medium hover:underline">
                    + Crear Nuevo
                  </button>
                )}
              </label>
              
              {isCreatingContact ? (
                <div className="bg-slate-50 p-4 rounded-xl space-y-3 mb-6 border border-slate-100 animate-slide-up">
                  <div className="flex justify-between items-center mb-2">
                    <h3 className="font-semibold text-slate-700 text-sm">Nuevo Contacto (Deudor)</h3>
                    <button onClick={() => setIsCreatingContact(false)} className="text-slate-400 hover:text-slate-600">
                      <X className="w-4 h-4" />
                    </button>
                  </div>
                  <div>
                    <label className="label-base text-xs">Documento (DNI/RUC)</label>
                    <input type="text" className="input-base text-sm" value={newContactData.dni} onChange={e => setNewContactData({...newContactData, dni: e.target.value})} placeholder="Ej. 70012345" />
                  </div>
                  <div>
                    <label className="label-base text-xs">Nombre Completo <span className="text-red-500">*</span></label>
                    <input type="text" className="input-base text-sm" value={newContactData.nombre} onChange={e => setNewContactData({...newContactData, nombre: e.target.value})} placeholder="Ej. Juan Pérez" autoFocus />
                  </div>
                  <div>
                    <label className="label-base text-xs">Teléfono</label>
                    <input type="text" className="input-base text-sm" value={newContactData.telefono} onChange={e => setNewContactData({...newContactData, telefono: e.target.value})} placeholder="Ej. 987654321" />
                  </div>
                  <button onClick={handleCreateContact} disabled={loading || !newContactData.nombre} className="btn btn-primary w-full py-2 text-sm mt-2">
                    {loading ? <Loader2 className="w-4 h-4 animate-spin mx-auto" /> : 'Guardar y Seleccionar'}
                  </button>
                </div>
              ) : (
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Users className="h-5 w-5 text-slate-400" />
                  </div>
                  <select className="input-base pl-10" value={contactoId} onChange={(e) => setContactoId(e.target.value)}>
                    <option value="">Seleccione un contacto...</option>
                    {contacts.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                  </select>
                </div>
              )}
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="label-base">Medio de Pago</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Wallet className="h-5 w-5 text-slate-400" />
                </div>
                <select className="input-base pl-10" value={metodoId} onChange={e => setMetodoId(e.target.value)}>
                  {methods.map(m => <option key={m.id} value={m.id}>{m.nombre} ({m.tipo})</option>)}
                </select>
              </div>
            </div>

            <div>
              <label className="label-base">Categoría</label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <Tag className="h-5 w-5 text-slate-400" />
                </div>
                <select className="input-base pl-10" value={categoriaId} onChange={e => setCategoriaId(e.target.value)}>
                  {categories.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                </select>
              </div>
            </div>
          </div>

          <div className="p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="text-sm font-semibold text-slate-800">¿Operación en Cuotas?</h4>
                <p className="text-xs text-slate-500">Divide el monto en pagos programados.</p>
              </div>
              <label className="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" className="sr-only peer" checked={esDiferida} onChange={() => setEsDiferida(!esDiferida)} />
                <div className="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
              </label>
            </div>
            
            {esDiferida && (
              <div className="mt-4 pt-4 border-t border-slate-200 animate-slide-up">
                <div className="flex gap-4 items-start">
                  <div className="w-1/3">
                    <label className="label-base text-xs">N° de Cuotas</label>
                    <input type="number" min="2" value={cuotas} onChange={(e) => setCuotas(e.target.value)} className="input-base text-center" />
                  </div>
                  <div className="w-2/3">
                    <p className="text-sm text-slate-600 mt-2">
                      Monto aprox. por cuota: <span className="font-bold text-slate-800">
                        S/ {montoCuotaAprox}
                      </span>
                    </p>
                  </div>
                </div>

                {vencimientos ? (
                  <div className="mt-3 bg-white border border-slate-200 rounded-xl p-3">
                    <p className="text-xs text-slate-500 font-medium mb-2">
                      Vencimientos según tu ciclo de facturación ({selectedMethod?.nombre} — corte {selectedMethod?.dia_corte}, pago {selectedMethod?.dia_pago}):
                    </p>
                    <ul className="space-y-1.5">
                      {vencimientos.map((f, i) => (
                        <li key={i} className="flex items-center justify-between text-sm">
                          <span className="text-slate-600">Cuota {i + 1} de {parseInt(cuotas, 10) || 2}</span>
                          <span className="font-semibold text-slate-800">S/ {montoCuotaAprox} · {formatDate(f)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : (
                  <p className="mt-3 text-xs text-slate-500">
                    Las cuotas vencerán mensualmente desde la fecha de operación. Para alinearlas a tu ciclo
                    de facturación, selecciona una tarjeta de crédito con día de corte y día de pago.
                  </p>
                )}
              </div>
            )}
          </div>
          
          <div>
            <label className="label-base">Descripción / Concepto</label>
            <textarea rows="2" className="input-base" placeholder="Ej. Almuerzo con clientes..." value={descripcion} onChange={e => setDescripcion(e.target.value)}></textarea>
          </div>
        </div>

        <div className="p-6 border-t border-slate-100 bg-slate-50 rounded-b-2xl flex justify-end gap-3 sticky bottom-0">
          <button onClick={() => closeModal('operation')} className="btn btn-secondary">Cancelar</button>
          <button onClick={handleSubmit} disabled={loading} className="btn btn-primary px-8">
            {loading ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Guardar Movimiento'}
          </button>
        </div>
      </div>
    </div>
  );
}
