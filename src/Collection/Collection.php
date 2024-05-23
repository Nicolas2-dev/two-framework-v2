<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Collection;

use Closure;
use Countable;
use ArrayAccess;
use ArrayIterator;
use CachingIterator;
use IteratorAggregate;

use Two\Support\Arr;
use Two\Application\Contracts\JsonableInterface;
use Two\Application\Contracts\ArrayableInterface;


class Collection implements ArrayAccess, ArrayableInterface, Countable, IteratorAggregate, JsonableInterface
{
    /**
     * Les éléments contenus dans la collection.
     *
     * @var array
     */
    protected $items = array();

    /**
     * Créez une nouvelle collection.
     *
     * @param  array  $items
     * @return void
     */
    public function __construct(array $items = array())
    {
        $this->items = $items;
    }

    /**
     * Créez une nouvelle instance de collection si la valeur n’en est pas déjà une.
     *
     * @param  mixed  $items
     * @return \Two\Collection\Collection
     */
    public static function make($items)
    {
        if (is_null($items)) return new static;

        if ($items instanceof Collection) return $items;

        return new static(is_array($items) ? $items : array($items));
    }

    /**
     * Obtenez tous les éléments de la collection.
     *
     * @return array
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * Réduisez les éléments de la collection en un seul tableau.
     *
     * @return \Two\Collection\Collection
     */
    public function collapse()
    {
        $results = array();

        foreach ($this->items as $values) {
            $results = array_merge($results, $values);
        }

        return new static($results);
    }

    /**
     * Différez la collection avec les éléments donnés.
     *
     * @param  \Two\Collection\Collection|\Two\Application\Contracts\ArrayableInterface|array  $items
     * @return \Two\Collection\Collection
     */
    public function diff($items)
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Exécutez un rappel sur chaque élément.
     *
     * @param  Closure  $callback
     * @return \Two\Collection\Collection
     */
    public function each(Closure $callback)
    {
        array_map($callback, $this->items);

        return $this;
    }

    /**
     * Récupère un élément imbriqué de la collection.
     *
     * @param  string  $key
     * @return \Two\Collection\Collection
     */
    public function fetch($key)
    {
        return new static(array_fetch($this->items, $key));
    }

    /**
     * Exécutez un filtre sur chacun des éléments.
     *
     * @param  Closure  $callback
     * @return \Two\Collection\Collection
     */
    public function filter(Closure $callback)
    {
        return new static(array_filter($this->items, $callback));
    }

    /**
     * Filtrez les éléments par la paire clé-valeur donnée.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  bool  $strict
     * @return static
     */
    public function where($key, $value, $strict = true)
    {
        return $this->filter(function ($item) use ($key, $value, $strict)
        {
            return ($strict ? (data_get($item, $key) === $value) : (data_get($item, $key) == $value));
        });
    }

    /**
     * Obtenez le premier élément de la collection.
     *
     * @param  \Closure   $callback
     * @param  mixed      $default
     * @return mixed|null
     */
    public function first(Closure $callback = null, $default = null)
    {
        if (is_null($callback)) {
            return count($this->items) > 0 ? reset($this->items) : null;
        } else {
            return array_first($this->items, $callback, $default);
        }
    }

    /**
     * Obtenez un tableau aplati des éléments de la collection.
     *
     * @return array
     */
    public function flatten()
    {
        return new static(array_flatten($this->items));
    }

    /**
     * Supprimer un élément de la collection par clé.
     *
     * @param  mixed  $key
     * @return void
     */
    public function forget($key)
    {
        unset($this->items[$key]);
    }

    /**
     * Obtenez un élément de la collection par clé.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return value($default);
    }

    /**
     * Regroupez un tableau associatif par un champ ou une valeur de fermeture.
     *
     * @param  callable|string  $groupBy
     * @return \Two\Collection\Collection
     */
    public function groupBy($groupBy)
    {
        $results = array();

        foreach ($this->items as $key => $value) {
            $key = is_callable($groupBy) ? $groupBy($value, $key) : data_get($value, $groupBy);

            $results[$key][] = $value;
        }

        return new static($results);
    }

    /**
     * Déterminez si un élément existe dans la collection par clé.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Concaténer les valeurs d'une clé donnée sous forme de chaîne.
     *
     * @param  string  $value
     * @param  string  $glue
     * @return string
     */
    public function implode($value, $glue = null)
    {
        $first = $this->first();

        if (is_array($first) || is_object($first)) {
            return implode($glue, $this->pluck($value)->all());
        }

        return implode($value, $this->items);
    }

