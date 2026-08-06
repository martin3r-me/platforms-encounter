<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\FieldDefinition;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class UpdateFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const TYPES = ['text', 'textarea', 'number', 'boolean', 'date', 'select'];
    private const AUDIENCES = ['patient', 'employer', 'internal', 'private'];

    public function getName(): string
    {
        return 'encounter.field_definitions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /encounter/field-definitions - Updates a field definition. REQUIRED: field_definition_id. Optional: key, label, type, audience, position, active, options.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'field_definition_id' => ['type' => 'integer', 'description' => 'Id of the field definition (REQUIRED).'],
                'key' => ['type' => 'string', 'description' => 'Optional.'],
                'label' => ['type' => 'string', 'description' => 'Optional.'],
                'type' => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Optional.'],
                'audience' => ['type' => 'string', 'enum' => self::AUDIENCES, 'description' => 'Optional.'],
                'position' => ['type' => 'integer', 'description' => 'Optional.'],
                'active' => ['type' => 'boolean', 'description' => 'Optional.'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: choices for type=select.'],
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

            $payload = [];
            foreach (['key', 'label'] as $f) {
                if (array_key_exists($f, $arguments) && $arguments[$f] !== null) {
                    $val = trim((string) $arguments[$f]);
                    if ($val === '') {
                        return ToolResult::error('VALIDATION_ERROR', "{$f} must not be empty.");
                    }
                    $payload[$f] = $val;
                }
            }
            if (array_key_exists('type', $arguments) && $arguments['type'] !== null) {
                if (!in_array($arguments['type'], self::TYPES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'type is invalid.');
                }
                $payload['type'] = $arguments['type'];
            }
            if (array_key_exists('audience', $arguments) && $arguments['audience'] !== null) {
                if (!in_array($arguments['audience'], self::AUDIENCES, true)) {
                    return ToolResult::error('VALIDATION_ERROR', 'audience is invalid.');
                }
                $payload['audience'] = $arguments['audience'];
            }
            if (array_key_exists('position', $arguments) && $arguments['position'] !== null) {
                $payload['position'] = (int) $arguments['position'];
            }
            if (array_key_exists('active', $arguments) && $arguments['active'] !== null) {
                $payload['active'] = (bool) $arguments['active'];
            }
            if (array_key_exists('options', $arguments)) {
                $payload['options'] = is_array($arguments['options']) ? array_values($arguments['options']) : null;
            }

            if (empty($payload)) {
                return ToolResult::error('NO_CHANGE', 'No changes provided.');
            }

            $field->update($payload);

            return ToolResult::success([
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'team_id' => $field->team_id,
                'message' => "Field definition '{$field->label}' updated successfully.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error updating field definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'field_definitions', 'settings', 'update'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => true,
        ];
    }
}
