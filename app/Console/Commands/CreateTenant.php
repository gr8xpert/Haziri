<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\ProvisionTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('hazri:tenant:create {slug} {company} {--plan=starter} {--admin-email=} {--admin-name=}')]
#[Description('Provision a new company: central row, subdomain, database, defaults and owner login')]
final class CreateTenant extends Command
{
    public function handle(ProvisionTenant $provision): int
    {
        $email = $this->option('admin-email') ?: $this->ask('Owner email');

        try {
            ['tenant' => $tenant, 'owner' => $owner, 'password' => $password] = $provision(
                slug: (string) $this->argument('slug'),
                companyName: (string) $this->argument('company'),
                adminEmail: (string) $email,
                planCode: $this->option('plan') ?: null,
                adminName: $this->option('admin-name') ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $messages) {
                $this->error(implode(' ', $messages));
            }

            return self::FAILURE;
        }

        $host = $tenant->slug . '.' . config('hazri.central_domain');
        $this->info("Tenant '{$tenant->company_name}' created (db: {$tenant->database()->getName()}).");
        $this->line("Panel:    http://{$host}/admin");
        $this->line("Login:    {$owner->email}");
        $this->line("Password: {$password}  (shown once — change after first login)");

        return self::SUCCESS;
    }
}
