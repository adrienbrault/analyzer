<?php

namespace AdrienBrault\Analyzer;

use AdrienBrault\Analyzer\Command\CountDependenciesCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new CountDependenciesCommand();

        return $defaultCommands;
    }
}
