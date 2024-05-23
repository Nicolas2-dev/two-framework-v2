<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Auth;

use Two\Support\Str;
use Two\Database\Connection;
use Two\Auth\Contracts\UserInterface;
use Two\Hashing\Contracts\HasherInterface;
use Two\Auth\Contracts\UserProviderInterface;


class DatabaseUserProvider implements UserProviderInterface
{
    /**
     * The active database connection.
     *
     * @var \Two\Database\Connection
     */
    protected $conn;

    /**
     * The hasher implementation.
     *
     * @var \Two\Hashing\Contracts\HasherInterface
     */
    protected $hasher;

    /**
     * The table containing the users.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param  \Two\Database\Connection  $conn
     * @param  \Two\Hashing\Contracts\HasherInterface  $hasher
     * @param  string  $table
     * @return void
     */
    public function __construct(Connection $conn, HasherInterface $hasher, $table)
    {
        $this->conn = $conn;
        $this->table = $table;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->conn->table($this->table)->find($identifier);

        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Retrieve a user by by their unique identifier and "remember me" token.
     *
     * @param  mixed   $identifier
     * @param  string  $token
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->conn->table($this->table)
            ->where('id', $identifier)
            ->where('remember_token', $token)
            ->first();

        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserInterface $user, $token)
    {
        $this->conn->table($this->table)
            ->where('id', $user->getAuthIdentifier())
            ->update(array('remember_token' => $token));
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // generic "user" object that will be utilized by the Guard instances.
        $query = $this->conn->table($this->table);

        foreach ($credentials as $key => $value) {
            if (! Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        // Now we are ready to execute the query to see if we have an user matching
        // the given credentials. If not, we will just return nulls and indicate
        // that there are no matching users for these given credential arrays.
        $user = $query->first();

        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Two\Auth\Contracts\UserInterface  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserInterface $user, array $credentials)
    {
        $plain = $credentials['password'];

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

}
