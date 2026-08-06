<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class DeleteAppointmentTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.appointments.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /encounter/appointments - Deletes an appointment (soft-delete). REQUIRED: appointment_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'appointment_id' => ['type' => 'integer', 'description' => 'Id of the appointment (REQUIRED).'],
            ],
            'required' => ['appointment_id'],
        ]);
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

            $appointment = Appointment::query()->forTeam($teamId)->find($id);
            if (!$appointment) {
                return ToolResult::error('NOT_FOUND', 'Appointment not found (or no access).');
            }

            $appointment->delete();

            return ToolResult::success(['id' => $id, 'message' => 'Appointment deleted.']);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error deleting appointment: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'appointments', 'delete'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
