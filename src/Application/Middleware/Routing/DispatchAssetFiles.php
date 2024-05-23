<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Routing;

use Closure;

use Two\Application\Two;


class DispatchAssetFiles
{
    /**
     * La mise en œuvre de l'application.
     *
     * @var \Two\Application\Two
     */
    protected $app;

    /**
     * Créez une nouvelle instance de middleware.
     *
     * @param  \Two\Application\Two  $app
     * @return void
     */
    public function __construct(Two $app)
    {
        $this->app = $app;
    }

    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $dispatcher = $this->app['assets.dispatcher'];

        return $dispatcher->dispatch($request) ?: $next($request);
    }
}
