<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Services\CreditCardService;
use App\Services\DebtPaymentService;
use App\Services\DTOs\ApplyPaymentDTO;
use App\Services\DTOs\CreateOperationDTO;
use App\Services\OperationService;
use App\Models\TipoOperacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SRSDemonstrationSeeder
 *
 * Reproduce los Flujos A, B, C y D descritos en el Capítulo 6.2 del SRS v3.0.
 * Orden de ejecución estricto para validar la integridad de las Reglas de Negocio.
 *
 * Flujo A → Compra para Tercero (contado)
 * Flujo B → Abono Parcial FIFO
 * Flujo C → Compra para Tercero en Cuotas
 * Flujo D → Pago de Estado de Cuenta (Tarjeta)
 */
class SRSDemonstrationSeeder extends Seeder
{
    public function __construct(
        private readonly OperationService   $operationService,
        private readonly DebtPaymentService $debtPaymentService,
        private readonly CreditCardService  $creditCardService,
    ) {}

    public function run(): void
    {
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Control-G · SRS Demo Seeder (Capítulo 6.2)');
        $this->command->info('═══════════════════════════════════════════════════════');

        DB::transaction(function () {
            // ── ESTADO INICIAL ────────────────────────────────────────────────────────
            $this->command->info('');
            $this->command->info('[INIT] Creando entidades base...');

            $pepito = Contact::create([
                'nombre'        => 'Pepito',
                'alias'         => 'Pepito',
                'tipo_contacto' => 'DEUDOR',
                'estado'        => 'ACTIVO',
            ]);

            $bbvaVisa = PaymentMethod::create([
                'tipo'                   => 'CREDITO',
                'nombre'                 => 'BBVA Visa Black',
                'banco'                  => 'BBVA',
                'moneda'                 => 'PEN',
                'linea_total'            => 10000.00,
                'saldo_actual'           => 10000.00, // Saldo = Línea para CC
                'dia_corte'              => 15,
                'dia_pago'              => 5,
                'comision_mantenimiento' => 0.00,
            ]);

            $yape = PaymentMethod::create([
                'tipo'         => 'BILLETERA',
                'nombre'       => 'Yape',
                'banco'        => 'BCP',
                'moneda'       => 'PEN',
                'saldo_actual' => 100.00,
            ]);

            $bcpAhorros = PaymentMethod::create([
                'tipo'         => 'DEBITO',
                'nombre'       => 'BCP Ahorros',
                'banco'        => 'BCP',
                'moneda'       => 'PEN',
                'saldo_actual' => 2000.00,
            ]);

            $this->command->info("  ✔ Contacto: {$pepito->nombre} (ID: {$pepito->id})");
            $this->command->info("  ✔ Tarjeta : {$bbvaVisa->nombre} (Línea: S/ {$bbvaVisa->linea_total})");
            $this->command->info("  ✔ Yape    : Saldo S/ {$yape->saldo_actual}");
            $this->command->info("  ✔ BCP     : Saldo S/ {$bcpAhorros->saldo_actual}");

            // ── FLUJO A: Compra para Tercero (S/ 2,000 contado) ──────────────────────
            $this->command->info('');
            $this->command->info('[FLUJO A] Compra para Tercero – TV S/ 2,000 con BBVA Visa Black...');

            $opFlujoa = $this->operationService->register(new CreateOperationDTO(
                tipoOperacion:   TipoOperacion::CompraTercero,
                contactId:       $pepito->id,
                paymentMethodId: $bbvaVisa->id,
                categoryId:      null,
                descripcion:     'Compra TV para Pepito',
                fechaOperacion:  now()->toDateString(),
                fechaVencimiento:null,
                montoOriginal:   2000.00,
                monedaOriginal:  'PEN',
                tipoCambio:      1.0,
                esDiferida:      false,
                numeroCuotas:    1,
            ));

            $bbvaVisa->refresh();
            $lineaDisp = $this->creditCardService->calcularLineaDisponible($bbvaVisa->id);

            $this->command->info("  ✔ Operación registrada (ID: {$opFlujoa->id})");
            $this->command->info("  ✔ Estado deuda: {$opFlujoa->estado_deuda?->value}");
            $this->command->info("  ✔ BBVA Línea Disponible: S/ {$lineaDisp['linea_disponible']} (esperado: 8000.00)");
            $this->assertEqual(8000.00, $lineaDisp['linea_disponible'], 'RN-02: Línea disponible tras Flujo A');

            // ── FLUJO B: Abono Parcial de S/ 500 vía Yape ────────────────────────────
            $this->command->info('');
            $this->command->info('[FLUJO B] Abono Parcial – S/ 500 de Pepito vía Yape (FIFO)...');

            $resultadoAbono = $this->debtPaymentService->apply(new ApplyPaymentDTO(
                montoOriginal:      500.00,
                monedaOriginal:     'PEN',
                tipoCambio:         1.0,
                paymentMethodId:    $yape->id,
                fechaPago:          now()->toDateString(),
                referencia:         'Abono Pepito vía Yape',
                notas:              'Flujo B SRS',
                modoAsignacion:     'auto',
                asignacionesManual: [],
                contactId:          $pepito->id,
                tipoOperacion:      null,
            ));

            $opFlujoa->refresh();
            $yape->refresh();

            $this->command->info("  ✔ Payment creado (ID: {$resultadoAbono->payment->id})");
            $this->command->info("  ✔ Asignado a operación {$opFlujoa->id}: S/ {$opFlujoa->monto_abonado} abonados");
            $this->command->info("  ✔ Saldo pendiente TV: S/ {$opFlujoa->monto_saldo} (esperado: 1500.00)");
            $this->command->info("  ✔ Estado deuda: {$opFlujoa->estado_deuda?->value} (esperado: PARCIAL)");
            $this->command->info("  ✔ Saldo Yape post-abono: S/ {$yape->saldo_actual} (esperado: 600.00)");
            $this->assertEqual(1500.00, (float)$opFlujoa->monto_saldo, 'Saldo TV tras Flujo B');
            $this->assertEqual('PARCIAL', $opFlujoa->estado_deuda instanceof \BackedEnum ? $opFlujoa->estado_deuda->value : $opFlujoa->estado_deuda, 'Estado PARCIAL Flujo B');
            $this->assertEqual(600.00, $yape->saldo_actual, 'Saldo Yape tras abono');

            // ── FLUJO C: Compra en Cuotas (S/ 2,000 / 6 cuotas) ─────────────────────
            $this->command->info('');
            $this->command->info('[FLUJO C] Compra en 6 Cuotas – Laptop S/ 2,000 con BBVA Visa Black...');

            $opFlujoC = $this->operationService->register(new CreateOperationDTO(
                tipoOperacion:   TipoOperacion::CompraTercero,
                contactId:       $pepito->id,
                paymentMethodId: $bbvaVisa->id,
                categoryId:      null,
                descripcion:     'Laptop para Pepito (6 cuotas)',
                fechaOperacion:  now()->toDateString(),
                fechaVencimiento:null,
                montoOriginal:   2000.00,
                monedaOriginal:  'PEN',
                tipoCambio:      1.0,
                esDiferida:      true,
                numeroCuotas:    6,
            ));

            $opFlujoC->load('installments');
            $lineaDispC = $this->creditCardService->calcularLineaDisponible($bbvaVisa->id);

            $montoCuotaEsperado = round(2000.00 / 6, 2); // 333.33

            $this->command->info("  ✔ Operación diferida (ID: {$opFlujoC->id}) - {$opFlujoC->installments->count()} cuotas generadas");
            $this->command->info("  ✔ Monto por cuota: S/ {$opFlujoC->installments->first()->monto_cuota} (esperado: ~S/ {$montoCuotaEsperado})");
            $this->command->info("  ✔ BBVA Línea Disponible: S/ {$lineaDispC['linea_disponible']} (esperado: ~6000.00)");
            $this->assertEqual(6, $opFlujoC->installments->count(), 'RN-08: 6 cuotas generadas');

            // ── FLUJO D: Pago de Tarjeta desde BCP Ahorros ───────────────────────────
            $this->command->info('');
            $this->command->info('[FLUJO D] Pago de Estado de Cuenta BBVA desde BCP Ahorros...');

            $estadoCuenta = $this->creditCardService->calcularEstadoDeCuenta($bbvaVisa->id);
            $montoFacturado = $estadoCuenta['total_facturado'];
            
            $this->command->info("  ✔ Monto facturado del mes: S/ {$montoFacturado}");

            // Solo proceder si hay algo que pagar
            if ($montoFacturado > 0) {
                $resultadoPago = $this->creditCardService->pagarTarjeta(
                    tarjetaCreditoId:  $bbvaVisa->id,
                    cuentaFuenteId:    $bcpAhorros->id,
                    montoOriginal:     $montoFacturado,
                    monedaOriginal:    'PEN',
                    tipoCambio:        1.0,
                    fechaPago:         now()->toDateString(),
                    descripcion:       'Pago estado de cuenta BBVA - Flujo D SRS',
                );

                $bcpAhorros->refresh();
                $this->command->info("  ✔ Pago registrado (Operación ID: {$resultadoPago['operation']->id})");
                $this->command->info("  ✔ BCP Saldo restante: S/ {$bcpAhorros->saldo_actual}");
            } else {
                $this->command->warn('  ⚠ No hay consumos facturados este ciclo (sin cuotas vencidas en período actual).');
            }

            // ── RESUMEN FINAL ─────────────────────────────────────────────────────────
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info('  RESUMEN FINAL DE SALDOS');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info("  Pepito – Deuda total activa (TV): S/ {$opFlujoa->fresh()->monto_saldo}");
            $this->command->info("  Pepito – Laptop (diferida): S/ {$opFlujoC->fresh()->monto_saldo}");
            $this->command->info("  BBVA Línea disponible: S/ {$this->creditCardService->calcularLineaDisponible($bbvaVisa->id)['linea_disponible']}");
            $this->command->info("  Yape saldo: S/ {$yape->fresh()->saldo_actual}");
            $this->command->info("  BCP saldo: S/ {$bcpAhorros->fresh()->saldo_actual}");
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info('  ✅ SRS Demo Seeder completado exitosamente.');
        });
    }

    private function assertEqual(float|int|string $expected, float|int|string $actual, string $label): void
    {
        $ok = is_string($expected)
            ? ($expected === $actual)
            : (abs((float)$expected - (float)$actual) <= 0.02);

        if (!$ok) {
            $this->command->error("  ❌ ASSERTION FAILED [{$label}]: esperado={$expected}, obtenido={$actual}");
        } else {
            $this->command->info("  ✅ OK [{$label}]");
        }
    }
}
