import React from 'react';
import { ShieldAlert, TrendingDown, TrendingUp, Loader2 } from 'lucide-react';
import clsx from 'clsx';
import { useAppStore } from '../../store/useAppStore';

const formatPEN = (n) =>
  `S/ ${(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function ExigibilityWidget() {
  const exigibilidad = useAppStore((state) => state.dashboardData.exigibilidad);
  const loading = useAppStore((state) => state.dashboardData.loading);

  const aCobrarMes = exigibilidad?.a_cobrar_mes ?? 0;
  const aPagarMes = exigibilidad?.a_pagar_mes ?? 0;
  const flujo = exigibilidad?.flujo_esperado ?? 0;
  const descalce = exigibilidad?.alerta_descalce ?? false;

  return (
    <div className="card p-6 flex flex-col h-full">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-lg font-bold text-slate-800">Exigibilidad del Mes</h2>
        {descalce && (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
            <ShieldAlert className="h-3 w-3" />
            Alerta de Descalce
          </span>
        )}
      </div>

      {loading && exigibilidad === null ? (
        <div className="flex-1 flex flex-col items-center justify-center text-slate-400">
          <Loader2 className="w-8 h-8 animate-spin mb-3" />
          <p className="text-sm">Calculando exigibilidad...</p>
        </div>
      ) : (
        <>
          <div className="flex-1 flex flex-col justify-center gap-6">
            {/* A Cobrar */}
            <div className="flex items-center gap-4">
              <div className="p-3 bg-emerald-100 rounded-xl text-emerald-600">
                <TrendingUp className="h-6 w-6" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium text-slate-500">A Cobrar este mes</p>
                <p className="text-2xl font-bold text-slate-800">{formatPEN(aCobrarMes)}</p>
              </div>
            </div>

            <div className="w-full h-px bg-slate-100 my-2"></div>

            {/* A Pagar */}
            <div className="flex items-center gap-4">
              <div className="p-3 bg-rose-100 rounded-xl text-rose-600">
                <TrendingDown className="h-6 w-6" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-medium text-slate-500">A Pagar este mes</p>
                <p className="text-2xl font-bold text-slate-800">{formatPEN(aPagarMes)}</p>
              </div>
            </div>
          </div>

          <div className={clsx(
            "mt-6 p-4 rounded-xl border",
            descalce ? "bg-rose-50 border-rose-200" : "bg-emerald-50 border-emerald-200"
          )}>
            <p className="text-sm text-center font-medium text-slate-700">
              Flujo Esperado: <span className={descalce ? "text-rose-600 font-bold" : "text-emerald-600 font-bold"}>
                {flujo < 0 ? '-' : ''}{formatPEN(Math.abs(flujo))}
              </span>
            </p>
          </div>
        </>
      )}
    </div>
  );
}
