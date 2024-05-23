<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Encryption;

use RuntimeException;

use Two\Support\Str;
use Two\Encryption\Exception\EncryptException;
use Two\Encryption\Exception\DecryptException;


class Encrypter
{
    /**
     * La clé de cryptage.
     *
     * @var string
     */
    protected $key;

    /**
     * L'algorithme utilisé pour le cryptage.
     *
     * @var string
     */
    protected $cipher;

    /**
     * Créez une nouvelle instance Encrypter.
     *
     * @param  string $key
     * @param  string $cipher
     * @return void
     */
    public function __construct($key, $cipher = 'AES-256-CBC')
    {
        $key = (string) $key;

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        if (static::supported($key, $cipher)) {
            $this->key = $key;
            $this->cipher = $cipher;
        } else {
            throw new RuntimeException('The only supported ciphers are AES-128-CBC and AES-256-CBC with the correct key lengths.');
        }
    }

    /**
     * Déterminez si la combinaison de clé et de chiffrement donnée est valide.
     *
     * @param  string $key
     * @param  string $cipher
     * @return bool
     */
    public static function supported($key, $cipher)
    {
        $length = mb_strlen($key, '8bit');

        return ((($cipher === 'AES-128-CBC') && ($length === 16)) || (($cipher === 'AES-256-CBC') && ($length === 32)));
    }

    /**
     * Chiffrez la valeur donnée.
     *
     * @param  string $value
     * @return string
     *
     * @throws \Two\Encryption\Exception\EncryptException
     */
    public function encrypt($value)
    {
        $iv = Str::randomBytes($this->getIvSize());

        $value = \openssl_encrypt(serialize($value), $this->cipher, $this->key, 0, $iv);

        if ($value === false) {
            throw new EncryptException('Could not encrypt the data.');
        }

        $mac = $this->hash($iv = base64_encode($iv), $value);

        return base64_encode(json_encode(compact('iv', 'value', 'mac')));
    }

    /**
     * Décryptez la valeur donnée.
     *
     * @param  string $payload
     * @return string
     *
     * @throws \Two\Encryption\Exception\DecryptException
     */
    public function decrypt($payload)
    {
        $payload = $this->getJsonPayload($payload);

        $iv = base64_decode($payload['iv']);

        $decrypted = \openssl_decrypt($payload['value'], $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new DecryptException('Could not decrypt the data.');
        }

        return unserialize($decrypted);
    }

    /**
     * Obtenez la taille IV pour le chiffre.
     *
     * @return int
     */
    protected function getIvSize()
    {
        return 16;
    }

    /**
     * Créez un MAC pour la valeur donnée.
     *
     * @param  string $iv
     * @param  string $value
     * @return string
     */
    protected function hash($iv, $value)
    {
        return hash_hmac('sha256', $iv .$value, $this->key);
    }

    /**
     * Obtenez le tableau JSON à partir de la charge utile donnée.
     *
     * @param  string $payload
     * @return array
     *
     * @throws \Two\Encryption\Exception\DecryptException
     */
    protected function getJsonPayload($payload)
    {
        $payload = json_decode(base64_decode($payload), true);

        if (! $payload || $this->invalidPayload($payload)) {
            throw new DecryptException('The payload is invalid.');
        }

        if (! $this->validMac($payload)) {
            throw new DecryptException('The MAC is invalid.');
        }

        return $payload;
    }

    /**
     * Vérifiez que la charge utile de chiffrement est valide.
     *
     * @param  array|mixed $data
     * @return bool
     */
    protected function invalidPayload($data)
    {
        return ! is_array($data) || ! isset($data['iv']) || ! isset($data['value']) || ! isset($data['mac']);
    }

    /**
     * Déterminez si le MAC pour la charge utile donnée est valide.
     *
     * @param  array $payload
     * @return bool
     *
     * @throws \RuntimeException
     */
    protected function validMac(array $payload)
    {
        $bytes = Str::randomBytes(16);

        $calcMac = hash_hmac('sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true);

        return Str::equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calcMac);
    }

}
