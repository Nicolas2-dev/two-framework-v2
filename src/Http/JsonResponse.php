<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Http;

use Two\Http\ResponseTrait;
use Two\Application\Contracts\JsonableInterface;

use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;


class JsonResponse extends SymfonyJsonResponse
{
    use ResponseTrait;

    /**
     * Les options d'encodage json.
     *
     * @var int
     */
    protected $jsonOptions;

    /**
     * Constructeur.
     *
     * @param  mixed  $data
     * @param  int    $status
     * @param  array  $headers
     * @param  int    $options
    */
    public function __construct($data = null, $status = 200, $headers = array(), $options = 0)
    {
        $this->jsonOptions = $options;

        parent::__construct($data, $status, $headers);
    }

    /**
     * Récupérez les données json_decoded de la réponse
     *
     * @param  bool  $assoc
     * @param  int   $depth
     * @return mixed
     */
    public function getData($assoc = false, $depth = 512)
    {
        return json_decode($this->data, $assoc, $depth);
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data = array()): static
    {
        if ($data instanceof JsonableInterface) {
            $this->data = $data->toJson($this->jsonOptions);
        } else {
            $this->data = json_encode($data, $this->jsonOptions);
        }

        return $this->update();
    }

    /**
     * Obtenez les options d'encodage JSON.
     *
     * @return int
     */
    public function getJsonOptions()
    {
        return $this->jsonOptions;
    }

    /**
     * Définissez les options d'encodage JSON.
     *
     * @param  int  $options
     * @return mixed
     */
    public function setJsonOptions($options)
    {
        $this->jsonOptions = $options;

        return $this->setData($this->getData());
    }

}
