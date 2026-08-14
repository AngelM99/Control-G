import React, { useState } from 'react';
import { Search, Loader2, AlertCircle, ArrowRight, User, CreditCard, Users, Calendar, ChevronDown } from 'lucide-react';
import api from '../../api/axios';
import { useNavigate } from 'react-router-dom';

const fmt = (n) =>
  `S/ ${(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const mesActual = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`; // YYYY-MM
};

const operationRow = (op) => (
  <div key={op.id} className="bg-slate-50 rounded-xl border border-slate-100 p-3">
    <div className="flex items-center justify-between gap-2">
      <div className="min-w-0">
        <p className="font-semibold text-slate-800 text-sm truncate">{op.descripcion}</p>
        <p className="text-xs text-slate-500">
          {op.tipo_operacion?.label}
          {op.es_diferida && ` · ${op.numero_cuotas} cuotas`}
        </p>
      </div>
      <span className={`font-bold text-sm whitespace-nowrap ${op.estado_deuda?.color === 'green' ? 'text-emerald-600' : 'text-red-600'}`}>
        {fmt(op.monto_saldo)}
      </span>
    </div>

    {op.es_diferida && op.installments?.length > 0 && (
      <div className="mt-2 space-y-1.5">
        {op.installments.map((inst) => (
          <div key={inst.id} className="flex items-center gap-2 pl-3 border-l-2 border-primary-200">
            <CreditCard className="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
            <div className="flex-1 text-xs">
              <span className="font-medium text-slate-700">Cuota {inst.numero_cuota}/{inst.total_cuotas}</span>
              <span className="text-slate-400"> · vence {inst.fecha_vencimiento}</span>
            </div>
            <span className={`text-xs font-semibold ${inst.saldo > 0 ? 'text-red-500' : 'text-emerald-600'}`}>
              {inst.saldo > 0 ? fmt(inst.saldo) : 'Pagada'}
            </span>
          </div>
        ))}
      </div>
    )}
  </div>
);

