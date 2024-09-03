<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{

    protected $namespace = 'App\Http\Controllers';
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();
        // $this->routes(function ()
        // {
        //     Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
        //     Route::middleware('web')->group(base_path('routes/web.php'));
        // });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();
        // $this->mapWebRoutes();

        $this->mapCustomApiRoutes();
        // $this->mapCustomWebRoutes();
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::middleware('api')
            ->namespace($this->namespace)
            ->prefix('api')
            ->group(base_path('routes/api.php'));
    }

    protected function mapCustomApiRoutes()
    {
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/general')->group(base_path('routes/api/general.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/mail/')->group(base_path('routes/api/sendmails.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/administration')->group(base_path('routes/api/administration.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/attendance')->group(base_path('routes/api/attendance.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/institution')->group(base_path('routes/api/institution.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/directory')->group(base_path('routes/api/directory.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/clientes')->group(base_path('routes/api/clientes.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/proveedores')->group(base_path('routes/api/proveedores.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/tipos-proveedor')->group(base_path('routes/api/tipoProveedor.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/inventario')->group(base_path('routes/api/inventario.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/productos')->group(base_path('routes/api/productos.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/ventas')->group(base_path('routes/api/ventas.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/compras')->group(base_path('routes/api/compras.php'));
        Route::middleware('auth:api')->namespace($this->namespace)->prefix('api/dte')->group(base_path('routes/api/dte.php'));
    
        Route::middleware(['api'])->namespace($this->namespace)->prefix('api/auth')->group(base_path('routes/api/auth.php'));
        Route::middleware('api')->namespace($this->namespace)->prefix('api/images')->group(base_path('routes/api/images.php'));

    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    // protected function mapWebRoutes()
    // {
    //     Route::middleware('web')
    //         ->namespace($this->namespace)
    //         ->group(base_path('routes/web.php'));
    // }

    // protected function mapCustomWebRoutes()
    // {
    //     Route::middleware('web')->namespace($this->namespace)->prefix('administracion')->group(base_path('routes/web/administracion.php'));
    // }
}
