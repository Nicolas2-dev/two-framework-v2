<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Pagination;

use Closure;
use ArrayIterator;
use RuntimeException;

use Two\Collection\Collection;
use Two\Application\Contracts\HtmlableInterface;


abstract class AbstractPaginator implements HtmlableInterface
{
    /**
     * Tous les éléments étant paginés.
     *
     * @var \Two\Collection\Collection
     */
    protected $items;

    /**
     * Le nombre d'éléments à afficher par page.
     *
     * @var int
     */
    protected $perPage;

    /**
     * La page actuelle en cours de "visualisation".
     *
     * @var int
     */
    protected $currentPage;

    /**
     * Le chemin de base à attribuer à toutes les URL.
     *
     * @var string
     */
    protected $path = '/';

    /**
     * Paramètres de requête à ajouter à toutes les URL.
     *
     * @var array
     */
    protected $query = array();

    /**
     * Fragment d'URL à ajouter à toutes les URL.
     *
     * @var string|null
     */
    protected $fragment;

    /**
     * La variable de chaîne de requête utilisée pour stocker la page.
     *
     * @var string
     */
    protected $pageName = 'page';

    /**
     * Le nombre de liens à afficher de chaque côté du lien de la page actuelle.
     *
     * @var int
     */
    public $onEachSide = 3;

    /**
     * L'instance du générateur d'URL.
     *
     * @var \Two\Pagination\UrlGenerator
     */
    protected $urlGenerator;

    /**
     * Le rappel du résolveur du générateur d’URL.
     *
     * @var \Closure
     */
    protected static $urlGeneratorResolver;

    /**
     * Le rappel du résolveur de page actuel.
     *
     * @var \Closure
     */
    protected static $currentPathResolver;

    /**
     * Le rappel du résolveur de page actuel.
     *
     * @var \Closure
     */
    protected static $currentPageResolver;

    /**
     * Le rappel du résolveur de fabrique de vues.
     *
     * @var \Closure
     */
    protected static $viewFactoryResolver;

    /**
     * La vue de pagination par défaut.
     *
     * @var string
     */
    public static $defaultView = 'Partials/Pagination/Default';

    /**
     * La vue de pagination "simple" par défaut.
     *
     * @var string
     */
    public static $defaultSimpleView = 'Partials/Pagination/Simple';


    /**
     * Déterminez si la valeur donnée est un numéro de page valide.
     *
     * @param  int  $page
     * @return bool
     */
    protected function isValidPageNumber($page)
    {
        return ($page >= 1) && (filter_var($page, FILTER_VALIDATE_INT) !== false);
    }

    /**
     * Obtenez l'URL de la page précédente.
     *
     * @return string|null
     */
    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

    /**
     * Créez une plage d'URL de pagination.
     *
     * @param  int  $start
     * @param  int  $end
     * @return array
     */
    public function getUrlRange($start, $end)
    {
        return collect(range($start, $end))->mapWithKeys(function ($page)
        {
            return array($page => $this->url($page));

        })->all();
    }

