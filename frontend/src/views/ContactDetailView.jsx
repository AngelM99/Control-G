import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, User, DollarSign, Calendar, Loader2, AlertCircle, ArrowUpRight, ArrowDownRight, CreditCard, History, ChevronDown } from 'lucide-react';
import api from '../api/axios';

const fmt = (n) =>
  `S/ ${(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const estadoBadge = (estado) => {
  const color = estado?.color || 'slate';
  const map = {
    yellow: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    red: 'bg-red-50 text-red-700 border-red-200',
    slate: 'bg-slate-100 text-slate-600 border-slate-200',
  };
  return (
    <span className={`px-2 py-1 rounded text-xs font-medium border whitespace-nowrap ${map[color] || map.slate}`}>
      {estado?.label || estado?.id || 'Desconocido'}
    </span>
  );
};

const cuotaBadge = (estado) => {
  const color = estado?.id || 'PENDIENTE';
  const map = {
    PENDIENTE: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    PARCIAL: 'bg-sky-50 text-sky-700 border-sky-200',
    PAGADA: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    ANULADA: 'bg-red-50 text-red-700 border-red-200',
  };
  return (
    <span className={`px-2 py-1 rounded text-xs font-medium border whitespace-nowrap ${map[color] || map.PENDIENTE}`}>
      {estado?.label || estado?.id || 'Pendiente'}
    </span>
  );
};

export default function ContactDetailView() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Payment Modal State
  const [showPaymentModal, setShowPaymentModal] = useState(false);
  const [paymentAmount, setPaymentAmount] = useState('');
  const [paymentMethodId, setPaymentMethodId] = useState('');
  const [methods, setMethods] = useState([]);
  const [processingPayment, setProcessingPayment] = useState(false);
  const [paymentError, setPaymentError] = useState('');
  const [paymentMode, setPaymentMode] = useState('auto'); // 'auto' | 'cuota'
  const [paymentCuotaId, setPaymentCuotaId] = useState('');

  // Expansión de operaciones
  const [expanded, setExpanded] = useState({});

  useEffect(() => {
    fetchData();
    fetchMethods();
  }, [id]);

  const fetchData = async () => {
    try {
      setLoading(true);
      const res = await api.get(`/contacts/${id}/ficha`);
      setData(res.data);
    } catch (err) {
      setError('Error al cargar la información del contacto.');
    } finally {
      setLoading(false);
    }
  };

  const fetchMethods = async () => {
    try {
      const res = await api.get('/payment-methods');
      const list = res.data.data || res.data || [];
      setMethods(list);
      if (list.length > 0) setPaymentMethodId(list[0].id);
    } catch (err) {
      console.error(err);
    }
  };

  const handlePayment = async () => {
    const monto = parseFloat(paymentAmount);
    if (!monto || monto <= 0) {
      setPaymentError('Monto inválido');
      return;
    }
    if (paymentMode === 'cuota') {
      if (!paymentCuotaId) {
        setPaymentError('Selecciona la cuota a pagar');
        return;
      }
      const cuota = cuotasDisponibles.find((c) => c.id === parseInt(paymentCuotaId));
      if (cuota && monto > cuota.saldo + 0.01) {
        setPaymentError(`El monto supera el saldo de la cuota (${fmt(cuota.saldo)}). Usa pagos parciales.`);
        return;
      }
    }
    try {
      setProcessingPayment(true);
      setPaymentError('');

      const payload = {
        monto_original: monto,
        moneda_original: 'PEN',
        payment_method_id: paymentMethodId ? parseInt(paymentMethodId) : null,
        fecha_pago: new Date().toISOString().split('T')[0],
        modo_asignacion: paymentMode === 'cuota' ? 'manual' : 'auto',
        contact_id: parseInt(id),
      };

      if (paymentMode === 'cuota' && paymentCuotaId) {
        const cuota = cuotasDisponibles.find((c) => c.id === parseInt(paymentCuotaId));
        payload.asignaciones_manual = [
          { operation_id: cuota.operation_id, installment_id: cuota.id, monto },
        ];
      }

      await api.post('/payments', payload);
      setShowPaymentModal(false);
      setPaymentAmount('');
      setPaymentCuotaId('');
      setPaymentMode('auto');
      fetchData(); // Recargar datos para ver deuda actualizada
    } catch (err) {
      setPaymentError(err.response?.data?.message || 'Error al procesar pago');
    } finally {
      setProcessingPayment(false);
    }
  };

  const openPaymentModal = () => {
    setPaymentError('');
    setPaymentAmount('');
    setPaymentCuotaId('');
    setPaymentMode('auto');
    setShowPaymentModal(true);
  };

  const selectCuota = (cuotaId) => {
    setPaymentCuotaId(cuotaId);
    const cuota = cuotasDisponibles.find((c) => c.id === parseInt(cuotaId));
    if (cuota) setPaymentAmount(String(cuota.saldo));
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Loader2 className="w-8 h-8 animate-spin text-primary-500" />
      </div>
    );
  }

  if (error || !data) {
    return (
      <div className="bg-red-50 p-6 rounded-2xl text-red-600 flex flex-col items-center gap-4">
        <AlertCircle className="w-8 h-8" />
        <h2 className="text-xl font-bold">{error}</h2>
        <button onClick={() => navigate('/dashboard')} className="btn btn-secondary">Volver al Dashboard</button>
      </div>
    );
  }

  const { contacto, resumen = {}, deudas_activas = [], ultimos_abonos = [] } = data;
  const saldoACobrar = resumen.saldo_a_cobrar ?? 0;
  const saldoAPagar = resumen.saldo_a_pagar ?? 0;
  const saldoNeto = resumen.saldo_neto ?? 0;

  const cuotasDisponibles = deudas_activas.flatMap((op) =>
    (op.installments || [])
      .filter((i) => i.saldo > 0)
      .map((i) => ({ ...i, operation_id: op.id, operacion_desc: op.descripcion }))
  );

  const cuotaSeleccionada = cuotasDisponibles.find((c) => c.id === parseInt(paymentCuotaId));

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex items-center gap-4">
        <button onClick={() => navigate('/dashboard')} className="p-2 bg-white rounded-full shadow-sm hover:bg-slate-50">
          <ArrowLeft className="w-5 h-5 text-slate-600" />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-slate-800 flex items-center gap-2">
            <User className="w-6 h-6 text-primary-500" />
            {contacto.nombre}
          </h1>
          <p className="text-slate-500 mt-1">
            {contacto.dni ? `DNI: ${contacto.dni} · ` : ''}Detalle de deudas y cuotas
          </p>
        </div>
      </div>

      {/* KPIs del contacto */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <p className="text-sm font-medium text-slate-500 uppercase tracking-wider">Por Cobrar</p>
              <ArrowUpRight className="w-5 h-5 text-emerald-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">{fmt(saldoACobrar)}</span>
            </div>
          </div>
          {saldoACobrar > 0 && (
            <button
              onClick={openPaymentModal}
              className="mt-6 btn btn-primary w-full justify-center"
            >
              Registrar Pago / Abono
            </button>
          )}
        </div>

        <div className="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <p className="text-sm font-medium text-slate-500 uppercase tracking-wider">Por Pagar</p>
              <ArrowDownRight className="w-5 h-5 text-rose-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className="text-3xl font-extrabold text-slate-900">{fmt(saldoAPagar)}</span>
            </div>
          </div>
          <div className="mt-6 text-xs text-slate-500">Préstamos recibidos de este contacto</div>
        </div>

        <div className="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between">
              <p className="text-sm font-medium text-slate-500 uppercase tracking-wider">Saldo Neto</p>
              <DollarSign className="w-5 h-5 text-primary-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className={`text-3xl font-extrabold ${saldoNeto >= 0 ? 'text-slate-900' : 'text-rose-600'}`}>
                {fmt(saldoNeto)}
              </span>
            </div>
          </div>
          <div className="mt-6 text-xs text-slate-500">Historico cobrado: {fmt(resumen.historico_cobrado)} · pagado: {fmt(resumen.historico_pagado)}</div>
        </div>
      </div>

      {/* Lista de Deudas Activas con desglose por cuota */}
      <h3 className="text-lg font-bold text-slate-800 mt-8 mb-4">Deudas Activas</h3>
      
      {deudas_activas.length === 0 ? (
        <div className="bg-white rounded-2xl p-8 text-center border border-slate-100">
          <p className="text-slate-500">Este contacto no tiene deudas pendientes.</p>
        </div>
      ) : (
        <div className="space-y-4">
          {deudas_activas.map((op) => {
            const isExpanded = expanded[op.id] ?? true;
            const tieneCuotas = op.es_diferida && (op.installments?.length || 0) > 0;
            const cuotasPagadas = tieneCuotas
              ? (op.installments || []).filter((i) => i.estado?.id === 'PAGADA').length
              : 0;
            const proxVencimiento = tieneCuotas
              ? (op.installments || []).find((i) => i.saldo > 0)?.fecha_vencimiento
              : op.fecha_vencimiento;

            return (
              <div key={op.id} className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div className="px-6 py-4 bg-slate-50/60 border-b border-slate-100">
                  <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2 min-w-0">
                      {tieneCuotas && (
                        <button
                          onClick={() => setExpanded((p) => ({ ...p, [op.id]: !isExpanded }))}
                          className="p-1 -m-1 hover:bg-slate-200 rounded transition-colors"
                          title={isExpanded ? 'Ocultar cuotas' : 'Ver cuotas'}
                        >
                          <ChevronDown className={`w-4 h-4 text-slate-400 transition-transform ${isExpanded ? '' : '-rotate-90'}`} />
                        </button>
                      )}
                      <div className="min-w-0">
                        <p className="font-semibold text-slate-800">{op.descripcion}</p>
                        <p className="text-xs text-slate-500">
                          {op.tipo_operacion?.label} · {op.fecha_operacion}
                          {tieneCuotas && (
                            <span className="ml-2 inline-flex items-center gap-1 text-primary-600">
                              <CreditCard className="w-3 h-3" />
                              {cuotasPagadas}/{op.numero_cuotas} cuotas pagadas
                            </span>
                          )}
                        </p>
                      </div>
                    </div>
                    <div className="text-right flex flex-col items-end gap-1 flex-shrink-0">
                      <div className="flex items-center gap-3">
                        <span className="text-sm text-slate-400">Original</span>
                        <span className="font-semibold text-slate-600">{fmt(op.monto_original)}</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <span className="text-sm text-slate-400">Saldo</span>
                        <span className="font-bold text-red-600">{fmt(op.monto_saldo)}</span>
                      </div>
                    </div>
                  </div>
                  <div className="flex items-center justify-between gap-3 mt-3">
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                      <Calendar className="w-4 h-4" />
                      Próximo vencimiento: <span className="font-semibold text-slate-700">{proxVencimiento || '-'}</span>
                    </div>
                    {estadoBadge(op.estado_deuda)}
                  </div>
                </div>

                {tieneCuotas && isExpanded && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                      <thead className="bg-slate-50 border-b border-slate-100 text-xs font-medium text-slate-500">
                        <tr>
                          <th className="px-4 py-3">Cuota</th>
                          <th className="px-4 py-3">Vencimiento</th>
                          <th className="px-4 py-3 text-right">Monto</th>
                          <th className="px-4 py-3 text-right">Abonado</th>
                          <th className="px-4 py-3 text-right">Saldo</th>
                          <th className="px-4 py-3 text-right">Estado</th>
                          <th className="px-4 py-3">Abonos (historial)</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {op.installments.map((inst) => (
                          <tr key={inst.id} className="hover:bg-slate-50/50 transition-colors">
                            <td className="px-4 py-3 font-medium text-slate-700">
                              Cuota {inst.numero_cuota}/{inst.total_cuotas}
                            </td>
                            <td className="px-4 py-3">
                              <div className="flex items-center gap-1.5 text-slate-600">
                                <Calendar className="w-3.5 h-3.5 text-slate-400" />
                                {inst.fecha_vencimiento || '-'}
                              </div>
                            </td>
                            <td className="px-4 py-3 text-right text-slate-600">{fmt(inst.monto_cuota)}</td>
                            <td className="px-4 py-3 text-right text-emerald-600 font-medium">{fmt(inst.monto_abonado)}</td>
                            <td className="px-4 py-3 text-right">
                              <span className="font-bold text-red-600">{fmt(inst.saldo)}</span>
                            </td>
                            <td className="px-4 py-3 text-right">{cuotaBadge(inst.estado)}</td>
                            <td className="px-4 py-3">
                              {inst.pagos?.length > 0 ? (
                                <div className="space-y-0.5">
                                  {inst.pagos.map((p, idx) => (
                                    <div key={idx} className="flex items-center gap-1.5 text-xs text-slate-500">
                                      <History className="w-3 h-3 text-slate-400 flex-shrink-0" />
                                      <span className="text-slate-600 font-medium">{fmt(p.monto)}</span>
                                      <span>{p.fecha}</span>
                                      {p.referencia && <span className="text-slate-400">· {p.referencia}</span>}
                                    </div>
                                  ))}
                                </div>
                              ) : (
                                <span className="text-xs text-slate-300">—</span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* Últimos Abonos */}
      <h3 className="text-lg font-bold text-slate-800 mt-8 mb-4">Últimos Abonos</h3>
      {ultimos_abonos.length === 0 ? (
        <div className="bg-white rounded-2xl p-8 text-center border border-slate-100">
          <p className="text-slate-500">Aún no hay abonos registrados para este contacto.</p>
        </div>
      ) : (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
          <table className="w-full text-left">
            <thead className="bg-slate-50 border-b border-slate-100 text-sm font-medium text-slate-500">
              <tr>
                <th className="px-6 py-4">Fecha</th>
                <th className="px-6 py-4">Operación</th>
                <th className="px-6 py-4">Referencia</th>
                <th className="px-6 py-4 text-right">Monto</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {ultimos_abonos.map((a, i) => (
                <tr key={i} className="hover:bg-slate-50/50 transition-colors">
                  <td className="px-6 py-4 text-sm text-slate-600">{a.fecha}</td>
                  <td className="px-6 py-4 text-sm text-slate-700">{a.operacion_desc || '-'}</td>
                  <td className="px-6 py-4 text-sm text-slate-500">{a.referencia || '-'}</td>
                  <td className="px-6 py-4 text-right font-bold text-emerald-600">{fmt(a.monto)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Payment Modal */}
      {showPaymentModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden animate-slide-up">
            <div className="p-6 border-b border-slate-100">
              <h2 className="text-xl font-bold text-slate-800">Registrar Pago de Deuda</h2>
              <p className="text-sm text-slate-500 mt-1">Contacto: {contacto.nombre}</p>
            </div>
            <div className="p-6 space-y-4">
              {paymentError && <div className="text-red-500 text-sm">{paymentError}</div>}

              {/* Modo de asignación */}
              <div>
                <label className="label-base">Aplicar pago a</label>
                <div className="grid grid-cols-2 gap-2 mt-1">
                  <button
                    type="button"
                    onClick={() => setPaymentMode('auto')}
                    className={`btn text-sm justify-center ${paymentMode === 'auto' ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    Automático (FIFO)
                  </button>
                  <button
                    type="button"
                    onClick={() => setPaymentMode('cuota')}
                    className={`btn text-sm justify-center ${paymentMode === 'cuota' ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    Cuota específica
                  </button>
                </div>
              </div>

              {paymentMode === 'cuota' && (
                <div>
                  <label className="label-base">¿Qué cuota vas a pagar?</label>
                  <select
                    className="input-base mt-1"
                    value={paymentCuotaId}
                    onChange={(e) => selectCuota(e.target.value)}
                  >
                    <option value="">Selecciona la cuota...</option>
                    {cuotasDisponibles.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.operacion_desc} · Cuota {c.numero_cuota}/{c.total_cuotas} · vence {c.fecha_vencimiento} · saldo {fmt(c.saldo)}
                      </option>
                    ))}
                  </select>
                  {cuotaSeleccionada && (
                    <div className="mt-2 bg-slate-50 rounded-xl p-3 text-xs text-slate-600">
                      <div className="flex justify-between">
                        <span>Monto de la cuota</span>
                        <span className="font-semibold">{fmt(cuotaSeleccionada.monto_cuota)}</span>
                      </div>
                      <div className="flex justify-between mt-1">
                        <span>Abonado a la fecha</span>
                        <span className="font-semibold text-emerald-600">{fmt(cuotaSeleccionada.monto_abonado)}</span>
                      </div>
                      <div className="flex justify-between mt-1">
                        <span>Saldo pendiente</span>
                        <span className="font-semibold text-red-600">{fmt(cuotaSeleccionada.saldo)}</span>
                      </div>
                      {cuotaSeleccionada.pagos?.length > 0 && (
                        <div className="mt-2 pt-2 border-t border-slate-200">
                          <p className="font-medium text-slate-500 mb-1">Abonos anteriores de esta cuota:</p>
                          <div className="space-y-0.5">
                            {cuotaSeleccionada.pagos.map((p, idx) => (
                              <div key={idx} className="flex justify-between">
                                <span>{p.fecha}{p.referencia ? ` · ${p.referencia}` : ''}</span>
                                <span className="font-medium">{fmt(p.monto)}</span>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}

              <div>
                <label className="label-base">Monto a pagar (Parcial o Total)</label>
                <div className="relative mt-1">
                  <DollarSign className="absolute left-3 top-2.5 w-5 h-5 text-slate-400" />
                  <input type="number" value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} placeholder="0.00" className="input-base pl-10" />
                </div>
                {paymentMode === 'cuota' && cuotaSeleccionada ? (
                  <p className="text-xs text-slate-500 mt-1">
                    Puedes abonar parcialmente. Saldo de la cuota: {fmt(cuotaSeleccionada.saldo)}.
                  </p>
                ) : (
                  <p className="text-xs text-slate-500 mt-1">El abono se aplicará a la deuda más antigua (FIFO).</p>
                )}
              </div>

              <div>
                <label className="label-base">¿Dónde ingresó el dinero?</label>
                <select className="input-base mt-1" value={paymentMethodId} onChange={(e) => setPaymentMethodId(e.target.value)}>
                  <option value="">Seleccione un método...</option>
                  {methods.map(m => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                </select>
              </div>
            </div>
            <div className="p-6 bg-slate-50 flex justify-end gap-3">
              <button onClick={() => setShowPaymentModal(false)} className="btn btn-secondary">Cancelar</button>
              <button onClick={handlePayment} disabled={processingPayment} className="btn btn-primary">
                {processingPayment ? <Loader2 className="w-5 h-5 animate-spin" /> : 'Procesar Pago'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}