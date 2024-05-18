<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Queue\Commands;

use RuntimeException;

use Two\Queue\IronQueue;
use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class SubscribeCommand extends Command
{

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'queue:subscribe';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Subscribe a URL to an Iron.io push queue';

    /**
     * Les méta-informations de la file d'attente d'Iron.io.
     *
     * @var object
     */
    protected $meta;

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function handle()
    {
        $iron = $this->container['queue']->connection();

        if ( ! $iron instanceof IronQueue)
        {
            throw new RuntimeException("Iron.io based queue must be default.");
        }

        $iron->getIron()->updateQueue($this->argument('queue'), $this->getQueueOptions());

        $this->line('<info>Queue subscriber added:</info> <comment>'.$this->argument('url').'</comment>');
    }

    /**
     * Obtenez les options de file d’attente.
     *
     * @return array
     */
    protected function getQueueOptions()
    {
        return array(
            'push_type' => $this->getPushType(), 'subscribers' => $this->getSubscriberList()
        );
    }

    /**
     * Obtenez le type de push pour la file d'attente.
     *
     * @return string
     */
    protected function getPushType()
    {
        if ($this->option('type')) return $this->option('type');

        try
        {
            return $this->getQueue()->push_type;
        }
        catch (\Exception $e)
        {
            return 'multicast';
        }
    }

    /**
     * Obtenez les abonnés actuels pour la file d’attente.
     *
     * @return array
     */
    protected function getSubscriberList()
    {
        $subscribers = $this->getCurrentSubscribers();

        $subscribers[] = array('url' => $this->argument('url'));

        return $subscribers;
    }

    /**
     * Obtenez la liste actuelle des abonnés.
     *
     * @return array
     */
    protected function getCurrentSubscribers()
    {
        try
        {
            return $this->getQueue()->subscribers;
        }
        catch (\Exception $e)
        {
            return array();
        }
    }

    /**
     * Obtenez les informations sur la file d’attente auprès d’Iron.io.
     *
     * @return object
     */
    protected function getQueue()
    {
        if (isset($this->meta)) return $this->meta;

        return $this->meta = $this->container['queue']->getIron()->getQueue($this->argument('queue'));
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('queue', InputArgument::REQUIRED, 'The name of Iron.io queue.'),
            array('url',   InputArgument::REQUIRED, 'The URL to be subscribed.'),
        );
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('type', null, InputOption::VALUE_OPTIONAL, 'The push type for the queue.'),
        );
    }

}
