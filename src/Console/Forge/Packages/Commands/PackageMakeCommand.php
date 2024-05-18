<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Console\Forge\Packages\Commands;

use Two\Console\Commands\Command;
use Two\Filesystem\Filesystem;
use Two\Packages\PackageManager;
use Two\Support\Arr;
use Two\Support\Str;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;


class PackageMakeCommand extends Command
{
    /**
     * Le nom de la commande de console.
     *
     * @var string
     */
    protected $name = 'package:make';

    /**
     * Description de la commande de la console.
     *
     * @var string
     */
    protected $description = 'Create a new Package';

    /**
     * Dossiers de packages à créer.
     *
     * @var array
     */
    protected $packageFolders = array(
        'default' => array(
            'assets/',
            'assets/css/',
            'assets/images/',
            'assets/js/',
            'src/',
            'src/Config/',
            'src/Database/',
            'src/Database/Migrations/',
            'src/Database/Seeds/',
            'src/Language/',
            'src/Providers/',
        ),
        'extended' => array(
            'assets/',
            'assets/css/',
            'assets/images/',
            'assets/js/',
            'src/',
            'src/Blocks/',
            'src/Config/',
            'src/Controllers/',
            'src/Database/',
            'src/Database/Migrations/',
            'src/Database/Seeds/',
            'src/Language/',
            'src/Models/',
            'src/Providers/',
            'src/Routes/',
            'src/Support/',
            'src/Views/',
        ),
        'module' => array(
            'Assets/',
            'Assets/css/',
            'Assets/images/',
            'Assets/js/',
            'Config/',
            'Blocks/',
            'Controllers/',
            'Database/',
            'Database/Migrations/',
            'Database/Seeds/',
            'Language/',
            'Models/',
            'Providers/',
            'Routes/',
            'Support/',
            'Views/',
        ),
        'theme' =>  array(
            'Assets/',
            'Assets/css/',
            'Assets/images/',
            'Assets/js/',
            'Config/',
            'Language/',
            'Overrides/',
            'Overrides/Packages/',
            'Providers/',
            'Views/',
            'Views/Layouts/',
            'Views/Layouts/RTL/',
        ),
    );

    /**
     * Fichiers de package à créer.
     *
     * @var array
     */
    protected $packageFiles = array(
        'default' => array(
            'src/Config/Config.php',
            'src/Database/Seeds/DatabaseSeeder.php',
            'src/Providers/PackageServiceProvider.php',
            'README.md',
            'composer.json'
        ),
        'extended' => array(
            'src/Blocks/Boxe.php',
            'src/Config/Config.php',
            'src/Database/Seeds/DatabaseSeeder.php',
            'src/Providers/AuthServiceProvider.php',
            'src/Providers/EventServiceProvider.php',
            'src/Providers/PackageServiceProvider.php',
            'src/Providers/RouteServiceProvider.php',
            'src/Routes/Api.php',
            'src/Routes/Web.php',
            'src/Routes/Admin.php',
            'src/Routes/Assets.php',
            'src/Support/helpers.php',
            'src/Bootstrap.php',
            'src/Events.php',
            'README.md',
            'composer.json'
        ),
        'module' => array(
            'Blocks/Boxe.php',
            'Config/Config.php',
            'Database/Seeds/DatabaseSeeder.php',
            'Providers/AuthServiceProvider.php',
            'Providers/EventServiceProvider.php',
            'Providers/ModuleServiceProvider.php',
            'Providers/RouteServiceProvider.php',
            'Routes/Api.php',
            'Routes/Web.php',
            'Routes/Admin.php',
            'Routes/Assets.php',
            'Support/helpers.php',
            'Bootstrap.php',
            'Events.php',
            'README.md',
        ),
        'theme' => array(
            'Assets/css/style.css',
            'Config/Config.php',
            'Providers/ThemeServiceProvider.php',
            'Views/Layouts/Default.php',
            'Views/Layouts/RTL/Default.php',
            'Bootstrap.php',
            'README.md',
        ),
    );

    /**
     * Stubs de package utilisés pour remplir les fichiers définis.
     *
     * @var array
     */
    protected $packageStubs = array(
        'default' => array(
            'config',
            'seeder',
            'standard-service-provider',
            'readme',
            'composer'
        ),
        'extended' => array(
            'boxe',
            'seeder',
            'auth-service-provider',
            'event-service-provider',
            'extended-service-provider',
            'route-service-provider',
            'api-routes',
            'web-routes',
            'admin-routes',
            'assets-routes',
            'helpers',            
            'bootstrap',
            'events',
            'readme',
            'composer'
        ),
        'module' => array(
            'boxe',
            'config',
            'seeder',
            'auth-service-provider',
            'event-service-provider',
            'module-service-provider',
            'route-service-provider',
            'api-routes',
            'web-routes',
            'admin-routes',
            'assets-routes',
            'helpers',            
            'bootstrap',
            'events',
            'readme',
        ),
        'theme' => array(
            'style',
            'config',
            'theme-service-provider',
            'layout',
            'layout',
            'theme-bootstrap',
            'readme',
        )
    );

