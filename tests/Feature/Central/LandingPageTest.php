<?php

declare(strict_types=1);

it('links the landing page to the live demo company when there is one', function () {
    config(['hazri.demo.tenant' => 'kunsystems']);

    $this->get('http://' . config('hazri.central_domain') . '/')
        ->assertOk()->assertSee('Try the live demo')->assertSee('kunsystems.' . config('hazri.central_domain'), false);
});

it('shows no demo button without a demo company', function () {
    config(['hazri.demo.tenant' => null]);

    $this->get('http://' . config('hazri.central_domain') . '/')->assertOk()->assertDontSee('Try the live demo');
});
