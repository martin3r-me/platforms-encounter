<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class UpdateAppointmentTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const STATUSES = ['planned', 'attended', 'cancelled', 'rescheduled'];

    public function getName(): string
    {
        return 'encounter.appointments.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /encounter/appointments - Updates an appointment. REQUIRED: appointment_id. Optional: scheduled_at, status, performed_by, doctor_stamp, notes, anamnesis, findings, remarks, confidential (empty string clears).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'appointment_id' => ['type' => 'integer', 'description' => 'Id of the appointment (REQUIRED).'],
                'scheduled_at' => ['type' => 'string', 'description' => 'Optional: ISO datetime.'],
                'status' => ['type' => 'string', 'enum' => self::STATUSES, 'description' => 'Optional.'],
                'performed_by' => ['type' => 'string', 'description' => 'Optional.'],
                'doctor_stamp' => ['type' => 'string', 'description' => 'Optional.'],
                'notes' => ['type' => 'string', 'description' => 'Optional.'],
                'anamnesis' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'findings' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'remarks' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
                'confidential' => ['type' => 'string', 'description' => 'Optional (encrypted).'],
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

            $payload = [];
            if (array_key_exists('scheduled_at', $arguments) && trim((string) $arguments['scheduled_at']) !== '') {
                $payload['scheduled_at'] = (string) $arguments['scheduled_at'];
            }
            if (array_key_exists('status', $arguments) && $arguments['status'] !== null) {
                if (!in_array($arguments['status'], self::STATUSES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'status is invalid.');
                }
                $payload['status'] = $arguments['status'];
            }
            foreach (['performed_by', 'doctor_stamp', 'notes', 'anamnesis', 'findings', 'remarks', 'confidential'] as $f) {
                if (array_key_exists($f, $arguments)) {
                    $payload[$f] = ($arguments[$f] === '' || $arguments[$f] === null) ? null : (string) $arguments[$f];
                }
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'No changes provided.');
            }

            $appointment->update($payload);

            return ToolResult::success([
                'id' => $appointment->id,
                'scheduled_at' => optional($appointment->scheduled_at)->toISOString(),
                'status' => $appointment->status?->value,
                'team_id' => $appointment->team_id,
                'message' => 'Appointment updated successfully.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error updating appointment: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'appointments', 'update'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
