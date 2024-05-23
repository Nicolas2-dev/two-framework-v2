<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Application\Middleware\Http;

use Closure;

use Two\Http\Exception\PostTooLargeException;


class VerifyPostSize
{
    /**
     * Gérer une demande entrante.
     *
     * @param  \Two\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Two\Http\Exception\PostTooLargeException
     */
    public function handle($request, Closure $next)
    {
        $contentLength = $request->server('CONTENT_LENGTH');

        if ($contentLength > $this->getPostMaxSize()) {
            throw new PostTooLargeException();
        }

        return $next($request);
    }

    /**
     * Déterminez le serveur 'post_max_size' en octets.
     *
     * @return int
     */
    protected function getPostMaxSize()
    {
        $postMaxSize = ini_get('post_max_size');

        switch (substr($postMaxSize, -1)) {
            case 'M':
            case 'm':
                return (int) $postMaxSize * 1048576;
            case 'K':
            case 'k':
                return (int) $postMaxSize * 1024;
            case 'G':
            case 'g':
                return (int) $postMaxSize * 1073741824;
        }

        return (int) $postMaxSize;
    }
}
