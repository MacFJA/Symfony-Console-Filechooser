<?php

namespace MacFJA\Symfony\Console\Filechooser;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class FilechooserHelper
 *
 * @author  MacFJA
 * @license MIT
 * @package MacFJA\Symfony\Console\Filechooser
 */
class FilechooserHelper extends Helper
{

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     *
     * @api
     */
    public function getName()
    {
        return 'filechooser';
    }

    private $inputStream;

    /**
     * Asks a question to the user.
     *
     * @param InputInterface $input    An InputInterface instance
     * @param OutputInterface $output   An OutputInterface instance
     * @param FileFilter $filter The question to ask
     *
     * @return string The user answer
     *
     * @throws \RuntimeException If there is no data to read in the input stream
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    public function ask(InputInterface $input, OutputInterface $output, FileFilter $filter)
    {
        if (!$input->isInteractive()) {
            return $filter->getDefault();
        }

        if (!$filter->getValidator()) {
            return $this->doAsk($output, $filter);
        }

        $that = $this;

        $interviewer = function () use ($output, $filter, $that) {
            return $that->doAsk($output, $filter);
        };

        return $this->validateAttempts($interviewer, $output, $filter);
    }

    /**
     * Sets the input stream to read from when interacting with the user.
     *
     * This is mainly useful for testing purpose.
     *
     * @param resource $stream The input stream
     *
     * @throws \InvalidArgumentException In case the stream is not a resource
     */
    public function setInputStream($stream)
    {
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('Input stream must be a valid resource.');
        }

