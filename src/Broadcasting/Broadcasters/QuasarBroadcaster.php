<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting\Broadcasters;

use Two\Support\Arr;
use Two\Support\Str;
use Two\Http\Request;
use Two\Container\Container;
use Two\Broadcasting\Broadcaster;
use Two\Broadcasting\Exception\BroadcastException;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;


class QuasarBroadcaster extends Broadcaster
{
    /**
     * L'ID de l'application pour accéder au serveur Push.
     *
     * @var string
     */
    protected $publicKey;


    /**
     * La clé secrète pour accéder au serveur Push.
     *
     * @var string
     */
    protected $secretKey;

    /**
     * Les options de connexion au serveur Push.
     *
     * @var array
     */
    protected $options = array();


    /**
     * Créez une nouvelle instance de diffuseur.
     *
     * @param  \Two\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container, array $config)
    {
        parent::__construct($container);

        //
        $this->publicKey = Arr::get($config, 'key');
        $this->secretKey = Arr::get($config, 'secret');

        $this->options = Arr::get($config, 'options', array());
    }

    /**
     * Renvoie la réponse d'authentification valide.
     *
     * @param  \Two\Http\Request  $request
     * @param  mixed  $result
     * @return mixed
     */
    public function validAuthenticationResponse(Request $request, $result)
    {
        $channel = $request->input('channel_name');

        $socketId = $request->input('socket_id');

        if (! Str::startsWith($channel, 'presence-')) {
            $result = $this->socketAuth($channel, $socketId);
        } else {
            $user = $request->user();

            $result = $this->presenceAuth(
                $channel, $socketId, $user->getAuthIdentifier(), $result
            );
        }

        return json_decode($result, true);
    }

    /**
     * Crée une signature de socket.
     *
     * @param string $socketId
     * @param string $customData
     *
     * @return string
     */
    protected function socketAuth($channel, $socketId, $customData = null)
    {
        if (preg_match('/^[-a-z0-9_=@,.;]+$/i', $channel) !== 1) {
            throw new BroadcastException('Invalid channel name ' .$channel);
        }

        //
        else if (preg_match('/^(?:\/[a-z0-9]+#)?[a-z0-9]+$/i', $socketId) !== 1) {
            throw new BroadcastException('Invalid socket ID ' .$socketId);
        }

        if (! is_null($customData)) {
            $auth = hash_hmac('sha256', $socketId .':' .$channel .':' .$customData, $this->secretKey, false);
        } else {
            $auth = hash_hmac('sha256', $socketId .':' .$channel, $this->secretKey, false);
        }

        $signature = compact('auth');

        // Ajoutez les données personnalisées si elles ont été fournies.
        if (! is_null($customData)) {
            $signature['payload'] = $customData;
        }

        return json_encode($signature);
    }

    /**
     * Crée une signature de présence (une extension de la signature de socket).
     *
     * @param string $socketId
     * @param string $userId
     * @param mixed  $userInfo
     *
     * @return string
     */
    protected function presenceAuth($channel, $socketId, $userId, $userInfo = null)
    {
        $userData = array('userId' => $userId);

        if (! is_null($userInfo)) {
            $userData['userInfo'] = $userInfo;
        }

        return $this->socketAuth($channel, $socketId, json_encode($userData));
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = array())
    {
        $socket = Arr::pull($payload, 'socket');

        $this->trigger(
            $this->formatChannels($channels), $event, $payload, $socket
        );
    }

    /**
     * Déclenchez un événement en fournissant le nom de l'événement et la charge utile.
     * Fournissez éventuellement un ID de socket pour exclure un client (très probablement l'expéditeur).
     *
     * @param array|string $channels        Un nom de canal ou un tableau de noms de canaux sur lequel publier l'événement.
     * @param string       $event
     * @param mixed        $data            Event data
     * @param string|null  $socketId        [optional]
     *
     * @return bool
     */
    protected function trigger($channels, $event, $data, $socketId = null)
    {
        $event = str_replace('\\', '.', $event);

        $payload = array(
            'channels' => json_encode($channels),
            'event'    => $event,
            'data'     => json_encode($data),
            'socketId' => $socketId ?: '',
        );

        $url = $this->createServerUrl(
            $path = sprintf('apps/%s/events', $this->publicKey)
        );

        return $this->executeHttpRequest(
            $url, $payload, $this->createBearerHash($path, $payload)
        );
    }

    /**
     * Calculez l'URL complète du serveur.
     *
     * @param string  $path
     *
     * @return string
     */
    protected function createServerUrl($path)
    {
        $host = Arr::get($this->options, 'httpHost', '127.0.0.1');

        $port = (int) Arr::get($this->options, 'httpPort', 2121);

        return sprintf('%s:%d/%s', $host, $port, $path);
    }

    /**
     * Calculez le hachage de la demande à partir du chemin et des données.
     *
     * @param string  $path
     * @param array  $data
     *
     * @return string
     */
    protected function createBearerHash($path, array $data)
    {
        $payload = "POST\n" .$path .':' .json_encode($data);

        return hash_hmac('sha256', $payload, $this->secretKey, false);
    }

    /**
     * Exécutez une requête HTTP sur le serveur Web Quasar.
     *
     * @param string  $url
     * @param array  $payload
     * @param string  $hash
     *
     * @return bool
     * @throws \Two\Broadcasting\Exception\BroadcastException
     */
    protected function executeHttpRequest($url, array $payload, $hash)
    {
        $client = new HttpClient();

        try {
            $response = $client->post($url, array(
                'headers' => array(
                    'CONNECTION'    => 'close',
                    'AUTHORIZATION' => 'Bearer ' .$hash,
                ),
                'body' => $payload,
            ));

            $status = (int) $response->getStatusCode();

            return ($status == 200) && ($response->getBody() == '200 OK');
        }
        catch (RequestException $e) {
            throw new BroadcastException($e->getMessage());
        }
    }
}
