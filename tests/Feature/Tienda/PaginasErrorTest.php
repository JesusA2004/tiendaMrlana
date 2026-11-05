<?php

it('la página 404 está en español y con la marca', function () {
    $this->get('/esta-pagina-no-existe')
        ->assertNotFound()
        ->assertSee('No encontramos esta página')
        ->assertSee('Ir a la tienda')
        ->assertDontSee('Not Found');
});

it('el 403 del panel para un vendedor está en español', function () {
    sembrarMetodosPago();

    $this->actingAs(vendedorTienda())
        ->get(route('admin.pagos.index'))
        ->assertForbidden()
        ->assertSee('Acceso no permitido')
        ->assertDontSee('Forbidden');
});
