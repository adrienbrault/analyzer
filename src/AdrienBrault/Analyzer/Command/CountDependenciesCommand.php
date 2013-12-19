<?php

namespace AdrienBrault\Analyzer\Command;

use AdrienBrault\Analyzer\Visitor\DependenciesVisitor;
use PHPParser_Lexer;
use PHPParser_NodeTraverser;
use PHPParser_NodeVisitor_NameResolver;
use PHPParser_Parser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class CountDependenciesCommand extends Command
{
    public function configure()
    {
        $this
            ->setName('count-dependencies')
            ->addArgument('directory', InputArgument::REQUIRED)
            ->addArgument('namespace', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getArgument('directory');
        $namespace = $input->getArgument('namespace');

        $parser = new PHPParser_Parser(new PHPParser_Lexer());
        $traverser = new PHPParser_NodeTraverser();
        $traverser->addVisitor(new PHPParser_NodeVisitor_NameResolver());
        $traverser->addVisitor($dependenciesVisitor = new DependenciesVisitor());

        $finder = $this->createFinder($directory);

        foreach ($finder as $file) {
            $traverser->traverse(
                $parser->parse($file->getContents())
            );
        }

        foreach ($this->getNamespaceDependencies($dependenciesVisitor, $namespace) as $dependency) {
            $output->writeln($dependency);
        }
    }

    private function createFinder($directory)
    {
        $finder = new Finder();
        $finder
            ->files()
            ->name('*.php')
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude('vendor')
            ->exclude('Tests')
            ->in(array($directory))
        ;

        return $finder;
    }

    private function getNamespaceDependencies(DependenciesVisitor $visitor, $namespace)
    {
        $map = function (array $dependencies, $namespace) {
            $mapped = array();

            foreach ($dependencies as $class => $classDependencies) {
                if (0 === strpos($class, $namespace)) {
                    $mapped[$class] = $classDependencies;
                }
            }

            return $mapped;
        };

        $reduce = function (array $dependencies) {
            return array_unique(array_reduce($dependencies, function ($reduced, $dependencies) {
                return array_merge($reduced, $dependencies);
            }, array()));
        };

        $mapExclude = function (array $dependencies, $namespace) {
            $mappedDependencies = array();

            foreach ($dependencies as $dependency) {
                if (0 !== strpos($dependency, $namespace)) {
                    $mappedDependencies[] = $dependency;
                }
            }

            return $mappedDependencies;
        };
        $namespaceDependencies = $mapExclude($reduce($map($visitor->getDependencies(), $namespace)), $namespace);
        sort($namespaceDependencies);

        return $namespaceDependencies;
    }
}
