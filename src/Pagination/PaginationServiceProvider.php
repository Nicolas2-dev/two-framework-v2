<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Pagination;

use Two\Application\Providers\ServiceProvider;


class PaginationServiceProvider extends ServiceProvider
{

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        Paginator::viewFactoryResolver(function ()
        {
            return $this->app['view'];
        });

        Paginator::currentPathResolver(function ($pageName = 'page')
        {
            return $this->app['request']->url();
        });

        Paginator::currentPageResolver(function ($pageName = 'page')
        {
            $page = $this->app['request']->input($pageName, 1);

            if ((filter_var($page, FILTER_VALIDATE_INT) !== false) && ((int) $page >= 1)) {
                return $page;
            }

            return 1;
        });

        Paginator::urlGeneratorResolver(function (AbstractPaginator $paginator)
        {
            return new UrlGenerator($paginator);
        });
    }
}
