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


class SimplePaginator extends AbstractPaginator implements ArrayableInterface, ArrayAccess, Countable, IteratorAggregate, JsonSerializable, JsonableInterface
{
    /**
     * Déterminez s’il y a plus d’éléments dans la source de données.
     *
     * @return bool
     */
    protected $hasMore;


    /**
     * Créez une nouvelle instance de paginateur.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options (path, query, fragment, pageName)
     * @return void
     */
    public function __construct($items, $perPage, $currentPage = null, array $options = array())
    {
        if (! $items instanceof Collection) {
            $items = Collection::make($items);
        }

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->perPage = $perPage;

        $this->currentPage = $this->setCurrentPage($currentPage);

        $this->hasMore = count($items) > ($this->perPage);

        $this->items = $items->slice(0, $this->perPage);

        if ($this->path !== '/') {
            $this->path = rtrim($this->path, '/');
        }
    }

    /**
     * Créez et renvoyez une nouvelle instance SimplePaginator.
     *
     * @param  int  $page
     * @return bool
     */
    public static function make(array $items, $perPage = 15, $pageName = 'page', $page = null)
    {
        if (is_null($page)) {
            $page = static::resolveCurrentPage($pageName);
        }

        $path = static::resolveCurrentPath($pageName);

        return new static($items, $perPage, $page, compact('path', 'pageName'));
    }

    /**
     * Obtenez la page actuelle de la demande.
     *
     * @param  int  $currentPage
     * @return int
     */
    protected function setCurrentPage($currentPage)
    {
        $currentPage = $currentPage ?: static::resolveCurrentPage();

        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
    }

    /**
     * Obtenez l'URL de la page suivante.
     *
     * @return string|null
     */
    public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
    }

    /**
     * Rendre le paginateur en utilisant la vue donnée.
     *
     * @param  string|null  $view
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
     * @param  string|null  $view
     * @param  array  $data
     * @return string
     */
    public function render($view = null, $data = array())
    {
        if (is_null($view)) {
            $view = static::$defaultSimpleView;
        }

        $data = array_merge($data, array(
            'paginator' => $this,
        ));

        return new HtmlString(
            static::viewFactory()->make($view, $data)->render()
        );
    }

    /**
     * Indiquez manuellement que le paginateur a plus de pages.
     *
     * @param  bool  $value
     * @return $this
     */
    public function hasMorePagesWhen($value = true)
    {
        $this->hasMore = $value;

        return $this;
    }

    /**
     * Déterminez s’il y a plus d’éléments dans la source de données.
     *
     * @return bool
     */
    public function hasMorePages()
    {
        return $this->hasMore;
    }

    /**
     * Obtenez l'instance sous forme de tableau.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'per_page'      => $this->perPage(),
            'current_page'  => $this->currentPage(),
            'next_page_url' => $this->nextPageUrl(),
            'prev_page_url' => $this->previousPageUrl(),
            'from'          => $this->firstItem(),
            'to'            => $this->lastItem(),
            'data'          => $this->items->toArray(),
        ];
    }

    /**
     * Convertissez l'objet en quelque chose de sérialisable JSON.
     *
     * @return array
     */
    public function jsonSerialize()
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
