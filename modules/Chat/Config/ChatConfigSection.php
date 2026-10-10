<?php

declare(strict_types=1);

namespace Modules\Chat\Config;

use App\Contracts\Config\ConfigSectionInterface;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatConfigSection implements ConfigSectionInterface
{
    public function key(): string
    {
        return 'chat';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dependsOn(): array
    {
        return ['agents'];
    }

    public function secretFields(): array
    {
        return [];
    }

    public function export(array $options): iterable
    {
        $conversations = ChatConversation::with('activeParticipants.agent')->get();

        return [
            'version' => $this->schemaVersion(),
            'records' => $conversations->map(function (ChatConversation $c) {
                return [
                    'id' => $c->id,
                    'title' => $c->title,
                    'mode' => $c->mode,
                    'orchestration' => $c->orchestration,
                    'max_turns' => $c->max_turns,
                    'participants' => $c->activeParticipants->map(fn ($p) => [
                        'agent_slug' => $p->agent?->slug,
                        'role' => $p->role,
                        'join_context' => $p->join_context,
                    ])->all(),
                ];
            })->all(),
        ];
    }

    public function validate(array $data): array
    {
        $errors = [];
        if (! isset($data['records']) || ! is_array($data['records'])) {
            $errors[] = 'Brak wymaganej tablicy records w sekcji chat.';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function plan(array $data, array $options): array
    {
        $create = [];
        $update = [];
        $skip = [];
        $conflicts = [];

        $records = $data['records'] ?? [];
        foreach ($records as $item) {
            $existing = ChatConversation::find($item['id'] ?? null);
            if (! $existing) {
                $create[] = $item;
            } else {
                $update[] = $item;
            }
        }

        return [
            'create' => $create,
            'update' => $update,
            'skip' => $skip,
            'conflicts' => $conflicts,
        ];
    }

    public function import(array $plan): array
    {
        $importedCount = 0;
        $updatedCount = 0;
        $errors = [];

        DB::transaction(function () use ($plan, &$importedCount) {
            foreach ($plan['create'] ?? [] as $item) {
                ChatConversation::create([
                    'id' => $item['id'] ?? (string) Str::uuid(),
                    'user_id' => 1,
                    'title' => $item['title'],
                    'mode' => $item['mode'] ?? 'single',
                    'orchestration' => $item['orchestration'] ?? 'mention',
                    'max_turns' => $item['max_turns'] ?? 20,
                ]);
                $importedCount++;
            }
        });

        return [
            'success' => true,
            'imported_count' => $importedCount,
            'updated_count' => $updatedCount,
            'errors' => $errors,
        ];
    }
}
