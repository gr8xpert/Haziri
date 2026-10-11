<x-filament-panels::page>
    @if ($this->isLocked())
        <x-filament::section icon="heroicon-o-lock-closed" icon-color="warning" :heading="__('Branding is managed by your provider')"
            :description="__('Contact them to change your logo, colours or names.')" />
    @endif
    <form wire:submit="save" class="flex flex-col gap-4">
        @if ($this->currentImage('logo') || $this->currentImage('icon'))
            <x-filament::section :heading="__('Current logo and icon')" :description="__('Upload a new file below to replace one.')">
                <div class="flex flex-wrap gap-6">
                    @foreach (['logo' => __('Logo'), 'icon' => __('App icon')] as $which => $label)
                        @if ($src = $this->currentImage($which))
                            <figure class="flex items-center gap-3">
                                <img src="{{ $src }}" alt="{{ $label }}" class="h-12 max-w-40 rounded-lg bg-white object-contain p-1 ring-1 ring-gray-950/10 dark:ring-white/10" />
                                <figcaption class="flex flex-col gap-1 text-sm">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $label }}</span>
                                    @unless ($this->isLocked())
                                        <x-filament::link tag="button" type="button" color="danger" size="sm" wire:click="removeImage('{{ $which }}')" wire:confirm="{{ __('Remove the :what?', ['what' => mb_strtolower($label)]) }}">{{ __('Remove') }}</x-filament::link>
                                    @endunless
                                </figcaption>
                            </figure>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        @endif
        {{ $this->form }}
        @unless ($this->isLocked())
            <div>
                <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">{{ __('Save branding') }}</x-filament::button>
            </div>
        @endunless
    </form>

    <x-filament::section :heading="__('Custom domain')" icon="heroicon-o-globe-alt"
        :description="__('Use your own address, e.g. attendance.yourcompany.pk: create a CNAME record pointing to :t.', ['t' => $this->cnameTarget()])">
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
