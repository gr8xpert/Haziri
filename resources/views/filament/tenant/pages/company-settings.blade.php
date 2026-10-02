<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}
        <div class="sticky bottom-0 z-10 -mx-4 border-t border-gray-950/[0.06] bg-gray-50/90 px-4 py-3 backdrop-blur md:-mx-6 md:px-6 lg:-mx-8 lg:px-8 dark:border-white/[0.06] dark:bg-gray-950/90">
            <x-filament::button type="submit" icon="heroicon-m-check" wire:loading.attr="disabled" wire:target="save">{{ __('Save settings') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
