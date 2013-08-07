<?php

namespace Kunstmaan\GeneratorBundle\Command;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Sensio\Bundle\GeneratorBundle\Command\GeneratorCommand;

use Kunstmaan\GeneratorBundle\Generator\BundleGenerator;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\KernelInterface;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;
use Sensio\Bundle\GeneratorBundle\Manipulator\RoutingManipulator;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;

/**
 * Generates bundles.
 */
class GenerateBundleCommand extends KunstmaanGeneratorCommand
{

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(
                array(new InputOption('namespace', '', InputOption::VALUE_REQUIRED, 'The namespace of the bundle to create'),
                new InputOption('dir', '', InputOption::VALUE_REQUIRED, 'The directory where to create the bundle'),
                new InputOption('bundle-name', '', InputOption::VALUE_REQUIRED, 'The optional bundle name'),))
            ->setHelp(
                <<<EOT
            The <info>generate:bundle</info> command helps you generates new bundles.

By default, the command interacts with the developer to tweak the generation.
Any passed option will be used as a default value for the interaction
(<comment>--namespace</comment> is the only one needed if you follow the
conventions):

<info>php app/console kuma:generate:bundle --namespace=Acme/BlogBundle</info>

Note that you can use <comment>/</comment> instead of <comment>\\</comment> for the namespace delimiter to avoid any
problem.

If you want to disable any user interaction, use <comment>--no-interaction</comment> but don't forget to pass all needed options:

<info>php app/console kuma:generate:bundle --namespace=Acme/BlogBundle --dir=src [--bundle-name=...] --no-interaction</info>

Note that the bundle namespace must end with "Bundle".
EOT
            )
            ->setName('kuma:generate:bundle');
    }

    protected function getOptionsRequired()
    {
        return array('namespace', 'dir');
    }

    protected function getWelcomeText()
    {
        return 'Bundle Generation';
    }

    protected function doExecute()
    {
        $dialog = $this->getDialogHelper();

        if ($this->assistant->isInteractive()) {
            if (!$this->assistant->askConfirmation('Do you confirm generation', 'yes', '?', true)) {
                $this->assistant->writeError('Command Aborted');
                return 1;
            }
        }


        $namespace = Validators::validateBundleNamespace($this->assistant->getOption('namespace'));
        if (!$bundle = $this->assistant->getOption('bundle-name')) {
            $bundle = strtr($namespace, array('\\' => ''));
        }
        $bundle = Validators::validateBundleName($bundle);
        $dir = Validators::validateTargetDir($this->assistant->getOption('dir'), $bundle, $namespace);
        $format = 'yml';


        if (!$this
            ->getContainer()
            ->get('filesystem')
            ->isAbsolutePath($dir)
        ) {
            $dir = getcwd() . '/' . $dir;
        }

        /** @var $generator BundleGenerator */
        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->setAssistant($this->assistant);
        $generator->generate($namespace, $bundle, $dir, $format);


        $runner = $this->assistant->getRunner();

        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($namespace, $bundle));

        // register the bundle in the Kernel class
        $runner($this->updateKernel($this->getContainer()->get('kernel'), $namespace, $bundle));

        // routing
        $runner($this->updateRouting($bundle, $format));

        $this->assistant->writeSummary();
    }

    protected function doInteract()
    {
        $this->assistant->writeSection('Welcome to the Kunstmaan bundle generator');

        // namespace
        $this->assistant
            ->writeLine(
                array('', 'Your application code must be written in <comment>bundles</comment>. This command helps', 'you generate them easily.', '',
                'Each bundle is hosted under a namespace (like <comment>Acme/Bundle/BlogBundle</comment>).',
                'The namespace should begin with a "vendor" name like your company name, your', 'project name, or your client name, followed by one or more optional category',
                'sub-namespaces, and it should end with the bundle name itself', '(which must have <comment>Bundle</comment> as a suffix).', '',
                'See http://symfony.com/doc/current/cookbook/bundles/best_practices.html#index-1 for more', 'details on bundle naming conventions.', '',
                'Use <comment>/</comment> instead of <comment>\\ </comment>for the namespace delimiter to avoid any problems.', '',));

        $namespace = $this->askForNamespace();

        // bundle name
        $bundle = $this->assistant->getOption('bundle-name') ? : strtr($namespace, array('\\Bundle\\' => '', '\\' => ''));
        $this->assistant
            ->writeLine(
                array('', 'In your code, a bundle is often referenced by its name. It can be the', 'concatenation of all namespace parts but it\'s really up to you to come',
                'up with a unique name (a good practice is to start with the vendor name).', 'Based on the namespace, we suggest <comment>' . $bundle . '</comment>.', '',));
        $bundle = $this->assistant->askAndValidate('Bundle name', array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleName'), $bundle, $bundle);
        $this->assistant->setOption('bundle-name', $bundle);

        // target dir
        $dir = $this->assistant->getOption('dir') ? : dirname($this
            ->getContainer()
            ->getParameter('kernel.root_dir')) . '/src';
        $this->assistant->writeLine(array('', 'The bundle can be generated anywhere. The suggested default directory uses', 'the standard conventions.', '',));
        $dir = $this->assistant
            ->askAndValidate('Target Directory',
                function ($dir) use ($bundle, $namespace) {
                    return Validators::validateTargetDir($dir, $bundle, $namespace);
                }, $dir, $dir);
        $this->assistant->setOption('dir', $dir);

        // format
        $this->assistant->writeLine(array('', 'Determine the format to use for the generated configuration.', '',));
        $this->assistant->writeLine(array('', 'Determined \'yml\' to be used as the format for the generated configuration', '',));
        $format = 'yml';

        // summary
        $this->assistant
            ->writeLine(
                array('', $this
                    ->getHelper('formatter')
                    ->formatBlock('Summary before generation', 'bg=blue;fg=white', true), '',
                    sprintf("You are going to generate a \"<info>%s\\%s</info>\" bundle\nin \"<info>%s</info>\" using the \"<info>%s</info>\" format.", $namespace, $bundle, $dir, $format),
                    '',));
    }

    /**
     * @param string          $namespace The namespace
     * @param string          $bundle    The bundle name
     *
     * @return array
     */
    protected function checkAutoloader($namespace, $bundle)
    {
        $this->assistant->write('Checking that the bundle is autoloaded: ');
        if (!class_exists($namespace . '\\' . $bundle)) {
            return array('- Edit the <comment>composer.json</comment> file and register the bundle', '  namespace in the "autoload" section:', '',);
        }
    }

    /**
     * @param InputInterface  $input     The command input
     * @param OutputInterface $output    The command output
     * @param KernelInterface $kernel    The kernel
     * @param string          $namespace The namespace
     * @param string          $bundle    The bundle
     *
     * @return array
     */
    protected function updateKernel(KernelInterface $kernel, $namespace, $bundle)
    {
        $auto = true;
        if ($this->assistant->isInteractive()) {
            $auto = $this->assistant->askConfirmation('Confirm automatic update of your Kernel', 'yes', '?', true);
        }

        $this->assistant->write('Enabling the bundle inside the Kernel: ');
        $manip = new KernelManipulator($kernel);
        try {
            $ret = $auto ? $manip->addBundle($namespace . '\\' . $bundle) : false;

            if (!$ret) {
                $reflected = new \ReflectionObject($kernel);

                return array(sprintf('- Edit <comment>%s</comment>', $reflected->getFilename()), '  and add the following bundle in the <comment>AppKernel::registerBundles()</comment> method:', '',
                    sprintf('    <comment>new %s(),</comment>', $namespace . '\\' . $bundle), '',);
            }
        } catch (\RuntimeException $e) {
            return array(sprintf('Bundle <comment>%s</comment> is already defined in <comment>AppKernel::registerBundles()</comment>.', $namespace . '\\' . $bundle), '',);
        }
    }

    /**
     * @param DialogHelper    $dialog The dialog helper
     * @param InputInterface  $input  The command input
     * @param OutputInterface $output The command output
     * @param string          $bundle The bundle name
     * @param string          $format the format
     *
     * @return array
     */
    protected function updateRouting($bundle, $format)
    {
        $auto = true;
        if ($this->assistant->isInteractive()) {
            $auto = $this->assistant->askConfirmation('Confirm automatic update of the Routing', 'yes', '?', true);
        }

        $this->assistant->write('Importing the bundle routing resource: ');
        $routing = new RoutingManipulator($this
            ->getContainer()
            ->getParameter('kernel.root_dir') . '/config/routing.yml');
        try {
            $ret = $auto ? $routing->addResource($bundle, $format) : false;
            if (!$ret) {
                $help = sprintf("        <comment>resource: \"@%s/Resources/config/routing.yml\"</comment>\n", $bundle);
                $help .= "        <comment>prefix:   /</comment>\n";

                return array('- Import the bundle\'s routing resource in the app main routing file:', '', sprintf('    <comment>%s:</comment>', $bundle), $help, '',);
            }
        } catch (\RuntimeException $e) {
            return array(sprintf('Bundle <comment>%s</comment> is already imported.', $bundle), '',);
        }
    }

    protected function createGenerator()
    {
        return new BundleGenerator($this->getContainer()->get('filesystem'));
    }
}
