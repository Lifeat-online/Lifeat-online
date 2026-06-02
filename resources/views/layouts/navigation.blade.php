<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 app-nav">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-stretch gap-4">
            <div class="flex min-w-0 flex-1">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 no-underline">
                        <span class="backend-brand-mark">L@</span>
                        <span class="hidden text-sm font-black tracking-wide text-gray-900 lg:inline">Life@ Admin</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="backend-nav-scroll hidden sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    @if (Auth::user()->hasRole('admin', 'editor', 'staff', 'support'))
                        <x-nav-link :href="route('admin.action-station.index')" :active="request()->routeIs('admin.action-station.*')">
                            {{ __('Action Station') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin', 'editor', 'support'))
                        <x-nav-link :href="route('admin.finance.index')" :active="request()->routeIs('admin.finance.*')">
                            {{ __('Finance') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin', 'editor', 'staff'))
                        <x-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">
                            {{ __('Customers') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin', 'editor', 'staff'))
                        <x-nav-link :href="route('admin.classifieds.index')" :active="request()->routeIs('admin.classifieds.*')">
                            {{ __('Classifieds') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin', 'editor'))
                        <x-nav-link :href="route('admin.fault-reports.index')" :active="request()->routeIs('admin.fault-reports.*')">
                            {{ __('Fault Reports') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin'))
                        <x-nav-link :href="route('admin.councillors.index')" :active="request()->routeIs('admin.councillors.*')">
                            {{ __('Councillors') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('admin', 'dev'))
                        <x-nav-link :href="route('dev.transport.setup')" :active="request()->routeIs('dev.transport.*')">
                            {{ __('Transport Setup') }}
                        </x-nav-link>
                        <x-nav-link :href="route('account.advertising.index')" :active="request()->routeIs('account.advertising.*')">
                            {{ __('Self Service') }}
                        </x-nav-link>
                        <x-nav-link :href="route('staff.advertising.index')" :active="request()->routeIs('staff.advertising.*')">
                            {{ __('Staff Ads') }}
                        </x-nav-link>
                        <x-nav-link :href="route('admin.push-notifications.index')" :active="request()->routeIs('admin.push-notifications.*')">
                            {{ __('Push') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('transport_manager', 'admin', 'dev'))
                        <x-nav-link :href="route('transport.manager.dashboard')" :active="request()->routeIs('transport.manager.*')">
                            {{ __('Transport') }}
                        </x-nav-link>
                    @endif
                    @if (Auth::user()->hasRole('transport_driver', 'admin'))
                        <x-nav-link :href="route('transport.driver.duty')" :active="request()->routeIs('transport.driver.duty')">
                            {{ __('Driver Duty') }}
                        </x-nav-link>
                        @if (Auth::user()->transportDriver?->activeDutySession)
                            <x-nav-link :href="route('transport.driver.workspace')" :active="request()->routeIs('transport.driver.workspace')">
                                {{ __('Driver Live') }}
                            </x-nav-link>
                        @endif
                    @endif
                    @if (Auth::user()->hasRole('councillor'))
                        <x-nav-link :href="route('councillor.faults.index')" :active="request()->routeIs('councillor.faults.*')">
                            {{ __('My Faults') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden shrink-0 sm:flex sm:items-center gap-3">
                @include('partials.language-switcher')
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 theme-toggle-admin" data-theme-toggle aria-label="Toggle dark and light mode" title="Toggle dark and light mode">
                    <svg data-theme-icon-sun xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
                    </svg>
                    <svg data-theme-icon-moon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3c-.02.25-.03.5-.03.75a9 9 0 009.07 9.04c.25 0 .5-.01.75-.03z" />
                    </svg>
                </button>
                <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 push-toggle-admin" data-push-toggle hidden>Enable alerts</button>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-2 text-sm font-semibold leading-4 text-gray-600 transition ease-in-out duration-150 hover:text-gray-900 focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if (strtolower((string) Auth::user()->email) === 'jameskoen78@gmail.com')
                            <x-dropdown-link :href="route('admin.dashboard', ['tab' => 'dev'])">
                                {{ __('Dev Dashboard') }}
                            </x-dropdown-link>
                            <button type="button" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none" data-fullscreen-toggle>
                                {{ __('Full screen') }}
                            </button>
                        @endif

                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @if (Auth::user()->hasRole('admin', 'editor', 'staff', 'support'))
                <x-responsive-nav-link :href="route('admin.action-station.index')" :active="request()->routeIs('admin.action-station.*')">
                    {{ __('Action Station') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin', 'editor', 'support'))
                <x-responsive-nav-link :href="route('admin.finance.index')" :active="request()->routeIs('admin.finance.*')">
                    {{ __('Finance') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin', 'editor', 'staff'))
                <x-responsive-nav-link :href="route('admin.customers.index')" :active="request()->routeIs('admin.customers.*')">
                    {{ __('Customers') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin', 'editor', 'staff'))
                <x-responsive-nav-link :href="route('admin.classifieds.index')" :active="request()->routeIs('admin.classifieds.*')">
                    {{ __('Classifieds') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin', 'editor'))
                <x-responsive-nav-link :href="route('admin.fault-reports.index')" :active="request()->routeIs('admin.fault-reports.*')">
                    {{ __('Fault Reports') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin'))
                <x-responsive-nav-link :href="route('admin.councillors.index')" :active="request()->routeIs('admin.councillors.*')">
                    {{ __('Councillors') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('admin', 'dev'))
                <x-responsive-nav-link :href="route('dev.transport.setup')" :active="request()->routeIs('dev.transport.*')">
                    {{ __('Transport Setup') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('account.advertising.index')" :active="request()->routeIs('account.advertising.*')">
                    {{ __('Self Service') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('staff.advertising.index')" :active="request()->routeIs('staff.advertising.*')">
                    {{ __('Staff Ads') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.push-notifications.index')" :active="request()->routeIs('admin.push-notifications.*')">
                    {{ __('Push') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('transport_manager', 'admin', 'dev'))
                <x-responsive-nav-link :href="route('transport.manager.dashboard')" :active="request()->routeIs('transport.manager.*')">
                    {{ __('Transport') }}
                </x-responsive-nav-link>
            @endif
            @if (Auth::user()->hasRole('transport_driver', 'admin'))
                <x-responsive-nav-link :href="route('transport.driver.duty')" :active="request()->routeIs('transport.driver.duty')">
                    {{ __('Driver Duty') }}
                </x-responsive-nav-link>
                @if (Auth::user()->transportDriver?->activeDutySession)
                    <x-responsive-nav-link :href="route('transport.driver.workspace')" :active="request()->routeIs('transport.driver.workspace')">
                        {{ __('Driver Live') }}
                    </x-responsive-nav-link>
                @endif
            @endif
            @if (Auth::user()->hasRole('councillor'))
                <x-responsive-nav-link :href="route('councillor.faults.index')" :active="request()->routeIs('councillor.faults.*')">
                    {{ __('My Faults') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <div class="px-4 pb-2">
                    <div class="flex items-center gap-2">
                    @include('partials.language-switcher')
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 theme-toggle-admin" data-theme-toggle aria-label="Toggle dark and light mode" title="Toggle dark and light mode">
                        <svg data-theme-icon-sun xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2.25M12 18.75V21M4.97 4.97l1.59 1.59M17.44 17.44l1.59 1.59M3 12h2.25M18.75 12H21M4.97 19.03l1.59-1.59M17.44 6.56l1.59-1.59M15.75 12A3.75 3.75 0 1112 8.25 3.75 3.75 0 0115.75 12z" />
                        </svg>
                        <svg data-theme-icon-moon xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12.79A9 9 0 1111.21 3c-.02.25-.03.5-.03.75a9 9 0 009.07 9.04c.25 0 .5-.01.75-.03z" />
                        </svg>
                    </button>
                    <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 push-toggle-admin" data-push-toggle hidden>Enable alerts</button>
                    </div>
                </div>
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                @if (strtolower((string) Auth::user()->email) === 'jameskoen78@gmail.com')
                    <x-responsive-nav-link :href="route('admin.dashboard', ['tab' => 'dev'])">
                        {{ __('Dev Dashboard') }}
                    </x-responsive-nav-link>
                    <button type="button" class="block w-full px-4 py-2 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-800 focus:bg-gray-100 focus:text-gray-800 focus:outline-none" data-fullscreen-toggle>
                        {{ __('Full screen') }}
                    </button>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const buttons = Array.from(document.querySelectorAll('[data-fullscreen-toggle]'));
            if (!buttons.length) return;

            const updateLabels = () => {
                buttons.forEach((button) => {
                    button.textContent = document.fullscreenElement ? 'Exit full screen' : 'Full screen';
                });
            };

            buttons.forEach((button) => {
                button.addEventListener('click', async () => {
                    try {
                        if (document.fullscreenElement) {
                            await document.exitFullscreen();
                        } else {
                            await document.documentElement.requestFullscreen();
                        }
                    } catch (error) {
                        console.error('Fullscreen toggle failed:', error);
                    } finally {
                        updateLabels();
                    }
                });
            });

            document.addEventListener('fullscreenchange', updateLabels);
            updateLabels();
        })();
    </script>
</nav>
