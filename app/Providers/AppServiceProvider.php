<?php

namespace App\Providers;

use ConduitUi\GitHubConnector\Connector;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use ConduitUI\Pr\PullRequests;
use ConduitUI\Pr\Services\GitHubPrService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PrServiceInterface::class, function ($app) {
            $token = $this->resolveToken();

            if ($token === null) {
                throw new \RuntimeException(
                    'GitHub token not configured. Set GITHUB_TOKEN environment variable or configure config/pr.php.'
                );
            }

            return new GitHubPrService(new Connector($token));
        });

        $this->app->singleton(GitHubPrService::class, function ($app) {
            return $app->make(PrServiceInterface::class);
        });
    }

    private function resolveToken(): ?string
    {
        $sources = [
            config('pr.github.token'),
            config('services.github.token'),
            env('GITHUB_TOKEN'),
        ];

        foreach ($sources as $token) {
            if (is_string($token) && trim($token) !== '') {
                return trim($token);
            }
        }

        return null;
    }
}
