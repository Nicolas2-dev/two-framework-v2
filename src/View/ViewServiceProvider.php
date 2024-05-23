<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Two\View\Factory;
use Two\View\Section;
use Two\View\FileViewFinder;
use Two\View\Engines\PhpEngine;
use Two\View\Engines\FileEngine;
use Two\View\Engines\CompilerEngine;
use Two\View\Engines\EngineResolver;
use Two\View\Compilers\MarkdownCompiler;
use Two\View\Compilers\TemplateCompiler;

use Two\Application\Providers\ServiceProvider;


class ViewServiceProvider extends ServiceProvider
{
    /**
     * Indique si le chargement du Provider est différé.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Enregistrez le fournisseur de services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerEngineResolver();

        $this->registerViewFinder();

        $this->registerFactory();

        $this->registerSection();
    }

    /**
     * Enregistrez l’instance de résolveur de moteur.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $this->app->singleton('view.engine.resolver', function($app)
        {
            $resolver = new EngineResolver();

            foreach (array('php', 'template', 'markdown', 'file') as $engine) {
                $method = 'register' .ucfirst($engine) .'Engine';

                call_user_func(array($this, $method), $resolver);
            }

            return $resolver;
        });
    }

    /**
     * Enregistrez l'implémentation du moteur PHP.
     *
     * @param  \Two\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerPhpEngine($resolver)
    {
        $resolver->register('php', function()
        {
            return new PhpEngine();
        });
    }

    /**
     * Enregistrez l’implémentation du moteur de modèle.
     *
     * @param  \Two\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerTemplateEngine($resolver)
    {
        $app = $this->app;

        // Le moteur du compilateur nécessite une instance de CompilerInterface, qui dans
        // ce cas sera le compilateur Template, nous allons donc d'abord créer le compilateur
        // instance à transmettre au moteur afin qu'il puisse compiler les vues correctement.
        $app->singleton('template.compiler', function($app)
        {
            $cachePath = $app['config']['view.compiled'];

            return new TemplateCompiler($app['files'], $cachePath);
        });

        $resolver->register('template', function() use ($app)
        {
            return new CompilerEngine($app['template.compiler'], $app['files']);
        });
    }

    /**
     * Enregistrez l’implémentation du moteur Markdown.
     *
     * @param  \Two\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerMarkdownEngine($resolver)
    {
        $app = $this->app;

        // Le moteur du compilateur nécessite une instance de CompilerInterface, qui dans
        // ce cas sera le compilateur Markdown, nous allons donc d'abord créer le compilateur
        // instance à transmettre au moteur afin qu'il puisse compiler les vues correctement.
        $app->singleton('markdown.compiler', function($app)
        {
            $cachePath = $app['config']['view.compiled'];

            return new MarkdownCompiler($app['files'], $cachePath);
        });

        $resolver->register('markdown', function() use ($app)
        {
            return new CompilerEngine($app['markdown.compiler'], $app['files']);
        });
    }

    /**
     * Enregistrez l’implémentation du moteur de fichiers.
     *
     * @param  \Two\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerFileEngine($resolver)
    {
        $resolver->register('file', function()
        {
            return new FileEngine();
        });
    }

    /**
     * Enregistrez View Factory.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->singleton('view', function($app)
        {
            // Ensuite, nous devons récupérer l'instance de résolveur de moteur qui sera utilisée par le
            // environnement. Le résolveur sera utilisé par un environnement pour obtenir chacun des
            // les différentes implémentations de moteurs tels que PHP simple ou moteur de modèles.
            $resolver = $app['view.engine.resolver'];

            $finder = $app['view.finder'];

            $factory = new Factory($resolver, $finder, $app['events']);

            // Nous définirons également l'instance de conteneur sur cet environnement de vue puisque le
            // les compositeurs de vues peuvent être des classes enregistrées dans le conteneur, ce qui permet
            // pour d'excellents compositeurs testables et flexibles pour le développeur d'applications.
            $factory->setContainer($app);

            $factory->share('app', $app);

            return $factory;
        });
    }

    /**
     * Enregistrez l’implémentation du viseur.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->singleton('view.finder', function($app)
        {
            $paths = $app['config']->get('view.paths', array());

            return new FileViewFinder($app['files'], $paths);
        });
    }

    /**
     * Enregistrez l’instance de View Section.
     *
     * @return void
     */
    public function registerSection()
    {
        $this->app->singleton('view.section', function($app)
        {
            return new Section($app['view']);
        });
    }

    /**
     * Obtenez les services fournis par le fournisseur.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'view', 'view.finder', 'view.engine.resolver',
            'template', 'template.compiler',
            'markdown', 'markdown.compiler',
            'section'
        );
    }
}
