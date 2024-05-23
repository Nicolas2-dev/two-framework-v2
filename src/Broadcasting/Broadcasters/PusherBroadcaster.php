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

use Pusher\Pusher;


class PusherBroadcaster extends Broadcaster
{
    /**
     * L'instance du SDK Pusher.
     *
     * @var \Pusher
     */
    protected $pusher;


    /**
     * Créez une nouvelle instance de diffuseur.
     *
     * @param  \Two\Container\Container  $container
     * @param  \Pusher  $pusher
     * @return void
     */
    public function __construct(Container $container, Pusher $pusher)
    {
        parent::__construct($container);

        //
        $this->pusher = $pusher;
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
            $result = $this->pusher->socket_auth($channel, $socketId);
        } else {
            $user = $request->user();

            $result = $this->pusher->presence_auth(
                $channel, $socketId, $user->getAuthIdentifier(), $result
            );
        }

        return json_decode($result, true);
    }

    /**
     * {@inheritdoc}
     */
    public function broadcast(array $channels, $event, array $payload = array())
    {
        $socket = Arr::pull($payload, 'socket');

        $event = str_replace('\\', '.', $event);

        $response = $this->pusher->trigger(
            $this->formatChannels($channels), $event, $payload, $socket, true
        );

        if (($response['status'] >= 200) && ($response['status'] <= 299)) {
            return;
        }

        throw new BroadcastException($response['body']);
    }

    /**
     * Obtenez l’instance du SDK Pusher.
     *
     * @return \Pusher
     */
    public function getPusher()
    {
        return $this->pusher;
    }
}
