<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\FieldDefinition;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class DeleteFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    public function getName(): string
    {
        return 'encounter.field_definitions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /encounter/field-definitions - Deletes a field definition. REQUIRED: field_definition_id.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'field_definition_id' => ['type' => 'integer', 'description' => 'Id of the field definition (REQUIRED).'],
            ],
            'required' => ['field_definition_id'],
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

            $id = (int) ($arguments['field_definition_id'] ?? 0);
            if ($id <= 0) {
                return ToolResult::error('VALIDATION_ERROR', 'field_definition_id is required.');
            }

            $field = FieldDefinition::query()->forTeam($teamId)->find($id);
            if (!$field) {
                return ToolResult::error('NOT_FOUND', 'Field definition not found (or no access).');
            }

            $label = $field->label;
            $field->delete();

            return ToolResult::success(['id' => $id, 'message' => "Field definition '{$label}' deleted."]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error deleting field definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'field_definitions', 'settings', 'delete'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
