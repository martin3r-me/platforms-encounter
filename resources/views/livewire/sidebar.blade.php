{{--
    Encounter · Sidebar (nx-Design-System). Nur var(--nx-*) Tokens.
--}}

<div>
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--nx-text)] uppercase border-b border-[color:var(--nx-line)] mb-2">
        Termine
    </div>

    <x-ui-sidebar-list label="Encounter">
        <x-ui-sidebar-item :href="route('encounter.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.appointments.index')">
            @svg('heroicon-o-calendar-days', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Termine</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.certificates.index')">
            @svg('heroicon-o-document-check', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Bescheinigungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('encounter.settings')">
            @svg('heroicon-o-cog-6-tooth', 'w-4 h-4 text-[var(--nx-text)]')
            <span class="ml-2 text-sm">Einstellungen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    @if($appointments->isNotEmpty())
        <x-ui-sidebar-list label="Zuletzt">
            @foreach($appointments as $appointment)
                <x-ui-sidebar-item :href="route('encounter.appointments.show', $appointment->id)">
                    @svg('heroicon-o-calendar', 'w-4 h-4 text-[var(--nx-text)]')
                    <span class="ml-2 text-sm truncate">{{ $appointment->patient?->getDisplayName() ?? '—' }}</span>
                </x-ui-sidebar-item>
            @endforeach
        </x-ui-sidebar-list>
    @endif

    <div x-show="collapsed" class="px-2 py-2 border-b border-[color:var(--nx-line)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('encounter.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
            <a href="{{ route('encounter.appointments.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--nx-text)] hover:bg-[var(--nx-bg)]">
                @svg('heroicon-o-calendar-days', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
