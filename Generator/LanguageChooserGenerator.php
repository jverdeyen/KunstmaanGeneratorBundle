<?php

namespace Kunstmaan\GeneratorBundle\Generator;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Sensio\Bundle\GeneratorBundle\Manipulator\KernelManipulator;


/**
 * Generate the needed files for the language chooser
 */
class LanguageChooserGenerator extends Generator
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $skeletonDir;

    /**
     * @var string
     */
    private $fullSkeletonDir;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @param Filesystem      $filesystem  The filesytem
     * @param string          $skeletonDir The skeleton directory
     * @param KernelInterface $kernel      The symfony kernel
     */
    public function __construct(Filesystem $filesystem, $skeletonDir, KernelInterface $kernel)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
        $this->fullSkeletonDir = realpath(__DIR__.'/../Resources/SensioGeneratorBundle/skeleton' . $skeletonDir);
        $this->kernel = $kernel;
    }

    /**
     * @param Bundle          $bundle  The bundle
     * @param string          $rootDir The root directory
     * @param OutputInterface $output  An OutputInterface instance
     */
    public function generate(Bundle $bundle, $rootDir, $output)
    {
        $parameters = array(
            'namespace'         => $bundle->getNamespace(),
            'bundle'            => $bundle,
        );

        $this->generateTemplates($bundle, $parameters, $rootDir, $output);
        $this->generateConfigs($bundle, $rootDir, $output);
        $this->updateKernel($this->kernel, $output);
    }


    /**
     * @param Bundle          $bundle  The bundle
     * @param string          $rootdir The root directory
     * @param OutputInterface $output  An OutputInterface instance
     */
    public function generateConfigs(Bundle $bundle, $rootdir, OutputInterface $output)
    {

        // handle the parameters.yml file
        $yamlFile = $rootdir.'/app/config/parameters.yml';
        $parameters = Yaml::parse($yamlFile);

        if(!isset($parameters['parameters']['autodetectlanguage'])) {
            $parameters['parameters']['autodetectlanguage'] = true;
        }

        if(!isset($parameters['parameters']['showlanguagechooser'])) {
            $parameters['parameters']['showlanguagechooser'] = true;
        }

        if(!isset($parameters['parameters']['languagechoosertemplate'])) {
            $parameters['parameters']['languagechoosertemplate'] = $bundle->getName() . ':LanguageChooser:view.html.twig';
        }

        if (!isset($parameters['parameters']['languagechooser'])) {
            $parameters['parameters']['languagechooser'] = explode('|', $parameters['parameters']['requiredlocales']);
        }

        $parameters = Yaml::dump($parameters);
        file_put_contents($yamlFile, $parameters);

        $output->writeln('Generating parameters.yml values : <info>OK</info>');


        // handle the routing
        $yamlFile = $rootdir.'/app/config/routing.yml';
        $routing = file_get_contents($yamlFile);
        $newRouting = '';

        if (stripos($routing, 'kunstmaan_language_chooser') === false) {
            $newRouting .= "kunstmaan_language_chooser:\n";
            $newRouting .= "    resource: \"@KunstmaanLanguageChooserBundle/Controller\"\n";
            $newRouting .= "    type:     annotation\n";
            $newRouting .= "    prefix:   /\n";
            $newRouting .= "\n";
            $newRouting .= $routing;

            file_put_contents($yamlFile, $newRouting);
            $output->writeln('Generating routing.yml values : <info>OK</info>');
        }
    }

    /**
     * @param Bundle          $bundle     The bundle
     * @param array           $parameters The template parameters
     * @param string          $rootDir    The root directory
     * @param OutputInterface $output     An OutputInterface instance

     */
    public function generateTemplates(Bundle $bundle, array $parameters, $rootDir, OutputInterface $output)
    {
        $dirPath = $bundle->getPath();
        $fullSkeletonDir = $this->fullSkeletonDir . '/Resources/views';

        $this->filesystem->copy($fullSkeletonDir . '/LanguageChooser/view.html.twig', $dirPath . '/Resources/views/LanguageChooser/view.html.twig', true);

        $output->writeln('Generating Twig Template : <info>OK</info>');
    }


    /**
     * Updates the AppKernel.php automatically to include the KunstmaanLanguageChooserBundle
     *
     * @param KernelInterface $kernel
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     */
    public function updateKernel(KernelInterface $kernel, $output)
    {
        $kernelManipulator = new KernelManipulator($kernel);

        // Enable Lunetics module
        $output->write('Enabling the LuneticsLocaleBundle inside the AppKernel.php: ');
        try {
            $success = $kernelManipulator->addBundle('Lunetics\LocaleBundle\LuneticsLocaleBundle');

            if (!$success) {
                $output->write('<error>FAILED</error>');
                $output->writeln('');
                $output->writeln('Please update the Symfony AppKernel.php file and add Lunetics\LocaleBundle\LuneticsLocaleBundle');
            } else {
                $output->writeln('<info>OK</info>');
            }
        } catch (\RuntimeException $e) {
            $output->write('<error>FAILED</error>');
            $output->writeln('<error>The Lunetics\LocaleBundle\LuneticsLocaleBundle is already defined in AppKernel::registerBundles()</error>');
        }

        // Enable Kunstmaan LanguageChooser module
        $output->write('Enabling the KunstmaanLangueChooserBundle inside the AppKernel.php: ');
        try {
            $success = $kernelManipulator->addBundle('Kunstmaan\LanguageChooserBundle\KunstmaanLanguageChooserBundle');

            if (!$success) {
                $output->write('<error>FAILED</error>');
                $output->writeln('');
                $output->writeln('Please update the Symfony AppKernel.php file and add Kunstmaan\LanguageChooserBundle\KunstmaanLanguageChooserBundle');
            } else {
                $output->writeln('<info>OK</info>');
            }
        } catch (\RuntimeException $e) {
            $output->write('<error>FAILED</error>');
            $output->writeln('<error>The KunstmaanLanguageChooserBundle is already defined in AppKernel::registerBundles()</error>');
        }
    }
}