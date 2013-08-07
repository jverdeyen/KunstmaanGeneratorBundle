<?php

namespace Kunstmaan\GeneratorBundle\Generator;

use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Generates an Article section
 */
class ArticleGenerator extends KunstmaanGenerator
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
     * @var bool
     */
    private $multiLanguage;

    /**
     * @param Filesystem $filesystem    The filesytem
     * @param string     $skeletonDir   The skeleton directory
     * @param bool       $multiLanguage If the site is multilanguage
     */
    public function __construct(Filesystem $filesystem, $skeletonDir, $multiLanguage)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
        $this->fullSkeletonDir = __DIR__.'/../Resources/SensioGeneratorBundle/skeleton' . $skeletonDir;
        $this->multiLanguage = $multiLanguage;
    }


    /** @var Bundle */
    private $bundle;
    /** @var string */
    private $entity;
    /** @var string */
    private $prefix;

    /**
     * @param Bundle          $bundle
     * @param string          $entity
     * @param string          $prefix
     * @param bool            $dummyData
     */
    public function generate(Bundle $bundle, $entity, $prefix, $dummyData)
    {
        $this->bundle = $bundle;
        $this->entity = $entity;
        $this->prefix = $prefix;
        $this->parameters = array(
            'namespace'         => $bundle->getNamespace(),
            'bundle'            => $bundle,
            'prefix'            => GeneratorUtils::cleanPrefix($prefix),
            'entity_class'      => $entity,
        );

        $this->executeSteps(array(
            'Generating entities' => 'generateEntities',
            'Generating repositories' => 'generateRepositories',
            'Generating Forms' => 'generateForm',
            'Generating AdminList Configurators' => 'generateAdminList',
            'Generating Controllers' => 'generateController',
            'Generating PagePart Configurators' => 'generatePagePartConfigs',
            'Generating Twig Templates' => 'generateTemplates',
            'Generating Routing' => 'generateRouting',
            'Generating Menu' => 'generateMenu',
            'Generating Services' => 'generateServices'
        ));

        if ($dummyData) {
            $this->executeStep('Generate Fixtures', function() {
                $this->generateFixtures();
            });
        }
    }

    public function generateServices()
    {
        $dirPath = sprintf("%s/Resources/config", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/config", $this->skeletonDir);
        $routing = $this->render($skeletonDir . '/services.yml', $this->parameters);
        GeneratorUtils::append($routing, $dirPath . '/services.yml');
    }

    public function generateMenu()
    {
        $dirPath = sprintf("%s/Helper/Menu", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Helper/Menu", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'MenuAdaptor');
    }

    public function generateRouting()
    {
        $dirPath = sprintf("%s/Resources/config", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/config", $this->skeletonDir);

        if($this->multiLanguage) {
            $routing = $this->render($skeletonDir . '/routing_multilanguage.yml', $this->parameters);
        } else {
            $routing = $this->render($skeletonDir . '/routing_singlelanguage.yml', $this->parameters);
        }
        GeneratorUtils::append($routing, $dirPath . '/routing.yml');
    }

    public function generateTemplates()
    {
        $dirPath = sprintf("%s/Resources/views", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/views", $this->skeletonDir);
        $fullSkeletonDir = sprintf("%s/Resources/views", $this->fullSkeletonDir);

        $this->filesystem->copy($fullSkeletonDir . '/OverviewPage/view.html.twig', $dirPath . '/' . $this->entity . '/' . $this->entity . 'OverviewPage/view.html.twig', true);
        GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Layout:layout.html.twig' %}\n", $dirPath . '/' . $this->entity . '/' . $this->entity . 'OverviewPage/view.html.twig');

        $this->filesystem->copy($fullSkeletonDir . '/Page/view.html.twig', $dirPath . '/' . $this->entity . '/' . $this->entity . 'Page/view.html.twig', true);
        GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Layout:layout.html.twig' %}\n", $dirPath . '/' . $this->entity . '/' . $this->entity . 'Page/view.html.twig');

        $this->renderFile($skeletonDir . '/PageAdminList/list.html.twig', $dirPath . '/AdminList/' . '/' . $this->entity . '/' . $this->entity . 'PageAdminList/list.html.twig', $this->parameters );
    }

    public function generateController()
    {
        $dirPath = sprintf("%s/Controller/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Controller", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'PageAdminListController', $this->parameters);
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'AuthorAdminListController', $this->parameters);
    }

    public function generatePagePartConfigs()
    {
        $dirPath = sprintf("%s/PagePartAdmin/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/PagePartAdmin", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'OverviewPagePagePartAdminConfigurator');
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'PagePagePartAdminConfigurator');
    }

    public function generateAdminList()
    {
        $dirPath = sprintf("%s/AdminList/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/AdminList", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'PageAdminListConfigurator', $this->parameters);
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'AuthorAdminListConfigurator', $this->parameters);
    }

    public function generateForm()
    {
        $dirPath = sprintf("%s/Form/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Form", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'OverviewPageAdminType', $this->parameters);
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'PageAdminType', $this->parameters);
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'AuthorAdminType', $this->parameters);
    }

    public function generateRepositories()
    {
        $dirPath = sprintf("%s/Repository/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Repository", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'OverviewPageRepository');
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'PageRepository');
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'AuthorRepository');
    }

    public function generateEntities()
    {
        $dirPath = sprintf("%s/Entity/" . $this->entity, $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Entity", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'OverviewPage');
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'Page');
        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'Author');
    }

    public function generateFixtures()
    {
        $dirPath = $this->bundle->getPath() . '/DataFixtures/ORM';
        $skeletonDir = $this->skeletonDir . '/DataFixtures/ORM';

        $this->generateSkeletonBasedClass($skeletonDir, $this->entity, $dirPath, 'ArticleFixtures');
    }

    /**
     * @param string $skeletonDir The full dir of the entity skeleton
     * @param string $entity
     * @param string $dirPath     The full fir of where the entity should be created
     * @param string $className   The class name of the entity to create
     * @param array  $parameters  The template parameters
     *
     * @throws \RuntimeException
     */
    protected function generateSkeletonBasedClass($skeletonDir, $entity, $dirPath, $className, array $parameters = null)
    {
        if (is_null($parameters)) {
            $parameters = $this->parameters;
        }

        $classPath = sprintf("%s/%s.php", $dirPath, $entity . $className);
        $skeletonPath = sprintf("%s/%s.php", $skeletonDir, $className);
        if (file_exists($classPath)) {
            throw new \RuntimeException(sprintf('Unable to generate the %s class as it already exists under the %s file', $className, $classPath));
        }
        $this->renderFile($skeletonPath, $classPath, $parameters);
    }
}
