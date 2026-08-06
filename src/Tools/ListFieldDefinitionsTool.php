<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Encounter\Models\FieldDefinition;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class ListFieldDefinitionsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.field_definitions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /encounter/field-definitions - Lists team-customizable field definitions (settings). Params: team_id (optional), audience, sort/limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas($this->getStandardGetSchema(), [
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'audience' => ['type' => 'string', 'enum' => ['patient', 'employer', 'internal', 'private'], 'description' => 'Optional: filter by audience.'],
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

            $query = FieldDefinition::query()->forTeam($teamId);
            if (isset($arguments['audience'])) {
                $query->where('audience', $arguments['audience']);
            }

            $this->applyStandardFilters($query, $arguments, ['audience', 'type', 'active', 'created_at']);
            $this->applyStandardSort($query, $arguments, ['position', 'audience', 'label', 'created_at'], 'position', 'asc');

            $result = $this->applyStandardPaginationResult($query, $arguments);

            $data = collect($result['data'])->map(fn (FieldDefinition $f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type?->value,
                'audience' => $f->audience?->value,
                'position' => $f->position,
                'active' => (bool) $f->active,
                'options' => $f->options,
                'team_id' => $f->team_id,
            ])->values()->toArray();

            return ToolResult::success(['data' => $data, 'pagination' => $result['pagination'] ?? null, 'team_id' => $teamId]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error loading field definitions: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true, 'category' => 'read', 'tags' => ['encounter', 'field_definitions', 'settings', 'list'],
            'risk_level' => 'safe', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
