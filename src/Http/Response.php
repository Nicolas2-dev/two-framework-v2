<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use ArrayObject;
use Exception;

use Two\Http\ResponseTrait;
use Two\Application\Contracts\JsonableInterface;
use Two\Application\Contracts\RenderableInterface;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class Response extends SymfonyResponse
{
    
    use ResponseTrait;

    /**
     * Le contenu original de la réponse.
     *
     * @var mixed
     */
    public $original;

   /**
     * L'exception qui a déclenché la réponse d'erreur (le cas échéant).
     *
     * @var \Exception|null
     */
    public $exception;


    /**
     * Définissez le contenu de la réponse.
     *
     * @param  mixed  $content
     * @return $this
     */
    public function setContent(mixed $content): static 
    {
        $this->original = $content;

        // Si le contenu est "JSONable", nous définirons l'en-tête approprié et convertirons
        // le contenu en JSON. Ceci est utile lors du retour de quelque chose comme des modèles
        // des routes qui seront automatiquement transformées en leur forme JSON.
        if ($this->shouldBeJson($content)) {
            $this->headers->set('Content-Type', 'application/json');

            $content = $this->morphToJson($content);
        }

        // Si ce contenu implémente la "RenderableInterface", alors nous appellerons la
        // rend la méthode sur l'objet afin d'éviter toute exception "__toString"
        // qui pourraient être lancées et dont les erreurs seraient masquées par la gestion de PHP.
        else if ($content instanceof RenderableInterface) {
            $content = $content->render();
        }

        return parent::setContent($content);
    }

    /**
     * Transformer le contenu donné en JSON.
     *
     * @param  mixed   $content
     * @return string
     */
    protected function morphToJson($content)
    {
        if ($content instanceof JsonableInterface) return $content->toJson();

        return json_encode($content);
    }

    /**
     * Déterminez si le contenu donné doit être transformé en JSON.
     *
     * @param  mixed  $content
     * @return bool
     */
    protected function shouldBeJson($content)
    {
        return ($content instanceof JsonableInterface) ||
               ($content instanceof ArrayObject) ||
               is_array($content);
    }

    /**
     * Obtenez le contenu de la réponse d'origine.
     *
     * @return mixed
     */
    public function getOriginalContent()
    {
        return $this->original;
    }

    /**
     * Définissez l'exception à joindre à la réponse.
     *
     * @param  \Exception  $e
     * @return $this
     */
    public function withException(Exception $e)
    {
        $this->exception = $e;

        return $this;
    }
}
