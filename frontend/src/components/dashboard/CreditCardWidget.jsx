import React from 'react';
import { CreditCard, AlertCircle, Loader2 } from 'lucide-react';
import { useAppStore } from '../../store/useAppStore';

const formatPEN = (n) =>
  `S/ ${(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatDate = (d) => {
  if (!d) return '-';
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y}`;
};

export default function CreditCardWidget() {
  const tarjetas = useAppStore((state) => state.dashboardData.tarjetas);
  const loading = useAppStore((state) => state.dashboardData.loading);

  return (
    <div className="card p-6 flex flex-col h-full">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-lg font-bold text-slate-800 flex items-center gap-2">
          <CreditCard className="h-5 w-5 text-primary-500" />
          Tarjetas de Crédito
        </h2>
      </div>

      <div className="space-y-6 flex-1">
        {loading && tarjetas === null ? (
          <div className="flex flex-col items-center justify-center text-slate-400 py-12">
            <Loader2 className="w-8 h-8 animate-spin mb-3" />
            <p className="text-sm">Cargando tarjetas...</p>
          </div>
        ) : tarjetas && tarjetas.length > 0 ? (
          tarjetas.map((t) => {
            const total = t.linea_total ?? 0;
            const usados = t.consumos_pendientes ?? 0;
            const porcentajeUso = total > 0 ? (usados / total) * 100 : 0;
            const isWarning = porcentajeUso > 80;

            return (
              <div key={t.id} className="relative p-5 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white overflow-hidden">
                <div className="flex justify-between items-start mb-4">
                  <div>
                    <h3 className="font-bold text-slate-800">{t.nombre}</h3>
                    <p className="text-xs text-slate-500 mt-1">Disponible: {formatPEN(t.linea_disponible)}</p>
                  </div>
                  {t.estado_cuenta && (
                    <div className="text-right">
                      <p className="text-sm font-semibold text-slate-800">{formatPEN(t.estado_cuenta.facturado_mes)}</p>
                      <p className="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Facturado</p>
                      {t.estado_cuenta.vencimiento && (
                        <p className="text-[10px] text-slate-400 mt-0.5">Pago límite: {formatDate(t.estado_cuenta.vencimiento)}</p>
                      )}
                    </div>
                  )}
                </div>

                {/* Progress Bar */}
                <div className="mt-4">
                  <div className="flex justify-between text-xs mb-1">
                    <span className="text-slate-600">Uso: {formatPEN(usados)}</span>
                    <span className="text-slate-400">Total: {formatPEN(total)}</span>
                  </div>
                  <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div 
                      className={`h-full rounded-full transition-all duration-500 ${isWarning ? 'bg-rose-500' : 'bg-primary-500'}`}
                      style={{ width: `${Math.min(porcentajeUso, 100)}%` }}
                    />
                  </div>
                  {isWarning && (
                    <p className="text-xs text-rose-600 mt-2 flex items-center gap-1">
                      <AlertCircle className="h-3 w-3" />
                      Línea de crédito casi agotada
                    </p>
                  )}
                </div>

                {/* Cuotas a pagar este ciclo */}
                {Array.isArray(t.estado_cuenta?.cuotas_a_pagar) && t.estado_cuenta.cuotas_a_pagar.length > 0 && (
                  <div className="mt-4 pt-4 border-t border-slate-100">
                    <p className="text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">
                      Cuota(s) a pagar este ciclo
                    </p>
                    <ul className="space-y-2">
                      {t.estado_cuenta.cuotas_a_pagar.map((c) => (
                        <li key={c.id} className="flex items-center justify-between gap-3 text-sm">
                          <div className="min-w-0">
                            <p className="text-slate-800 font-medium truncate">{c.descripcion}</p>
                            <p className="text-xs text-slate-500">
                              Cuota {c.numero_cuota}/{c.total_cuotas} · Vence {formatDate(c.fecha_vencimiento)}
                            </p>
                          </div>
                          <span className="font-bold text-slate-800 whitespace-nowrap">{formatPEN(c.monto_saldo)}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            );
          })
        ) : (
          <div className="flex flex-col items-center justify-center text-slate-400 py-12">
            <CreditCard className="w-8 h-8 mb-3 opacity-30" />
            <p className="text-sm text-center">Aún no tienes tarjetas registradas.<br />Regístralas en Configuración.</p>
          </div>
        )}
      </div>
    </div>
  );
}
