<?php
/**
 * @author  Nicolas Devoy
 * @email   nicolas@Two-framework.fr 
 * @version 1.0.0
 * @date    15 mai 2024
 */
namespace Two\View\Compilers;

use Closure;

use Two\Support\Arr;
use Two\Support\Str;
use Two\View\Compilers\Compiler;
use Two\View\Contracts\Compilers\CompilerInterface;


class TemplateCompiler extends Compiler implements CompilerInterface
{

    /**
     * Toutes les extensions enregistrées.
     *
     * @var array
     */
    protected $extensions = array();

    /**
     * Le fichier est en cours de compilation.
     *
     * @var string
     */
    protected $path;

    /**
     * Toutes les fonctions disponibles du compilateur.
     *
     * @var array
     */
    protected $compilers = array(
        'Extensions',
        'Statements',
        'Comments',
        'Echos'
    );

    /**
     * Tableau de balises d'ouverture et de fermeture pour les échos échappés.
     *
     * @var array
     */
    protected $contentTags = array('{{', '}}');

    /**
     * Tableau de balises d'ouverture et de fermeture pour les échos échappés.
     *
     * @var array
     */
    protected $escapedTags = array('{{{', '}}}');

    /**
     * Tableau de lignes de pied de page à ajouter au modèle.
     *
     * @var array
     */
    protected $footer = array();

    /**
     * Espace réservé pour marquer temporairement la position des blocs verbatim.
     *
     * @var string
     */
    protected $verbatimPlaceholder = '@__verbatim__@';

    /**
     * Tableau pour stocker temporairement les blocs verbatim trouvés dans le modèle.
     *
     * @var array
     */
    protected $verbatimBlocks = [];

    /**
     * Compteur pour garder une trace des instructions forelse imbriquées.
     *
     * @var int
     */
    protected $forelseCounter = 0;


    /**
     * Compilez la vue sur le chemin donné.
     *
     * @param  string  $path
     * @return void
     */
    public function compile($path = null)
    {
        if (! is_null($path)) {
            $this->setPath($path);
        }

        $contents = $this->compileString($this->files->get($path));

        if ( ! is_null($this->cachePath)) {
            $this->files->put($this->getCompiledPath($this->getPath()), $contents);
        }
    }

    /**
     * Obtenez le chemin en cours de compilation.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Définissez le chemin en cours de compilation.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Compilez le contenu du modèle de modèle donné.
     *
     * @param  string  $value
     * @return string
     */
    public function compileString($value)
    {
        $result = '';

        if (strpos($value, '@verbatim') !== false) {
            $value = $this->storeVerbatimBlocks($value);
        }

        $this->footer = array();

        // Ici, nous allons parcourir tous les jetons renvoyés par le lexer Zend et analyser chacun d'eux dans le PHP valide correspondant.
        // Nous aurons alors ce modèle comme PHP correctement rendu et pouvant être rendu nativement.
        foreach (token_get_all($value) as $token) {
            $result .= is_array($token) ? $this->parseToken($token) : $token;
        }

        if (! empty($this->verbatimBlocks)) {
            $result = $this->restoreVerbatimBlocks($result);
        }

        // Si des lignes de pied de page doivent être ajoutées à un modèle, nous les ajouterons ici à la fin du modèle.
        // Ceci est utilisé principalement pour l'héritage du modèle via le mot-clé extends qui doit être ajouté.
        if (count($this->footer) > 0) {
            $result = ltrim($result, PHP_EOL) .PHP_EOL .implode(PHP_EOL, array_reverse($this->footer));
        }

        return $result;
    }

    /**
     * Stockez les blocs verbatim et remplacez-les par un espace réservé temporaire.
     *
     * @param  string  $value
     * @return string
     */
    protected function storeVerbatimBlocks($value)
    {
        return preg_replace_callback('/(?<!@)@verbatim(.*?)@endverbatim/s', function ($matches) {
            $this->verbatimBlocks[] = $matches[1];

            return $this->verbatimPlaceholder;
        }, $value);
    }

