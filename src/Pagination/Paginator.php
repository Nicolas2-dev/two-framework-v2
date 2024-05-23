<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Pagination;

use Countable;
use ArrayAccess;
use JsonSerializable;
use IteratorAggregate;

use Two\Collection\Collection;
use Two\Support\HtmlString;
use Two\Application\Contracts\JsonableInterface;
use Two\Application\Contracts\ArrayableInterface;


class Paginator extends AbstractPaginator implements ArrayableInterface, ArrayAccess, Countable, IteratorAggregate, JsonSerializable, JsonableInterface
{
    /**
     * Le nombre total d'éléments avant le découpage.
     *
     * @var int
     */
    protected $total;

    /**
     * La dernière page disponible.
     *
     * @var int
     */
    protected $lastPage;


    /**
     * Créez une nouvelle instance de paginateur.
     *
     * @param  mixed  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $total, $perPage, $currentPage = null, array $options = array())
    {
        if (! $items instanceof Collection) {
            $items = Collection::make($items);
        }

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total   = $total;
        $this->perPage = $perPage;

        $this->lastPage = (int) ceil($total / $perPage);

        $this->currentPage = $this->setCurrentPage($currentPage, $this->pageName);

        $this->items = $items;

        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/');
        }
    }

    /**
     * Créez et renvoyez une nouvelle instance de Paginator.
     *
     * @param  int  $page
     * @return bool
     */
    public static function make(array $items, $total, $perPage = 15, $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = static::resolveCurrentPage($pageName);
        }

        $path = static::resolveCurrentPath($pageName);

        return new static($items, $total, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Obtenez la page actuelle de la demande.
     *
     * @param  int  $currentPage
     * @param  string  $pageName
     * @return int
     */
    protected function setCurrentPage($currentPage, $pageName)
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage($pageName);

        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
    }

    /**
     * Rendre le paginateur en utilisant la vue donnée.
     *
     * @param  string  $view
     * @param  array  $data
     * @return string
     */
    public function links($view = null, $data = array())
    {
        return $this->render($view, $data);
    }

    /**
     * Rendre le paginateur en utilisant la vue donnée.
     *
     * @param  string  $view
     * @param  array  $data
     * @return string
     */
    public function render($view = null, $data = array())
    {
        if (is_null($view)) {
            $view = static::$defaultView;
        }

        $data = array_merge($data, array(
            'paginator' => $this,
            'elements'  => $this->elements(),
        ));

        return new HtmlString(
            static::viewFactory()->make($view, $data)->render()
        );
    }

    /**
     * Obtenez le tableau d’éléments à transmettre à la vue.
     *
     * @return array
     */
    protected function elements()
    {
        $window = $this->getUrlWindow($this->onEachSide);

        return array_filter(array(
            $window['first'],
            is_array($window['slider']) ? '...' : null,
            $window['slider'],
            is_array($window['last']) ? '...' : null,
            $window['last'],
        ));
    }

    /**
     * Obtenez la fenêtre des URL à afficher.
     *
     * @param  int  $onEachSide
     * @return array
     */
    public function getUrlWindow($onEachSide = 3)
    {
        if (! $this->hasPages()) {
            return array('first' => null, 'slider' => null, 'last' => null);
        }

        $window = $onEachSide * 2;

        if ($this->lastPage() < ($window + 6)) {
            return $this->getSmallSlider();
        }

        // Si la page actuelle est très proche du début de la plage de pages, nous
        // affiche simplement le début de la plage de pages, suivi des 2 dernières pages
        // liens dans cette liste, puisque nous n'aurons pas de place pour créer un slider complet.
        if ($this->currentPage() <= $window) {
            return $this->getSliderTooCloseToBeginning($window);
        }

        // Si la page actuelle est proche de la fin de la plage de pages, nous obtiendrons simplement
        // ces deux premières pages, suivies d'une fenêtre plus grande de ces pages de fin
        // puisque nous sommes trop près de la fin de la liste pour créer un curseur complet.
        else if ($this->currentPage() > ($this->lastPage() - $window)) {
            return $this->getSliderTooCloseToEnding($window);
        }

        // Si nous avons suffisamment d'espace des deux côtés de la page actuelle pour créer un curseur, nous
        // l'entourera des majuscules de début et de fin, avec cette fenêtre
        // de pages au milieu fournissant une configuration de paginateur coulissant de style Google.
        return $this->getFullSlider($onEachSide);
    }

    /**
     * Obtenez le curseur des URL, il n'y a pas assez de pages à glisser.
     *
     * @return array
     */
    protected function getSmallSlider()
    {
        return array(
            'first'  => $this->getUrlRange(1, $this->lastPage()),
            'slider' => null,
            'last'   => null,
        );
    }

    /**
     * Obtenez le curseur des URL lorsqu'il est trop proche du début de la fenêtre.
     *
     * @param  int  $window
     * @return array
     */
    protected function getSliderTooCloseToBeginning($window)
    {
        $lastPage = $this->lastPage();

        return array(
            'first'  => $this->getUrlRange(1, $window + 2),
            'slider' => null,
            'last'   => $this->getUrlRange($lastPage - 1, $lastPage),
        );
    }

    /**
     * Obtenez le curseur des URL lorsqu'il est trop proche de la fin de la fenêtre.
     *
     * @param  int  $window
     * @return array
     */
    protected function getSliderTooCloseToEnding($window)
    {
        $lastPage = $this->lastPage();

        $last = $this->getUrlRange($lastPage - ($window + 2), $lastPage);

        return array(
            'first'  => $this->getUrlRange(1, 2),
            'slider' => null,
            'last'   => $last,
        );
    }

    /**
     * Obtenez le curseur des URL lorsqu'un curseur complet peut être créé.
     *
     * @param  int  $onEachSide
     * @return array
     */
    protected function getFullSlider($onEachSide)
    {
        $currentPage = $this->currentPage();

        $slider = $this->getUrlRange(
            $currentPage - $onEachSide,
            $currentPage + $onEachSide
        );

        $lastPage = $this->lastPage();

        return array(
            'first'  => $this->getUrlRange(1, 2),
            'slider' => $slider,
            'last'   => $this->getUrlRange($lastPage - 1, $lastPage),
        );
    }

    /**
     * Obtenez le nombre total d’éléments paginés.
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Déterminez s’il y a des pages à afficher.
     *
     * @return bool
     */
    public function hasPages()
    {
        return $this->lastPage() > 1;
    }

    /**
     * Déterminez s’il y a plus d’éléments dans la source de données.
     *
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->currentPage() < $this->lastPage();
    }

    /**
     * Obtenez l'URL de la page suivante.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        if ($this->lastPage() > $this->currentPage()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    /**
     * Obtenez la dernière page.
     *
     * @return int
     */
    public function lastPage()
    {
        return $this->lastPage;
    }

    /**
     * Obtenez l'instance sous forme de tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'total'         => $this->total(),
            'per_page'      => $this->perPage(),
            'current_page'  => $this->currentPage(),
            'last_page'     => $this->lastPage(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
            'from'          => $this->firstItem(),
            'to'            => $this->lastItem(),
            'data'          => $this->items->toArray(),
        );
    }

    /**
     * Convertissez l'objet en quelque chose de sérialisable JSON.
     *
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Convertissez l'objet en sa représentation JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
}