        $this->inputStream = $stream;
    }

    /**
     * Returns the helper's input stream
     *
     * @return resource
     */
    public function getInputStream()
    {
        return $this->inputStream;
    }


    /**
     * Asks the question to the user.
     *
     * This method is public for PHP 5.3 compatibility, it should be private.
     *
     * @param OutputInterface $output
     * @param FileFilter $filter
     *
     * @return bool|mixed|null|string
     *
     * @throws \RuntimeException
     */
    public function doAsk(OutputInterface $output, FileFilter $filter)
    {
        $inputStream = $this->inputStream ? : STDIN;

        $message = $filter->getQuestion();

        $output->write($message);


        $ret = trim($this->autocomplete($output, $filter, $inputStream));

        $ret = strlen($ret) > 0 ? $ret : $filter->getDefault();

        if ($normalizer = $filter->getNormalizer()) {
            return $normalizer($ret);
        }

        return $ret;
    }

    /**
     * Autocompletes a question.
     *
     * @param OutputInterface $output
     * @param FileFilter $filter
     * @param $inputStream
     *
     * @return string
     */
    private function autocomplete(OutputInterface $output, FileFilter $filter, $inputStream)
    {
        $autocomplete = $filter->getResultFor($filter->getDefault());
        $ret = $filter->getDefault();

        $i = strlen($ret);
        $ofs = 0;
        $matches = $autocomplete;
        $numMatches = count($matches);

        $sttyMode = shell_exec('stty -g');

        // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
        shell_exec('stty -icanon -echo');

        // Add highlighted text style
        $output->getFormatter()->setStyle('hl', new OutputFormatterStyle('black', 'white'));

        $output->write($ret);
        $this->searchCompletion($ret, $filter, $ofs, $matches, $numMatches);
        $this->displaySuggestion($output, $matches, $numMatches, $ofs, $i);

        // Read a keypress
        while (!feof($inputStream)) {
            $c = fread($inputStream, 1);

            // Backspace Character
            if ("\177" === $c) {
                if (0 === $numMatches && 0 !== $i) {
                    $i--;
                    // Move cursor backwards
                    $output->write("\033[1D");
                }

                if ($i === 0) {
                    $ofs = -1;
                    $matches = $filter->getResultFor($ret);
                    $numMatches = count($matches);
                } else {
                    $matches = array();
                    $numMatches = 0;
                }

                // Pop the last character off the end of our string
                $ret = substr($ret, 0, $i);
            } elseif ("\033" === $c) {
                // Did we read an escape sequence?
                $c .= fread($inputStream, 2);

                // A = Up Arrow. B = Down Arrow
                if (isset($c[2]) && ('A' === $c[2] || 'B' === $c[2])) {
                    if ('A' === $c[2] && -1 === $ofs) {
                        $ofs = 0;
                    }

                    if (0 === $numMatches) {
                        continue;
                    }

                    $ofs += ('A' === $c[2]) ? -1 : 1;
                    $ofs = ($numMatches + $ofs) % $numMatches;
                }
            } elseif (ord($c) < 32) {
                if ("\t" === $c || "\n" === $c) {
                    if ($numMatches > 0 && -1 !== $ofs) {
                        $ret = $matches[$ofs];
                        // Echo out remaining chars for current match
                        $output->write(substr($ret, $i));
                        $i = strlen($ret);
                        $this->searchCompletion($ret, $filter, $ofs, $matches, $numMatches);
                    }

                    if ("\n" === $c) {
                        $output->write($c);
                        break;
                    }

                    //$numMatches = 0;
                }
                if ("\t" !== $c) {
                    //continue;
                }
            } else {
                $output->write($c);
                $ret .= $c;
                $i++;
                $this->searchCompletion($ret, $filter, $ofs, $matches, $numMatches);
            }

            $this->displaySuggestion($output, $matches, $numMatches, $ofs, $i);
        }

        // Reset stty so it behaves normally again
        shell_exec(sprintf('stty %s', $sttyMode));

        return $ret;
    }

    /**
     * Find all valid completion
     * @param string $partial
     * @param FileFilter $filter
     * @param int $ofs
     * @param array $matches
     * @param int $numMatches
     */
    private function searchCompletion($partial, $filter, &$ofs, &$matches, &$numMatches)
    {
        $ret = $partial;
        $i = strlen($ret);

        $numMatches = 0;
        $matches = array();
        $ofs = 0;

        $autocomplete = $filter->getResultFor($ret);

        foreach ($autocomplete as $value) {
            // If typed characters match the beginning chunk of value (e.g. [AcmeDe]moBundle)
            if (0 === strpos($value, $ret) && $i !== strlen($value)) {
                $matches[$numMatches++] = $value;
            }
        }
    }

    /**
     * Display the suggestion $ofs of the list matches
     * @param OutputInterface $output
     * @param array $matches
     * @param int $numMatches
     * @param int $ofs
     * @param int $partialLength
     */
    private function displaySuggestion(OutputInterface $output, $matches, $numMatches, $ofs, $partialLength)
    {
        $output->write("\033[K");

        if ($numMatches > 0 && -1 !== $ofs) {
            // Save cursor position
            $output->write("\0337");
            // Write highlighted text
            $output->write('<hl>' . substr($matches[$ofs], $partialLength) . '</hl>');
            // Restore cursor position
            $output->write("\0338");
        }
    }

    /**
     * Validates an attempt.
     *
     * @param callable $interviewer A callable that will ask for a question and return the result
     * @param OutputInterface $output      An Output instance
     * @param FileFilter $filter    A Question instance
     *
     * @return string The validated response
     *
     * @throws \Exception In case the max number of attempts has been reached and no valid response has been given
     */
    private function validateAttempts($interviewer, OutputInterface $output, FileFilter $filter)
    {
        $error = null;
        $attempts = $filter->getMaxAttempts();
        while (null === $attempts || $attempts--) {
            /** @var null|\Exception $error */
            if (null !== $error) {
                if (null !== $this->getHelperSet() && $this->getHelperSet()->has('formatter')) {
                    $message = $this->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error');
                } else {
                    $message = '<error>' . $error->getMessage() . '</error>';
                }

                $output->writeln($message);
            }

            try {
                return call_user_func($filter->getValidator(), $interviewer());
            } catch (\Exception $error) {
            }
        }

        throw $error;
    }
}