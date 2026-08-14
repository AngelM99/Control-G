<?php

namespace Tests\Feature;

use App\Exceptions\OverpaymentException;
use App\Models\Contact;
use App\Models\Operation;
use App\Models\PaymentMethod;
use App\Services\CreditCardService;
use App\Services\DebtPaymentService;
use App\Services\DTOs\ApplyPaymentDTO;
use App\Services\DTOs\CreateOperationDTO;
use App\Services\OperationService;
use App\Models\TipoOperacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BusinessRulesTest — Suite de integración completa.
 *
 * Valida el estricto cumplimiento de las Reglas de Negocio RN-01 a RN-08
 * y los Flujos Operativos A, B, C, D del SRS v3.0.
 *
 * Usa RefreshDatabase para garantizar aislamiento entre pruebas.
 */
class BusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Setup Compartido ──────────────────────────────────────────────────────

    private OperationService $operationService;
    private DebtPaymentService $paymentService;
    private CreditCardService $creditCardService;

    private Contact $pepito;
    private PaymentMethod $bbvaVisa;
    private PaymentMethod $yape;
    private PaymentMethod $bcpAhorros;

    protected function setUp(): void
    {
        parent::setUp();

        // Autenticar a un usuario para las rutas protegidas
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->operationService  = app(OperationService::class);
        $this->paymentService    = app(DebtPaymentService::class);
        $this->creditCardService = app(CreditCardService::class);

        // Entidades base del SRS §6.2
        $this->pepito = Contact::create([
            'nombre'        => 'Pepito',
            'alias'         => 'Pepito',
            'tipo_contacto' => 'DEUDOR',
            'estado'        => 'ACTIVO',
        ]);

        $this->bbvaVisa = PaymentMethod::create([
            'tipo'                   => 'CREDITO',
            'nombre'                 => 'BBVA Visa Black',
            'banco'                  => 'BBVA',
            'moneda'                 => 'PEN',
            'linea_total'            => 10000.00,
            'saldo_actual'           => 10000.00,
            'dia_corte'              => 15,
            'dia_pago'               => 5,
            'comision_mantenimiento' => 0.00,
        ]);

        $this->yape = PaymentMethod::create([
            'tipo'         => 'BILLETERA',
            'nombre'       => 'Yape',
            'banco'        => 'BCP',
            'moneda'       => 'PEN',
            'saldo_actual' => 100.00,
        ]);

        $this->bcpAhorros = PaymentMethod::create([
            'tipo'         => 'DEBITO',
            'nombre'       => 'BCP Ahorros',
            'banco'        => 'BCP',
            'moneda'       => 'PEN',
            'saldo_actual' => 2000.00,
        ]);
    }

    // ─── Helper: Registrar Compra para Tercero ─────────────────────────────────

    private function registrarCompraTercero(
        float $monto = 2000.00,
        bool $diferida = false,
        int $cuotas = 1
    ): Operation {
        return $this->operationService->register(new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::CompraTercero,
            contactId:       $this->pepito->id,
            paymentMethodId: $this->bbvaVisa->id,
            categoryId:      null,
            descripcion:     "Compra test S/ {$monto}",
            fechaOperacion:  now()->toDateString(),
            fechaVencimiento:null,
            montoOriginal:   $monto,
            monedaOriginal:  'PEN',
            tipoCambio:      1.0,
            esDiferida:      $diferida,
            numeroCuotas:    $cuotas,
        ));
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 1 — RN-01: INDEPENDENCIA DE GASTOS PERSONALES
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function rn01_compra_tercero_no_incrementa_gastos_del_mes(): void
    {
        // Flujo A: Compra de TV S/ 2,000 para Pepito
        $operation = $this->registrarCompraTercero(2000.00);

        // RN-01: COMPRA_TERCERO NO debe sumarse a gastos personales
        $gastosMes = Operation::where('tipo_operacion', 'GASTO_PERSONAL')
            ->whereMonth('fecha_operacion', now()->month)
            ->whereYear('fecha_operacion', now()->year)
            ->sum('monto_pen');

        $this->assertEquals(0.00, (float) $gastosMes,
            'RN-01: La compra para tercero NO debe aparecer en gastos personales del mes.');

        // La operación sí debe existir
        $this->assertDatabaseHas('operations', [
            'id'             => $operation->id,
            'tipo_operacion' => 'COMPRA_TERCERO',
            'estado_deuda'   => 'PENDIENTE',
        ]);
    }

    /** @test */
    public function rn01_gasto_personal_si_incrementa_acumulador_mes(): void
    {
        // Un GASTO_PERSONAL sí debe sumar
        $gastoPersonal = PaymentMethod::create([
            'tipo'         => 'EFECTIVO',
            'nombre'       => 'Efectivo',
            'moneda'       => 'PEN',
            'saldo_actual' => 5000.00,
        ]);

        $this->operationService->register(new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::GastoPersonal,
            contactId:       null,
            paymentMethodId: $gastoPersonal->id,
            categoryId:      null,
            descripcion:     'Almuerzo',
            fechaOperacion:  now()->toDateString(),
            fechaVencimiento:null,
            montoOriginal:   50.00,
            monedaOriginal:  'PEN',
            tipoCambio:      1.0,
            esDiferida:      false,
            numeroCuotas:    1,
        ));

        $gastosMes = Operation::where('tipo_operacion', 'GASTO_PERSONAL')
            ->whereMonth('fecha_operacion', now()->month)
            ->sum('monto_pen');

        $this->assertEquals(50.00, (float) $gastosMes,
            'RN-01: El gasto personal SÍ debe aparecer en el acumulador del mes.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 2 — RN-02: LÍNEA DISPONIBLE DE TARJETA
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function rn02_linea_disponible_disminuye_tras_compra_tercero(): void
    {
        // Verificar línea inicial
        $lineaInicial = $this->creditCardService->calcularLineaDisponible($this->bbvaVisa->id);
        $this->assertEquals(10000.00, $lineaInicial['linea_disponible'],
            'RN-02: Línea disponible inicial debe ser 10,000.');

        // Flujo A
        $this->registrarCompraTercero(2000.00);

        $lineaPostCompra = $this->creditCardService->calcularLineaDisponible($this->bbvaVisa->id);

        $this->assertEquals(8000.00, $lineaPostCompra['linea_disponible'],
            'RN-02: Tras compra de S/ 2,000, la línea disponible debe ser S/ 8,000.');
        $this->assertEquals(2000.00, $lineaPostCompra['consumos_pendientes'],
            'RN-02: Consumos pendientes deben ser S/ 2,000.');
    }

    /** @test */
    public function rn02_linea_disponible_se_restaura_tras_abono_completo(): void
    {
        $operation = $this->registrarCompraTercero(2000.00);

        // Abonar el total
        $this->paymentService->apply(new ApplyPaymentDTO(
            montoOriginal:      2000.00,
            monedaOriginal:     'PEN',
            tipoCambio:         1.0,
            paymentMethodId:    $this->yape->id,
            fechaPago:          now()->toDateString(),
            referencia:         null,
            notas:              null,
            modoAsignacion:     'auto',
            asignacionesManual: [],
            contactId:          $this->pepito->id,
        ));

        $operation->refresh();
        $linea = $this->creditCardService->calcularLineaDisponible($this->bbvaVisa->id);

        $this->assertEquals('CANCELADO', $operation->estado_deuda instanceof \BackedEnum
            ? $operation->estado_deuda->value : $operation->estado_deuda,
            'RN-02: Tras pago completo, estado_deuda debe ser CANCELADO.');
        $this->assertEquals(10000.00, $linea['linea_disponible'],
            'RN-02: Línea disponible debe restaurarse a 10,000 tras pago completo.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 3 — RN-06: ANTI-SOBREPAGO
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function rn06_intento_sobrepago_lanza_excepcion(): void
    {
        // Deuda de S/ 2,000
        $this->registrarCompraTercero(2000.00);

        $this->expectException(OverpaymentException::class);

        // Intentar abonar S/ 3,000 cuando solo se deben S/ 2,000
        $this->paymentService->apply(new ApplyPaymentDTO(
            montoOriginal:      3000.00,
            monedaOriginal:     'PEN',
            tipoCambio:         1.0,
            paymentMethodId:    $this->bcpAhorros->id,
            fechaPago:          now()->toDateString(),
            referencia:         null,
            notas:              null,
            modoAsignacion:     'auto',
            asignacionesManual: [],
            contactId:          $this->pepito->id,
        ));
    }

    /** @test */
    public function rn06_api_responde_422_ante_sobrepago(): void
    {
        // Deuda de S/ 2,000
        $this->registrarCompraTercero(2000.00);

        $response = $this->postJson('/api/payments', [
            'monto_original'    => 3000.00,
            'moneda_original'   => 'PEN',
            'payment_method_id' => $this->bcpAhorros->id,
            'fecha_pago'        => now()->toDateString(),
            'modo_asignacion'   => 'auto',
            'contact_id'        => $this->pepito->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['rule' => 'RN-06']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 4 — MOTOR FIFO
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function fifo_abono_salda_primera_deuda_mas_antigua(): void
    {
        // Crear deuda 1 (más antigua: hace 10 días) — S/ 500
        $op1 = $this->operationService->register(new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::CompraTercero,
            contactId:       $this->pepito->id,
            paymentMethodId: $this->bbvaVisa->id,
            categoryId:      null,
            descripcion:     'Deuda antigua S/ 500',
            fechaOperacion:  now()->subDays(10)->toDateString(),
            fechaVencimiento:null,
            montoOriginal:   500.00,
            monedaOriginal:  'PEN',
            tipoCambio:      1.0,
            esDiferida:      false,
            numeroCuotas:    1,
        ));

        // Crear deuda 2 (más reciente: ayer) — S/ 800
        $op2 = $this->operationService->register(new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::CompraTercero,
            contactId:       $this->pepito->id,
            paymentMethodId: $this->bbvaVisa->id,
            categoryId:      null,
            descripcion:     'Deuda reciente S/ 800',
            fechaOperacion:  now()->subDays(1)->toDateString(),
            fechaVencimiento:null,
            montoOriginal:   800.00,
            monedaOriginal:  'PEN',
            tipoCambio:      1.0,
            esDiferida:      false,
            numeroCuotas:    1,
        ));

        // Abonar S/ 600 en modo FIFO (auto)
        $resultado = $this->paymentService->apply(new ApplyPaymentDTO(
            montoOriginal:      600.00,
            monedaOriginal:     'PEN',
            tipoCambio:         1.0,
            paymentMethodId:    $this->bcpAhorros->id,
            fechaPago:          now()->toDateString(),
            referencia:         'Abono FIFO',
            notas:              null,
            modoAsignacion:     'auto',
            asignacionesManual: [],
            contactId:          $this->pepito->id,
        ));

        $op1->refresh();
        $op2->refresh();

        // FIFO: La deuda más antigua (op1, S/ 500) debe estar CANCELADA
        $estadoOp1 = $op1->estado_deuda instanceof \BackedEnum
            ? $op1->estado_deuda->value : $op1->estado_deuda;
        $this->assertEquals('CANCELADO', $estadoOp1,
            'FIFO: La deuda más antigua (S/ 500) debe quedar CANCELADA con el abono de S/ 600.');

        // El residuo (S/ 100) debe haber ido a op2
        $this->assertEquals(100.00, (float) $op2->monto_abonado,
            'FIFO: El residuo de S/ 100 debe haber abonado a la segunda deuda.');

        $estadoOp2 = $op2->estado_deuda instanceof \BackedEnum
            ? $op2->estado_deuda->value : $op2->estado_deuda;
        $this->assertEquals('PARCIAL', $estadoOp2,
            'FIFO: La deuda más reciente debe quedar en estado PARCIAL.');

        $this->assertEquals(0.00, (float) $resultado->saldoSinAsignar,
            'FIFO: Todo el monto debe quedar asignado (saldo_sin_asignar = 0).');
    }

    /** @test */
    public function fifo_abono_respeta_orden_cuotas_en_diferidos(): void
    {
        // Compra en 3 cuotas
        $operation = $this->registrarCompraTercero(monto: 300.00, diferida: true, cuotas: 3);
        $cuotas = $operation->installments()->orderBy('numero_cuota')->get();

        $this->assertCount(3, $cuotas, 'Deben generarse exactamente 3 cuotas.');

        // Abonar monto exacto de la primera cuota (S/ 100)
        $this->paymentService->apply(new ApplyPaymentDTO(
            montoOriginal:      100.00,
            monedaOriginal:     'PEN',
            tipoCambio:         1.0,
            paymentMethodId:    $this->bcpAhorros->id,
            fechaPago:          now()->toDateString(),
            referencia:         null,
            notas:              null,
            modoAsignacion:     'auto',
            asignacionesManual: [],
            contactId:          $this->pepito->id,
        ));

        $cuotas = $operation->installments()->orderBy('numero_cuota')->get();

        $this->assertEquals('PAGADA', $cuotas->first()->estado instanceof \BackedEnum
            ? $cuotas->first()->estado->value : $cuotas->first()->estado,
            'FIFO Cuotas: La cuota 1 debe estar PAGADA.');
        $this->assertEquals('PENDIENTE', $cuotas->get(1)->estado instanceof \BackedEnum
            ? $cuotas->get(1)->estado->value : $cuotas->get(1)->estado,
            'FIFO Cuotas: La cuota 2 debe seguir PENDIENTE.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 5 — RN-08: GENERACIÓN DE CUOTAS
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function rn08_genera_n_cuotas_correctamente(): void
    {
        // Flujo C: Laptop S/ 2,000 en 6 cuotas
        $operation = $this->registrarCompraTercero(monto: 2000.00, diferida: true, cuotas: 6);

        $cuotas = $operation->installments;
        $this->assertCount(6, $cuotas, 'RN-08: Deben generarse exactamente 6 cuotas.');

        // Cuota estándar
        $cuotaEstandar = round(2000.00 / 6, 2); // 333.33
        foreach ($cuotas->take(5) as $cuota) {
            $this->assertEquals($cuotaEstandar, (float) $cuota->monto_cuota,
                "RN-08: Cuota {$cuota->numero_cuota} debe ser S/ {$cuotaEstandar}.");
        }

        // La última absorbe el residuo
        $sumaStandard = $cuotaEstandar * 5;
        $ultimaCuotaEsperada = round(2000.00 - $sumaStandard, 2);
        $this->assertEquals($ultimaCuotaEsperada, (float) $cuotas->last()->monto_cuota,
            'RN-08: La última cuota debe absorber el residuo de redondeo.');

        // Suma total debe ser exactamente S/ 2,000
        $sumaTotalCuotas = $cuotas->sum('monto_cuota');
        $this->assertEqualsWithDelta(2000.00, (float) $sumaTotalCuotas, 0.01,
            'RN-08: La suma de todas las cuotas debe ser S/ 2,000.');
    }

    /** @test */
    public function rn08_rechaza_operacion_diferida_con_1_cuota(): void
    {
        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $this->operationService->register(new CreateOperationDTO(
            tipoOperacion:   TipoOperacion::CompraTercero,
            contactId:       $this->pepito->id,
            paymentMethodId: $this->bbvaVisa->id,
            categoryId:      null,
            descripcion:     'Error: diferida con 1 cuota',
            fechaOperacion:  now()->toDateString(),
            fechaVencimiento:null,
            montoOriginal:   1000.00,
            monedaOriginal:  'PEN',
            tipoCambio:      1.0,
            esDiferida:      true,
            numeroCuotas:    1, // ← Debe fallar (RN-08 exige ≥ 2)
        ));
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 6 — RN-04: SOFT DELETE / ANULACIÓN
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function rn04_anulacion_no_elimina_fisicamente(): void
    {
        $operation = $this->registrarCompraTercero(500.00);
        $id = $operation->id;

        $this->operationService->anular($operation, 'Test de anulación RN-04');

        // Debe existir con soft delete
        $this->assertSoftDeleted('operations', ['id' => $id]);

        // No debe aparecer en consultas normales
        $this->assertNull(Operation::find($id), 'RN-04: Una operación anulada no debe aparecer en búsquedas normales.');

        // Sí debe aparecer con withTrashed
        $this->assertNotNull(Operation::withTrashed()->find($id), 'RN-04: La operación debe persistir con soft delete.');
    }

    /** @test */
    public function rn04_no_puede_anular_operacion_con_abonos(): void
    {
        $operation = $this->registrarCompraTercero(2000.00);

        // Abonar parcialmente
        $this->paymentService->apply(new ApplyPaymentDTO(
            montoOriginal:      500.00,
            monedaOriginal:     'PEN',
            tipoCambio:         1.0,
            paymentMethodId:    $this->bcpAhorros->id,
            fechaPago:          now()->toDateString(),
            referencia:         null,
            notas:              null,
            modoAsignacion:     'auto',
            asignacionesManual: [],
            contactId:          $this->pepito->id,
        ));

        $this->expectException(\App\Exceptions\BusinessRuleException::class);

        $operation->refresh();
        $this->operationService->anular($operation, 'Intento de anulación inválida');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  BLOQUE 7 — API ENDPOINTS: Validación HTTP
    // ══════════════════════════════════════════════════════════════════════════

    /** @test */
    public function api_store_operation_devuelve_201_con_datos_correctos(): void
    {
        $response = $this->postJson('/api/operations', [
            'tipo_operacion'    => 'COMPRA_TERCERO',
            'contact_id'        => $this->pepito->id,
            'payment_method_id' => $this->bbvaVisa->id,
            'descripcion'       => 'API Test Compra',
            'fecha_operacion'   => now()->toDateString(),
            'monto_original'    => 1500.00,
            'moneda_original'   => 'PEN',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.tipo_operacion.id', 'COMPRA_TERCERO')
                 ->assertJsonPath('data.estado_deuda.id', 'PENDIENTE')
                 ->assertJsonPath('data.monto_pen', 1500);
    }

    /** @test */
    public function api_store_operation_sin_contacto_en_compra_tercero_devuelve_422(): void
    {
        $response = $this->postJson('/api/operations', [
            'tipo_operacion'    => 'COMPRA_TERCERO',
            // Sin contact_id → debe fallar
            'payment_method_id' => $this->bbvaVisa->id,
            'descripcion'       => 'Sin contacto',
            'fecha_operacion'   => now()->toDateString(),
            'monto_original'    => 1000.00,
            'moneda_original'   => 'PEN',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['contact_id']);
    }

    /** @test */
    public function api_dashboard_kpis_devuelve_estructura_esperada(): void
    {
        $response = $this->getJson('/api/dashboard/kpis');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         'gastos_mes',
                         'ingresos_mes',
                         'flujo_neto',
                         'cuentas_por_cobrar',
                         'cuentas_por_pagar',
                         'patrimonio_deudas_neto',
                     ]
                 ]);
    }

    /** @test */
    public function api_contact_ficha_consolidada_devuelve_estructura_esperada(): void
    {
        // Crear deuda previa
        $this->registrarCompraTercero(1000.00);

        $response = $this->getJson("/api/contacts/{$this->pepito->id}/ficha");

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'contacto' => ['id', 'nombre'],
                     'resumen'  => [
                         'saldo_a_cobrar',
                         'saldo_a_pagar',
                         'historico_cobrado',
                         'historico_pagado',
                         'saldo_neto',
                     ],
                     'deudas_activas',
                     'ultimos_abonos',
                 ])
                 ->assertJsonPath('resumen.saldo_a_cobrar', 1000);
    }
}
