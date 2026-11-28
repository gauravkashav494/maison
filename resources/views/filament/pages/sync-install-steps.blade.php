{{-- Settings → Shop software sync in the client panel: how to connect the shop PC. --}}
<ol class="list-decimal space-y-3 ps-5 text-sm text-gray-700 dark:text-gray-300">
    <li>
        <span class="font-medium text-gray-950 dark:text-white">Get the API from your billing software's developer.</span>
        The agent talks to your billing software through a small API on your shop's network. Ask us for
        the "Local API for the EcomVision sync agent" specification and give it to them. When it is
        ready, they give you its <span class="font-medium">address</span> (usually http://localhost:8765/ecom/v1)
        and an <span class="font-medium">API key</span>.
    </li>
    <li>
        <span class="font-medium text-gray-950 dark:text-white">Enter them here.</span>
        Use <span class="font-medium">Billing software connection</span> above. The agent reads them from
        your store, so the installer doesn't ask, and a later change needs no reinstall.
    </li>
    <li>
        <span class="font-medium text-gray-950 dark:text-white">Create a setup code</span>
        with <span class="font-medium">Create agent setup code</span> above, and copy it. It is shown only
        once and works like a password.
    </li>
    <li>
        <span class="font-medium text-gray-950 dark:text-white">Download the installer</span>
        from <span class="font-medium">Download the agent</span> below (there is one for Windows 10 or 11, and
        one for Windows 7 or 8.1), and run it on the PC that runs your billing software. It asks only for the setup code, then checks it can reach both your store and
        your billing software before it installs. If Windows shows "Windows protected your PC", choose
        <span class="font-medium">More info → Run anyway</span>.
    </li>
    <li>
        <span class="font-medium text-gray-950 dark:text-white">Check it is syncing.</span>
        The agent starts with Windows and syncs every 5 minutes. Within a few minutes the status below
        shows <span class="font-medium">Syncing</span>. If it shows a problem, the errors say what went wrong.
        To remove it, use Windows' <span class="font-medium">Apps &amp; features</span>.
    </li>
</ol>
