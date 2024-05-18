<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Tag;

use Two\Cache\Tag\TagSet;
use Two\Cache\Tag\TaggedCache;


abstract class TaggableStore
{

    /**
     * Commencez à exécuter une nouvelle opération de balises.
     *
     * @param  string  $name
     * @return \Two\Cache\Tag\TaggedCache
     */
    public function section($name)
    {
        return $this->tags($name);
    }

    /**
     * Commencez à exécuter une nouvelle opération de balises.
     *
     * @param  array|mixed  $names
     * @return \Two\Cache\Tag\TaggedCache
     */
    public function tags($names)
    {
        $names = is_array($names) ? $names : func_get_args();

        return new TaggedCache($this, new TagSet($this, $names));
    }
}
