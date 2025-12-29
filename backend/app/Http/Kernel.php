    /**
     * Custom API middleware
     */
    protected $middlewareAliases = [
        // ... existing middlewares
        'api.rate_limit' => \App\Http\Middleware\ApiRateLimiting::class,
        'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
    ];
