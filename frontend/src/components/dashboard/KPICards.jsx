import React from 'react';
import { ArrowUpRight, ArrowDownRight, Activity, DollarSign } from 'lucide-react';
import clsx from 'clsx';
import { useAppStore } from '../../store/useAppStore';

const formatPEN = (n) =>
  `S/ ${(n ?? 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export default function KPICards() {
  const kpis = useAppStore((state) => state.dashboardData.kpis);

  const cards = [
    {
      id: 1,
      name: 'Cuentas por Cobrar',
      value: formatPEN(kpis?.cuentas_por_cobrar),
      icon: ArrowUpRight,
      color: 'text-emerald-500',
      bg: 'bg-emerald-500/10',
    },
    {
      id: 2,
      name: 'Gastos del Mes',
      value: formatPEN(kpis?.gastos_mes),
      icon: ArrowDownRight,
      color: 'text-rose-500',
      bg: 'bg-rose-500/10',
    },
    {
      id: 3,
      name: 'Ingresos del Mes',
      value: formatPEN(kpis?.ingresos_mes),
      icon: DollarSign,
      color: 'text-blue-500',
      bg: 'bg-blue-500/10',
    },
    {
      id: 4,
      name: 'Flujo Neto (Mes)',
      value: formatPEN(kpis?.flujo_neto),
      icon: Activity,
      color: 'text-primary-500',
      bg: 'bg-primary-500/10',
    },
  ];

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {cards.map((item) => (
        <div key={item.id} className="card p-6 flex flex-col justify-between hover:shadow-md transition-shadow group cursor-pointer">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium text-slate-500">{item.name}</h3>
            <div className={clsx('p-2 rounded-lg transition-colors', item.bg, 'group-hover:bg-opacity-20')}>
              <item.icon className={clsx('h-5 w-5', item.color)} aria-hidden="true" />
            </div>
          </div>
          
          <div className="mt-4 flex items-baseline gap-2">
            <span className="text-3xl font-bold tracking-tight text-slate-800">{item.value}</span>
          </div>
          
          <div className="mt-2 flex items-center text-sm">
            <span className="text-slate-500">Mes actual</span>
          </div>
        </div>
      ))}
    </div>
  );
}
