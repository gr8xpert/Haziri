<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\SuperAdmin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates the platform owner's login for admin.{domain} on a fresh server, or sets a new password for an existing
 * one. Without --password a strong one is generated and printed once.
 */
#[Signature('hazri:super-admin {email} {--name=Super Admin} {--password=}')]
#[Description('Create a super-admin login (or reset its password)')]
final class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));
        $validator = Validator::make(['email' => $email, 'password' => $password], ['email' => 'required|email', 'password' => 'min:10']);
        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $admin = SuperAdmin::query()->firstOrNew(['email' => $email]);
        $existed = $admin->exists;
        $admin->fill(['name' => $admin->name ?? $this->option('name'), 'password' => $password])->save();

        $this->info(($existed ? 'Password reset for ' : 'Super admin created: ') . $email);
        if (! $this->option('password')) {
            $this->line("Password: {$password}  (shown once: store it now)");
        }

        return self::SUCCESS;
    }
}
