<?php

namespace App\Config;

class WorkflowResolver
{
    /**
     * Resolve workflow config for a given repository.
     *
     * Matches repo against config keys (supports glob patterns like "conduit-ui/*"),
     * then merges repo-specific overrides onto defaults.
     *
     * @return array{merge_strategy: string, base_branch: string, delete_branch: bool, required_reviews: int}
     */
    public static function forRepo(string $repository): array
    {
        /** @var array{merge_strategy: string, base_branch: string, delete_branch: bool, required_reviews: int} $defaults */
        $defaults = config('workflows.defaults', [
            'merge_strategy' => 'merge',
            'base_branch' => 'main',
            'delete_branch' => true,
            'required_reviews' => 0,
        ]);

        /** @var array<string, array<string, mixed>> $repos */
        $repos = config('workflows.repos', []);

        foreach ($repos as $pattern => $overrides) {
            if (self::matches($repository, $pattern)) {
                return array_merge($defaults, $overrides);
            }
        }

        return $defaults;
    }

    /**
     * Check if a repository string matches a glob-like pattern.
     */
    public static function matches(string $repository, string $pattern): bool
    {
        // Exact match
        if ($repository === $pattern) {
            return true;
        }

        // Convert glob pattern to regex
        $regex = str_replace(
            ['*', '?'],
            ['[^/]*', '[^/]'],
            $pattern
        );

        return (bool) preg_match('#^' . $regex . '$#', $repository);
    }
}
