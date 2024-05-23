<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Pagination;

use Two\Support\Str;


class UrlGenerator
{
    /**
     * L’implémentation de Paginator.
     *
     * @var \Two\Pagination\Contracts\PaginatorInterface
     */
    protected $paginator;


    /**
     * Créez une nouvelle instance de générateur d'URL.
     *
     * @param  \Two\Pagination\Contracts\PaginatorInterface  $paginator
     * @return void
     */
    public function __construct(AbstractPaginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * Résolvez l’URL d’un numéro de page donné.
     *
     * @param  int  $page
     * @return string
     */
    public function url($page)
    {
        $paginator = (object) $this->getPaginator();

        //
        $pageName = $paginator->getPageName();

        $query = array_merge(
            $paginator->getQuery(), array($pageName => $page)
        );

        return $this->buildUrl(
            $paginator->getPath(), $query, $paginator->fragment()
        );
    }

    /**
     * Créez la partie requête complète d’une URL.
     *
     * @param  string  $path
     * @param  array  $query
     * @param  string|null  $fragment
     * @return string
     */
    protected function buildUrl($path, array $query, $fragment)
    {
        if (! empty($query)) {
            $separator = Str::contains($path, '?') ? '&' : '?';

            $path .= $separator .http_build_query($query, '', '&');
        }

        if (! empty($fragment)) {
            $path .= '#' .$fragment;
        }

        return $path;
    }

    /**
     * Obtenez l’implémentation de Paginator.
     *
     * @return string
     */
    public function getPaginator()
    {
        return $this->paginator;
    }
}
