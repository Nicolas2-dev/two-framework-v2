<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Database\Commands\Seeds;

use Two\Console\Commands\Command;
use Two\Console\Traits\ConfirmableTrait;
use Two\Database\Contracts\ConnectionResolverInterface as Resolver;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputOption;


class SeedCommand extends Command
{
    use ConfirmableTrait;

    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'db:seed';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Seed the database with records';

    /**
     * L'instance du résolveur de connexion.
     *
     * @var \Two\Database\Contracts\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * Créez une nouvelle instance de commande de départ de base de données.
     *
     * @param  \Two\Database\Contracts\ConnectionResolverInterface  $resolver
     * @return void
     */
    public function __construct(Resolver $resolver)
    {
        parent::__construct();

        $this->resolver = $resolver;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return;
        }

        $this->resolver->setDefaultConnection($this->getDatabase());

        $this->getSeeder()->run();
    }

    /**
     * Obtenez une instance de seeder à partir du conteneur.
     *
     * @return \Two\Database\Seeder
     */
    protected function getSeeder()
    {
        $className = str_replace('/', '\\', $this->input->getOption('class'));

        $rootNamespace = $this->container->getNamespace();

        if (! Str::startsWith($className, $rootNamespace) && ! Str::contains($className, '\\')) {
            $className = $rootNamespace .'Database\Seeds\\' .$className;
	}

        $instance = $this->container->make($className);

        return $instance->setContainer($this->container)->setCommand($this);
    }

    /**
     * Obtenez le nom de la connexion à la base de données à utiliser.
     *
     * @return string
     */
    protected function getDatabase()
    {
        $database = $this->input->getOption('database');

        return $database ?: $this->container['config']['database.default'];
    }

    /**
     * Obtenez les options de commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('class',    null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder', 'DatabaseSeeder'),
            array('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to seed'),
            array('force',    null, InputOption::VALUE_NONE,     'Force the operation to run when in production.'),
        );
    }
}
