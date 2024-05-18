<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Support\Str;
use Two\Console\Forge\Packages\Commands\MakeCommand;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class PolicyMakeCommand extends MakeCommand
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'package:make:policy';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Package Policy class';

    /**
     * Chaîne pour stocker le type de commande.
     *
     * @var string
     */
    protected $type = 'Policy';

    /**
     * Dossiers de packages à créer.
     *
     * @var array
     */
    protected $listFolders = array(
        'Policies/',
    );

    /**
     * Fichiers de package à créer.
     *
     * @var array
     */
    protected $listFiles = array(
        '{{filename}}.php',
    );

    /**
     * Option de signature du package.
     *
     * @var array
     */
    protected $signOption = array(
        'model',
    );

    /**
     * Stubs de package utilisés pour remplir les fichiers définis.
     *
     * @var array
     */
    protected $listStubs = array(
        'default' => array(
            'policy.plain.stub',
        ),
        'model' => array(
            'policy.stub',
        ),
    );

    /**
     * Résolvez le conteneur après avoir obtenu le chemin du fichier.
     *
     * @param string $filePath
     *
     * @return array
     */
    protected function resolveByPath($filePath)
    {
        $this->data['filename']  = $this->makeFileName($filePath);
        $this->data['namespace'] = $this->getNamespace($filePath);

        $this->data['className'] = basename($filePath);

        //
        $this->data['model'] = 'dummy';

        $this->data['fullModel']   = 'dummy';
        $this->data['camelModel']  = 'dummy';
        $this->data['pluralModel'] = 'dummy';

        $this->data['userModel']     = 'dummy';
        $this->data['fullUserModel'] = 'dummy';
    }

    /**
     * Résolvez le conteneur après avoir obtenu l’option de saisie.
     *
     * @param string $option
     *
     * @return array
     */
    protected function resolveByOption($option)
    {
        $model = str_replace('/', '\\', $option);

        $namespaceModel = $this->container->getNamespace() .'Models\\' .$model;

        if (Str::startsWith($model, '\\')) {
            $this->data['fullModel'] = trim($model, '\\');
        } else {
            $this->data['fullModel'] = $namespaceModel;
        }

        $this->data['model'] = $model = class_basename(trim($model, '\\'));

        $this->data['camelModel'] = Str::camel($model);

        $this->data['pluralModel'] = Str::plural(Str::camel($model));

        //
        $config = $this->container['config'];

        $this->data['fullUserModel'] = $model = $config->get('auth.providers.users.model', 'App\Models\User');

        $this->data['userModel'] = class_basename(trim($model, '\\'));
    }

    /**
     * Remplacez le texte de l'espace réservé par des valeurs correctes.
     *
     * @return string
     */
    protected function formatContent($content)
    {
        $searches = array(
            '{{filename}}',
            '{{namespace}}',
            '{{className}}',
            '{{model}}',
            '{{fullModel}}',
            '{{camelModel}}',
            '{{pluralModel}}',
            '{{userModel}}',
            '{{fullUserModel}}',

        );

        $replaces = array(
            $this->data['filename'],
            $this->data['namespace'],
            $this->data['className'],
            $this->data['model'],
            $this->data['fullModel'],
            $this->data['camelModel'],
            $this->data['pluralModel'],
            $this->data['userModel'],
            $this->data['fullUserModel'],
        );

        $content = str_replace($searches, $replaces, $content);

        //
        $class = $this->data['fullModel'];

        return str_replace("use {$class};\nuse {$class};", "use {$class};", $content);
    }


    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('slug', InputArgument::REQUIRED, 'The slug of the Package.'),
            array('name', InputArgument::REQUIRED, 'The name of the Policy class.'),
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
            array('--model', null, InputOption::VALUE_OPTIONAL, 'The model that the policy applies to.'),
        );
    }
}
