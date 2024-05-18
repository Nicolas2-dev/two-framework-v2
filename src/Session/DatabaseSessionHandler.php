<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Session;

use SessionHandlerInterface;

use Two\Database\Connection;
use Two\Session\Contracts\ExistenceAwareInterface;


class DatabaseSessionHandler implements SessionHandlerInterface, ExistenceAwareInterface
{
    /**
     * L'instance de connexion à la base de données.
     *
     * @var \Two\Database\Connection
     */
    protected $connection;

    /**
     * Le nom de la table de session.
     *
     * @var string
     */
    protected $table;

    /**
     * L’état d’existence de la session.
     *
     * @var bool
     */
    protected $exists;

    /**
     * Créez une nouvelle instance de gestionnaire de session de base de données.
     *
     * @param  \Two\Database\Connection  $connection
     * @param  string  $table
     * @return void
     */
    public function __construct(Connection $connection, $table)
    {
        $this->table = $table;
        $this->connection = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId): string
    {
        $session = (object) $this->getQuery()->find($sessionId);

        if (isset($session->payload))
        {
            $this->exists = true;

            return base64_decode($session->payload);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data): bool
    {
        if ($this->exists) {
            $this->getQuery()->where('id', $sessionId)->update(array(
                'payload' => base64_encode($data),
                'last_activity' => time(),
            ));
        } else {
            $this->getQuery()->insert(array(
                'id' => $sessionId,
                'payload' => base64_encode($data),
                'last_activity' => time(),
            ));
        }

        $this->exists = true;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId): bool
    {
        $this->getQuery()->where('id', $sessionId)->delete();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime): int
    {
        $this->getQuery()->where('last_activity', '<=', (time() - $lifetime))->delete();

        return 0;
    }

    /**
     * Obtenez une nouvelle instance du générateur de requêtes pour la table.
     *
     * @return \Two\Database\Query\Builder
     */
    protected function getQuery()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Définissez l'état d'existence de la session.
     *
     * @param  bool  $value
     * @return $this
     */
    public function setExists($value)
    {
        $this->exists = $value;

        return $this;
    }

}
