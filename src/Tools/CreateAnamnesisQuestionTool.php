<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\AnamnesisQuestion;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class CreateAnamnesisQuestionTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const TYPES = ['yes_no', 'text', 'scale', 'choice'];

    public function getName(): string
    {
        return 'encounter.anamnesis_questions.POST';
    }

    public function getDescription(): string
    {
        return 'POST /encounter/anamnesis-questions - Creates an anamnesis question (settings). '
            . 'REQUIRED: text. Optional: type (yes_no|text|scale|choice, default yes_no), options (for choice/scale), '
            . 'section, examiner_scope (null=all examiners), occasion_id (bind to an ArbMedVV occasion; null=general), position, active.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'        => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'text'           => ['type' => 'string', 'description' => 'The question text (REQUIRED).'],
                'type'           => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Answer type. Default: yes_no.'],
                'options'        => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: options for choice/scale.'],
                'section'        => ['type' => 'string', 'description' => 'Optional: grouping section.'],
                'examiner_scope' => ['type' => 'string', 'description' => 'Optional: restrict to examiner group (null=all).'],
                'occasion_id'    => ['type' => 'integer', 'description' => 'Optional: bind to this ArbMedVV occasion (null=general question).'],
                'position'       => ['type' => 'integer', 'description' => 'Optional: sort order.'],
                'active'         => ['type' => 'boolean', 'description' => 'Optional: default true.'],
            ],
            'required' => ['text'],
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

            $text = trim((string) ($arguments['text'] ?? ''));
            if ($text === '') {
                return ToolResult::error('VALIDATION_ERROR', 'text is required.');
            }

            $type = (string) ($arguments['type'] ?? 'yes_no');
            if (!in_array($type, self::TYPES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'type is invalid.');
            }

            // Optional ArbMedVV-Anlass-Bindung.
            $occasionId = !empty($arguments['occasion_id']) ? (int) $arguments['occasion_id'] : null;
            if ($occasionId && class_exists(\Platform\Arbmedvv\Models\Occasion::class)) {
                $exists = \Platform\Arbmedvv\Models\Occasion::query()
                    ->where('team_id', $teamId)->whereKey($occasionId)->exists();
                if (!$exists) {
                    return ToolResult::error('VALIDATION_ERROR', 'occasion_id not found in the ArbMedVV catalog.');
                }
            }

            $question = AnamnesisQuestion::create([
                'team_id'            => $teamId,
                'created_by_user_id' => $context->user->id,
                'catalog_type'       => $occasionId ? 'arbmedvv_occasion' : null,
                'catalog_id'         => $occasionId,
                'text'               => $text,
                'type'               => $type,
                'options'            => isset($arguments['options']) && is_array($arguments['options']) ? array_values($arguments['options']) : null,
                'section'            => isset($arguments['section']) && $arguments['section'] !== '' ? (string) $arguments['section'] : null,
                'examiner_scope'     => isset($arguments['examiner_scope']) && $arguments['examiner_scope'] !== '' ? (string) $arguments['examiner_scope'] : null,
                'position'           => (int) ($arguments['position'] ?? 0),
                'active'             => array_key_exists('active', $arguments) ? (bool) $arguments['active'] : true,
            ]);

            return ToolResult::success([
                'id'          => $question->id,
                'text'        => $question->text,
                'type'        => $type,
                'occasion_id' => $occasionId,
                'team_id'     => $question->team_id,
                'message'     => 'Anamnesis question created successfully.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error creating anamnesis question: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'anamnesis_questions', 'settings', 'create'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
