<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Tag;

use Two\Cache\Contracts\StoreInterface;


class TagSet
{
    /**
     * L’implémentation du cache store.
     *
     * @var \Two\Cache\Contracts\StoreInterface
     */
    protected $store;

    /**
     * Les noms de balises.
     *
     * @var array
     */
    protected $names = array();


    /**
     * Créez une nouvelle instance TagSet.
     *
     * @param  \Two\Cache\Contracts\StoreInterface  $store
     * @param  array  $names
     * @return void
     */
    public function __construct(StoreInterface $store, array $names = array())
    {
        $this->store = $store;
        $this->names = $names;
    }

    /**
     * Réinitialisez toutes les balises de l’ensemble.
     *
     * @return void
     */
    public function reset()
    {
        array_walk($this->names, array($this, 'resetTag'));
    }

    /**
     * Obtenez l'identifiant de balise unique pour une balise donnée.
     *
     * @param  string  $name
     * @return string
     */
    public function tagId($name)
    {
        return $this->store->get($this->tagKey($name)) ?: $this->resetTag($name);
    }

    /**
     * Obtenez un tableau d’identifiants de balises pour toutes les balises de l’ensemble.
     *
     * @return array
     */
    protected function tagIds()
    {
        return array_map(array($this, 'tagId'), $this->names);
    }

    /**
     * Obtenez un espace de noms unique qui change lorsque l'une des balises est vidée.
     *
     * @return string
     */
    public function getNamespace()
    {
        return implode('|', $this->tagIds());
    }

    /**
     * Réinitialiser la balise et renvoyer le nouvel identifiant de balise
     *
     * @param  string  $name
     * @return string
     */
    public function resetTag($name)
    {
        $this->store->forever($this->tagKey($name), $id = str_replace('.', '', uniqid('', true)));

        return $id;
    }

    /**
     * Obtenez la clé d’identification de balise pour une balise donnée.
     *
     * @param  string  $name
     * @return string
     */
    public function tagKey($name)
    {
        return 'tag:' .$name .':key';
    }

}
