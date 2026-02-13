<?php

namespace App\Commands;

use App\Concerns\ResolvesRepository;
use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class ListCommand extends Command
{
    use ResolvesRepository;

    protected $signature = 'list-prs
        {--repo= : Repository (owner/name)}
        {--state=open : Filter by state (open, closed, all)}
        {--author= : Filter by author}
        {--limit=30 : Maximum results}';

    protected $description = 'List pull requests for a repository';

    public function handle(PrServiceInterface $service): int
    {
        $repo = $this->resolveRepositoryOrFail();
        $state = $this->option('state');
        $author = $this->option('author');
        $limit = (int) $this->option('limit');

        $this->info("Pull requests for {$repo} (state: {$state}):");

        $query = $service->for($repo)->state($state)->take($limit);

        if ($author) {
            $query->author($author);
        }

        $prs = $query->get();

        if (empty($prs)) {
            $this->line('  No pull requests found.');
            return self::SUCCESS;
        }

        $renderer = new PullRequestRenderer($this->output);

        foreach ($prs as $pr) {
            $renderer->renderLine($pr);
        }

        $this->newLine();
        $this->line("  Showing " . count($prs) . " PR(s)");

        return self::SUCCESS;
    }
}