    /**
     * L'instance des packages.
     *
     * @var \Two\Packages\PackageManager
     */
    protected $packages;

    /**
     * L'instance du système de fichiers.
     *
     * @var Two\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Tableau pour stocker les détails de configuration.
     *
     * @var array
     */
    protected $data = array();


    /**
     * Créez une nouvelle instance de commande.
     *
     * @param Filesystem $files
     * @param \Two\Packages\PackageManager    $packages
     */
    public function __construct(Filesystem $files, PackageManager $packages)
    {
        parent::__construct();

        $this->files  = $files;

        $this->packages = $packages;
    }

    /**
     * Exécutez la commande de la console.
     *
     * @return mixed
     */
    public function handle()
    {
        $type = $this->option('type');

        if (! in_array($type, array('module', 'theme', 'package'))) {
            return $this->error('Invalid package type specified.');
        }

        $name = $this->argument('name');

        if (strpos($name, '/') > 0) {
            list ($vendor, $name) = explode('/', $name);
        } else {
            $vendor = 'AcmeCorp';
        }

        if ($type == 'module') {
            $namespace = $this->packages->getModulesNamespace();

            $vendor = basename(str_replace('\\', '/',  $namespace));
        } else if ($type == 'theme') {
            $namespace = $this->packages->getThemesNamespace();

            $vendor = basename(str_replace('\\', '/',  $namespace));
        }

        if (Str::length($name) > 3) {
            $slug = Str::snake($name);
        } else {
            $slug = Str::lower($name);
        }

        $this->data['slug'] = $slug;

        //
        if (Str::length($slug) > 3) {
            $name = Str::studly($slug);
        } else {
            $name = Str::upper($slug);
        }

        $this->data['name'] = $name;

        //
        $this->data['package'] = $package = Str::studly($vendor) .'/' .$name;

        $this->data['lower_package'] = Str::snake($vendor, '-') .'/' .str_replace('_', '-', $slug);

        $this->data['namespace'] = str_replace('/', '\\', $package);

        //
        $config = $this->container['config'];

        $this->data['author']   = $config->get('packages.author.name');
        $this->data['email']    = $config->get('packages.author.email');
        $this->data['homepage'] = $config->get('packages.author.homepage');

        $this->data['license'] = 'MIT';

        if ($this->option('quick')) {
            return $this->generate($type);
        }

        $this->stepOne($type);
    }

    /**
     * Étape 1 : Configurer le package.
     *
     * @param  string  $type
     * @return mixed
     */
    private function stepOne($type)
    {
        $input = $this->ask('Please enter the name of the Package:', $this->data['name']);

        if (Str::length($input) > 3) {
            $name = Str::studly($input);
        } else {
            $name = Str::upper($input);
        }

        $this->data['name'] = $name;

        //
        $input = $this->ask('Please enter the slug of the Package:', $this->data['slug']);

        if (Str::length($input) > 3) {
            $slug = Str::snake($input);
        } else {
            $slug = Str::lower($input);
        }

        $this->data['slug'] = $slug;

        if ($type == 'module') {
            $namespace = $this->packages->getModulesNamespace();

            $vendor = basename(str_replace('\\', '/',  $namespace));
        } else if ($type == 'theme') {
            $namespace = $this->packages->getThemesNamespace();

            $vendor = basename(str_replace('\\', '/',  $namespace));
        } else {
            if (strpos($this->data['package'], '/') > 0) {
                list ($vendor) = explode('/', $this->data['package']);
            } else {
                $vendor = 'AcmeCorp';
            }

            if (empty($vendor = $this->ask('Please enter the vendor of the Package:', $vendor))) {
                $vendor = 'AcmeCorp';
            }
        }

        $this->data['package'] = Str::studly($vendor) .'/' .$name;

        $this->data['lower_package'] = Str::snake($vendor, '-') .'/' .str_replace('_', '-', $slug);

        //
        $this->data['namespace'] = $this->ask('Please enter the namespace of the Package:', $this->data['namespace']);

        $this->data['license'] = $this->ask('Please enter the license of the Package:', $this->data['license']);

        $this->comment('You have provided the following information:');

        $this->comment('Name:       ' .$this->data['name']);
        $this->comment('Slug:       ' .$this->data['slug']);
        $this->comment('Package:    ' .$this->data['package']);
        $this->comment('Namespace:  ' .$this->data['namespace']);
        $this->comment('License:    ' .$this->data['license']);

        if ($this->confirm('Do you wish to continue?')) {
            $this->generate($type);
        } else {
            return $this->stepOne($type);
        }

        return true;
    }

