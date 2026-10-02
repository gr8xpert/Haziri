<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#022c22">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="{{ \App\Support\Brand::iconUrl() }}">
    <title>{{ $company }} | Kiosk</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/kiosk.js'])
</head>
<body class="min-h-dvh overflow-x-hidden bg-brand-deep text-white select-none"
      x-data="kiosk({ token: @js($token), requirePhoto: @js($kiosk->require_photo), modes: @js($kiosk->allowed_modes ?? ['pin']) })">
    <div aria-hidden="true" class="pointer-events-none fixed inset-0 bg-[radial-gradient(45%_55%_at_20%_15%,rgb(16_185_129/0.20),transparent),radial-gradient(40%_50%_at_85%_90%,rgb(5_150_105/0.16),transparent)]"></div>

    <div class="relative flex min-h-dvh flex-col px-5 pt-[max(env(safe-area-inset-top),1.25rem)] pb-[max(env(safe-area-inset-bottom),1.25rem)] sm:px-8">

        {{-- Top bar --}}
        <header class="mx-auto flex w-full max-w-6xl items-center gap-3">
            <img src="{{ \App\Support\Brand::iconUrl() }}" alt="" class="size-10 rounded-xl ring-1 ring-white/15">
            <div class="min-w-0 leading-tight">
                <div class="truncate text-[1.0625rem] font-semibold">{{ $company }}</div>
                <div class="truncate text-sm text-white/55">{{ $kiosk->branch?->name ?? 'Attendance kiosk' }}</div>
            </div>
            <div class="ms-auto flex items-center gap-2">
                <div x-show="pending > 0" x-cloak class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 px-3 py-1.5 text-sm font-medium text-amber-200 ring-1 ring-amber-300/25">
                    @svg('heroicon-m-cloud-arrow-up', 'size-4')
                    <span x-text="`${pending} waiting to sync`"></span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/[0.06] px-3 py-1.5 text-sm text-white/75 ring-1 ring-white/10" role="status">
                    <span class="relative flex size-2">
                        <span x-show="online" class="kiosk-ping absolute inline-flex size-full rounded-full bg-emerald-400"></span>
                        <span class="relative inline-flex size-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                    </span>
                    <span x-text="online ? 'Online' : 'Offline'">Online</span>
                </div>
            </div>
        </header>

        {{-- Main composition --}}
        <main class="mx-auto grid w-full max-w-6xl flex-1 content-center gap-4 py-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:gap-6">

            {{-- Left: time + camera --}}
            <section class="kiosk-panel flex flex-col p-6 sm:p-7 md:max-lg:flex-row md:max-lg:items-center md:max-lg:gap-6">
                <div class="md:max-lg:flex-1">
                <p class="text-lg text-emerald-200/80" x-text="greeting()"></p>
                <div class="mt-1 flex items-baseline gap-3">
                    <span class="text-7xl leading-none font-semibold tracking-tight tabular-nums xl:text-8xl" x-text="clock"></span>
                    <span class="text-2xl font-medium text-white/50 uppercase" x-text="period"></span>
                </div>
                <p class="mt-3 text-lg text-white/60" x-text="today"></p>
                </div>

                <div class="relative mt-6 aspect-[4/3] max-h-[34vh] w-full overflow-hidden rounded-3xl md:max-lg:mt-0 md:max-lg:w-[46%] md:max-lg:shrink-0 bg-black/35 ring-1 ring-white/10 lg:mt-auto lg:max-h-none">
                    <video x-ref="video" playsinline muted class="size-full -scale-x-100 object-cover transition-opacity duration-500" :class="cameraOn ? 'opacity-100' : 'opacity-0'"></video>

                    {{-- Framing guide: four corner brackets --}}
                    <div x-show="cameraOn" aria-hidden="true" class="pointer-events-none absolute inset-[12%]">
                        <span class="absolute start-0 top-0 size-10 rounded-ss-2xl border-s-[3px] border-t-[3px] border-white/70"></span>
                        <span class="absolute end-0 top-0 size-10 rounded-se-2xl border-e-[3px] border-t-[3px] border-white/70"></span>
                        <span class="absolute start-0 bottom-0 size-10 rounded-es-2xl border-s-[3px] border-b-[3px] border-white/70"></span>
                        <span class="absolute end-0 bottom-0 size-10 rounded-ee-2xl border-e-[3px] border-b-[3px] border-white/70"></span>
                    </div>
                    <div x-show="cameraOn" class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-10 pb-4 text-center text-sm font-medium text-white/85"
                         x-text="mode === 'qr' ? 'Hold your QR badge inside the frame' : 'Look at the camera while you punch'"></div>

                    {{-- No camera --}}
                    <div x-show="!cameraOn" class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                        <span class="flex size-14 items-center justify-center rounded-2xl bg-white/[0.07] ring-1 ring-white/10">@svg('heroicon-o-video-camera-slash', 'size-7 text-white/60')</span>
                        <p class="font-medium text-white/80">Camera unavailable</p>
                        <p class="max-w-xs text-sm text-white/50">
                            @if ($kiosk->require_photo)
                                This kiosk needs a photo with every punch. Allow camera access in the browser settings.
                            @else
                                Punches are still recorded, without a photo.
                            @endif
                        </p>
                    </div>
                </div>
            </section>

            {{-- Right: input and result --}}
            <section class="kiosk-panel flex flex-col p-6 sm:p-7 lg:min-h-[33rem]">
                <template x-if="screen === 'input'">
                    <div class="flex flex-1 flex-col">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-semibold tracking-tight">Punch in or out</h1>
                                <p class="mt-1 text-white/60" x-text="mode === 'qr' ? @js(__('Scan the QR badge from your :app app', ['app' => \App\Support\Brand::name()])) : (askKioskPin ? 'Now enter your 4-digit kiosk PIN' : 'Enter your employee PIN')"></p>
                            </div>
                            <div x-show="modes.length > 1" class="grid shrink-0 grid-cols-2 rounded-xl bg-white/[0.06] p-1 text-sm font-medium ring-1 ring-white/10">
                                <button @click="mode = 'pin'" :aria-pressed="mode === 'pin'" :class="mode === 'pin' ? 'bg-white text-brand-deep shadow' : 'text-white/65'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 transition">
                                    @svg('heroicon-m-hashtag', 'size-4') PIN
                                </button>
                                <button @click="mode = 'qr'; scanLoop()" :aria-pressed="mode === 'qr'" :class="mode === 'qr' ? 'bg-white text-brand-deep shadow' : 'text-white/65'" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 transition">
                                    @svg('heroicon-m-qr-code', 'size-4') QR
                                </button>
                            </div>
                        </div>

                        <div x-show="mode === 'qr'" class="mt-5 flex flex-1 flex-col items-center justify-center rounded-3xl bg-white/[0.04] px-8 py-12 text-center ring-1 ring-white/10">
                            <span class="kiosk-float flex size-20 items-center justify-center rounded-3xl bg-emerald-400/15 ring-1 ring-emerald-300/30">@svg('heroicon-o-qr-code', 'size-10 text-emerald-300')</span>
                            <p class="mt-6 text-2xl font-semibold">Show your QR badge</p>
                            <p class="mt-2 max-w-xs text-white/60">{{ __('Open the :app app on your phone and hold the badge up to the camera.', ['app' => \App\Support\Brand::name()]) }}</p>
                        </div>

                        <div x-show="mode === 'pin'" class="mt-5 flex flex-1 flex-col">
                            {{-- PIN display --}}
                            <div class="flex h-20 items-center justify-center rounded-2xl bg-black/20 ring-1 ring-white/10" aria-live="polite">
                                <span x-show="askKioskPin" class="flex gap-4">
                                    <template x-for="i in 4" :key="i">
                                        <span class="size-4 rounded-full transition duration-200" :class="i <= kioskPin.length ? 'scale-110 bg-white' : 'bg-white/20'"></span>
                                    </template>
                                </span>
                                <span x-show="!askKioskPin" class="flex items-center gap-1 text-4xl font-semibold tabular-nums">
                                    <template x-for="(d, i) in pin.split('')" :key="i">
                                        <span class="kiosk-digit inline-block w-[1.1ch] text-center" x-text="d"></span>
                                    </template>
                                    <span class="kiosk-caret ms-1 h-9 w-[3px] rounded-full bg-emerald-300"></span>
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-3">
                                <template x-for="k in ['1','2','3','4','5','6','7','8','9','del','0','ok']" :key="k">
                                    <button @click="press(k)"
                                            class="kiosk-key flex h-16 items-center justify-center rounded-2xl text-3xl font-medium xl:h-20"
                                            :class="k === 'ok' ? 'bg-emerald-400 text-emerald-950 shadow-[0_8px_24px_-8px_rgb(52_211_153/0.6)] active:bg-emerald-300' : (k === 'del' ? 'bg-white/[0.05] text-white/75 ring-1 ring-white/10 active:bg-white/15' : 'bg-white/[0.09] ring-1 ring-white/[0.06] active:bg-white/20')"
                                            :aria-label="k === 'del' ? 'Delete' : (k === 'ok' ? 'Confirm' : k)">
                                        <span x-show="k !== 'del' && k !== 'ok'" x-text="k"></span>
                                        <span x-show="k === 'del'">@svg('heroicon-o-backspace', 'size-8')</span>
                                        <span x-show="k === 'ok'">@svg('heroicon-m-check', 'size-9')</span>
                                    </button>
                                </template>
                            </div>

                            <div class="mt-auto pt-4">
                                <div class="grid grid-cols-3 gap-1 rounded-2xl bg-white/[0.06] p-1 text-base font-medium ring-1 ring-white/10" role="radiogroup" aria-label="Punch direction">
                                    <template x-for="d in ['auto','in','out']" :key="d">
                                        <button @click="direction = d" role="radio" :aria-checked="direction === d" class="inline-flex items-center justify-center gap-1.5 rounded-xl py-2.5 transition"
                                                :class="direction === d ? 'bg-white text-brand-deep shadow' : 'text-white/65'">
                                            <span x-text="d === 'auto' ? 'Auto' : (d === 'in' ? 'Punch in' : 'Punch out')"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="screen === 'done'">
                    <div class="flex flex-1 flex-col items-center justify-center text-center" role="status">
                        <div class="kiosk-pop flex size-32 items-center justify-center rounded-full bg-emerald-400 text-emerald-950 shadow-[0_0_0_14px_rgb(52_211_153/0.14),0_0_0_28px_rgb(52_211_153/0.06)]">
                            @svg('heroicon-m-check', 'size-20')
                        </div>
                        <div class="kiosk-rise mt-10 text-4xl font-semibold tracking-tight text-balance" x-text="result.name ? `${result.direction === 'out' ? 'Goodbye' : greeting()}, ${result.name}` : 'Saved'"></div>
                        <div class="kiosk-rise mt-4 inline-flex items-center gap-3 text-2xl text-white/80 [animation-delay:80ms]">
                            <span class="tabular-nums" x-text="result.time"></span>
                            <span x-show="result.direction" class="rounded-full px-4 py-1 text-xl font-bold tracking-wide"
                                  :class="result.direction === 'out' ? 'bg-amber-300 text-amber-950' : 'bg-emerald-300 text-emerald-950'"
                                  x-text="result.direction === 'out' ? 'OUT' : 'IN'"></span>
                        </div>
                        <div x-show="result.queued" class="mt-6 inline-flex items-center gap-2 rounded-full bg-amber-400/15 px-4 py-2 text-amber-200 ring-1 ring-amber-300/30">
                            @svg('heroicon-m-wifi', 'size-5') Offline. It will sync automatically.
                        </div>
                        <div class="mt-12 h-1 w-48 overflow-hidden rounded-full bg-white/10"><div class="kiosk-countdown h-full bg-white/50"></div></div>
                    </div>
                </template>

                <template x-if="screen === 'locked'">
                    <div class="flex flex-1 flex-col items-center justify-center text-center" role="alert">
                        <div class="flex size-32 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/20">
                            @svg('heroicon-m-lock-closed', 'size-16')
                        </div>
                        <div class="mt-10 max-w-md text-3xl font-semibold tracking-tight text-balance" x-text="access.message"></div>
                        <div x-show="access.code" class="mt-6 rounded-2xl bg-white/10 px-6 py-3 font-mono text-5xl font-bold tracking-[0.3em] ring-1 ring-white/20" x-text="access.code"></div>
                        <div class="mt-4 text-lg text-white/60">This screen opens by itself once the tablet is approved.</div>
                    </div>
                </template>

                <template x-if="screen === 'notice'">
                    <div class="flex flex-1 flex-col items-center justify-center text-center" role="status">
                        <div class="kiosk-pop flex size-32 items-center justify-center rounded-full bg-amber-300 text-amber-950 shadow-[0_0_0_14px_rgb(252_211_77/0.14)]">
                            @svg('heroicon-m-clock', 'size-20')
                        </div>
                        <div class="kiosk-rise mt-10 text-4xl font-semibold tracking-tight text-balance" x-text="result.name ? `${result.name}, you already punched` : 'Already punched'"></div>
                        <div class="kiosk-rise mt-4 text-2xl text-white/80 [animation-delay:80ms]" x-text="result.message"></div>
                        <div class="mt-3 text-lg text-white/60">Nothing new was saved. Wait a minute before punching again.</div>
                        <div class="mt-12 h-1 w-48 overflow-hidden rounded-full bg-white/10"><div class="kiosk-countdown h-full bg-white/50"></div></div>
                    </div>
                </template>

                <template x-if="screen === 'error'">
                    <div class="flex flex-1 flex-col items-center justify-center text-center" role="alert">
                        <div class="kiosk-shake flex size-32 items-center justify-center rounded-full bg-rose-500 text-white shadow-[0_0_0_14px_rgb(244_63_94/0.14)]">
                            @svg('heroicon-m-x-mark', 'size-20')
                        </div>
                        <div class="mt-10 max-w-md text-3xl font-semibold tracking-tight text-balance" x-text="result.message"></div>
                        <div class="mt-3 text-lg text-white/60">Please try again.</div>
                        <div class="mt-12 h-1 w-48 overflow-hidden rounded-full bg-white/10"><div class="kiosk-countdown h-full bg-white/50"></div></div>
                    </div>
                </template>
            </section>
        </main>

        <footer class="mx-auto flex w-full max-w-6xl items-center justify-between text-sm text-white/40">
            <span>Forgot your PIN? Ask HR.</span>
            <span>{{ \App\Support\Brand::name() }}</span>
        </footer>
    </div>

    <style>
        .kiosk-panel {
            border-radius: 2rem;
            background: linear-gradient(180deg, rgb(255 255 255 / 0.06), rgb(255 255 255 / 0.025));
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.08), 0 30px 60px -30px rgb(0 0 0 / 0.5);
            outline: 1px solid rgb(255 255 255 / 0.08);
            outline-offset: -1px;
        }
        .kiosk-key { transition: transform 120ms ease, background-color 120ms ease; }
        .kiosk-key:active { transform: scale(0.95); }
        .kiosk-countdown { transform-origin: left; animation: kiosk-countdown 3s linear both; }
        @keyframes kiosk-countdown { from { transform: scaleX(1); } to { transform: scaleX(0); } }
        .kiosk-caret { animation: kiosk-blink 1.1s steps(1) infinite; }
        @keyframes kiosk-blink { 50% { opacity: 0; } }

        @media (prefers-reduced-motion: no-preference) {
            .kiosk-panel { animation: kiosk-rise 600ms cubic-bezier(0.22, 1, 0.36, 1) backwards; }
            .kiosk-panel + .kiosk-panel { animation-delay: 90ms; }
            .kiosk-rise { animation: kiosk-rise 500ms cubic-bezier(0.22, 1, 0.36, 1) backwards; }
            .kiosk-digit { animation: kiosk-digit 220ms cubic-bezier(0.22, 1, 0.36, 1) backwards; }
            .kiosk-pop { animation: kiosk-pop 460ms cubic-bezier(0.34, 1.56, 0.64, 1) backwards; }
            .kiosk-shake { animation: kiosk-shake 420ms ease-in-out; }
            .kiosk-float { animation: kiosk-float 3.2s ease-in-out infinite; }
            .kiosk-ping { animation: kiosk-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite; }
            @keyframes kiosk-rise { from { opacity: 0; transform: translateY(12px); } }
            @keyframes kiosk-digit { from { opacity: 0; transform: translateY(8px) scale(0.8); } }
            @keyframes kiosk-pop { from { opacity: 0; transform: scale(0.5); } }
            @keyframes kiosk-shake { 20%, 60% { transform: translateX(-10px); } 40%, 80% { transform: translateX(10px); } }
            @keyframes kiosk-float { 50% { transform: translateY(-6px); } }
            @keyframes kiosk-ping { 75%, 100% { transform: scale(2.4); opacity: 0; } }
        }
        @media (prefers-reduced-motion: reduce) {
            .kiosk-caret { animation: none; }
        }
    </style>
</body>
</html>
