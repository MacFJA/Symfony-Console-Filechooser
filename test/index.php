<?php

use MacFJA\Symfony\Console\Filechooser\FilechooserHelper;
use MacFJA\Symfony\Console\Filechooser\FileFilter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application();
$app->getHelperSet()->set(new FilechooserHelper());
$app->register('ask-path')->setCode(
    function (InputInterface $input, OutputInterface $output) use ($app) {
        // ask and validate the answer
        /** @var FilechooserHelper $dialog */
        $dialog = $app->getHelperSet()->get('filechooser');
        $filter = new FileFilter('Where is your file? ');
        //$filter->sortByType();
        $color = $dialog->ask($input, $output, $filter);

        $output->writeln(sprintf('You have just entered: %s', $color));
    }
);

$app->run();
