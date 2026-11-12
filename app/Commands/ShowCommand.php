<?php

namespace App\Commands;

use App\Concerns\ResolvesRepository;
use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    use ResolvesRepository;

    protected $signature = 'show
        {number : PR number}
        {--repo= : Repository (owner/name)}';

    protected $description = 'Show details for a specific pull request';

    public function handle(PrServiceInterface $service): int
    {
        $repo = $this->resolveRepositoryOrFail();
        $number = (int) $this->argument('number');

        try {
            $pr = $service->find($repo, $number);
        } catch (\Throwable $e) {
            $this->error("Failed to fetch PR #{$number}: ".$e->getMessage());

            return self::FAILURE;
        }

        $renderer = new PullRequestRenderer($this->output);
        $renderer->renderDetail($pr);

        return self::SUCCESS;
    }
}
