<?php

namespace App\Providers;

use App\Services\CreditCardService;
use App\Services\DebtPaymentService;
use App\Services\OperationService;
use Illuminate\Support\ServiceProvider;

/**
 * DomainServiceProvider
 *
 * Registra los servicios de dominio de Control-G en el contenedor de Laravel.
 * Utiliza singleton para garantizar una única instancia por request (stateless).
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Servicios sin dependencias externas ───────────────────────────────────
        $this->app->singleton(OperationService::class);
        $this->app->singleton(DebtPaymentService::class);

        // ── CreditCardService depende de los anteriores (inyección automática) ───
        $this->app->singleton(CreditCardService::class, function ($app) {
            return new CreditCardService(
                operationService:   $app->make(OperationService::class),
                debtPaymentService: $app->make(DebtPaymentService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
