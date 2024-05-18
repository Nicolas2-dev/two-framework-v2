<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Cache\Datanase;

use Exception;
use LogicException;

use Two\Database\Connection;
use Two\Encryption\Encrypter;
use Two\Cache\Contracts\StoreInterface;


class DatabaseStore implements StoreInterface
{
    /**
     * L'instance de connexion à la base de données.
     *
     * @var \Two\Database\Connection
     */
    protected $connection;

    /**
     * L'instance du chiffreur.
     *
     * @var \Two\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * Le nom de la table de cache.
     *
     * @var string
     */
    protected $table;

    /**
     * Une chaîne qui doit être ajoutée aux clés.
     *
     * @var string
     */
    protected $prefix;


    /**
     * Créez un nouveau magasin de base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @param  \Encryption\Encrypter  $encrypter
     * @param  string  $table
     * @param  string  $prefix
     * @return void
     */
    public function __construct(Connection $connection, Encrypter $encrypter, $table, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        $this->encrypter = $encrypter;
        $this->connection = $connection;
    }

    /**
     * Récupérer un élément du cache par clé.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        $prefixed = $this->prefix.$key;

        $cache = $this->table()->where('key', '=', $prefixed)->first();

        // Si nous avons un enregistrement de cache, nous vérifierons le délai d'expiration par rapport au courant
        // temps sur le système et voir si l'enregistrement a expiré. Si c'est le cas, nous le ferons
        // supprime les enregistrements de la table de base de données afin qu'ils ne soient plus renvoyés.
        if (! is_null($cache)) {
            if (is_array($cache)) $cache = (object) $cache;

            if (time() >= $cache->expiration) {
                $this->forget($key);

                return null;
            }

            return $this->encrypter->decrypt($cache->value);
        }
    }

    /**
     * Stockez un élément dans le cache pendant un nombre de minutes donné.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  int     $minutes
     * @return void
     */
    public function put($key, $value, $minutes)
    {
        $key = $this->prefix.$key;

        // Toutes les valeurs mises en cache dans la base de données sont cryptées au cas où cela serait utilisé
        // en tant que magasin de données de session par le consommateur. Nous calculerons également l'expiration
        // heure et place-le sur la table afin que nous le vérifiions lors de notre récupération.
        $value = $this->encrypter->encrypt($value);

        $expiration = $this->getTime() + ($minutes * 60);

        try
        {
            $this->table()->insert(compact('key', 'value', 'expiration'));
        } catch (Exception $e) {
            $this->table()->where('key', '=', $key)->update(compact('value', 'expiration'));
        }
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     *
     * @throws \LogicException
     */
    public function increment($key, $value = 1)
    {
        throw new LogicException("Increment operations not supported by this driver.");
    }

    /**
     * Incrémente la valeur d'un élément dans le cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     *
     * @throws \LogicException
     */
    public function decrement($key, $value = 1)
    {
        throw new LogicException("Decrement operations not supported by this driver.");
    }

    /**
     * Obtenez l'heure actuelle du système.
     *
     * @return int
     */
    protected function getTime()
    {
        return time();
    }

    /**
     * Stockez un élément dans le cache indéfiniment.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        return $this->put($key, $value, 5256000);
    }

    /**
     * Supprimer un élément du cache.
     *
     * @param  string  $key
     * @return void
     */
    public function forget($key)
    {
        $this->table()->where('key', '=', $this->prefix.$key)->delete();
    }

    /**
     * Supprimez tous les éléments du cache.
     *
     * @return void
     */
    public function flush()
    {
        $this->table()->delete();
    }

    /**
     * Obtenez un générateur de requêtes pour la table de cache.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function table()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Obtenez la connexion à la base de données sous-jacente.
     *
     * @return \Two\Database\Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Obtenez l'instance de chiffrement.
     *
     * @return \Two\Encryption\Encrypter
     */
    public function getEncrypter()
    {
        return $this->encrypter;
    }

    /**
     * Obtenez le préfixe de la clé de cache.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

}
