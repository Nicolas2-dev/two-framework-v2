<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Routing\Response;

use Two\Http\Response;
use Two\Http\JsonResponse;
use Two\Support\Traits\MacroableTrait;
use Two\Support\Str;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class ResponseFactory
{
    use MacroableTrait;


    /**
     * Renvoie une nouvelle réponse de l'application.
     *
     * @param  string  $content
     * @param  int  $status
     * @param  array  $headers
     * @return \Two\Http\Response
     */
    public function make($content = '', $status = 200, array $headers = array())
    {
        return new Response($content, $status, $headers);
    }

    /**
     * Renvoie une nouvelle réponse JSON de l'application.
     *
     * @param  mixed  $data
     * @param  int  $status
     * @param  array  $headers
     * @param  int  $options
     * @return \Two\Http\JsonResponse
     */
    public function json($data = array(), $status = 200, array $headers = array(), $options = 0)
    {
        return new JsonResponse($data, $status, $headers, $options);
    }

    /**
     * Renvoie une nouvelle réponse JSONP de l'application.
     *
     * @param  string  $callback
     * @param  mixed  $data
     * @param  int  $status
     * @param  array  $headers
     * @param  int  $options
     * @return \Two\Http\JsonResponse
     */
    public function jsonp($callback, $data = array(), $status = 200, array $headers = array(), $options = 0)
    {
        return $this->json($data, $status, $headers, $options)->setCallback($callback);
    }

    /**
     * Renvoie une nouvelle réponse diffusée en continu depuis l'application.
     *
     * @param  \Closure  $callback
     * @param  int  $status
     * @param  array  $headers
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function stream($callback, $status = 200, array $headers = array())
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Créez une nouvelle réponse de téléchargement de fichier.
     *
     * @param  \SplFileInfo|string  $file
     * @param  string  $name
     * @param  array  $headers
     * @param  string|null  $disposition
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($file, $name = null, array $headers = array(), $disposition = 'attachment')
    {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (! is_null($name)) {
            return $response->setContentDisposition($disposition, $name, str_replace('%', '', Str::ascii($name)));
        }

        return $response;
    }

    /**
     * Renvoie le contenu brut d'un fichier binaire.
     *
     * @param  \SplFileInfo|string  $file
     * @param  array  $headers
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function file($file, array $headers = array())
    {
        return new BinaryFileResponse($file, 200, $headers);
    }
}
