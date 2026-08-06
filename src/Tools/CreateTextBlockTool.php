<?php

namespace Platform\Encounter\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Encounter\Models\TextBlock;
use Platform\Encounter\Tools\Concerns\ResolvesEncounterTeam;

class CreateTextBlockTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesEncounterTeam;

    private const AUDIENCES = ['patient', 'employer', 'internal', 'private'];

    public function getName(): string
    {
        return 'encounter.text_blocks.POST';
    }

    public function getDescription(): string
    {
        return 'POST /encounter/text-blocks - Creates a reusable text block (settings). REQUIRED: title, audience (patient|employer|internal|private). Optional: content, position, active (default true). Employer-audience blocks appear on employer certificates (no medical results).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => ['type' => 'integer', 'description' => 'Optional: team id. Default: current team.'],
                'title' => ['type' => 'string', 'description' => 'Title (REQUIRED).'],
                'audience' => ['type' => 'string', 'enum' => self::AUDIENCES, 'description' => 'Audience (REQUIRED).'],
                'content' => ['type' => 'string', 'description' => 'Optional: block text.'],
                'position' => ['type' => 'integer', 'description' => 'Optional: sort order.'],
                'active' => ['type' => 'boolean', 'description' => 'Optional: default true.'],
            ],
            'required' => ['title', 'audience'],
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

            $title = trim((string) ($arguments['title'] ?? ''));
            if ($title === '') {
                return ToolResult::error('VALIDATION_ERROR', 'title is required.');
            }
            $audience = (string) ($arguments['audience'] ?? '');
            if (!in_array($audience, self::AUDIENCES, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'audience is invalid.');
            }

            $block = TextBlock::create([
                'team_id'  => $teamId,
                'title'    => $title,
                'audience' => $audience,
                'content'  => isset($arguments['content']) && $arguments['content'] !== '' ? (string) $arguments['content'] : null,
                'position' => (int) ($arguments['position'] ?? 0),
                'active'   => array_key_exists('active', $arguments) ? (bool) $arguments['active'] : true,
            ]);

            return ToolResult::success([
                'id' => $block->id,
                'title' => $block->title,
                'audience' => $block->audience?->value,
                'team_id' => $block->team_id,
                'message' => "Text block '{$block->title}' created successfully.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Error creating text block: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false, 'category' => 'action', 'tags' => ['encounter', 'text_blocks', 'settings', 'create'],
            'risk_level' => 'write', 'requires_auth' => true, 'requires_team' => true, 'idempotent' => false,
        ];
    }
}
