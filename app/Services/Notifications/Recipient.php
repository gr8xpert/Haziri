<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Employee;
use App\Models\User;

final readonly class Recipient
{
    public function __construct(
        public string $name,
        public ?string $email = null,
        public ?string $phone = null,
        public string $locale = 'en',
        public ?int $userId = null,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self($user->name, $user->email, $user->phone ?? $user->employee?->phone, $user->locale ?? 'en', $user->id);
    }

    /** Prefer the employee's login (for in-app + their locale); fall back to the employee record. */
    public static function fromEmployee(Employee $employee): self
    {
        $user = User::query()->where('employee_id', $employee->id)->where('is_active', true)->first();

        return $user !== null
            ? new self($employee->fullName(), $user->email ?: $employee->email, $employee->phone ?: $user->phone, $user->locale ?? 'en', $user->id)
            : new self($employee->fullName(), $employee->email, $employee->phone);
    }

    public function key(): string
    {
        return $this->userId ? "user:{$this->userId}" : ($this->email ?? $this->phone ?? $this->name);
    }
}
