<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Queue;

use Two\Queue\Job;
use Two\Bus\Contracts\DispatcherInterface;


class CallQueuedHandler
{
    /**
     * La mise en œuvre du répartiteur de bus.
     *
     * @var \Two\Bus\Contracts\DispatcherInterface
     */
    protected $dispatcher;


    /**
     * Créez une nouvelle instance de gestionnaire.
     *
     * @param  \Two\Bus\Contracts\DispatcherInterface  $dispatcher
     * @return void
     */
    public function __construct(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Gérez le travail en file d'attente.
     *
     * @param  \Two\Queue\Job  $job
     * @param  array  $data
     * @return void
     */
    public function call(Job $job, array $data)
    {
        $command = $this->setJobInstanceIfNecessary(
            $job, unserialize($data['command'])
        );

        $this->dispatcher->dispatchNow(
            $command, $handler = $this->resolveHandler($job, $command)
        );

        if (! $job->isDeleted()) {
            $job->delete();
        }
    }

    /**
     * Résolvez le gestionnaire pour la commande donnée.
     *
     * @param  \Two\Queue\Job  $job
     * @param  mixed  $command
     * @return mixed
     */
    protected function resolveHandler($job, $command)
    {
        $handler = $this->dispatcher->getCommandHandler($command) ?: null;

        if (! is_null($handler)) {
            $this->setJobInstanceIfNecessary($job, $handler);
        }

        return $handler;
    }

    /**
     * Définissez l'instance de travail de la classe donnée si nécessaire.
     *
     * @param  \Two\Queue\Job  $job
     * @param  mixed  $instance
     * @return mixed
     */
    protected function setJobInstanceIfNecessary(Job $job, $instance)
    {
        if (in_array('Two\Queue\Traits\InteractsWithQueueTrait', class_uses_recursive(get_class($instance)))) {
            $instance->setJob($job);
        }

        return $instance;
    }

    /**
     * Appelez la méthode ayant échoué sur l’instance de travail.
     *
     * @param  array  $data
     * @return void
     */
    public function failed(array $data)
    {
        $command = unserialize($data['command']);

        if (method_exists($command, 'failed')) {
            $command->failed($e);
        }
    }
}