    /**
     * Générez le package.
     */
    protected function generate($type)
    {
        $slug = $this->data['slug'];

        if ($type == 'module') {
            $path = $this->getModulePath($slug);
        } else if ($type == 'theme') {
            $path = $this->getThemePath($slug);
        } else {
            $path = $this->getPackagePath($slug);
        }

        if ($this->files->exists($path)) {
            $this->error('The Package [' .$slug .'] already exists!');

            return false;
        }

        $steps = array(
            'Generating folders...'          => 'generateFolders',
            'Generating files...'            => 'generateFiles',
            'Generating .gitkeep ...'        => 'generateGitkeep',
            'Updating the composer.json ...' => 'updateComposerJson',
        );

        $progress = new ProgressBar($this->output, count($steps));

        $progress->start();

        foreach ($steps as $message => $method) {
            $progress->setMessage($message);

            call_user_func(array($this, $method), $type);

            $progress->advance();
        }

        $progress->finish();

        $this->info("\nGenerating optimized class loader");

        $this->container['composer']->dumpOptimized();

        $this->info("Package generated successfully.");
    }

    /**
     * Générez des dossiers de packages définis.
     *
     * @param string $type
     * @return void
     */
    protected function generateFolders($type)
    {
        $slug = $this->data['slug'];

        if ($type == 'module') {
            $path = $this->packages->getModulesPath();

            $packagePath = $this->getModulePath($slug);

            $mode = 'module';
        } else if ($type == 'theme') {
            $path = $this->packages->getThemesPath();

            $packagePath = $this->getThemePath($slug);

            $mode = 'theme';
        } else {
            $path = $this->packages->getPackagesPath();

            $packagePath = $this->getPackagePath($slug);

            $mode = $this->option('extended') ? 'extended' : 'default';
        }

        if (! $this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true, true);
        }

        $this->files->makeDirectory($packagePath);

        // Générez les répertoires du package.
        $packageFolders = $this->packageFolders[$mode];

        foreach ($packageFolders as $folder) {
            $path = $packagePath .$folder;

            $this->files->makeDirectory($path);
        }

        // Générez les répertoires internes de langue.
        $languageFolders = $this->getLanguagePaths($slug, $type);