export default function ContactsWidget() {
  const [modo, setModo] = useState('persona'); // 'persona' | 'mes'
  const [dni, setDni] = useState('');
  const [criterio, setCriterio] = useState('fecha_operacion'); // 'fecha_operacion' | 'fecha_vencimiento'
  const [periodo, setPeriodo] = useState(mesActual);
  const [periodoMes, setPeriodoMes] = useState(mesActual);

  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');
  const [expandedIds, setExpandedIds] = useState({});
  const navigate = useNavigate();

  const handleSearch = async (e) => {
    e.preventDefault();

    if (modo === 'persona' && !dni) return;

    try {
      setLoading(true);
      setError('');
      setResult(null);
      setExpandedIds({});

      if (modo === 'persona') {
        const res = await api.get(`/contacts/search?dni=${dni}&periodo=${periodo}`);
        setResult({ tipo: 'persona', ...res.data });
      } else {
        const res = await api.get(`/contacts/deudas-periodo?periodo=${periodoMes}&criterio=${criterio}`);
        setResult({ tipo: 'mes', ...res.data });
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Error al consultar la información.');
    } finally {
      setLoading(false);
    }
  };

  const toggleDeudor = (contactId) => {
    setExpandedIds((prev) => ({ ...prev, [contactId]: !prev[contactId] }));
  };

  return (
    <div className="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex flex-col max-h-[560px]">
      <div className="flex items-center gap-3 mb-4">
        <div className="w-10 h-10 rounded-xl bg-primary-100 flex items-center justify-center text-primary-600">
          {modo === 'persona' ? <Search className="w-5 h-5" /> : <Users className="w-5 h-5" />}
        </div>
        <div>
          <h2 className="text-lg font-bold text-slate-800">Buscar Deuda</h2>
          <p className="text-sm text-slate-500">
            {modo === 'persona' ? 'Consulta rápida por DNI' : 'Reporte mensual de deudores'}
          </p>
        </div>
      </div>

      {/* Toggle Persona | Mes */}
      <div className="grid grid-cols-2 gap-2 mb-5">
        <button
          type="button"
          onClick={() => { setModo('persona'); setResult(null); setError(''); }}
          className={`btn text-sm py-2 justify-center ${modo === 'persona' ? 'btn-primary' : 'btn-secondary'}`}
        >
          <Search className="w-4 h-4 mr-1.5" /> Por Persona
        </button>
        <button
          type="button"
          onClick={() => { setModo('mes'); setResult(null); setError(''); }}
          className={`btn text-sm py-2 justify-center ${modo === 'mes' ? 'btn-primary' : 'btn-secondary'}`}
        >
          <Users className="w-4 h-4 mr-1.5" /> Por Mes
        </button>
      </div>

      <form onSubmit={handleSearch} className="space-y-4 mb-6">
        {modo === 'persona' ? (
          <div className="flex gap-2">
            <div className="flex-1">
              <input
                type="text"
                className="input-base text-sm py-2 w-full"
                placeholder="DNI del contacto"
                value={dni}
                onChange={(e) => setDni(e.target.value)}
              />
            </div>
            <div className="w-32">
              <input
                type="month"
                className="input-base text-sm py-2 w-full"
                value={periodo}
                onChange={(e) => setPeriodo(e.target.value)}
              />
            </div>
          </div>
        ) : (
          <div className="space-y-3">
            <div className="flex gap-2">
              <div className="flex-1 flex items-center gap-2 input-base text-sm py-2">
                <Calendar className="w-4 h-4 text-slate-400" />
                <input
                  type="month"
                  className="w-full outline-none"
                  value={periodoMes}
                  onChange={(e) => setPeriodoMes(e.target.value)}
                />
              </div>
            </div>
            <div>
              <label className="text-xs font-medium text-slate-500 mb-1 block">Criterio del periodo</label>
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => setCriterio('fecha_operacion')}
                  className={`btn text-xs py-1.5 justify-center ${criterio === 'fecha_operacion' ? 'btn-primary' : 'btn-secondary'}`}
                >
                  Fecha de compra
                </button>
                <button
                  type="button"
                  onClick={() => setCriterio('fecha_vencimiento')}
                  className={`btn text-xs py-1.5 justify-center ${criterio === 'fecha_vencimiento' ? 'btn-primary' : 'btn-secondary'}`}
                >
                  Vencimiento
                </button>
              </div>
            </div>
          </div>
        )}
        <button
          type="submit"
          disabled={loading || (modo === 'persona' && !dni)}
          className="btn btn-primary w-full py-2"
        >
          {loading ? <Loader2 className="w-5 h-5 animate-spin mx-auto" /> : 'Mostrar'}
        </button>
      </form>

      {error && (
        <div className="p-3 bg-red-50 text-red-600 rounded-xl text-sm flex gap-2 items-center">
          <AlertCircle className="w-4 h-4 flex-shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {result && result.tipo === 'persona' && (
        <div className="flex-1 flex flex-col min-h-0 animate-slide-up">
          <div className="flex items-center gap-3 p-5 pb-4 border-b border-slate-100">
            <div className="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
              <User className="w-5 h-5" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-bold text-slate-800 truncate">{result.contacto.nombre}</p>
              <p className="text-xs text-slate-500">DNI: {result.contacto.dni || '-'}</p>
            </div>
            <div className="text-right flex-shrink-0">
              <p className="text-sm font-bold text-red-600">{fmt(result.total_deuda)}</p>
              <p className="text-[11px] text-slate-400">Deuda {periodo}</p>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-3 space-y-2">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 px-2">
              Detalle por compra ({result.operaciones?.length ?? 0})
            </p>
            {result.operaciones?.length === 0 && (
              <p className="text-sm text-slate-500 text-center py-4">Sin deudas en este periodo.</p>
            )}
            {result.operaciones?.map((op) => operationRow(op))}
          </div>

          <button
            onClick={() => navigate(`/contacts/${result.contacto.id}`)}
            className="btn bg-slate-200 text-slate-700 hover:bg-slate-300 w-full mt-4 flex items-center justify-center gap-2"
          >
            Ver Ficha Completa <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      )}

      {result && result.tipo === 'mes' && (
        <div className="flex-1 flex flex-col min-h-0 animate-slide-up">
          <div className="p-5 pb-4 border-b border-slate-100">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-bold text-slate-800">Reporte periodo {result.periodo}</p>
                <p className="text-xs text-slate-500">
                  {result.cantidad_deudores} deudores · criterio:{' '}
                  {result.criterio === 'fecha_operacion' ? 'fecha de compra' : 'vencimiento'}
                </p>
              </div>
              <p className="text-lg font-black text-red-600">{fmt(result.total_general)}</p>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto p-3 space-y-2">
            {result.contactos?.length === 0 && (
              <p className="text-sm text-slate-500 text-center py-4">No hay deudores en este periodo.</p>
            )}
            {result.contactos?.map((c) => {
              const isExpanded = expandedIds[c.contact_id];
              return (
                <div key={c.contact_id} className="bg-slate-50 rounded-xl border border-slate-100 overflow-hidden">
                  <button
                    onClick={() => toggleDeudor(c.contact_id)}
                    className="w-full flex items-center gap-2 p-3 hover:bg-slate-100/60 transition-colors"
                  >
                    <div className="w-9 h-9 rounded-full bg-white flex items-center justify-center text-slate-400 shadow-sm flex-shrink-0">
                      <User className="w-4 h-4" />
                    </div>
                    <div className="flex-1 min-w-0 text-left">
                      <p className="font-semibold text-slate-800 text-sm truncate">{c.contacto?.nombre}</p>
                      <p className="text-xs text-slate-500">
                        {c.contacto?.dni ? `DNI ${c.contacto.dni} · ` : ''}{c.operaciones?.length} compra(s)
                      </p>
                    </div>
                    <span className="font-bold text-red-600 text-sm whitespace-nowrap">{fmt(c.total_deuda)}</span>
                    <ChevronDown className={`w-4 h-4 text-slate-400 transition-transform ${isExpanded ? 'rotate-180' : ''}`} />
                  </button>

                  {isExpanded && (
                    <div className="px-3 pb-3 pr-3">
                      <div className="space-y-2 animate-fade-in">
                        {c.operaciones.map((op) => operationRow(op))}
                      </div>
                      <button
                        onClick={() => navigate(`/contacts/${c.contact_id}`)}
                        className="w-full mt-2 btn bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 text-xs py-1.5 flex items-center justify-center gap-1.5"
                      >
                        Ver Ficha Completa <ArrowRight className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {!result && !error && (
        <div className="flex-1 flex flex-col items-center justify-center text-slate-400">
          {modo === 'persona' ? (
            <>
              <Search className="w-8 h-8 mb-2 opacity-20" />
              <p className="text-sm">Ingresa el DNI para consultar</p>
            </>
          ) : (
            <>
              <Users className="w-8 h-8 mb-2 opacity-20" />
              <p className="text-sm">Selecciona un mes para ver el reporte</p>
            </>
          )}
        </div>
      )}
    </div>
  );
}