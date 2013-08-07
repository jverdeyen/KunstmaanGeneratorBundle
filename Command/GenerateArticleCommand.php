<?php

namespace Kunstmaan\GeneratorBundle\Command;

use Kunstmaan\GeneratorBundle\Generator\ArticleGenerator;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Symfony\Component\Console\Input\InputOption;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

use Sensio\Bundle\GeneratorBundle\Generator;

/**
 * Generates classes based on the AbstractArticle classes from KunstmaanArticleBundle
 */
class GenerateArticleCommand extends KunstmaanGeneratorCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                array(
                    new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace to generate the Article classes in'),
                    new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The article class name ("News", "Press", ..."'),
                    new InputOption('prefix', '', InputOption::VALUE_OPTIONAL, 'The prefix to be used in the table names of the generated entities'),
                    new InputOption('dummydata', null, InputOption::VALUE_NONE, 'If set, the task will generate data fixtures to populate your database')
                )
            )
            ->setDescription('Generates Article classes based on KunstmaanArticleBundle')
            ->setHelp(<<<EOT
The <info>kuma:generate:article</info> command generates classes for Articles using the KunstmaanArticleBundle

<info>php app/console kuma:generate:article --namespace=Namespace/NamedBundle --entity=Article</info>

Use the <info>--prefix</info> option to add a prefix to the table names of the generated entities

<info>php app/console kuma:generate:article --namespace=Namespace/NamedBundle --prefix=demo_</info>

Add the <info>--dummydata</info> option to create data fixtures to populate your database

<info>php app/console kuma:generate:article --namespace=Namespace/NamedBundle --dummydata</info>
EOT
            )
            ->setName('kuma:generate:article');
    }

    protected function getWelcomeText()
    {
        return 'Article Generation';
    }

    protected function getOptionsRequired()
    {
        return array('namespace', 'entity');
    }

    protected function doExecute()
    {
        $namespace = Validators::validateBundleNamespace($this->assistant->getOption('namespace'));
        $bundle = strtr($namespace, array('\\' => ''));
        $entity = ucfirst($this->assistant->getOption('entity'));

        $prefix = GeneratorUtils::cleanPrefix($this->assistant->getOption('prefix'));
        $dummyData = $this->assistant->getOption('dummydata');

        $bundle = $this
            ->getApplication()
            ->getKernel()
            ->getBundle($bundle);

        /** @var $generator ArticleGenerator */
        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->setAssistant($this->assistant);
        $generator->generate($bundle, $entity, $prefix, $dummyData);

        $this->assistant->writeLine(array(
            'Make sure you update your database first before using the created entities:',
            '    Directly update your database:          <comment>app/console doctrine:schema:update --force</comment>',
            '    Create a Doctrine migration and run it: <comment>app/console doctrine:migrations:diff && app/console doctrine:migrations:migrate</comment>',
            ''
        ));
    }

    protected function doInteract()
    {
        $this->assistant->writeSection('Welcome to the Kunstmaan Article generator');

        $this->askForNamespace(array(
            '',
            'This command helps you to generate the Article classes.',
            'You must specify the namespace of the bundle where you want to generate the classes in.',
            'Use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any problem.',
            '',
        ));

        // entity
        $entity = $this->assistant->getOptionOrDefault('entity');

        if (is_null($entity)) {
            $this->assistant->writeLine(array(
                '',
                'The name of your article entity: <comment>News</comment>',
                '',
            ));

            $entityValidation = function ($entity) {
                if (empty($entity)) {
                    throw new \RuntimeException('You have to provide a entity name!');
                } else {
                    return $entity;
                }
            };

            $entity = $this->assistant->askAndValidate('Entity Name', $entityValidation, 'News');
            $this->assistant->setOption('entity', $entity);
        }

        $this->askForPrefix();
    }

    protected function createGenerator()
    {
        return new ArticleGenerator($this->getContainer()->get('filesystem'), '/article', $this->getContainer()->getParameter('multilanguage'));
    }
}
