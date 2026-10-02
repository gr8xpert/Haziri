<x-filament-panels::page>
    @vite('resources/js/face-enroll.js')

    <x-filament::section :heading="__('Enrol a face')" icon="heroicon-o-camera"
        :description="__('The employee looks at the camera in good light. Three samples are taken; only face measurements are stored, never the picture.')">
        <div x-data="faceEnroll" class="grid gap-6 lg:grid-cols-2">
            <div class="flex flex-col gap-4">
                <label class="text-sm font-medium text-gray-950 dark:text-white" for="fe-employee">{{ __('Employee') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="fe-employee" wire:model.live="employeeId">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($this->getEmployeeOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="button" icon="heroicon-m-video-camera" color="gray" x-on:click="start()" x-show="!cameraOn">{{ __('Start camera') }}</x-filament::button>
                    <x-filament::button type="button" icon="heroicon-m-camera" x-on:click="capture()" x-show="cameraOn"
                        x-bind:disabled="busy || !$wire.employeeId">{{ __('Capture face') }}</x-filament::button>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300" x-text="status" aria-live="polite"></p>
            </div>
            <div class="overflow-hidden rounded-xl bg-gray-950/5 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                <video x-ref="video" autoplay playsinline muted class="aspect-video w-full -scale-x-100 object-cover"></video>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Enrolled employees')" icon="heroicon-o-users">
        <ul class="flex flex-col divide-y divide-gray-950/[0.05] text-sm dark:divide-white/[0.05]">
            @forelse ($this->getEnrolled() as $row)
                <li class="flex items-center justify-between gap-3 py-2.5">
                    <span class="font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</span>
                    <span class="ms-auto text-gray-500 dark:text-gray-400">{{ trans_choice(':n sample|:n samples', $row['samples'], ['n' => $row['samples']]) }}</span>
                    <x-filament::link tag="button" color="danger" size="sm" wire:click="forget({{ $row['id'] }})"
                        wire:confirm="{{ __('Delete this employee\'s face data?') }}">{{ __('Delete face data') }}</x-filament::link>
                </li>
            @empty
                <li class="py-2.5 text-gray-500 dark:text-gray-400">{{ __('Nobody is enrolled yet.') }}</li>
            @endforelse
        </ul>
    </x-filament::section>
</x-filament-panels::page>
