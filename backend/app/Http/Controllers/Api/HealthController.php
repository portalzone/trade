<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Basic health check
     */
    public function index()
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'service' => 'T-Trade API',
            'version' => '1.0.0',
        ]);
    }

    /**
     * Detailed health check with dependencies
     */
    public function detailed()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Check database
        try {
            DB::connection()->getPdo();
            $health['checks']['database'] = [
                'status' => 'up',
                'response_time' => $this->measureResponseTime(fn() => DB::select('SELECT 1')),
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Check Redis
        try {
            Redis::ping();
            $health['checks']['redis'] = [
                'status' => 'up',
                'response_time' => $this->measureResponseTime(fn() => Redis::ping()),
            ];
        } catch (\Exception $e) {
            $health['checks']['redis'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
            $health['status'] = 'unhealthy';
        }

        // Check cache
        try {
            Cache::put('health_check', true, 10);
            $health['checks']['cache'] = [
                'status' => Cache::get('health_check') ? 'up' : 'down',
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'down',
                'error' => $e->getMessage(),
            ];
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $statusCode);
    }

    /**
     * Measure response time in milliseconds
     */
    protected function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2);
    }
}
