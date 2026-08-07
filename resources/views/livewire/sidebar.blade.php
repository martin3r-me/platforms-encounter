{{--
    Encounter · Haupt-Sidebar (nx). Modul-Links + Betrieb-Baum als Kontext-Linse.
--}}

<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        Sprechstunde
    </div>

    <x-ui-sidebar-list>
        <x-ui-sidebar-item :href="route('encounter.cockpit')" :active="request()->routeIs('encounter.cockpit')">
            @svg('heroicon-o-rectangle-group', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Sprechstunde</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.appointments.index')"
            :active="(request()->routeIs('encounter.appointments.index') || request()->routeIs('encounter.dashboard')) && ! request()->query('node')">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Termine</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.certificates.index')" :active="request()->routeIs('encounter.certificates.*')">
            @svg('heroicon-o-document-check', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Bescheinigungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.settings')" :active="request()->routeIs('encounter.settings')">
            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Einstellungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    <x-ui-tree-nav label="Nach Betrieb" :nodes="$nodes" :activeId="$activeId" />

    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('encounter.cockpit') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-rectangle-group', 'w-5 h-5')
            </a>
            <a href="{{ route('encounter.appointments.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-clock', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
