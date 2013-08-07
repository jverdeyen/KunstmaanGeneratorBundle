<?php

namespace Kunstmaan\GeneratorBundle\Command;


use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

use Sensio\Bundle\GeneratorBundle\Generator;

use Kunstmaan\GeneratorBundle\Generator\DefaultSiteGenerator;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Generates a default website based on Kunstmaan bundles
 */
class GenerateDefaultSiteCommand extends KunstmaanGeneratorCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                array(
                     new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace to generate the default website in'),
                     new InputOption('prefix', '', InputOption::VALUE_OPTIONAL, 'The prefix to be used in the table names of the generated entities')
                )
            )
            ->setDescription('Generates a basic website based on Kunstmaan bundles with default templates')
            ->setHelp(<<<EOT
The <info>kuma:generate:site</info> command generates an website using the Kunstmaan bundles

<info>php app/console kuma:generate:default-site --namespace=Namespace/NamedBundle</info>

Use the <info>--prefix</info> option to add a prefix to the table names of the generated entities

<info>php app/console kuma:generate:default-site --namespace=Namespace/NamedBundle --prefix=demo_</info>
EOT
            )
            ->setName('kuma:generate:default-site');
    }

    protected function getWelcomeText()
    {
        return 'Site Generation';
    }

    protected function getOptionsRequired()
    {
        return array('namespace');
    }

    protected function doExecute()
    {
        $namespace = Validators::validateBundleNamespace($this->assistant->getOption('namespace'));
        $bundle = strtr($namespace, array('\\' => ''));

        $prefix = GeneratorUtils::cleanPrefix($this->assistant->getOption('prefix'));
        $bundle = $this
            ->getApplication()
            ->getKernel()
            ->getBundle($bundle);

        $rootDir = $this->getApplication()->getKernel()->getRootDir();

        /** @var $generator DefaultSiteGenerator */
        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->setAssistant($this->assistant);

        $generator->generate($bundle, $prefix, $rootDir);
    }


    /**
     * {@inheritdoc}
     */
    protected function doInteract()
    {
        $this->assistant->writeLine('Welcome to the Kunstmaan default site generator');

        $this->askForNamespace(array(
            '',
            'This command helps you to generate a default site setup.',
            'You must specify the namespace of the bundle where you want to generate the default site setup.',
            'Use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any problem.',
            '',
        ));

        $this->askForPrefix();
    }

    protected function createGenerator()
    {
        return new DefaultSiteGenerator($this->getContainer()->get('filesystem'), '/defaultsite');
    }
}
