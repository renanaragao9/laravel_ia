<?php

namespace App\Providers;

use App\Contracts\LlmClient;
use App\Contracts\ResultSource;
use App\Contracts\ToolGateway;
use App\Services\Draws\HtmlTableResultSource;
use App\Services\Llm\McpToolGateway;
use App\Services\Llm\OpenRouterClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ResultSource::class, HtmlTableResultSource::class);
        $this->app->bind(LlmClient::class, OpenRouterClient::class);
        $this->app->bind(ToolGateway::class, McpToolGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
