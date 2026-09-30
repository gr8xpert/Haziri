<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check-circle">{{ __('Save branding') }}</x-filament::button>
    </form>

    <x-filament::section :heading="__('Custom domain')">
        <p class="mb-3 text-sm text-gray-500">{{ __('Use your own address, e.g. attendance.yourcompany.pk. Your :d address keeps working.', ['d' => tenant('slug') . '.' . config('hazri.central_domain')]) }}</p>
        <ul class="mb-3 space-y-1 text-sm">
            @forelse ($this->getDomains() as $d)
                <li class="flex items-center gap-3"><span class="font-mono">{{ $d }}</span>
                    <x-filament::link tag="button" color="danger" wire:click="removeDomain('{{ $d }}')" wire:confirm="{{ __('Remove this domain?') }}">{{ __('Remove') }}</x-filament::link></li>
            @empty
                <li class="text-gray-500">{{ __('No custom domain yet.') }}</li>
            @endforelse
        </ul>
        <form wire:submit="addDomain" class="flex gap-2">
            <x-filament::input.wrapper class="grow" :valid="! $errors->has('newDomain')">
                <x-filament::input type="text" wire:model="newDomain" placeholder="attendance.yourcompany.pk" />
            </x-filament::input.wrapper>
            <x-filament::button type="submit">{{ __('Add domain') }}</x-filament::button>
        </form>
        @error('newDomain')<p class="mt-1 text-sm text-danger-600">{{ $message }}</p>@enderror
    </x-filament::section>
</x-filament-panels::page>
