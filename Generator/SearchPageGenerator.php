<?php

namespace Kunstmaan\GeneratorBundle\Generator;

use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates a SearchPage using KunstmaanSearchBundle and KunstmaanNodeSearchBundle
 */
class SearchPageGenerator extends KunstmaanGenerator
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
     * @param Filesystem $filesystem  The filesytem
     * @param string     $skeletonDir The skeleton directory

     */
    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }

    private $rootDir;
    /** @var Bundle */
    private $bundle;
    /**
     * @param Bundle          $bundle  The bundle
     * @param string          $prefix  The prefix
     * @param string          $rootDir The root directory
     */
    public function generate(Bundle $bundle, $prefix, $rootDir)
    {
        $this->parameters = array(
            'namespace'         => $bundle->getNamespace(),
            'bundle'            => $bundle,
            'prefix'            => GeneratorUtils::cleanPrefix($prefix)
        );
        $this->bundle = $bundle;
        $this->rootDir = $rootDir;

        $this->executeSteps(array(
            'Generating Entities' => 'generateEntities',
            'Generating Twig Templates' => 'generateTemplates'
        ));
    }

    public function generateTemplates()
    {
        $dirPath = $this->bundle->getPath();
        $fullSkeletonDir = $this->skeletonDir . '/Resources/views';

        $this->filesystem->copy(__DIR__.'/../Resources/SensioGeneratorBundle/skeleton' . $fullSkeletonDir . '/Pages/Search/SearchPage/view.html.twig', $dirPath . '/Resources/views/Pages/Search/SearchPage/view.html.twig', true);
        GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Page:layout.html.twig' %}\n", $dirPath . '/Resources/views/Pages/Search/SearchPage/view.html.twig');
    }

    public function generateEntities()
    {
        $dirPath = sprintf("%s/Entity/Pages/Search/", $this->bundle->getPath());
        $fullSkeletonDir = sprintf("%s/Entity/Pages/Search/", $this->skeletonDir);

        $this->generateSkeletonBasedClass($fullSkeletonDir, $dirPath, 'SearchPage', $this->parameters);
    }

    /**
     * @param string $fullSkeletonDir The full dir of the entity skeleton
     * @param string $dirPath         The full fir of where the entity should be created
     * @param string $className       The class name of the entity to create
     * @param array  $parameters      The template parameters
     *
     * @throws \RuntimeException
     */
    private function generateSkeletonBasedClass($fullSkeletonDir, $dirPath, $className, array $parameters)
    {
        $classPath = sprintf("%s/%s.php", $dirPath, $className);
        if (file_exists($classPath)) {
            throw new \RuntimeException(sprintf('Unable to generate the %s class as it already exists under the %s file', $className, $classPath));
        }
        $this->renderFile($fullSkeletonDir.  $className . '.php', $classPath, $parameters);
    }

}
