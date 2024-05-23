<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\View;

use Closure;

use Two\View\ViewErrorBag;
use Two\View\Factory as ViewFactory;


class ShareErrorsFromSession
{
    /**
     * L’implémentation de la fabrique de vues.
     *
     * @var \Two\View\Factory
     */
    protected $view;

    /**
     * Créez une nouvelle instance de classeur d'erreurs.
     *
     * @param  \Two\View\Factory  $view
     * @return void
     */
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
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
        $this->view->share(
            'errors', $request->session()->get('errors', new ViewErrorBag())
        );

        return $next($request);
    }
}
