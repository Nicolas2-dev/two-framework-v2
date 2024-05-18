<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;
use Two\Console\Traits\ConfirmableTrait;
use Two\Console\Forge\Publishers\ConfigPublisher;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ConfigPublishCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'config:publish';

    /**
     * La description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Publish a package's configuration to the application";

    /**
     * L'instance de l'éditeur de configuration.
     *
     * @var \Two\Foundation\ConfigPublisher
     */
    protected $publisher;


    /**
     * Créez une nouvelle instance de commande de publication de configuration.
     *
     * @param  \Two\Foundation\ConfigPublisher  $config
     * @return void
     */
    public function __construct(ConfigPublisher $publisher)
    {
        parent::__construct();

        $this->publisher = $publisher;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $package = $this->input->getArgument('package');

        $proceed = $this->confirmToProceed('Config Already Published!', function() use ($package)
        {
            return $this->publisher->alreadyPublished($package);
        });

        if (! $proceed) return;

        $this->publisher->publishPackage($package);

        $this->output->writeln('<info>Configuration published for package:</info> '.$package);
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('package', InputArgument::REQUIRED, 'The configuration namespace of the package being published.'),
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
            array('force', null, InputOption::VALUE_NONE, 'Force the operation to run when the file already exists.'),
        );
    }

}
