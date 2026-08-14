<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Alimentación',
                'tipo'   => 'GASTO',
                'icono'  => 'Utensils',
                'color'  => '#EF4444', // Red
            ],
            [
                'nombre' => 'Transporte',
                'tipo'   => 'GASTO',
                'icono'  => 'Car',
                'color'  => '#F59E0B', // Amber
            ],
            [
                'nombre' => 'Servicios Básicos',
                'tipo'   => 'GASTO',
                'icono'  => 'Zap',
                'color'  => '#3B82F6', // Blue
            ],
            [
                'nombre' => 'Entretenimiento',
                'tipo'   => 'GASTO',
                'icono'  => 'Tv',
                'color'  => '#8B5CF6', // Purple
            ],
            [
                'nombre' => 'Salud',
                'tipo'   => 'GASTO',
                'icono'  => 'Heart',
                'color'  => '#10B981', // Emerald
            ],
            [
                'nombre' => 'Salario',
                'tipo'   => 'INGRESO',
                'icono'  => 'Banknote',
                'color'  => '#10B981', // Emerald
            ],
            [
                'nombre' => 'Negocio / Ventas',
                'tipo'   => 'INGRESO',
                'icono'  => 'Briefcase',
                'color'  => '#3B82F6', // Blue
            ],
            [
                'nombre' => 'Otros Ingresos',
                'tipo'   => 'INGRESO',
                'icono'  => 'PlusCircle',
                'color'  => '#F59E0B', // Amber
            ]
        ];

        foreach ($categorias as $cat) {
            Category::firstOrCreate(
                ['nombre' => $cat['nombre'], 'tipo' => $cat['tipo']],
                $cat
            );
        }
    }
}