    /**
     * Remplacez les espaces réservés bruts par le code d'origine stocké dans les blocs bruts.
     *
     * @param  string  $result
     * @return string
     */
    protected function restoreVerbatimBlocks($result)
    {
        $result = preg_replace_callback('/' .preg_quote($this->verbatimPlaceholder) .'/', function ()
        {
            return array_shift($this->verbatimBlocks);

        }, $result);

        $this->verbatimBlocks = [];

        return $result;
    }

    /**
     * Analysez les jetons du modèle.
     *
     * @param  array  $token
     * @return string
     */
    protected function parseToken($token)
    {
        list($id, $content) = $token;

        if ($id == T_INLINE_HTML) {
            foreach ($this->compilers as $type) {
                $method = 'compile' .$type;

                $content = call_user_func(array($this, $method), $content);
            }
        }

        return $content;
    }

    /**
     * Exécutez les extensions définies par l'utilisateur.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileExtensions($value)
    {
        foreach ($this->extensions as $compiler) {
            $value = call_user_func($compiler, $value, $this);
        }

        return $value;
    }

    /**
     * Compilez les commentaires du modèle en PHP valide.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileComments($value)
    {
        $pattern = sprintf('/%s--((.|\s)*?)--%s/', $this->contentTags[0], $this->contentTags[1]);

        return preg_replace($pattern, '<?php /*$1*/ ?>', $value);
    }

    /**
     * Compilez les échos du modèle dans un PHP valide.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEchos($value)
    {
        $difference = strlen($this->contentTags[0]) - strlen($this->escapedTags[0]);

        if ($difference > 0) {
            return $this->compileEscapedEchos($this->compileRegularEchos($value));
        }

        return $this->compileRegularEchos($this->compileEscapedEchos($value));
    }

    /**
     * Compilez les instructions de modèle commençant par « @ »
     *
     * @param  string  $value
     * @return mixed
     */
    protected function compileStatements($value)
    {
        $callback = function($match)
        {
            if (method_exists($this, $method = 'compile' .ucfirst($match[1]))) {
                $match[0] = call_user_func(array($this, $method), Arr::get($match, 3));
            }

            return isset($match[3]) ? $match[0] : $match[0] .$match[2];
        };

        return preg_replace_callback('/\B@(\w+)([ \t]*)(\( ( (?>[^()]+) | (?3) )* \))?/x', $callback, $value);
    }

    /**
     * Compilez les instructions d'écho "régulières".
     *
     * @param  string  $value
     * @return string
     */
    protected function compileRegularEchos($value)
    {
        $pattern = sprintf('/(@)?%s\s*(.+?)\s*%s(\r?\n)?/s', $this->contentTags[0], $this->contentTags[1]);

        $callback = function($matches)
        {
            $whitespace = empty($matches[3]) ? '' : $matches[3] .$matches[3];

            return $matches[1] ? substr($matches[0], 1) : '<?php echo ' .$this->compileEchoDefaults($matches[2]) .'; ?>' .$whitespace;
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compilez les instructions d'écho échappées.
     *
     * @param  string  $value
     * @return string
     */
    protected function compileEscapedEchos($value)
    {
        $pattern = sprintf('/%s\s*(.+?)\s*%s(\r?\n)?/s', $this->escapedTags[0], $this->escapedTags[1]);

        $callback = function($matches)
        {
            $whitespace = empty($matches[2]) ? '' : $matches[2] .$matches[2];

            return '<?php echo e('.$this->compileEchoDefaults($matches[1]).'); ?>'.$whitespace;
        };

        return preg_replace_callback($pattern, $callback, $value);
    }

    /**
     * Compilez les valeurs par défaut pour l'instruction echo.
     *
     * @param  string  $value
     * @return string
     */
    public function compileEchoDefaults($value)
    {
        return preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $value);
    }

    /**
     * Compilez chaque instruction en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEach($expression)
    {
        return "<?php echo \$__env->renderEach{$expression}; ?>";
    }

    /**
     * Compilez les instructions de rendement en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileYield($expression)
    {
        return "<?php echo \$__env->yieldContent{$expression}; ?>";
    }

    /**
     * Compilez les instructions show en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileShow($expression)
    {
        return "<?php echo \$__env->yieldSection(); ?>";
    }

    /**
     * Compilez les instructions de section en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileSection($expression)
    {
        return "<?php \$__env->startSection{$expression}; ?>";
    }

    /**
     * Compilez les instructions append en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileAppend($expression)
    {
        return "<?php \$__env->appendSection(); ?>";
    }

    /**
     * Compilez les instructions de fin de section en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndsection($expression)
    {
        return "<?php \$__env->stopSection(); ?>";
    }

    /**
     * Compilez les instructions stop en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStop($expression)
    {
        return "<?php \$__env->stopSection(); ?>";
    }

    /**
     * Compilez les instructions d'écrasement en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileOverwrite($expression)
    {
        return "<?php \$__env->stopSection(true); ?>";
    }

    /**
     * Compilez les instructions à moins que ce soit en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnless($expression)
    {
        return "<?php if ( ! $expression): ?>";
    }

    /**
     * Compilez les instructions de fin à moins que ce soit en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndunless($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * Compilez les instructions else en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElse($expression)
    {
        return "<?php else: ?>";
    }

    /**
     * Compilez les instructions for en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileFor($expression)
    {
        return "<?php for{$expression}: ?>";
    }

    /**
     * Compilez les instructions foreach en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForeach($expression)
    {
        return "<?php foreach{$expression}: ?>";
    }

    /**
     * Compilez les instructions break en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileBreak($expression)
    {
        return $expression ? "<?php if{$expression} break; ?>" : '<?php break; ?>';
    }

    /**
     * Compilez les instructions continue en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileContinue($expression)
    {
        return $expression ? "<?php if{$expression} continue; ?>" : '<?php continue; ?>';
    }

    /**
     * Compilez les instructions forelse en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileForelse($expression)
    {
        $empty = '$__empty_' . ++$this->forelseCounter;

        return "<?php {$empty} = true; foreach{$expression}: {$empty} = false; ?>";
    }

    /**
     * Compilez les instructions can en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCan($expression)
    {
        return "<?php if (app('Two\\Auth\\Contracts\\Access\\GateInterface')->check{$expression}): ?>";
    }

    /**
     * Compilez les instructions impossible en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCannot($expression)
    {
        return "<?php if (app('Two\\Auth\\Contracts\\Access\\GateInterface')->denies{$expression}): ?>";
    }

    /**
     * Compilez les instructions if en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileIf($expression)
    {
        return "<?php if{$expression}: ?>";
    }

    /**
     * Compilez les instructions else-if en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElseif($expression)
    {
        return "<?php elseif{$expression}: ?>";
    }

    /**
     * Compilez les instructions forelse en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEmpty($expression)
    {
        $empty = '$__empty_' . $this->forelseCounter--;

        return "<?php endforeach; if ({$empty}): ?>";
    }

    /**
     * Compilez les instructions de la section has en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileHasSection($expression)
    {
        return "<?php if (! empty(trim(\$__env->yieldContent{$expression}))): ?>";
    }

    /**
     * Compilez les instructions while en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileWhile($expression)
    {
        return "<?php while{$expression}: ?>";
    }

    /**
     * Compilez les instructions de fin de période en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndwhile($expression)
    {
        return "<?php endwhile; ?>";
    }

    /**
     * Compilez les instructions de fin en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndfor($expression)
    {
        return "<?php endfor; ?>";
    }

    /**
     * Compilez les instructions de fin pour chaque en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndforeach($expression)
    {
        return "<?php endforeach; ?>";
    }

    /**
     * Compilez les instructions end-can en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndcan($expression)
    {
        return '<?php endif; ?>';
    }

    /**
     * Compilez les instructions end-cannot en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndcannot($expression)
    {
        return '<?php endif; ?>';
    }

    /**
     * Compilez les instructions end-if en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndif($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * Compilez les instructions de fin pour le reste en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndforelse($expression)
    {
        return "<?php endif; ?>";
    }

    /**
     * Compilez les instructions PHP brutes en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePhp($expression)
    {
        return ! empty($expression) ? "<?php {$expression}; ?>" : '<?php ';
    }

    /**
     * Compilez l'instruction end-php en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndphp($expression)
    {
        return ' ?>';
    }

    /**
     * Compilez les instructions non définies en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnset($expression)
    {
        return "<?php unset{$expression}; ?>";
    }

    /**
     * Compilez les instructions extends en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileExtends($expression)
    {
        $expression = $this->stripParentheses($expression);

        $data = "<?php echo \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";

        $this->footer[] = $data;

        return '';
    }

    /**
     * Compilez les instructions include en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileInclude($expression)
    {
        $expression = $this->stripParentheses($expression);

        return "<?php echo \$__env->make($expression, array_except(get_defined_vars(), array('__data', '__path')))->render(); ?>";
    }

    /**
     * Compilez les instructions de pile dans le contenu
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileStack($expression)
    {
        return "<?php echo \$__env->yieldContent{$expression}; ?>";
    }

    /**
     * Compilez les instructions push en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePush($expression)
    {
        return "<?php \$__env->startSection{$expression}; ?>";
    }

    /**
     * Compilez les instructions endpush en PHP valide.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileEndpush($expression)
    {
        return "<?php \$__env->appendSection(); ?>";
    }

    /**
     * Supprimez les parenthèses de l’expression donnée.
     *
     * @param  string  $expression
     * @return string
     */
    public function stripParentheses($expression)
    {
        if (Str::startsWith($expression, '(') && Str::endsWith($expression, ')')) {
            $expression = substr($expression, 1, -1);
        }

        return $expression;
    }

    /**
     * Enregistrez un compilateur de modèles personnalisé.

     *
     * @param  \Closure  $compiler
     * @return void
     */
    public function extend(Closure $compiler)
    {
        $this->extensions[] = $compiler;
    }

    /**
     * Obtenez l’expression régulière d’une fonction Template générique.
     *
     * @param  string  $function
     * @return string
     */
    public function createMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*\))/';
    }

    /**
     * Obtenez l’expression régulière d’une fonction Template générique.
     *
     * @param  string  $function
     * @return string
     */
    public function createOpenMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*)\)/';
    }

    /**
     * Créez un simple modèle de correspondance.
     *
     * @param  string  $function
     * @return string
     */
    public function createPlainMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*)/';
    }

    /**
     * Définit les balises de contenu utilisées pour le compilateur.
     *
     * @param  string  $openTag
     * @param  string  $closeTag
     * @param  bool    $escaped
     * @return void
     */
    public function setContentTags($openTag, $closeTag, $escaped = false)
    {
        $property = ($escaped === true) ? 'escapedTags' : 'contentTags';

        $this->{$property} = array(preg_quote($openTag), preg_quote($closeTag));
    }

    /**
     * Définit les balises de contenu échappées utilisées pour le compilateur.
     *
     * @param  string  $openTag
     * @param  string  $closeTag
     * @return void
     */
    public function setEscapedContentTags($openTag, $closeTag)
    {
        $this->setContentTags($openTag, $closeTag, true);
    }

    /**
     * Obtient les balises de contenu utilisées pour le compilateur.
     *
     * @return string
     */
    public function getContentTags()
    {
        return $this->getTags();
    }

    /**
     * Obtient les balises de contenu échappées utilisées pour le compilateur.
     *
     * @return string
     */
    public function getEscapedContentTags()
    {
        return $this->getTags(true);
    }

    /**
     * Obtient les balises utilisées pour le compilateur.
     *
     * @param  bool  $escaped
     * @return array
     */
    protected function getTags($escaped = false)
    {
        $tags = $escaped ? $this->escapedTags : $this->contentTags;

        return array_map('stripcslashes', $tags);
    }

}
