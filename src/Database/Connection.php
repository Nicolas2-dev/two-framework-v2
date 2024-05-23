<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Database;

use PDO;
use Closure;
use DateTime;
use LogicException;

use Two\Events\Dispatcher;
use Two\Database\Query\Processor;
use Two\Database\Contracts\ConnectionInterface;

use Doctrine\DBAL\Connection as DoctrineConnection;


class Connection implements ConnectionInterface
{
    /**
     * La connexion PDO active.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * La connexion PDO active utilisée pour les lectures.
     *
     * @var PDO
     */
    protected $readPdo;

    /**
     * Instance de reconnecteur pour la connexion.
     *
     * @var callable
     */
    protected $reconnector;

    /**
     * L'implémentation de la grammaire des requêtes.
     *
     * @var \Two\Database\Query\Grammar
     */
    protected $queryGrammar;

    /**
     * L’implémentation de la grammaire du schéma.
     *
     * @var \Two\Database\Schema\Grammar
     */
    protected $schemaGrammar;

    /**
     * Implémentation du post-processeur de requête.
     *
     * @var \Two\Database\Query\Processor
     */
    protected $postProcessor;

    /**
     * L'instance du répartiteur d'événements.
     *
     * @var \Two\Events\Dispatcher
     */
    protected $events;

    /**
     * L'instance du gestionnaire de cache.
     *
     * @var \Two\Cache\CacheManager
     */
    protected $cache;

    /**
     * Le mode de récupération par défaut de la connexion.
     *
     * @var int
     */
    protected $fetchMode = PDO::FETCH_ASSOC;

    /**
     * Le nombre de transactions actives.
     *
     * @var int
     */
    protected $transactions = 0;

    /**
     * Toutes les requêtes sont exécutées sur la connexion.
     *
     * @var array
     */
    protected $queryLog = array();

    /**
     * Indique si les requêtes sont enregistrées.
     *
     * @var bool
     */
    protected $loggingQueries = true;

    /**
     * Indique si la connexion est en « essai à sec ».
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * Le nom de la base de données connectée.
     *
     * @var string
     */
    protected $database;

    /**
     * Le préfixe de table pour la connexion.
     *
     * @var string
     */
    protected $tablePrefix = '';

    /**
     * Les options de configuration de la connexion à la base de données.
     *
     * @var array
     */
    protected $config = array();

    /**
     * Créez une nouvelle instance de connexion à la base de données.
     *
     * @param  \PDO     $pdo
     * @param  string   $database
     * @param  string   $tablePrefix
     * @param  array    $config
     * @return void
     */
    public function __construct(PDO $pdo, $database = '', $tablePrefix = '', array $config = array())
    {
        $this->pdo = $pdo;

        //
        $this->database = $database;

        $this->tablePrefix = $tablePrefix;

        $this->config = $config;

        //
        $this->useDefaultQueryGrammar();

        $this->useDefaultPostProcessor();
    }

