<?php

namespace Kunstmaan\GeneratorBundle\Command;

use Kunstmaan\GeneratorBundle\Generator\SearchPageGenerator;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Symfony\Component\Console\Input\InputOption;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Generator;

/**
 * Generates a SearchPage based on the KunstmaanNodeSearchBundle
 */
class GenerateSearchPageCommand extends KunstmaanGeneratorCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                array(
                     new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace to generate the SearchPage in'),
                     new InputOption('prefix', '', InputOption::VALUE_OPTIONAL, 'The prefix to be used in the table names of the generated entities')
                )
            )
            ->setDescription('Generates a SearchPage based on KunstmaanNodeSearchBundle')
            ->setHelp(<<<EOT
The <info>kuma:generate:searchpage</info> command generates a SearchPage using the KunstmaanNodeSearchBundle and KunstmaanSearchBundle

<info>php app/console kuma:generate:searchpage --namespace=Namespace/NamedBundle</info>

Use the <info>--prefix</info> option to add a prefix to the table names of the generated entities

<info>php app/console kuma:generate:searchpage --namespace=Namespace/NamedBundle --prefix=demo_</info>
EOT
            )
            ->setName('kuma:generate:searchpage');
    }

    protected function getWelcomeText()
    {
        return 'Search Page Generation';
    }

    protected function getOptionsRequired()
    {
        return array('namespace');
    }

    protected function doExecute()
    {
        $namespace = Validators::validateBundleNamespace($this->assistant->getOption('namespace'));
        $bundle = strtr($namespace, array('\\' => ''));

        $prefix = $this->assistant->getOption('prefix');
        $bundle = $this
            ->getApplication()
            ->getKernel()
            ->getBundle($bundle);

        $rootDir = $this->getApplication()->getKernel()->getRootDir();

        /** @var $generator SearchPageGenerator */
        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->setAssistant($this->assistant);
        $generator->generate($bundle, $prefix, $rootDir);

        // TODO: Use the 'getRunner' and the 'writeSummary'. Need to return an array with everything that still has to be done.
        $this->assistant->writeLine(array(
            'Make sure you update your database first before using the created entities:',
            '    Directly update your database:          <comment>app/console doctrine:schema:update --force</comment>',
            '    Create a Doctrine migration and run it: <comment>app/console doctrine:migrations:diff && app/console doctrine:migrations:migrate</comment>',
            ''));
    }

    protected function doInteract()
    {
        $this->assistant->writeSection('Welcome to the SearchPage generator');

        $this->askForNamespace(array(
            '',
            'This command helps you to generate a SearchPage.',
            'You must specify the namespace of the bundle where you want to generate the SearchPage in.',
            'Use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any problem.',
            '',
        ));

        $this->askForPrefix();
    }

    protected function createGenerator()
    {
        return new SearchPageGenerator($this->getContainer()->get('filesystem'), '/searchpage');
    }
}
