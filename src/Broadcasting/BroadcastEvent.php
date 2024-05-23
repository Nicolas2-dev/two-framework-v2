<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Broadcasting;

use ReflectionClass;
use ReflectionProperty;

use Two\Queue\Job;
use Two\Application\Contracts\ArrayableInterface;
use Two\Broadcasting\Contracts\BroadcasterInterface;


class BroadcastEvent
{
    /**
     * La mise en œuvre du diffuseur.
     *
     * @var \Two\Broadcasting\Contracts\BroadcasterInterface
     */
    protected $broadcaster;


    /**
     * Créez une nouvelle instance de gestionnaire de tâches.
     *
     * @param  \Two\Broadcasting\Contracts\BroadcasterInterface  $broadcaster
     * @return void
     */
    public function __construct(BroadcasterInterface $broadcaster)
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * Gérez le travail en file d'attente.
     *
     * @param  \Two\Queue\Job  $job
     * @param  array  $data
     * @return void
     */
    public function handle(Job $job, array $data)
    {
        $event = unserialize($data['event']);

        if (method_exists($event, 'broadcastAs')) {
            $name = $event->broadcastAs();
        } else {
            $name = get_class($event);
        }

        if (! is_array($channels = $event->broadcastOn())) {
            $channels = array($channels);
        }

        $this->broadcaster->broadcast(
            $channels, $name, $this->getPayloadFromEvent($event)
        );

        $job->delete();
    }

    /**
     * Obtenez la charge utile pour l'événement donné.
     *
     * @param  mixed  $event
     * @return array
     */
    protected function getPayloadFromEvent($event)
    {
        if (method_exists($event, 'broadcastWith')) {
            return $event->broadcastWith();
        }

        $payload = array();

        //
        $reflection = new ReflectionClass($event);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $key = $property->getName();

            $value = $property->getValue($event);

            $payload[$key] = $this->formatProperty($value);
        }

        return $payload;
    }

    /**
     * Formatez la valeur donnée pour une propriété.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function formatProperty($value)
    {
        if ($value instanceof ArrayableInterface) {
            return $value->toArray();
        }

        return $value;
    }
}
