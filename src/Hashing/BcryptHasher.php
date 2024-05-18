<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Hashing;

use RuntimeException;

use Two\Hashing\Contracts\HasherInterface;


class BcryptHasher implements HasherInterface
{
    
    /**
     * Facteur de coût de cryptage par défaut.
     *
     * @var int
     */
    protected $rounds = 10;

    /**
     * Hachez la valeur donnée.
     *
     * @param  string  $value
     * @param  array   $options
     * @return string
     *
     * @throws \RuntimeException
     */
    public function make($value, array $options = array())
    {
        $cost = isset($options['rounds']) ? $options['rounds'] : $this->rounds;

        $hash = password_hash($value, PASSWORD_DEFAULT, array('cost' => $cost));

        if ($hash === false) {
            throw new RuntimeException("Bcrypt hashing not supported.");
        }

        return $hash;
    }

    /**
     * Vérifiez la valeur simple donnée par rapport à un hachage.
     *
     * @param  string  $value
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function check($value, $hashedValue, array $options = array())
    {
        return password_verify($value, $hashedValue);
    }

    /**
     * Vérifiez si le hachage donné a été haché à l'aide des options données.
     *
     * @param  string  $hashedValue
     * @param  array   $options
     * @return bool
     */
    public function needsRehash($hashedValue, array $options = array())
    {
        $cost = isset($options['rounds']) ? $options['rounds'] : $this->rounds;

        return password_needs_rehash($hashedValue, PASSWORD_DEFAULT, array('cost' => $cost));
    }
}
