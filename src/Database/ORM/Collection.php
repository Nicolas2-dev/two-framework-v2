<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database\ORM;

use Two\Collection\Collection as BaseCollection;


class Collection extends BaseCollection
{
    /**
     * Rechercher un modèle dans la collection par clé.
     *
     * @param  mixed  $key
     * @param  mixed  $default
     * @return \Two\Database\ORM\Model
     */
    public function find($key, $default = null)
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        return array_first($this->items, function($itemKey, $model) use ($key)
        {
            return $model->getKey() == $key;

        }, $default);
    }

    /**
     * Chargez un ensemble de relations dans la collection.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function load($relations)
    {
        if (count($this->items) > 0) {
            if (is_string($relations)) $relations = func_get_args();

            $query = $this->first()->newQuery()->with($relations);

            $this->items = $query->eagerLoadRelations($this->items);
        }

        return $this;
    }

    /**
     * Ajoutez un élément à la collection.
     *
     * @param  mixed  $item
     * @return $this
     */
    public function add($item)
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Déterminez si une clé existe dans la collection.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function contains($key)
    {
        return ! is_null($this->find($key));
    }

    /**
     * Récupère un élément imbriqué de la collection.
     *
     * @param  string  $key
     * @return static
     */
    public function fetch($key)
    {
        return new static(array_fetch($this->toArray(), $key));
    }

    /**
     * Obtenez la valeur maximale d'une clé donnée.
     *
     * @param  string  $key
     * @return mixed
     */
    public function max($key)
    {
        return $this->reduce(function($result, $item) use ($key)
        {
            return (is_null($result) || $item->{$key} > $result) ? $item->{$key} : $result;
        });
    }

    /**
     * Obtenez la valeur minimale d'une clé donnée.
     *
     * @param  string  $key
     * @return mixed
     */
    public function min($key)
    {
        return $this->reduce(function($result, $item) use ($key)
        {
            return (is_null($result) || $item->{$key} < $result) ? $item->{$key} : $result;
        });
    }

    /**
     * Obtenez le tableau de clés primaires
     *
     * @return array
     */
    public function modelKeys()
    {
        return array_map(function($m) { return $m->getKey(); }, $this->items);
    }

    /**
     * Fusionnez la collection avec les éléments donnés.
     *
     * @param  \ArrayAccess|array  $items
     * @return static
     */
    public function merge($items)
    {
        $dictionary = $this->getDictionary();

        foreach ($items as $item) {
            $dictionary[$item->getKey()] = $item;
        }

        return new static(array_values($dictionary));
    }

    /**
     * Différez la collection avec les éléments donnés.
     *
     * @param  \ArrayAccess|array  $items
     * @return static
     */
    public function diff($items)
    {
        $diff = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (! isset($dictionary[$item->getKey()])) {
                $diff->add($item);
            }
        }

        return $diff;
    }

    /**
     * Intersection de la collection avec les éléments donnés.
     *
     * @param  \ArrayAccess|array  $items
     * @return static
     */
    public function intersect($items)
    {
        $intersect = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (isset($dictionary[$item->getKey()])) {
                $intersect->add($item);
            }
        }

        return $intersect;
    }

    /**
     * Renvoyez uniquement les éléments uniques de la collection.
     *
     * @return static
     */
    public function unique()
    {
        $dictionary = $this->getDictionary();

        return new static(array_values($dictionary));
    }

    /**
     * Renvoie uniquement les modèles de la collection avec les clés spécifiées.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function only($keys)
    {
        $dictionary = array_only($this->getDictionary(), $keys);

        return new static(array_values($dictionary));
    }

    /**
     * Renvoie tous les modèles de la collection à l'exception des modèles avec les clés spécifiées.
     *
     * @param  mixed  $keys
     * @return static
     */
    public function except($keys)
    {
        $dictionary = array_except($this->getDictionary(), $keys);

        return new static(array_values($dictionary));
    }

    /**
     * Obtenez un dictionnaire saisi par clés primaires.
     *
     * @param  \ArrayAccess|array  $items
     * @return array
     */
    public function getDictionary($items = null)
    {
        $items = is_null($items) ? $this->items : $items;

        $dictionary = array();

        foreach ($items as $value) {
            $dictionary[$value->getKey()] = $value;
        }

        return $dictionary;
    }

    /**
     * Obtenez un tableau avec les valeurs d'une clé donnée.
     *
     * @param  string  $value
     * @param  string|null  $key
     * @return \Two\Collection\Collection
     */
    public function pluck($value, $key = null)
    {
        return $this->toBase()->pluck($value, $key);
    }

    /**
     * Obtenez une instance de collection Support de base à partir de cette collection.
     *
     * @return \Two\Collection\Collection
     */
    public function toBase()
    {
        return new BaseCollection($this->items);
    }

}
