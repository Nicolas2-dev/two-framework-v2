<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View;

use Two\Application\Contracts\HtmlableInterface;


class Expression implements HtmlableInterface
{
    /**
     * La chaîne HTML.
     *
     * @var string
     */
    protected $html;


    /**
     * Créez une nouvelle instance de chaîne HTML.
     *
     * @param  string  $html
     * @return void
     */
    public function __construct($html)
    {
        $this->html = $html;
    }

    /**
     * Obtenez la chaîne HTML.
     *
     * @return string
     */
    public function toHtml()
    {
        return $this->html;
    }

    /**
     * Obtenez la chaîne HTML.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
    }
}
