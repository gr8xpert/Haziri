<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-4">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">{{ __('Save branding') }}</x-filament::button>
        </div>
    </form>

    <x-filament::section :heading="__('Custom domain')" icon="heroicon-o-globe-alt"
        :description="__('Use your own address, e.g. attendance.yourcompany.pk. Your :d address keeps working.', ['d' => tenant('slug') . '.' . config('hazri.central_domain')])">
        <ul class="mb-4 flex flex-col divide-y divide-gray-950/[0.05] text-sm dark:divide-white/[0.05]">
            @forelse ($this->getDomains() as $d)
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="inline-flex items-center gap-2 font-medium text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-m-link" class="size-4 text-gray-400" />{{ $d }}
                    </span>
                    <x-filament::link tag="button" color="danger" size="sm" wire:click="removeDomain('{{ $d }}')" wire:confirm="{{ __('Remove this domain?') }}">{{ __('Remove') }}</x-filament::link>
                </li>
            @empty
                <li class="py-2.5 text-gray-500 dark:text-gray-400">{{ __('No custom domain yet.') }}</li>
            @endforelse
        </ul>
        <form wire:submit="addDomain" class="flex flex-col gap-2 sm:flex-row">
            <label for="hz-domain" class="sr-only">{{ __('Domain') }}</label>
            <x-filament::input.wrapper class="grow" :valid="! $errors->has('newDomain')">
                <x-filament::input id="hz-domain" type="text" wire:model="newDomain" placeholder="attendance.yourcompany.pk" />
            </x-filament::input.wrapper>
            <x-filament::button type="submit" color="gray" icon="heroicon-m-plus">{{ __('Add domain') }}</x-filament::button>
        </form>
        @error('newDomain')<p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
    </x-filament::section>
</x-filament-panels::page>
