<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->getCompanies() as $id => $name)
            <a href="{{ route('tenant.switch', $id) }}"
               class="hz-card group flex items-center gap-4 p-5 transition hover:ring-primary-500/50 focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:outline-none">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-base font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                    {{ mb_strtoupper(mb_substr($name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold text-gray-950 dark:text-white">{{ $name }}</span>
                    <span class="block text-sm text-gray-500 dark:text-gray-400">{{ __('Open this company') }}</span>
                </span>
                <x-filament::icon icon="heroicon-m-arrow-right" class="size-5 text-gray-400 transition-transform motion-safe:group-hover:translate-x-0.5 rtl:rotate-180" />
            </a>
        @endforeach
    </div>

    <x-filament::section :heading="__('Add another company')" :description="__('You need a login with the same email in that company. Type its address and the password of that login.')">
        <form wire:submit="link" class="grid max-w-xl gap-3">
            <label for="hz-link-address" class="hz-label">{{ __('Company address') }}</label>
            <x-filament::input.wrapper :valid="! $errors->has('address')">
                <x-filament::input id="hz-link-address" wire:model="address" :placeholder="__('for example acme or attendance.acme.pk')" />
            </x-filament::input.wrapper>
            @error('address')<p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
            <label for="hz-link-password" class="hz-label">{{ __('Your password in that company') }}</label>
            <x-filament::input.wrapper :valid="! $errors->has('password')">
                <x-filament::input id="hz-link-password" type="password" wire:model="password" autocomplete="off" />
            </x-filament::input.wrapper>
            @error('password')<p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
            <div><x-filament::button type="submit" icon="heroicon-m-link">{{ __('Add company') }}</x-filament::button></div>
        </form>
    </x-filament::section>
</x-filament-panels::page>
