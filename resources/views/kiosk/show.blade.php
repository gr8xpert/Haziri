<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#065f46">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>{{ $company }} — Kiosk</title>
    @vite(['resources/css/app.css', 'resources/js/kiosk.js'])
</head>
<body class="h-screen overflow-hidden bg-brand-dark text-white select-none"
      x-data="kiosk({ token: @js($token), requirePhoto: @js($kiosk->require_photo), modes: @js($kiosk->allowed_modes ?? ['pin']) })">
    <div class="grid h-full grid-cols-1 gap-6 p-6 md:grid-cols-2">
        <section class="flex flex-col">
            <div class="text-lg opacity-80">{{ $company }}{{ $kiosk->branch ? ' · ' . $kiosk->branch->name : '' }}</div>
            <div class="text-6xl font-semibold tabular-nums" x-text="clock"></div>
            <div class="mt-1 text-lg opacity-80" x-text="greeting()"></div>
            <video x-ref="video" playsinline muted class="mt-6 aspect-[4/3] w-full max-w-md rounded-2xl bg-black/40 object-cover"></video>
            <div x-show="pending > 0" x-cloak class="mt-3 w-fit rounded-full bg-amber-500 px-3 py-1 text-sm text-black"
                 x-text="`${pending} punch(es) waiting for internet`"></div>
        </section>

        <section class="flex flex-col items-center justify-center">
            <template x-if="screen === 'input'">
                <div class="w-full max-w-sm">
                    <div x-show="modes.length > 1" class="mb-4 grid grid-cols-2 rounded-xl bg-white/10 p-1">
                        <button @click="mode = 'pin'" :class="mode === 'pin' && 'bg-white text-brand-dark'" class="rounded-lg py-2">PIN</button>
                        <button @click="mode = 'qr'; scanLoop()" :class="mode === 'qr' && 'bg-white text-brand-dark'" class="rounded-lg py-2">QR</button>
                    </div>

                    <div x-show="mode === 'qr'" class="rounded-2xl bg-white/10 p-8 text-center text-xl">Show your QR badge to the camera</div>

                    <div x-show="mode === 'pin'">
                        <div class="mb-3 text-center text-sm opacity-80" x-text="askKioskPin ? 'Enter your 4-digit kiosk PIN' : 'Enter your employee PIN'"></div>
                        <div class="mb-4 h-16 rounded-xl bg-white/10 text-center text-4xl leading-[4rem] tracking-[0.3em]"
                             x-text="askKioskPin ? '•'.repeat(kioskPin.length) : pin"></div>
                        <div class="grid grid-cols-3 gap-3">
                            <template x-for="k in ['1','2','3','4','5','6','7','8','9','del','0','ok']" :key="k">
                                <button @click="press(k)" class="h-20 rounded-2xl text-3xl font-medium active:scale-95"
                                        :class="k === 'ok' ? 'bg-emerald-400 text-black' : 'bg-white/15'"
                                        x-text="k === 'del' ? '⌫' : (k === 'ok' ? '✓' : k)"></button>
                            </template>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-sm">
                            <template x-for="d in ['auto','in','out']" :key="d">
                                <button @click="direction = d" class="rounded-lg py-2" :class="direction === d ? 'bg-white text-brand-dark' : 'bg-white/10'"
                                        x-text="d === 'auto' ? 'Auto' : (d === 'in' ? 'In' : 'Out')"></button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="screen === 'done'">
                <div class="text-center">
                    <div class="text-7xl">✓</div>
                    <div class="mt-4 text-4xl font-semibold" x-text="result.name ? `${greeting()}, ${result.name}` : 'Saved'"></div>
                    <div class="mt-2 text-2xl opacity-90" x-text="`${result.time} · ${result.direction === 'out' ? 'Out' : (result.direction === 'in' ? 'In' : '')}`"></div>
                    <div x-show="result.queued" class="mt-3 text-amber-300">Offline — will sync automatically</div>
                </div>
            </template>

            <template x-if="screen === 'error'">
                <div class="text-center">
                    <div class="text-7xl">✕</div>
                    <div class="mt-4 text-2xl" x-text="result.message"></div>
                </div>
            </template>
        </section>
    </div>
</body>
</html>
