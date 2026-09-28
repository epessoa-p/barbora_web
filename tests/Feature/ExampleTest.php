<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_raiz_lleva_al_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_el_dashboard_exige_iniciar_sesion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_la_pantalla_de_login_carga(): void
    {
        $this->get('/login')->assertOk()->assertSee('Barbora');
    }
}
