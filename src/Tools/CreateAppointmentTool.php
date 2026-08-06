<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;
use Platform\Patient\Models\Patient;

class CreateAppointmentTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const STATUSES = ['planned', 'attended', 'cancelled', 'rescheduled'];

    public function getName(): string
    {
        return 'encounter.appointments.POST';
    }

    public function getDescription(): string
    {
        return 'POST /encounter/appointments - Creates an appointment. REQUIRED: patient_id (must belong to the team), scheduled_at (ISO datetime). Optional: status (default planned), performed_by, doctor_stamp, notes, anamnesis, findings, remarks, confidential (encrypted).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'patient_id' => ['type' => 'integer', 'description' => 'Patient id (REQUIRED).'],
                'scheduled_at' => ['type' => 'string', 'description' => 'Appointment datetime, ISO 8601 (REQUIRED).'],
                'status' => ['type' => 'string', 'enum' => self::STATUSES, 'description' => 'Optional: default planned.'],
                'performed_by' => ['type' => 'string', 'description' => 'Optional: performing physician name.'],
                'doctor_stamp' => ['type' => 'string', 'description' => 'Optional.'],
                'notes' => ['type' => 'string', 'description' => 'Optional.'],
                'anamnesis' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'findings' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'remarks' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'confidential' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
            ],
            'required' => ['patient_id', 'scheduled_at'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'No user in context.');
            }

            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $patientId = (int) ($arguments['patient_id'] ?? 0);
            $patient = Patient::query()->forTeam($teamId)->find($patientId);
            if (!$patient) {
                return ToolResult::error('VALIDATION_ERROR', 'patient_id not found in this team.');
            }

            $scheduledAt = trim((string) ($arguments['scheduled_at'] ?? ''));
            if ($scheduledAt === '') {
                return ToolResult::error('VALIDATION_ERROR', 'scheduled_at is required.');
            }

            $status = $arguments['status'] ?? 'planned';
            if (!in_array($status, self::STATUSES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'status is invalid.');
            }

            $payload = ['team_id' => $teamId, 'patient_id' => $patient->id, 'scheduled_at' => $scheduledAt, 'status' => $status];
            foreach (['performed_by', 'doctor_stamp', 'notes', 'anamnesis', 'findings', 'remarks', 'confidential'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $payload[$f] = ($arguments[$f] === '' || $arguments[$f] === null) ? null : (string) $arguments[$f];
                }
            }

            $appointment = Appointment::create($payload);

            return ToolResult::success([
                'id' => $appointment->id,
                'uuid' => $appointment->uuid,
                'patient_id' => $appointment->patient_id,
                'scheduled_at' => optional($appointment->scheduled_at)->toISOString(),
                'status' => $appointment->status?->value,
                'team_id' => $appointment->team_id,
                'message' => 'Appointment created successfully.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error creating appointment: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'appointments', 'create'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
