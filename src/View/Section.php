<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Two\View\Factory;


class Section
{
    /**
     * @var Two\View\Factory
     */
    protected $factory;


    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Commencez à injecter du contenu dans une section.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    public function start($section, $content = '')
    {
        $this->factory->startSection($section, $content);
    }

    /**
     * Arrêtez d'injecter du contenu dans une section et affichez son contenu.
     *
     * @return string
     */
    public function show()
    {
        echo $this->factory->yieldSection();
    }

    /**
     * Arrêtez d'injecter du contenu dans une section et renvoyez son contenu.
     *
     * @return string
     */
    public function fetch()
    {
        return $this->factory->yieldSection();
    }

    /**
     * Arrêtez d’injecter du contenu dans une section.
     *
     * @return string
     */
    public function end()
    {
        return $this->factory->stopSection();
    }

    /**
     * Arrêtez d’injecter du contenu dans une section.
     *
     * @return string
     */
    public function stop()
    {
        return $this->factory->stopSection();
    }

    /**
     * Arrêtez d’injecter du contenu dans une section.
     *
     * @return string
     */
    public function overwrite()
    {
        return $this->factory->stopSection(true);
    }

    /**
     * Arrêtez d’injecter du contenu dans une section et ajoutez-le.
     *
     * @return string
     */
    public function append()
    {
        return $this->factory->appendSection();
    }

    /**
     * Obtenez le contenu de la chaîne d'une section.
     *
     * @param  string  $section
     * @param  string  $default
     * @return string
     */
    public function render($section, $default = '')
    {
        return $this->factory->yieldContent($section, $default);
    }
}
