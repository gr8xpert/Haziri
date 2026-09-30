<?php

declare(strict_types=1);

use Tests\TenantTestCase;
use Tests\TestCase;

/*
| Unit tests (status engine etc.) are plain PHP and don't boot Laravel — keeps them fast.
| Feature/Tenant tests run inside the `pest` test tenant; Feature/Central tests only boot the app.
*/
pest()->extend(TenantTestCase::class)->in('Feature/Tenant');
pest()->extend(TestCase::class)->in('Feature/Central');
