<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Localization;

use Closure;

use Two\Application\Two;


class SetupLanguage
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
     * Traiter une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Two\Http\Exception\PostTooLargeException
     */
    public function handle($request, Closure $next)
    {
        $this->updateLocale($request);

        return $next($request);
    }

    /**
     * Mettez à jour les paramètres régionaux de l'application.
     *
     * @param  \Two\Http\Request  $request
     * @return void
     */
    protected function updateLocale($request)
    {
        $session = $this->app['session'];

        if ($session->has('language')) {
            $locale = $session->get('language');
        } else {
            $locale = $request->cookie(PREFIX .'language', $this->app['config']['app.locale']);

            $session->set('language', $locale);
        }

        $this->app['language']->setLocale($locale);
    }
}
