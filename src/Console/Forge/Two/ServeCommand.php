<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\Command;

use Symfony\Component\Console\Input\InputOption;


class ServeCommand extends Command
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'two:serve';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = "Serve the Application on the PHP development server";

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        $this->checkPhpVersion();

        chdir($this->container['path.base']);

        $host = $this->input->getOption('host');

        $port = $this->input->getOption('port');

        $public = $this->container['path.public'];

        $this->info("Two Framework development Server started on http://{$host}:{$port}");

        passthru('"'.PHP_BINARY.'"'." -S {$host}:{$port} -t \"{$public}\" server.php");
    }

    /**
     * Vérifiez que la version actuelle de PHP est >= 8.2.
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function checkPhpVersion()
    {
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            throw new \Exception('This PHP binary is not version 5.5 or greater.');
        }
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on.', 'localhost'),
            array('port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on.', 8000),
        );
    }

}
