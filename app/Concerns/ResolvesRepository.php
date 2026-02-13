<?php

namespace App\Concerns;

trait ResolvesRepository
{
    /**
     * Resolve the repository from --repo option or from the current git remote.
     */
    protected function resolveRepository(): ?string
    {
        // If --repo option given, use it
        if ($this->option('repo')) {
            return $this->option('repo');
        }

        // Otherwise detect from git remote
        return $this->detectFromGitRemote();
    }

    /**
     * Detect the owner/repo from the current directory's git remote.
     */
    protected function detectFromGitRemote(): ?string
    {
        $output = [];
        $exitCode = 0;

        exec('git remote get-url origin 2>/dev/null', $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        $url = $output[0];

        // SSH format: git@github.com:owner/repo.git
        if (preg_match('#git@github\.com:(.+)/(.+?)(?:\.git)?$#', $url, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        // HTTPS format: https://github.com/owner/repo.git
        if (preg_match('#https?://github\.com/(.+)/(.+?)(?:\.git)?$#', $url, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return null;
    }

    /**
     * Resolve the repository or fail with an error.
     */
    protected function resolveRepositoryOrFail(): string
    {
        $repo = $this->resolveRepository();

        if ($repo === null) {
            $this->error('Could not determine repository. Use --repo=owner/name or run from a git directory.');
            exit(1);
        }

        return $repo;
    }
}