    /**
     * Obtenez l'URL d'un numéro de page donné.
     *
     * @param  int  $page
     * @return string
     */
    public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        return $this->getUrlGenerator()->url($page);
    }

    /**
     * Obtenez/définissez le fragment d’URL à ajouter aux URL.
     *
     * @param  string|null  $fragment
     * @return $this|string|null
     */
    public function fragment($fragment = null)
    {
        if (is_null($fragment)) {
            return $this->fragment;
        }

        $this->fragment = $fragment;

        return $this;
    }

    /**
     * Ajoutez un ensemble de valeurs de chaîne de requête au paginateur.
     *
     * @param  array|string  $keys
     * @param  string|null  $value
     * @return $this
     */
    public function appends($keys, $value = null)
    {
        if (! is_array($keys)) {
            return $this->addQuery($keys, $value);
        }

        foreach ($keys as $key => $value) {
            $this->addQuery($key, $value);
        }

        return $this;
    }

    /**
     * Ajoutez une valeur de chaîne de requête au paginateur.
     *
     * @param  string  $key
     * @param  string  $value
     * @return $this
     */
    protected function addQuery($key, $value)
    {
        if ($key !== $this->pageName) {
            $this->query[$key] = $value;
        }

        return $this;
    }

    /**
     * Obtenez l’ensemble des valeurs de chaîne de requête au paginateur.
     *
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Obtenez la tranche d’éléments en cours de pagination.
     *
     * @return array
     */
    public function items()
    {
        return $this->items->all();
    }

    /**
     * Obtenez le numéro du premier élément de la tranche.
     *
     * @return int
     */
    public function firstItem()
    {
        if (count($this->items) > 0) {
            return (($this->currentPage - 1) * $this->perPage) + 1;
        }
    }

    /**
     * Obtenez le numéro du dernier élément de la tranche.
     *
     * @return int
     */
    public function lastItem()
    {
        if (count($this->items) > 0) {
            return $this->firstItem() + $this->count() - 1;
        }
    }

    /**
     * Obtenez le nombre d’éléments affichés par page.
     *
     * @return int
     */
    public function perPage()
    {
        return $this->perPage;
    }

    /**
     * Déterminez s’il y a suffisamment d’éléments à diviser en plusieurs pages.
     *
     * @return bool
     */
    public function hasPages()
    {
        return ($this->currentPage() != 1) || $this->hasMorePages();
    }

    /**
     * Déterminez si le paginateur est sur la première page.
     *
     * @return bool
     */
    public function onFirstPage()
    {
        return $this->currentPage() <= 1;
    }

    /**
     * Obtenez la page actuelle.

     *
     * @return int
     */
    public function currentPage()
    {
        return $this->currentPage;
    }

    /**
     * Obtenez la variable de chaîne de requête utilisée pour stocker la page.
     *
     * @return string
     */
    public function getPageName()
    {
        return $this->pageName;
    }

    /**
     * Définissez la variable de chaîne de requête utilisée pour stocker la page.
     *
     * @param  string  $name
     * @return $this
     */
    public function setPageName($name)
    {
        $this->pageName = $name;

        return $this;
    }

    /**
     * Obtenez le chemin de base à attribuer à toutes les URL.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Définissez le chemin de base à attribuer à toutes les URL.
     *
     * @param  string  $path
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Définissez le nombre de liens à afficher de chaque côté du lien de la page actuelle.
     *
     * @param  int  $count
     * @return this
     */
    public function onEachSide($count)
    {
        $this->onEachSide = $count;

        return $this;
    }

    /**
     * Obtenez l'instance du générateur d'URL.
     *
     * @return \Two\Pagination\UrlGenerator
     */
    public function getUrlGenerator()
    {
        if (isset($this->urlGenerator)) {
            return $this->urlGenerator;
        }

        // Vérifiez si un résolveur de générateur d'URL a été défini.
        else if (! isset(static::$urlGeneratorResolver)) {
            throw new RuntimeException("URL Generator resolver not set on Paginator.");
        }

        return $this->urlGenerator = call_user_func(static::$urlGeneratorResolver, $this);
    }

    /**
     * Définissez le rappel du résolveur du générateur d'URL.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public static function urlGeneratorResolver(Closure $resolver)
    {
        static::$urlGeneratorResolver = $resolver;
    }

    /**
     * Résolvez le chemin de requête actuel ou renvoyez la valeur par défaut.
     *
     * @param  string  $pageName
     * @param  string  $default
     * @return string
     */
    public static function resolveCurrentPath($pageName = 'page', $default = '/')
    {
        if (isset(static::$currentPathResolver)) {
            return call_user_func(static::$currentPathResolver, $pageName);
        }

        return $default;
    }

    /**
     * Définissez le rappel actuel du résolveur de chemin de requête.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public static function currentPathResolver(Closure $resolver)
    {
        static::$currentPathResolver = $resolver;
    }

    /**
     * Résolvez la page actuelle ou renvoyez la valeur par défaut.
     *
     * @param  string  $pageName
     * @param  int  $default
     * @return int
     */
    public static function resolveCurrentPage($pageName = 'page', $default = 1)
    {
        if (isset(static::$currentPageResolver)) {
            return call_user_func(static::$currentPageResolver, $pageName);
        }

        return $default;
    }

    /**
     * Définissez le rappel du résolveur de page actuel.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public static function currentPageResolver(Closure $resolver)
    {
        static::$currentPageResolver = $resolver;
    }

    /**
     * Obtenez une instance de la fabrique de vues à partir du résolveur.
     *
     * @return \Two\View\Factory
     */
    public static function viewFactory()
    {
        return call_user_func(static::$viewFactoryResolver);
    }

    /**
     * Définissez le rappel du résolveur de fabrique de vues.
     *
     * @param  \Closure  $resolver
     * @return void
     */
    public static function viewFactoryResolver(Closure $resolver)
    {
        static::$viewFactoryResolver = $resolver;
    }

    /**
     * Définissez la vue de pagination par défaut.
     *
     * @param  string  $view
     * @return void
     */
    public static function defaultView($view)
    {
        static::$defaultView = $view;
    }

    /**
     * Définissez la vue de pagination « simple » par défaut.
     *
     * @param  string  $view
     * @return void
     */
    public static function defaultSimpleView($view)
    {
        static::$defaultSimpleView = $view;
    }

    /**
     * Obtenez un itérateur pour les éléments.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items->all());
    }

    /**
     * Déterminez si la liste des éléments est vide ou non.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    /**
     * Obtenez le nombre d'éléments pour la page actuelle.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Obtenez la collection sous-jacente du paginateur.
     *
     * @return \Two\Collection\Collection
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Définissez la collection sous-jacente du paginateur.
     *
     * @param  mixed  $items
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = ($items instanceof Collection) ? $items : Collection::make($items);

        return $this;
    }

    /**
     * Déterminez si l’élément donné existe.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return $this->items->has($key);
    }

    /**
     * Obtenez l'article au décalage donné.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->items->get($key);
    }

    /**
     * Définissez l'élément au décalage donné.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        $this->items->put($key, $value);
    }

    /**
     * Désactivez l'élément à la clé donnée.
     *
     * @param  mixed  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        $this->items->forget($key);
    }

    /**
     * Rendre le contenu du paginateur au format HTML.
     *
     * @return string
     */
    public function toHtml()
    {
        return (string) $this->render();
    }

    /**
     * Effectuez des appels dynamiques dans la collection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->items, $method), $parameters);
    }

    /**
     * Rendre le contenu du paginateur lors de la conversion en chaîne.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->render();
    }
}
