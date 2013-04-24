<?php

namespace Kunstmaan\GeneratorBundle\Command;

use Kunstmaan\GeneratorBundle\Generator\LanguageChooserGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Sensio\Bundle\GeneratorBundle\Generator;


/**
 * Enables and generates a template for the kunstmaan language chooser
 */
class GenerateLanguageChooserCommand extends GenerateDoctrineCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace of the bundle to use')))
            ->setDescription('Enables the Kunstmaan Language Chooser and generates the template')
            ->setHelp(<<<EOT
The <info>kuma:generate:languagechooser</info> command enables the KunstmaanLanguageChooser bundle and generates a basic language chooser twig template.

<info>php app/console kuma:generate:languagechooser</info>
EOT
            )
            ->setName('kuma:generate:languagechooser');
    }


    /**
     * Executes the command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach (array('namespace') as $option) {
            if (null === $input->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }

        $namespace  = Validators::validateBundleNamespace($input->getOption('namespace'));
        $kernel     = $this->getContainer()->get('kernel');

        $bundle = strtr($namespace, array('\\' => ''));
        $bundle = Validators::validateBundleName($bundle);

        $targetBundle = $kernel->getBundle($bundle);

        $rootDir = realpath($kernel->getRootDir() . '/../');

        $generator = $this->createGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->generate($targetBundle, $rootDir, $output);

        /**
         * @todo do this in the generator, currently yml output is not readable anymore then
         */
        $output->writeln('');
        $output->writeln('Please add import for @KunstmaanLanguageChooserBundle/Resources/config/config.yml to the config.yml file');
    }


    /**
     * Interacts with the user.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Setting up and generating the language chooser template.');

        // get namespacename
        $namespace = null;
        try {
            $namespace = $input->getOption('namespace') ? Validators::validateBundleNamespace($input->getOption('namespace')) : null;
        } catch (\Exception $error) {
            $output->writeln($dialog->getHelperSet()->get('formatter')->formatBlock($error->getMessage(), 'error'));
        }

        if (is_null($namespace)) {
            $output->writeln(array(
                '',
                'Please enter the namespace of the bundle where you want to put the language chooser splashpage view',
                '',
                'For example: Kunstmaan/LanguageChooserBundle'
            ));

            $namespace = $dialog->askAndValidate($output, $dialog->getQuestion('The namespace', $namespace), array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'), false, $namespace);
            $input->setOption('namespace', $namespace);
        }
    }

    /**
     * Returns the HelperInterface
     *
     * @return DialogHelper|\Symfony\Component\Console\Helper\HelperInterface
     */
    protected function getDialogHelper()
    {
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog || get_class($dialog) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper') {
            $this->getHelperSet()->set($dialog = new DialogHelper());
        }

        return $dialog;
    }

    /**
     * Returns a new LanguageChooserGenerator
     *
     * @return LanguageChooserGenerator
     */
    protected function createGenerator()
    {
        return new LanguageChooserGenerator($this->getContainer()->get('filesystem'), '/languagechooser', $this->getContainer()->get('kernel'));
    }
}
