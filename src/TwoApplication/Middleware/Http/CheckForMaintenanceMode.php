<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\TwoApplication\Middleware\Http;

use Closure;

use Two\TwoApplication\TwoApplication;

use Symfony\Component\HttpKernel\Exception\HttpException;


class CheckForMaintenanceMode
{
    /**
     * La mise en œuvre de l'application.
     *
     * @var Two\TwoApplication\TwoApplication
     */
    protected $app;

    /**
     * Créez une nouvelle instance de middleware.
     *
     * @param  Two\TwoApplication\TwoApplication  $app
     * @return void
     */
    public function __construct(TwoApplication $app)
    {
        $this->app = $app;
    }

    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public function handle($request, Closure $next)
    {
        if ($this->app->isDownForMaintenance()) {
            throw new HttpException(503);
        }

        return $next($request);
    }
}
