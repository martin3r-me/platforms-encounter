<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\FieldDefinition;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class CreateFieldDefinitionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const TYPES = ['text', 'textarea', 'number', 'boolean', 'date', 'select'];
    private const AUDIENCES = ['patient', 'employer', 'internal', 'private'];

    public function getName(): string
    {
        return 'encounter.field_definitions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /encounter/field-definitions - Creates a team-customizable field definition (settings). REQUIRED: key, label, type (text|textarea|number|boolean|date|select), audience (patient|employer|internal|private). Optional: position, active (default true), options (array, for type=select).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'key' => ['type' => 'string', 'description' => 'Machine key (REQUIRED).'],
                'label' => ['type' => 'string', 'description' => 'Display label (REQUIRED).'],
                'type' => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Field type (REQUIRED).'],
                'audience' => ['type' => 'string', 'enum' => self::AUDIENCES, 'description' => 'Audience (REQUIRED).'],
                'position' => ['type' => 'integer', 'description' => 'Optional: sort order.'],
                'active' => ['type' => 'boolean', 'description' => 'Optional: default true.'],
                'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: choices for type=select.'],
            ],
            'required' => ['key', 'label', 'type', 'audience'],
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

            $key = trim((string) ($arguments['key'] ?? ''));
            $label = trim((string) ($arguments['label'] ?? ''));
            $type = (string) ($arguments['type'] ?? '');
            $audience = (string) ($arguments['audience'] ?? '');

            if ($key === '' || $label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'key and label are required.');
            }
            if (!in_array($type, self::TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'type is invalid.');
            }
            if (!in_array($audience, self::AUDIENCES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'audience is invalid.');
            }

            $field = FieldDefinition::create([
                'team_id'  => $teamId,
                'key'      => $key,
                'label'    => $label,
                'type'     => $type,
                'audience' => $audience,
                'position' => (int) ($arguments['position'] ?? 0),
                'active'   => array_key_exists('active', $arguments) ? (bool) $arguments['active'] : true,
                'options'  => isset($arguments['options']) && is_array($arguments['options']) ? array_values($arguments['options']) : null,
            ]);

            return ToolResult::success([
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type?->value,
                'audience' => $field->audience?->value,
                'team_id' => $field->team_id,
                'message' => "Field definition '{$field->label}' created successfully.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error creating field definition: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'field_definitions', 'settings', 'create'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
