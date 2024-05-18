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
use Two\Hashing\Contracts\HasherInterface;
use Two\Auth\Contracts\UserInterface;
use Two\Auth\Contracts\UserProviderInterface;


class DatabaseUserProvider implements UserProviderInterface
{
    /**
     * La connexion à la base de données active.
     *
     * @var \Two\Database\Connection
     */
    protected $conn;

    /**
     * L'implémentation du hachage.
     *
     * @var \Two\Hashing\Contracts\HasherInterface
     */
    protected $hasher;

    /**
     * La table contenant les utilisateurs.
     *
     * @var string
     */
    protected $table;

    /**
     * Créez un nouveau fournisseur d'utilisateurs de base de données.
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
     * Récupérez un utilisateur par son identifiant unique.
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
     * Récupérez un utilisateur grâce à son identifiant unique et son jeton « se souvenir de moi ».
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
     * Mettez à jour le jeton « Se souvenir de moi » pour l'utilisateur donné dans le stockage.
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
     * Récupérez un utilisateur à l'aide des informations d'identification fournies.
     *
     * @param  array  $credentials
     * @return \Two\Auth\Contracts\UserInterface|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        // Nous allons d’abord ajouter chaque élément d’identification à la requête en tant que clause Where.
        // Ensuite, nous pouvons exécuter la requête et, si nous trouvons un utilisateur, la renvoyer dans un
        // objet "utilisateur" générique qui sera utilisé par les instances Guard.
        $query = $this->conn->table($this->table);

        foreach ($credentials as $key => $value) {
            if (! Str::contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        // Nous sommes maintenant prêts à exécuter la requête pour voir si nous avons un utilisateur correspondant
        // les informations d'identification données. Sinon, nous renverrons simplement des valeurs nulles et indiquerons
        // qu'il n'y a aucun utilisateur correspondant pour ces tableaux d'informations d'identification donnés.
        $user = $query->first();

        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Validez un utilisateur par rapport aux informations d'identification fournies.
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
