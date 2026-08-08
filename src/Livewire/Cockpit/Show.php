<?php

namespace Platform\Encounter\Livewire\Cockpit;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Platform\Encounter\Models\Appointment as AppointmentModel;
use Platform\Encounter\Services\JournalRegistry;
use Platform\Patient\Models\Patient as PatientModel;

/**
 * Sprechstunde-Cockpit — EINE ärztliche Oberfläche: Tages-Agenda (links) + Patienten-Akte
 * (rechts). Komponiert bestehende Bausteine: Agenda aus Appointment, Akte aus JournalRegistry
 * (Termine + Vorsorge + Beschäftigung, modulübergreifend). Der Arzt bleibt in einer View.
 */
class Show extends Component
{
    #[Url(as: 'd')]
    public string $date = '';

    #[Url(as: 'p')]
    public ?int $selectedPatientId = null;

    public ?int $selectedAppointmentId = null;

    /** Rechte Fläche: 'verlauf' (Akte) | 'stammdaten'. */
    public string $tab = 'verlauf';

    /** Behandler-Filter der Agenda: 'mine' (Default) | 'all' | '<doctor_entity_id>'. */
    #[Url(as: 'doc')]
    public string $doc = 'mine';

    public function mount(): void
    {
        if ($this->date === '' || !$this->isValidDate($this->date)) {
            $this->date = $this->resolveInitialDate();
        }
    }

    /**
     * Sinnvoller Einstiegstag: der nächste Tag mit Terminen (>= heute, im aktiven
     * Behandler-Filter), sonst der letzte vergangene Tag mit Terminen, sonst heute.
     * Verhindert den leeren „heute = 0 Termine"-Einstieg.
     */
    protected function resolveInitialDate(): string
    {
        $today = now()->toDateString();
        $team  = (int) Auth::user()->currentTeam->id;

        $base = AppointmentModel::query()->forTeam($team);

        $myDoctorId = \Platform\Encounter\Support\Doctors::forUser($team, (int) Auth::id());
        $filter = null;
        if ($this->doc === 'mine' && $myDoctorId) {
            $filter = (int) $myDoctorId;
        } elseif ($this->doc !== 'mine' && $this->doc !== 'all' && ctype_digit($this->doc)) {
            $filter = (int) $this->doc;
        }
        if ($filter) {
            $base->where('doctor_entity_id', $filter);
        }

        $next = (clone $base)->whereDate('scheduled_at', '>=', $today)
            ->orderBy('scheduled_at')->value('scheduled_at');
        if ($next) {
            return Carbon::parse($next)->toDateString();
        }

        $prev = (clone $base)->whereDate('scheduled_at', '<', $today)
            ->orderByDesc('scheduled_at')->value('scheduled_at');
        if ($prev) {
            return Carbon::parse($prev)->toDateString();
        }

        return $today;
    }