    /**
     * Intersection de la collection avec les éléments donnés.
     *
     * @param  \Two\Collection\Collection|\Two\Application\Contracts\ArrayableInterface|array  $items
     * @return \Two\Collection\Collection
     */
    public function intersect($items)
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Déterminez si la collection est vide ou non.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->items);
    }

    /**
    * Obtenez le dernier élément de la collection.
    *
    * @return mixed|null
    */
    public function last()
    {
        return count($this->items) > 0 ? end($this->items) : null;
    }

    /**
     * Obtenez un tableau avec les valeurs d'une clé donnée.
     *
     * @param  string  $value
     * @param  string  $key
     * @return array
     */
    public function lists($value, $key = null)
    {
        return array_pluck($this->items, $value, $key);
    }

    /**
     * Exécutez une carte sur chacun des éléments.
     *
     * @param  Closure  $callback
     * @return \Two\Collection\Collection
     */
    public function map(Closure $callback)
    {
        return new static(array_map($callback, $this->items, array_keys($this->items)));
    }

    /**
     * Exécutez une carte de regroupement sur les éléments.
     *
     * Le rappel doit renvoyer un tableau associatif avec une seule paire clé/valeur.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapToGroups(callable $callback)
    {
        $groups = $this->map($callback)->reduce(function ($groups, $pair) {
            $groups[key($pair)][] = reset($pair);

            return $groups;

        }, array());

        return with(new static($groups))->map(array($this, 'make'));
    }

    /**
     * Exécutez une carte associative sur chacun des éléments.
     *
     * Le rappel doit renvoyer un tableau associatif avec une seule paire clé/valeur.
     *
     * @param  callable  $callback
     * @return static
     */
    public function mapWithKeys(callable $callback)
    {
        $result = array();

        foreach ($this->items as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return new static($result);
    }

    /**
     * Fusionnez la collection avec les éléments donnés.
     *
     * @param  \Two\Collection\Collection|\Two\Application\Contracts\ArrayableInterface|array  $items
     * @return \Two\Collection\Collection
     */
    public function merge($items)
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Obtenez et supprimez le dernier élément de la collection.
     *
     * @return mixed|null
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * Poussez un élément au début de la collection.
     *
     * @param  mixed  $value
     * @return void
     */
    public function prepend($value)
    {
        array_unshift($this->items, $value);
    }

    /**
     * Poussez un élément à la fin de la collection.
     *
     * @param  mixed  $value
     * @return void
     */
    public function push($value)
    {
        $this->items[] = $value;
    }

    /**
     * Extrait un élément de la collection.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function pull($key, $default = null)
    {
        return array_pull($this->items, $key, $default);
    }

    /**
     * Mettez un élément dans la collection par clé.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function put($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * Réduisez la collection à une seule valeur.
     *
     * @param  callable  $callback
     * @param  mixed  $initial
     * @return mixed
     */
    public function reduce($callback, $initial = null)
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Obtenez un ou plusieurs objets au hasard de la collection.
     *
     * @param  int $amount
     * @return mixed
     */
    public function random($amount = 1)
    {
        $keys = array_rand($this->items, $amount);

        return is_array($keys) ? array_intersect_key($this->items, array_flip($keys)) : $this->items[$keys];
    }

    /**
     * Inverser l’ordre des articles.
     *
     * @return \Two\Collection\Collection
     */
    public function reverse()
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Obtenez et supprimez le premier élément de la collection.
     *
     * @return mixed|null
     */
    public function shift()
    {
        return array_shift($this->items);
    }

    /**
     * Découpez le tableau de collection sous-jacent.
     *
     * @param  int   $offset
     * @param  int   $length
     * @param  bool  $preserveKeys
     * @return \Two\Collection\Collection
     */
    public function slice($offset, $length = null, $preserveKeys = false)
    {
        return new static(array_slice($this->items, $offset, $length, $preserveKeys));
    }

    /**
     * Découpez le tableau de collection sous-jacent.
     *
     * @param  int $size
     * @param  bool  $preserveKeys
     * @return \Two\Collection\Collection
     */
    public function chunk($size, $preserveKeys = false)
    {
        $chunks = new static;

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk)
        {
            $chunks->push(new static($chunk));
        }

        return $chunks;
    }

    /**
     * Triez chaque élément avec un rappel.
     *
     * @param  Closure  $callback
     * @return \Two\Collection\Collection
     */
    public function sort(Closure $callback)
    {
        uasort($this->items, $callback);

        return $this;
    }

    /**
     * Triez la collection en utilisant la fermeture donnée.
     *
     * @param  \Closure|string  $callback
     * @param  int              $options
     * @param  bool             $descending
     * @return \Two\Collection\Collection
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $results = array();

        if (is_string($callback)) {
            $callback = $this->valueRetriever($callback);
        }

        // Nous allons d’abord parcourir les éléments et obtenir le comparateur à partir d’un rappel
        // fonction qui nous a été donnée. Ensuite, nous trierons les valeurs renvoyées et
        // et récupérez les valeurs correspondantes pour les clés triées de ce tableau.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        // Une fois que nous avons trié toutes les clés du tableau, nous les parcourrons
        // et récupérons le modèle correspondant afin que nous puissions définir la liste des éléments sous-jacents
        // vers la version triée. Ensuite, nous retournerons simplement l'instance de collection.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        $this->items = $results;

        return $this;
    }

    /**
     * Triez la collection par ordre décroissant en utilisant la fermeture donnée.
     *
     * @param  \Closure|string  $callback
     * @param  int              $options
     * @return \Two\Collection\Collection
     */
    public function sortByDesc($callback, $options = SORT_REGULAR)
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Partie d'épissage du tableau de collection sous-jacent.
     *
     * @param  int    $offset
     * @param  int    $length
     * @param  mixed  $replacement
     * @return \Two\Collection\Collection
     */
    public function splice($offset, $length = 0, $replacement = array())
    {
        return new static(array_splice($this->items, $offset, $length, $replacement));
    }

    /**
     * Obtenez la somme des valeurs données.
     *
     * @param  \Closure  $callback
     * @param  string  $callback
     * @return mixed
     */
    public function sum($callback)
    {
        if (is_string($callback)) {
            $callback = $this->valueRetriever($callback);
        }

        return $this->reduce(function($result, $item) use ($callback) {
            return $result += $callback($item);
        }, 0);
    }

    /**
     * Prenez le premier ou le dernier {$limit} éléments.
     *
     * @param  int  $limit
     * @return \Two\Collection\Collection
     */
    public function take($limit = null)
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * Transformez chaque élément de la collection à l'aide d'un rappel.
     *
     * @param  Closure  $callback
     * @return \Two\Collection\Collection
     */
    public function transform(Closure $callback)
    {
        $this->items = array_map($callback, $this->items);

        return $this;
    }

    /**
     * Obtenez les valeurs d'une clé donnée.
     *
     * @param  string|array  $value
     * @param  string|null  $key
     * @return static
     */
    public function pluck($value, $key = null)
    {
        return with(new static(Arr::pluck($this->items, $value, $key)))->first();
    }

    /**
     * Renvoie uniquement les éléments uniques du tableau de collection.
     *
     * @return \Two\Collection\Collection
     */
    public function unique()
    {
        return new static(array_unique($this->items));
    }

    /**
     * Récupérez les clés des objets de la collection.
     *
     * @return static
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * Réinitialisez les clés du tableau sous-jacent.
     *
     * @return \Two\Collection\Collection
     */
    public function values()
    {
        $this->items = array_values($this->items);

        return $this;
    }

    /**
     * Obtenez un rappel de récupération de valeur.
     *
     * @param  string  $value
     * @return \Closure
     */
    protected function valueRetriever($value)
    {
        return function($item) use ($value)
        {
            return data_get($item, $value);
        };
    }

    /**
     * Obtenez la collection d’éléments sous forme de tableau simple.
     *
     * @return array
     */
    public function toArray()
    {
        return array_map(function($value)
        {
            return ($value instanceof ArrayableInterface) ? $value->toArray() : $value;
        }, $this->items);
    }

    /**
     * Obtenez la collection d’éléments au format JSON.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        $items = array_map(function ($value)
        {
            if ($value instanceof JsonableInterface) {
                return json_decode($value->toJson(), true);
            } elseif ($value instanceof ArrayableInterface) {
                return $value->toArray();
            } else {
                return $value;
            }
        }, $this->items);

        return json_encode($items, $options);
    }

    /**
     * Obtenez un itérateur pour les éléments.
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Obtenez une instance de CachingIterator.
     *
     * @return \CachingIterator
     */
    public function getCachingIterator($flags = CachingIterator::CALL_TOSTRING)
    {
        return new CachingIterator($this->getIterator(), $flags);
    }

    /**
     * Comptez le nombre d'éléments dans la collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Déterminez si un élément existe avec un décalage.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Obtenez un élément à un décalage donné.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    /**
     * Définissez l'élément à un décalage donné.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value): void
    {
        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Désactivez l'élément à un décalage donné.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    /**
     * Convertissez la collection en sa représentation sous forme de chaîne.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Tableau de résultats d’éléments de Collection ou ArrayableInterface.
     *
     * @param  \Two\Collection\Collection|\Two\Application\Contracts\ArrayableInterface|array  $items
     * @return array
     */
    private function getArrayableItems($items)
    {
        if (is_array($items)) {
            return $items;
        } else if ($items instanceof Collection) {
            return $items->all();
        } else if ($items instanceof ArrayableInterface) {
            return $items->toArray();
        }

        return (array) $items;
    }

}
