{{-- Umschalter Kalender ⇄ Liste für die zusammengelegte „Termine"-Fläche. --}}
@php($onCal = request()->routeIs('encounter.dashboard'))
@php($onList = request()->routeIs('encounter.appointments.index'))
<div class="inline-flex rounded-md border border-[color:var(--nx-line)] overflow-hidden">
    <a href="{{ route('encounter.dashboard') }}" wire:navigate
       @class([
           'px-3 py-1.5 text-sm text-[color:var(--nx-text)]',
           'bg-[color:var(--nx-active)] font-semibold' => $onCal,
           'hover:bg-[color:var(--nx-hover)]' => !$onCal,
       ])>
        @svg('heroicon-o-calendar-days', 'w-4 h-4 inline -mt-0.5') Kalender
    </a>
    <a href="{{ route('encounter.appointments.index') }}" wire:navigate
       @class([
           'px-3 py-1.5 text-sm text-[color:var(--nx-text)] border-l border-[color:var(--nx-line)]',
           'bg-[color:var(--nx-active)] font-semibold' => $onList,
           'hover:bg-[color:var(--nx-hover)]' => !$onList,
       ])>
        @svg('heroicon-o-list-bullet', 'w-4 h-4 inline -mt-0.5') Liste
    </a>
</div>
