<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class GetAppointmentTool implements ToolContract, ToolMetadataContract
{
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.appointment.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/appointment - Shows a single appointment with its rendered services. REQUIRED: appointment_id. Encrypted clinical text (anamnesis/findings/remarks/confidential) is NOT returned (Schweigepflicht).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'appointment_id' => ['type' => 'integer', 'description' => 'Id of the appointment (REQUIRED).'],
            ],
            'required' => ['appointment_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $id = (int) ($arguments['appointment_id'] ?? 0);
            if ($id <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'appointment_id is required.');
            }

            $appointment = Appointment::query()->forTeam($teamId)->with(['patient', 'services'])->find($id);
            if (!$appointment) {
                return ToolResult::error('NOT_FOUND', 'Appointment not found (or no access).');
            }

            return ToolResult::success([
                'id' => $appointment->id,
                'uuid' => $appointment->uuid,
                'patient_id' => $appointment->patient_id,
                'patient_name' => $appointment->patient?->getDisplayName(),
                'scheduled_at' => optional($appointment->scheduled_at)->toISOString(),
                'status' => $appointment->status?->value,
                'performed_by' => $appointment->performed_by,
                'doctor_stamp' => $appointment->doctor_stamp,
                'notes' => $appointment->notes,
                'services' => $appointment->services->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'catalog_type' => $s->catalog_type,
                    'catalog_id' => $s->catalog_id,
                    'result' => $s->result,
                    'next_due' => optional($s->next_due)->toDateString(),
                ])->values()->toArray(),
                'team_id' => $appointment->team_id,
                'created_at' => $appointment->created_at?->toISOString(),
                'updated_at' => $appointment->updated_at?->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading appointment: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'appointment', 'get'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
