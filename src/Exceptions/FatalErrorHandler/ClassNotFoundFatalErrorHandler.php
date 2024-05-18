<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\Exceptions\FatalErrorHandler;

use Throwable;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use Two\Exceptions\Debug\DebugClassLoader;
use Two\Exceptions\Exception\FatalErrorException;
use Two\Exceptions\Exception\ClassNotFoundException;
use Two\Exceptions\Contracts\FatalErrorHandlerInterface;

use Composer\Autoload\ClassLoader as ComposerClassLoader;


/**
 * ErrorHandler pour les classes qui n’existent pas.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassNotFoundFatalErrorHandler implements FatalErrorHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleError(array $error, FatalErrorException $exception)
    {
        $messageLen = \strlen($error['message']);
        $notFoundSuffix = '\' not found';
        $notFoundSuffixLen = \strlen($notFoundSuffix);
        if ($notFoundSuffixLen > $messageLen) {
            return;
        }

        if (0 !== substr_compare($error['message'], $notFoundSuffix, -$notFoundSuffixLen)) {
            return;
        }

        foreach (['class', 'interface', 'trait'] as $typeName) {
            $prefix = ucfirst($typeName).' \'';
            $prefixLen = \strlen($prefix);
            if (0 !== strpos($error['message'], $prefix)) {
                continue;
            }

            $fullyQualifiedClassName = substr($error['message'], $prefixLen, -$notFoundSuffixLen);
            if (false !== $namespaceSeparatorIndex = strrpos($fullyQualifiedClassName, '\\')) {
                $className = substr($fullyQualifiedClassName, $namespaceSeparatorIndex + 1);
                $namespacePrefix = substr($fullyQualifiedClassName, 0, $namespaceSeparatorIndex);
                $message = sprintf('Attempted to load %s "%s" from namespace "%s".', $typeName, $className, $namespacePrefix);
                $tail = ' for another namespace?';
            } else {
                $className = $fullyQualifiedClassName;
                $message = sprintf('Attempted to load %s "%s" from the global namespace.', $typeName, $className);
                $tail = '?';
            }

            if ($candidates = $this->getClassCandidates($className)) {
                $tail = array_pop($candidates).'"?';
                if ($candidates) {
                    $tail = ' for e.g. "'.implode('", "', $candidates).'" or "'.$tail;
                } else {
                    $tail = ' for "'.$tail;
                }
            }
            $message .= "\nDid you forget a \"use\" statement".$tail;

            return new ClassNotFoundException($message, $exception);
        }
    }

    /**
     * Essaie de deviner l'espace de noms complet pour un nom de classe donné.
     *
     * Par défaut, il recherche les classes PSR-0 et PSR-4 enregistrées via un Symfony ou un Composer
     * chargeur automatique (qui devrait couvrir tous les cas courants).
     *
     * @param string $class Un nom de classe (sans son espace de noms)
     *
     * @return array Un tableau de noms de classe complets possibles
     */
    private function getClassCandidates(string $class): array
    {
        if (!\is_array($functions = spl_autoload_functions())) {
            return [];
        }

        // trouver les chargeurs automatiques Composer
        $classes = [];

        foreach ($functions as $function) {
            if (!\is_array($function)) {
                continue;
            }
            // obtenir les chargeurs de classes enveloppés par DebugClassLoader
            if ($function[0] instanceof DebugClassLoader) {
                $function = $function[0]->getClassLoader();

                if (!\is_array($function)) {
                    continue;
                }
            }

            if ($function[0] instanceof ComposerClassLoader) {
                foreach ($function[0]->getPrefixesPsr4() as $prefix => $paths) {
                    foreach ($paths as $path) {
                        $classes = array_merge($classes, $this->findClassInPath($path, $class, $prefix));
                    }
                }
            }
        }

        return array_unique($classes);
    }

    private function findClassInPath(string $path, string $class, string $prefix): array
    {
        if (!$path = realpath($path.'/'.strtr($prefix, '\\_', '//')) ?: realpath($path.'/'.\dirname(strtr($prefix, '\\_', '//'))) ?: realpath($path)) {
            return [];
        }

        $classes = [];
        $filename = $class.'.php';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if ($filename == $file->getFileName() && $class = $this->convertFileToClass($path, $file->getPathName(), $prefix)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function convertFileToClass(string $path, string $file, string $prefix): ?string
    {
        $candidates = [
            // classe avec espace de noms
            $namespacedClass = str_replace([$path.\DIRECTORY_SEPARATOR, '.php', '/'], ['', '', '\\'], $file),
            // classe avec espace de noms (avec répertoire cible)
            $prefix.$namespacedClass,
            // classe avec espace de noms (avec répertoire cible et séparateur)
            $prefix.'\\'.$namespacedClass,
            // Classe PEAR
            str_replace('\\', '_', $namespacedClass),
            // classe PEAR (avec répertoire cible)
            str_replace('\\', '_', $prefix.$namespacedClass),
            // classe PEAR (avec répertoire cible et séparateur)
            str_replace('\\', '_', $prefix.'\\'.$namespacedClass),
        ];

        if ($prefix) {
            $candidates = array_filter($candidates, function ($candidate) use ($prefix) { return 0 === strpos($candidate, $prefix); });
        }

        // Nous ne pouvons pas utiliser le chargeur automatique ici car la plupart d'entre eux l'utilisent ; mais si la classe
        // n'est pas trouvé, le nouvel appel du chargeur automatique nécessitera à nouveau le fichier, ce qui mènera à un
        // Erreur "impossible de redéclarer la classe".
        foreach ($candidates as $candidate) {
            if ($this->classExists($candidate)) {
                return $candidate;
            }
        }

        try {
            require_once $file;
        } catch (Throwable $e) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if ($this->classExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function classExists(string $class): bool
    {
        return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
    }
}
