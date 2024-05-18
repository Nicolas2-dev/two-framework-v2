<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Two;

use Two\Console\Commands\GeneratorCommand;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputOption;


class PolicyMakeCommand extends GeneratorCommand
{
    /**
     * Le nom de la commande de la console.
     *
     * @var string
     */
    protected $name = 'make:policy';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Policy class';

    /**
     * Le type de classe générée.
     *
     * @var string
     */
    protected $type = 'Policy';


    /**
     * Construisez la classe avec le nom donné.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->replaceUserNamespace(
            parent::buildClass($name)
        );

        $model = $this->option('model');

        return $model ? $this->replaceModel($stub, $model) : $stub;
    }

    /**
     * Remplacez l'espace de noms du modèle utilisateur.
     *
     * @param  string  $stub
     * @return string
     */
    protected function replaceUserNamespace($stub)
    {
        $config = $this->container['config'];

        //
        $namespaceModel = $config->get('auth.providers.users.model', 'App\Models\User');

        $model = class_basename(trim($namespaceModel, '\\'));

        //
        $stub = str_replace('{{fullUserModel}}', $namespaceModel, $stub);

        return str_replace('{{userModel}}', $model, $stub);
    }

    /**
     * Remplacez le modèle pour le stub donné.
     *
     * @param  string  $stub
     * @param  string  $model
     * @return string
     */
    protected function replaceModel($stub, $model)
    {
        $model = str_replace('/', '\\', $model);

        $namespaceModel = $this->container->getNamespace() .'Models\\' .$model;

        if (Str::startsWith($model, '\\')) {
            $stub = str_replace('{{fullModel}}', trim($model, '\\'), $stub);
        } else {
            $stub = str_replace('{{fullModel}}', $namespaceModel, $stub);
        }

        $stub = str_replace(
            "use {$namespaceModel};\nuse {$namespaceModel};", "use {$namespaceModel};", $stub
        );

        $model = class_basename(trim($model, '\\'));

        $stub = str_replace('{{model}}', $model, $stub);

        $stub = str_replace('{{camelModel}}', Str::camel($model), $stub);

        return str_replace('{{pluralModel}}', Str::plural(Str::camel($model)), $stub);
    }

    /**
     * Obtenez le fichier stub du générateur.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('model')) {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/policy.stub');
        } else {
            return realpath(__DIR__) .str_replace('/', DS, '/stubs/policy.plain.stub');
        }
    }

    /**
     * Obtenez l'espace de noms par défaut pour la classe.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace .'\Policies';
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('model', 'm', InputOption::VALUE_OPTIONAL, 'The model that the policy applies to.'),
        );
    }
}
