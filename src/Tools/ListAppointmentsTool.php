<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Encounter\Models\Appointment;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class ListAppointmentsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.appointments.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/appointments - Lists appointments. Params: team_id (optional), status (optional: planned|attended|cancelled|rescheduled), sort/limit/offset. Encrypted clinical text is NOT returned.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'status' => ['type' => 'string', 'enum' => ['planned', 'attended', 'cancelled', 'rescheduled'], 'description' => 'Optional: filter by status.'],
            ],
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

            $query = Appointment::query()->forTeam($teamId)->with('patient');

            if (isset($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }

            $this->applyStandardFilters($query, $arguments, ['status', 'patient_id', 'scheduled_at', 'created_at']);
            $this->applyStandardSort($query, $arguments, ['scheduled_at', 'status', 'created_at'], 'scheduled_at', 'desc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (Appointment $a) => [
                'id' => $a->id,
                'uuid' => $a->uuid,
                'patient_id' => $a->patient_id,
                'patient_name' => $a->patient?->getDisplayName(),
                'scheduled_at' => optional($a->scheduled_at)->toISOString(),
                'status' => $a->status?->value,
                'team_id' => $a->team_id,
            ])->values()->toArray();

            return ToolResult::success(['data' => $data, 'pagination' => $result['pagination'] ?? null, 'team_id' => $teamId]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading appointments: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'appointments', 'list'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
