<?php


namespace MacFJA\Symfony\Console\Filechooser;


use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class FileFilter
 *
 * @method $this addAdapter(AdapterInterface $adapter, int $priority)
 * @method $this useBestAdapter()
 * @method $this setAdapter(string $name)
 * @method $this removeAdapters()
 * @method $this directories()
 * @method $this date(string $date)
 * @method $this size(string $size)
 * @method $this exclude(string|array $dirs)
 * @method $this ignoreDotFiles(bool $ignoreDotFiles)
 * @method $this ignoreVCS(bool $ignoreVCS)
 * @method $this sort(\Closure $closure)
 * @method $this sortByName()
 * @method $this sortByType()
 * @method $this sortByAccessedTime()
 * @method $this sortByChangedTime()
 * @method $this sortByModifiedTime()
 * @method $this followLinks()
 * @method $this ignoreUnreadableDirs(bool $ignore)
 *
 * @author MacFJA
 * @package MacFJA\Symfony\Console\Filechooser
 */
class FileFilter
{
    private $wrappedMethodHistory = array();
    private $question;
    private $attempts;
    private $validator;
    private $default;
    private $normalizer;

    /**
     * Constructor.
     *
     * @param string $question The question to ask to the user
     * @param mixed $default  The default answer to return if the user enters nothing
     */
    public function __construct($question, $default = null)
    {
        $this->question = $question;
        $this->default = $default;
    }

    /**
     * Returns the question.
     *
     * @return string
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * Returns the default answer.
     *
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Gets the validator for the question.
     *
     * @return null|callable
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Sets a validator for the question.
     *
     * @param null|callable $validator
     *
     * @return $this The current instance
     */
    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Sets the maximum number of attempts.
     *
     * Null means an unlimited number of attempts.
     *
     * @param null|int $attempts
     *
     * @return $this The current instance
     *
     * @throws \InvalidArgumentException In case the number of attempts is invalid.
     */
    public function setMaxAttempts($attempts)
    {
        if (null !== $attempts && $attempts < 1) {
            throw new \InvalidArgumentException('Maximum number of attempts must be a positive value.');
        }

        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Gets the maximum number of attempts.
     *
     * Null means an unlimited number of attempts.
     *
     * @return null|int
     */
    public function getMaxAttempts()
    {
        return $this->attempts;
    }

    /**
     * Gets the normalizer for the response.
     *
     * The normalizer can ba a callable (a string), a closure or a class implementing __invoke.
     *
     * @return string|\Closure
     */
    public function getNormalizer()
    {
        return $this->normalizer;
    }

    /**
     * Sets a normalizer for the response.
     *
     * The normalizer can be a callable (a string), a closure or a class implementing __invoke.
     *
     * @param string|\Closure $normalizer
     *
     * @return $this The current instance
     */
    public function setNormalizer($normalizer)
    {
        $this->normalizer = $normalizer;

        return $this;
    }

    /**
     * Get the list of directory and files that match the current partial path (or its parent directory)
     * @param string $partialPath
     * @return array
     */
    public function getResultFor($partialPath)
    {
        if (file_exists($partialPath) && is_dir($partialPath)) {
            $path = $partialPath;
        } else {
            $path = dirname($partialPath);
        }

        if (!file_exists($path) || !is_dir($path) || !is_readable($path)) {
            return array();
        }

        $finder = $this->newFinder()->depth(0)->in($path);

        $paths = array();
        foreach ($finder as $file) {
            /** @var SplFileInfo $file */
            $filePath = $file->getPathname();
            if ($file->isDir()) {
                $filePath .= DIRECTORY_SEPARATOR;
            }
            if ($path == '/' && $partialPath == '/') {
                $filePath = substr($filePath, 1);
            }
            $paths[] = $filePath;
        }
        return $paths;
    }

    /**
     * Return a new Symfony Finder configured
     * @return Finder
     */
    protected function newFinder()
    {
        $finder = new Finder();
        $this->finderWrapperInject($finder);
        return $finder;
    }

    /**
     * Configure a Symfony Finder
     * @param $finder
     */
    protected function finderWrapperInject($finder)
    {
        foreach ($this->wrappedMethodHistory as $row) {
            call_user_func_array(array($finder, $row['method']), $row['args']);
        }
    }

    /**
     * Magic function to wrapper some Symfony Finder method
     * @param string $name
     * @param array $arguments
     * @return $this
     * @throws \BadMethodCallException
     */
    function __call($name, $arguments)
    {
        $supportedFinderMethod = array(
            'addAdapter',
            'useBestAdapter',
            'setAdapter',
            'removeAdapters',
            'directories',
            'date', /*'name', 'notName',*/
            /*'contains', 'notContains', 'path', 'notPath',*/
            'size',
            'exclude',
            'ignoreDotFiles',
            'ignoreVCS',
            'sort',
            'sortByName',
            'sortByType',
            'sortByAccessedTime',
            'sortByChangedTime',
            'sortByModifiedTime', /* 'filter',*/
            'followLinks',
            'ignoreUnreadableDirs' /*, 'append'*/
        );

        if (!in_array($name, $supportedFinderMethod)) {
            throw new \BadMethodCallException();
        }
        $this->finderWrapperAdd($name, $arguments);
        return $this;
    }

    /**
     * Keep in memory Finder configuration
     * @param string $method
     * @param array $args
     */
    protected function finderWrapperAdd($method, $args)
    {
        $this->wrappedMethodHistory[] = array('method' => $method, 'args' => $args);
    }
}