        foreach ($languageFolders as $folder) {
            $path = $packagePath .$folder;

            $this->files->makeDirectory($path);
        }
    }

    /**
     * Générez des fichiers de package définis.
     *
     * @param string $type
     * @return void
     */
    protected function generateFiles($type)
    {
        if ($type == 'module') {
            $mode = 'module';

            $this->data['type']       = 'Module';
            $this->data['lower_type'] = 'module';
        } else if ($type == 'theme') {
            $mode = 'theme';

            $this->data['type']       = 'Theme';
            $this->data['lower_type'] = 'theme';
        } else {
            $mode = $this->option('extended') ? 'extended' : 'default';

            $this->data['type']       = 'Package';
            $this->data['lower_type'] = 'package';
        }

        $packageFiles = $this->packageFiles[$mode];

        //
        $slug = $this->data['slug'];

        if ($type == 'module') {
            $packagePath = $this->getModulePath($slug);
        } else if ($type == 'theme') {
            $packagePath = $this->getThemePath($slug);
        } else {
            $packagePath = $this->getPackagePath($slug);
        }

        foreach ($packageFiles as $key => $file) {
            $file = $this->formatContent($file);

            $this->files->put(
                $this->getDestinationFile($file, $packagePath), $this->getStubContent($key, $mode)
            );
        }

        // Générer les fichiers de langue
        $content ='<?php

return array (
);';

        $languageFolders = $this->getLanguagePaths($slug, $type);

        foreach ($languageFolders as $folder) {
            $path = $packagePath .$folder .DS .'messages.php';

            $this->files->put($path, $content);
        }
    }

    /**
     * Générez des fichiers .gitkeep dans les dossiers générés.
     *
     * @param string $type
     * @return void
     */
    protected function generateGitkeep($type)
    {
        $slug = $this->data['slug'];

        if ($type == 'module') {
            $packagePath = $this->getModulePath($slug);

            $mode = 'module';
        } else if ($type == 'theme') {
            $packagePath = $this->getThemePath($slug);

            $mode = 'theme';
        } else {
            $packagePath = $this->getPackagePath($slug);

            $mode = $this->option('extended') ? 'extended' : 'default';
        }

        $packageFolders = $this->packageFolders[$mode];

        foreach ($packageFolders as $folder) {
            $path = $packagePath .$folder;

            //
            $files = $this->files->glob($path .'/*');

            if(! empty($files)) continue;

            $gitkeep = $path .DS .'.gitkeep';

            $this->files->put($gitkeep, '');
        }
    }

    /**
     * Mettez à jour le composer.json et exécutez Composer.
     *
     * @param string $type
     * @return void
     */
    protected function updateComposerJson($type)
    {
        // Si le package généré est un Module.
        if ($type == 'module') {
            $namespace = 'Modules\\';

            $directory = 'modules/';
        }

        // Si le package généré est un Theme.
        else if ($type == 'theme') {
            $namespace = 'Themes\\';

            $directory = 'themes/';
        }

        // Traitement standard pour les Colis.
        else {
            $namespace = $this->data['namespace'] .'\\';

            $directory = 'packages/' . $this->data['name'] . "/src/";
        }

        $composerJson = getenv('COMPOSER') ?: 'composer.json';

        $path = base_path($composerJson);

        // Obtenez le contenu du composer.json sous une forme décodée.
        $config = json_decode(file_get_contents($path), true);

        if (! is_array($config)) {
            return;
        }

        // Mettre à jour le composer.json
        else if (! Arr::has($config, "autoload.psr-4.$namespace")) {
            Arr::set($config, "autoload.psr-4.$namespace", $directory);

            $output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

            file_put_contents($path, $output);
        }
    }

    /**
     * Obtenez le chemin d’accès au package.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function getPackagePath($slug = null)
    {
        if (! is_null($slug)) {
            return $this->packages->getPackagePath($slug);
        }

        return $this->packages->getPackagesPath();
    }

    /**
     * Obtenez le chemin d’accès au package.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function getModulePath($slug = null)
    {
        if (! is_null($slug)) {
            return $this->packages->getModulePath($slug);
        }

        return $this->packages->getModulesPath();
    }

    /**
     * Obtenez le chemin d’accès au package.
     *
     * @param string $slug
     *
     * @return string
     */
    protected function getThemePath($slug = null)
    {
        if (! is_null($slug)) {
            return $this->packages->getThemePath($slug);
        }

        return $this->packages->getThemesPath();
    }

    protected function getLanguagePaths($slug, $type)
    {
        $paths = array();

        $languages = $this->container['config']['languages'];

        foreach (array_keys($languages) as $code) {
            $path = 'Language' .DS .strtoupper($code);

            if ($type == 'package') {
                $path = 'src' .DS .$path;
            }

            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Obtenez le fichier de destination.
     *
     * @param string $file
     * @param string $packagePath
     *
     * @return string
     */
    protected function getDestinationFile($file, $packagePath)
    {
        $slug = $this->data['slug'];

        return $packagePath .$this->formatContent($file);
    }

    /**
     * Obtenez le contenu du stub par clé.
     *
     * @param int $key
     * @param bool $mode
     *
     * @return string
     */
    protected function getStubContent($key, $mode)
    {
        $packageStubs = $this->packageStubs[$mode];

        //
        $stub = $packageStubs[$key];

        $path = __DIR__ .DS .'stubs' .DS .$stub .'.stub';

        $content = $this->files->get($path);

        return $this->formatContent($content);
    }

    /**
     * Remplacez le texte de l'espace réservé par des valeurs correctes.
     *
     * @return string
     */
    protected function formatContent($content)
    {
        $searches = array(
            '{{type}}',
            '{{lower_type}}',
            '{{slug}}',
            '{{name}}',
            '{{namespace}}',
            '{{package}}',
            '{{lower_package}}',
            '{{author}}',
            '{{email}}',
            '{{homepage}}',
            '{{license}}',
            '{{json_namespace}}',
        );

        $replaces = array(
            $this->data['type'],
            $this->data['lower_type'],
            $this->data['slug'],
            $this->data['name'],
            $this->data['namespace'],
            $this->data['package'],
            $this->data['lower_package'],
            $this->data['author'],
            $this->data['email'],
            $this->data['homepage'],
            $this->data['license'],
            str_replace('/', '\\\\', $this->data['package']),
        );

        return str_replace($searches, $replaces, $content);
    }

    /**
     * Obtenez les arguments de la commande de la console.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'The name of the Package.'),
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
            array('--quick',    '-Q', InputOption::VALUE_NONE, 'Skip the make:package Wizard and use default values'),
            array('--extended', null, InputOption::VALUE_NONE, 'Generate an extended Package'),

            array('--type', null, InputOption::VALUE_REQUIRED, 'Generate an Application Module, Theme or Package', 'package'),
        );
    }
}