    protected function isValidDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }

    public function goPrev(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
    }

    public function goNext(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
    }

    public function goToday(): void
    {
        $this->date = now()->toDateString();
    }

    /** Tag aus dem Tages-Navigator wählen. */
    public function selectDay(string $date): void
    {
        if ($this->isValidDate($date)) {
            $this->date = $date;
        }
    }

    /** Termin wählen → rechts die Akte des zugehörigen Patienten laden. */
    public function selectAppointment(int $appointmentId): void
    {
        $appointment = AppointmentModel::query()
            ->forTeam((int) Auth::user()->currentTeam->id)
            ->findOrFail($appointmentId);

        $this->selectedAppointmentId = $appointmentId;
        $this->selectedPatientId = $appointment->patient_id;
        $this->tab = 'verlauf';
    }

    /** Patient direkt wählen (z. B. aus Suche) — ohne Termin. */
    public function selectPatient(int $patientId): void
    {
        $this->selectedAppointmentId = null;
        $this->selectedPatientId = $patientId;
        $this->tab = 'verlauf';
    }

    /** Bescheinigung aus dem gewählten Termin ausstellen (audience = patient|employer). */
    public function issueCertificate(string $audienceValue): void
    {
        if (!$this->selectedAppointmentId) {
            return;
        }

        $team = (int) Auth::user()->currentTeam->id;
        $appointment = AppointmentModel::query()->forTeam($team)
            ->with(['services', 'patient'])->find($this->selectedAppointmentId);

        $audience = \Platform\Encounter\Enums\Audience::tryFrom($audienceValue);

        if (!$appointment || !$audience) {
            return;
        }

        resolve(\Platform\Encounter\Services\CertificateService::class)->issue($appointment, $audience);

        $this->tab = 'verlauf';
        $this->dispatch('toast', message: 'Bescheinigung ausgestellt.', type: 'success');
    }

    public function render()
    {
        $team = (int) Auth::user()->currentTeam->id;

        // Behandler-Roster + „mein" Arzt (für „Meine Termine").
        $doctorLabels = \Platform\Encounter\Support\Doctors::options($team);
        $myDoctorId   = \Platform\Encounter\Support\Doctors::forUser($team, (int) Auth::id());

        $q = AppointmentModel::query()->forTeam($team)->with('patient')->whereDate('scheduled_at', $this->date);

        $activeDoctorFilter = null;
        if ($this->doc === 'mine' && $myDoctorId) {
            $activeDoctorFilter = (int) $myDoctorId;
        } elseif ($this->doc !== 'mine' && $this->doc !== 'all' && ctype_digit($this->doc)) {
            $activeDoctorFilter = (int) $this->doc;
        }
        if ($activeDoctorFilter) {
            $q->where('doctor_entity_id', $activeDoctorFilter);
        }

        $appointments = $q->orderBy('scheduled_at')->get();

        // Tages-Navigator mit Count-Badges: kommende Arbeitstage (respektiert Meine/Alle-Filter).
        $today       = now()->toDateString();
        $windowStart = Carbon::parse($today)->subDays(2)->startOfDay();
        $windowEnd   = Carbon::parse($today)->addDays(20)->endOfDay();

        $countsQ = AppointmentModel::query()->forTeam($team)
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd]);
        if ($activeDoctorFilter) {
            $countsQ->where('doctor_entity_id', $activeDoctorFilter);
        }
        $countsByDay = $countsQ->selectRaw('DATE(scheduled_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');

        // Tage mit Terminen + heute + gewählter Tag, aufsteigend.
        $dayStrip = collect($countsByDay->keys())
            ->push($today)->push($this->date)
            ->filter()->unique()->sort()->values()
            ->map(function ($ds) use ($countsByDay, $today) {
                $c = Carbon::parse($ds);
                return [
                    'date'     => $ds,
                    'weekday'  => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$c->dayOfWeek],
                    'day'      => $c->format('d.m.'),
                    'count'    => (int) ($countsByDay[$ds] ?? 0),
                    'isToday'  => $ds === $today,
                    'isActive' => $ds === $this->date,
                ];
            })->all();

        $patient       = null;
        $grouped       = [];
        $dueProvisions = [];

        if ($this->selectedPatientId) {
            $patient = PatientModel::query()->forTeam($team)
                ->with(['phoneNumbers', 'emailAddresses', 'postalAddresses'])
                ->find($this->selectedPatientId);

            if ($patient) {
                $entries = resolve(JournalRegistry::class)->entriesFor((int) $patient->id, $team);

                foreach ($entries as $e) {
                    if (!empty($e['date'])) {
                        $grouped[$e['date']->format('Y-m-d')][] = $e;
                    }
                    if (($e['type'] ?? null) === 'provision' && !empty($e['badge'])) {
                        $dueProvisions[] = $e;
                    }
                }
            } else {
                $this->selectedPatientId = null; // stale/foreign id
            }
        }

        return view('encounter::livewire.cockpit.show', [
            'appointments'  => $appointments,
            'day'           => Carbon::parse($this->date),
            'isToday'       => $this->date === now()->toDateString(),
            'patient'       => $patient,
            'grouped'       => $grouped,
            'dueProvisions' => $dueProvisions,
            'dayStrip'      => $dayStrip,
            'doctorLabels'  => $doctorLabels,
            'hasDoctors'    => !empty($doctorLabels),
            'hasMyDoctor'   => (bool) $myDoctorId,
        ])->layout('platform::layouts.app');
    }
}
