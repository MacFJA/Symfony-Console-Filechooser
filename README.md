File chooser
============

The library provide a file selector capacity to any symfony console application.
It's support autocomplete functionality for navigating in directory.

Installation
------------

### Composer

```sh
composer require macfja/symfony-console-filechooser
```

### Usage

```php
use MacFJA\Symfony\Console\Filechooser\FilechooserHelper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

$app = new Application();

// Adding the helper to Symfony Console Application
$app->getHelperSet()->set(new FilechooserHelper());

$app->register('ask-path')->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
    // ask and validate the answer
    $dialog = $app->getHelperSet()->get('filechooser');
    $filter = new \MacFJA\Symfony\Console\Filechooser\FileFilter('Where is your file? ');
    $path = $dialog->ask($input, $output, $filter);
    $output->writeln(sprintf('You have just entered: %s', $path));
});

$app->run();
```