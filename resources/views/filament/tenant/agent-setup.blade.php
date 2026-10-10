<div class="space-y-4 text-sm">
    <p>{{ __('For machines that only have a LAN address (no cloud / ADMS option): a small program on one office PC reads them over the network and sends the punches here. Nothing changes on your network.') }}</p>

    @unless ($program)
        <p class="rounded-lg bg-warning-50 p-3 text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-300">
            {{ __('The program is not on this server yet. Ask Haziri support for hazri-agent.exe.') }}
        </p>
    @endunless

    <ol class="list-decimal space-y-2 ps-5">
        <li>{{ __('Use a Windows PC that is always on and on the same network as the machines (the PC that runs your old attendance software is fine).') }}</li>
        <li>{{ __('Add each machine under Devices with type "Agent" and its LAN IP first, so it is in the setup file.') }}</li>
        <li>{{ __('Download the program and the setup file below into one folder, e.g.') }} <code>C:\Haziri</code>. {{ __('The file must be called') }} <code>agent.yaml</code>.</li>
        <li>{{ __('Test it: open Command Prompt in that folder and run') }}<br>
            <code class="mt-1 inline-block rounded bg-gray-100 px-2 py-1 dark:bg-white/10">hazri-agent.exe -c agent.yaml --once</code><br>
            {{ __('Within a minute the machines show online on this page.') }}</li>
        <li>{{ __('Make it start with Windows: download NSSM from') }} <a href="https://nssm.cc/download" target="_blank" rel="noopener" class="text-primary-600 underline dark:text-primary-400">nssm.cc</a>, {{ __('then in Command Prompt (as administrator):') }}
            <pre class="mt-1 overflow-x-auto rounded bg-gray-100 p-2 text-xs dark:bg-white/10">nssm install HaziriAgent "C:\Haziri\hazri-agent.exe" -c C:\Haziri\agent.yaml --log C:\Haziri\hazri-agent.log
nssm set HaziriAgent AppDirectory C:\Haziri
nssm set HaziriAgent Start SERVICE_AUTO_START
nssm start HaziriAgent</pre></li>
    </ol>

    <p class="text-gray-500 dark:text-gray-400">{{ __('Each setup file has its own new key. Keep it private; you can revoke old ones under Agent keys.') }}</p>
</div>
