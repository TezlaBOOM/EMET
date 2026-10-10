<?php

declare(strict_types=1);

namespace App\Services\Scenarios;

class ScenarioGraphValidator
{
    public const MAX_NODES = 200;

    public const VALID_NODE_TYPES = [
        'start',
        'agent',
        'skill',
        'memory',
        'condition',
        'parallel',
        'join',
        'loop',
        'human',
        'transform',
        'delay',
        'end',
    ];

    /**
     * Waliduje strukturę grafu scenariusza.
     *
     * @param  array<string, mixed>  $graphJson
     * @return array{is_valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validate(array $graphJson): array
    {
        $errors = [];
        $warnings = [];

        $nodes = $graphJson['nodes'] ?? ($graphJson['drawflow']['Home']['data'] ?? []);

        if (empty($nodes)) {
            return [
                'is_valid' => false,
                'errors' => ['Graf scenariusza nie zawiera żadnych węzłów.'],
                'warnings' => [],
            ];
        }

        if (count($nodes) > self::MAX_NODES) {
            $errors[] = 'Przekroczono maksymalną liczbę węzłów w scenariuszu ('.count($nodes).' > '.self::MAX_NODES.').';
        }

        $hasStart = false;
        $hasEnd = false;
        $adjacency = [];
        $nodeTypes = [];

        foreach ($nodes as $nodeKey => $node) {
            $nodeId = (string) ($node['id'] ?? $nodeKey);
            $nodeType = (string) ($node['type'] ?? ($node['name'] ?? ''));

            $nodeTypes[$nodeId] = $nodeType;
            $adjacency[$nodeId] = [];

            if (! in_array($nodeType, self::VALID_NODE_TYPES, true)) {
                $errors[] = "Nieznany typ węzła '{$nodeType}' dla węzła {$nodeId}.";
            }

            if ($nodeType === 'start') {
                $hasStart = true;
            }

            if ($nodeType === 'end') {
                $hasEnd = true;
            }

            // Wydobycie krawędzi wychodzących
            if (isset($node['outputs']) && is_array($node['outputs'])) {
                foreach ($node['outputs'] as $output) {
                    $connections = $output['connections'] ?? [];
                    foreach ($connections as $conn) {
                        $targetNode = (string) ($conn['node'] ?? '');
                        if ($targetNode !== '') {
                            $adjacency[$nodeId][] = $targetNode;
                        }
                    }
                }
            }
        }

        // Krawędzie z dedykowanej listy edges (jeśli istnieje)
        if (isset($graphJson['edges']) && is_array($graphJson['edges'])) {
            foreach ($graphJson['edges'] as $edge) {
                $source = (string) ($edge['source'] ?? $edge['from'] ?? '');
                $target = (string) ($edge['target'] ?? $edge['to'] ?? '');
                if ($source !== '' && $target !== '') {
                    $adjacency[$source][] = $target;
                }
            }
        }

        if (! $hasStart) {
            $errors[] = 'Graf musi zawierać co najmniej jeden węzeł startowy (start).';
        }

        if (! $hasEnd) {
            $warnings[] = 'Zaleca się umieszczenie co najmniej jednego węzła końcowego (end).';
        }

        // Weryfikacja acykliczności (z wyjątkiem węzłów loop)
        if ($this->hasCycleIgnoringLoops($adjacency, $nodeTypes)) {
            $errors[] = 'Wykryto niedozwolony cykl w grafie scenariusza poza węzłami pętli (loop).';
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Sprawdza obecność cykli metodą DFS z pominięciem powrotów z węzła loop.
     *
     * @param  array<string, array<int, string>>  $adjacency
     * @param  array<string, string>  $nodeTypes
     */
    protected function hasCycleIgnoringLoops(array $adjacency, array $nodeTypes): bool
    {
        $visited = [];
        $recStack = [];

        foreach (array_keys($adjacency) as $nodeId) {
            if (! isset($visited[$nodeId])) {
                if ($this->isCyclicUtil($nodeId, $adjacency, $nodeTypes, $visited, $recStack)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isCyclicUtil(
        string $nodeId,
        array &$adjacency,
        array &$nodeTypes,
        array &$visited,
        array &$recStack
    ): bool {
        $visited[$nodeId] = true;
        $recStack[$nodeId] = true;

        $neighbors = $adjacency[$nodeId] ?? [];
        foreach ($neighbors as $neighbor) {
            // Ignorujemy powroty z węzła typu loop
            if (($nodeTypes[$nodeId] ?? '') === 'loop') {
                continue;
            }

            if (! isset($visited[$neighbor])) {
                if ($this->isCyclicUtil($neighbor, $adjacency, $nodeTypes, $visited, $recStack)) {
                    return true;
                }
            } elseif (! empty($recStack[$neighbor])) {
                return true;
            }
        }

        $recStack[$nodeId] = false;

        return false;
    }
}