    /**
     * Définissez la grammaire de la requête sur l'implémentation par défaut.
     *
     * @return void
     */
    public function useDefaultQueryGrammar()
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Obtenez l'instance de grammaire de requête par défaut.
     *
     * @return \Two\Database\Query\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar;
    }

    /**
     * Définissez la grammaire du schéma sur l'implémentation par défaut.
     *
     * @return void
     */
    public function useDefaultSchemaGrammar()
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Obtenez l'instance de grammaire de schéma par défaut.
     *
     * @return \Two\Database\Schema\Grammar
     */
    protected function getDefaultSchemaGrammar() {}

    /**
     * Définissez le post-processeur de requête sur l’implémentation par défaut.
     *
     * @return void
     */
    public function useDefaultPostProcessor()
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Obtenez l'instance de post-processeur par défaut.
     *
     * @return \Two\Database\Query\Processor
     */
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor;
    }

    /**
     * Obtenez une instance de générateur de schéma pour la connexion.
     *
     * @return \Two\Database\Schema\Builder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Schema\Builder($this);
    }

    /**
     * Commencez une requête fluide sur une table de base de données.
     *
     * @param  string  $table
     * @return \Two\Database\Query\Builder
     */
    public function table($table)
    {
        $processor = $this->getPostProcessor();

        $query = new Query\Builder($this, $this->getQueryGrammar(), $processor);

        return $query->from($table);
    }

    /**
     * Obtenez une nouvelle expression de requête brute.
     *
     * @param  mixed  $value
     * @return \Two\Database\Query\Expression
     */
    public function raw($value)
    {
        return new Query\Expression($value);
    }

    /**
     * Exécutez une instruction select et renvoyez un seul résultat.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return mixed
     */
    public function selectOne($query, $bindings = array())
    {
        $records = $this->select($query, $bindings);

        return count($records) > 0 ? reset($records) : null;
    }

    /**
     * Exécutez une instruction select sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return array
     */
    public function selectFromWriteConnection($query, $bindings = array())
    {
        return $this->select($query, $bindings, false);
    }

    /**
     * Exécutez une instruction select sur la base de données.
     *
     * @param  string  $query
     * @param  array  $bindings
     * @param  bool  $useReadPdo
     * @return array
     */
    public function select($query, $bindings = array(), $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings) use ($useReadPdo)
        {
            if ($me->pretending()) return array();

            $bindings = $me->prepareBindings($bindings);

            //
            $statement = $me->createPdoStatement($query, $this->getPdoForSelect($useReadPdo));

            $statement->execute($bindings);

            return $statement->fetchAll($me->getFetchMode());
        });
    }

    /**
     * Analysez et encapsulez les variables de la requête, puis renvoyez une instance d'instruction PDO préparée.
     *
     * @param  string  $query
     * @param  \PDO  $pdo
     * @return \PDOStatement
     */
    protected function createPdoStatement($query, $pdo = null)
    {
        if (is_null($pdo)) {
            $pdo = $this->getPdo();
        }

        $grammar = $this->getQueryGrammar();

        $query = preg_replace_callback('#\{(.*?)\}#', function ($matches) use ($grammar)
        {
            $value = $matches[1];

            return $grammar->wrap($value);

        }, $query);

        return $pdo->prepare($query);
    }

    /**
     * Obtenez la connexion PDO à utiliser pour une requête de sélection.
     *
     * @param  bool  $useReadPdo
     * @return \PDO
     */
    protected function getPdoForSelect($useReadPdo = true)
    {
        return $useReadPdo ? $this->getReadPdo() : $this->getPdo();
    }

    /**
     * Exécutez une instruction insert sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function insert($query, $bindings = array())
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Exécutez une instruction de mise à jour sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function update($query, $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Exécutez une instruction delete sur la base de données.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function delete($query, $bindings = array())
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Exécutez une instruction SQL et renvoyez le résultat booléen.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return bool
     */
    public function statement($query, $bindings = array())
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings)
        {
            if ($me->pretending()) return true;

            $bindings = $me->prepareBindings($bindings);

            //
            $statement = $me->createPdoStatement($query);

            return $statement->execute($bindings);
        });
    }

    /**
     * Exécutez une instruction SQL et obtenez le nombre de lignes affectées.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @return int
     */
    public function affectingStatement($query, $bindings = array())
    {
        return $this->run($query, $bindings, function ($me, $query, $bindings)
        {
            if ($me->pretending()) return 0;

            $bindings = $me->prepareBindings($bindings);

            //
            $statement = $me->createPdoStatement($query);

            $statement->execute($bindings);

            return $statement->rowCount();
        });
    }

    /**
     * Exécutez une requête brute et non préparée sur la connexion PDO.
     *
     * @param  string  $query
     * @return bool
     */
    public function unprepared($query)
    {
        return $this->run($query, array(), function ($me, $query)
        {
            if ($me->pretending()) return true;

            $result = $me->getPdo()->exec($query);

            return (bool) $result;
        });
    }

    /**
     * Préparez les liaisons de requête pour l’exécution.
     *
     * @param  array  $bindings
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTime) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            }

            // La valeur n'est pas une instance DateTime.
            else if ($value === false) {
                $bindings[$key] = 0;
            }
        }

        return $bindings;
    }

    /**
     * Exécuter une clôture dans une transaction.
     *
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction(Closure $callback)
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);

            $this->commit();
        }
        catch (\Exception $e) {
            $this->rollBack();

           throw $e;
        }
        catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        return $result;
    }

    /**
     * Démarrez une nouvelle transaction de base de données.
     *
     * @return void
     */
    public function beginTransaction()
    {
        ++$this->transactions;

        if ($this->transactions == 1) {
            $this->pdo->beginTransaction();
        }

        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Validez la transaction de base de données active.
     *
     * @return void
     */
    public function commit()
    {
        if ($this->transactions == 1) $this->pdo->commit();

        --$this->transactions;

        $this->fireConnectionEvent('committed');
    }

    /**
     * Annulez la transaction de base de données active.
     *
     * @return void
     */
    public function rollBack()
    {
        if ($this->transactions == 1) {
            $this->transactions = 0;

            $this->pdo->rollBack();
        } else {
            --$this->transactions;
        }

        $this->fireConnectionEvent('rollingBack');
    }

    /**
     * Obtenez le nombre de transactions actives.
     *
     * @return int
     */
    public function transactionLevel()
    {
        return $this->transactions;
    }

    /**
     * Exécutez le rappel donné en mode « exécution à sec ».
     *
     * @param  \Closure  $callback
     * @return array
     */
    public function pretend(Closure $callback)
    {
        $this->pretending = true;

        $this->queryLog = array();

        //
        $callback($this);

        $this->pretending = false;

        return $this->queryLog;
    }

    /**
     * Exécutez une instruction SQL et enregistrez son contexte d'exécution.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Database\QueryException
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $this->reconnectIfMissingConnection();

        $start = microtime(true);

        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        }
        catch (QueryException $e) {
            $result = $this->tryAgainIfCausedByLostConnection(
                $e, $query, $bindings, $callback
            );
        }

        $time = $this->getElapsedTime($start);

        $this->logQuery($query, $bindings, $time);

        return $result;
    }

    /**
     * Exécutez une instruction SQL.
     *
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Database\QueryException
     */
    protected function runQueryCallback($query, $bindings, Closure $callback)
    {
        try {
            $result = $callback($this, $query, $bindings);
        }
        catch (\Exception $e) {
            throw new QueryException(
                $query, $this->prepareBindings($bindings), $e
            );
        }

        return $result;
    }

    /**
     * Gérez une exception de requête survenue lors de l’exécution de la requête.
     *
     * @param  \Two\Database\QueryException  $e
     * @param  string    $query
     * @param  array     $bindings
     * @param  \Closure  $callback
     * @return mixed
     *
     * @throws \Database\QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, Closure $callback)
    {
        if ($this->causedByLostConnection($e)) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Déterminez si l’exception donnée a été causée par une perte de connexion.
     *
     * @param  \Two\Database\QueryException
     * @return bool
     */
    protected function causedByLostConnection(QueryException $e)
    {
        return str_contains($e->getMessage(), array(
            'server has gone away',
            'no connection to the server',
            'Lost connection',
            'is dead or not enabled',
            'Error while sending',
            'decryption failed or bad record mac',
            'SSL connection has been closed unexpectedly',
            'Error writing data to the connection',
            'Resource deadlock avoided',
            'Transaction() on null',
            'child connection forced to terminate due to client_idle_limit',
        ));
    }

    /**
     * Déconnectez-vous de la connexion PDO sous-jacente.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->setPdo(null)->setReadPdo(null);
    }

    /**
     * Reconnectez-vous à la base de données.
     *
     * @return void
     *
     * @throws \LogicException
     */
    public function reconnect()
    {
        if (is_callable($this->reconnector)) {
            return call_user_func($this->reconnector, $this);
        }

        throw new LogicException("Lost connection and no reconnector available.");
    }

    /**
     * Reconnectez-vous à la base de données si une connexion PDO est manquante.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->getPdo()) || is_null($this->getReadPdo())) {
            $this->reconnect();
        }
    }

    /**
     * Enregistrez une requête dans le journal des requêtes de la connexion.
     *
     * @param  string  $query
     * @param  array   $bindings
     * @param  float|null  $time
     * @return void
     */
    public function logQuery($query, $bindings, $time = null)
    {
        if (isset($this->events)) {
            $this->events->dispatch('Two.query', array($query, $bindings, $time, $this->getName()));
        }

        if (! $this->loggingQueries) return;

        $this->queryLog[] = compact('query', 'bindings', 'time');
    }

    /**
     * Enregistrez un écouteur de requêtes de base de données avec la connexion.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function listen(Closure $callback)
    {
        if (isset($this->events)) {
            $this->events->listen('Two.query', $callback);
        }
    }

    /**
     * Déclenchez un événement pour cette connexion.
     *
     * @param  string  $event
     * @return void
     */
    protected function fireConnectionEvent($event)
    {
        if (isset($this->events)) {
            $this->events->dispatch('connection.'.$this->getName().'.'.$event, $this);
        }
    }

    /**
     * Obtenez le temps écoulé depuis un point de départ donné.
     *
     * @param  int    $start
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Obtenez une instance de colonne de schéma de doctrine.
     *
     * @param  string  $table
     * @param  string  $column
     * @return \Doctrine\DBAL\Schema\Column
     */
    public function getDoctrineColumn($table, $column)
    {
        $schema = $this->getDoctrineSchemaManager();

        return $schema->listTableDetails($table)->getColumn($column);
    }

    /**
     * Obtenez le gestionnaire de schéma Doctrine DBAL pour la connexion.
     *
     * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
     */
    public function getDoctrineSchemaManager()
    {
        return $this->getDoctrineDriver()->getSchemaManager($this->getDoctrineConnection());
    }

    /**
     * Obtenez l’instance de connexion à la base de données Doctrine DBAL.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDoctrineConnection()
    {
        $driver = $this->getDoctrineDriver();

        $data = array('pdo' => $this->pdo, 'dbname' => $this->getConfig('database'));

        return new DoctrineConnection($data, $driver);
    }

    /**
     * Obtenez la connexion PDO actuelle.
     *
     * @return \PDO
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Obtenez la connexion PDO actuelle utilisée pour la lecture.
     *
     * @return \PDO
     */
    public function getReadPdo()
    {
        if ($this->transactions >= 1) return $this->getPdo();

        return $this->readPdo ?: $this->pdo;
    }

    /**
     * Définissez la connexion PDO.
     *
     * @param  \PDO|null  $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        if ($this->transactions >= 1) {
            throw new \RuntimeException("Can't swap PDO instance while within transaction.");
        }

        $this->pdo = $pdo;

        return $this;
    }

    /**
     * Définissez la connexion PDO utilisée pour la lecture.
     *
     * @param  \PDO|null  $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        $this->readPdo = $pdo;

        return $this;
    }

    /**
     * Définissez l'instance de reconnexion sur la connexion.
     *
     * @param  callable  $reconnector
     * @return $this
     */
    public function setReconnector(callable $reconnector)
    {
        $this->reconnector = $reconnector;

        return $this;
    }

    /**
     * Obtenez le nom de connexion à la base de données.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->getConfig('name');
    }

    /**
     * Obtenez une option parmi les options de configuration.
     *
     * @param  string  $option
     * @return mixed
     */
    public function getConfig($option)
    {
        return array_get($this->config, $option);
    }

    /**
     * Obtenez le nom du pilote PDO.
     *
     * @return string
     */
    public function getDriverName()
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Obtenez la grammaire de requête utilisée par la connexion.
     *
     * @return \Two\Database\Query\Grammar
     */
    public function getQueryGrammar()
    {
        return $this->queryGrammar;
    }

    /**
     * Définissez la grammaire de requête utilisée par la connexion.
     *
     * @param  \Two\Database\Query\Grammar
     * @return void
     */
    public function setQueryGrammar(Query\Grammar $grammar)
    {
        $this->queryGrammar = $grammar;
    }

    /**
     * Obtenez la grammaire du schéma utilisée par la connexion.
     *
     * @return \Two\Database\Query\Grammar
     */
    public function getSchemaGrammar()
    {
        return $this->schemaGrammar;
    }

    /**
     * Définissez la grammaire du schéma utilisée par la connexion.
     *
     * @param  \Two\Database\Schema\Grammar
     * @return void
     */
    public function setSchemaGrammar(Schema\Grammar $grammar)
    {
        $this->schemaGrammar = $grammar;
    }

    /**
     * Obtenez le post-processeur de requête utilisé par la connexion.
     *
     * @return \Two\Database\Query\Processor
     */
    public function getPostProcessor()
    {
        return $this->postProcessor;
    }

    /**
     * Définissez le post-processeur de requête utilisé par la connexion.
     *
     * @param  \Two\Database\Query\Processor
     * @return void
     */
    public function setPostProcessor(Processor $processor)
    {
        $this->postProcessor = $processor;
    }

    /**
     * Obtenez le répartiteur d'événements utilisé par la connexion.
     *
     * @return \Two\Events\Dispatcher
     */
    public function getEventDispatcher()
    {
        return $this->events;
    }

    /**
     * Définissez l’instance du répartiteur d’événements sur la connexion.
     *
     * @param  \Two\Events\Dispatcher
     * @return void
     */
    public function setEventDispatcher(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Obtenez l'instance du gestionnaire de cache.
     *
     * @return \Two\Cache\CacheManager
     */
    public function getCacheManager()
    {
        if ($this->cache instanceof Closure) {
            $this->cache = call_user_func($this->cache);
        }

        return $this->cache;
    }

    /**
     * Définissez l'instance du gestionnaire de cache sur la connexion.
     *
     * @param  \Two\Cache\CacheManager|\Closure  $cache
     * @return void
     */
    public function setCacheManager($cache)
    {
        $this->cache = $cache;
    }

    /**
     * Déterminez si la connexion est en "essai à sec".
     *
     * @return bool
     */
    public function pretending()
    {
        return $this->pretending === true;
    }

    /**
     * Obtenez le mode de récupération par défaut pour la connexion.
     *
     * @return int
     */
    public function getFetchMode()
    {
        return $this->fetchMode;
    }

    /**
     * Définissez le mode de récupération par défaut pour la connexion.
     *
     * @param  int  $fetchMode
     * @return int
     */
    public function setFetchMode($fetchMode)
    {
        $this->fetchMode = $fetchMode;
    }

    /**
     * Obtenez le journal des requêtes de connexion.
     *
     * @return array
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Effacez le journal des requêtes.
     *
     * @return void
     */
    public function flushQueryLog()
    {
        $this->queryLog = array();
    }

    /**
     * Activez le journal des requêtes sur la connexion.
     *
     * @return void
     */
    public function enableQueryLog()
    {
        $this->loggingQueries = true;
    }

    /**
     * Désactivez le journal des requêtes sur la connexion.
     *
     * @return void
     */
    public function disableQueryLog()
    {
        $this->loggingQueries = false;
    }

    /**
     * Déterminez si nous enregistrons les requêtes.
     *
     * @return bool
     */
    public function logging()
    {
        return $this->loggingQueries;
    }

    /**
     * Obtenez le nom de la base de données connectée.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database;
    }

    /**
     * Définissez le nom de la base de données connectée.
     *
     * @param  string  $database
     * @return string
     */
    public function setDatabaseName($database)
    {
        $this->database = $database;
    }

    /**
     * Obtenez le préfixe de table pour la connexion.
     *
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->tablePrefix;
    }

    /**
     * Définissez le préfixe de table utilisé par la connexion.
     *
     * @param  string  $prefix
     * @return void
     */
    public function setTablePrefix($prefix)
    {
        $this->tablePrefix = $prefix;

        $this->getQueryGrammar()->setTablePrefix($prefix);
    }

    /**
     * Définissez le préfixe de la table et renvoyez la grammaire.
     *
     * @param  \Two\Database\Grammar  $grammar
     * @return \Two\Database\Grammar
     */
    public function withTablePrefix(Grammar $grammar)
    {
        $grammar->setTablePrefix($this->tablePrefix);

        return $grammar;
    }

}
