<?php

namespace Kunstmaan\GeneratorBundle\Generator;

use Kunstmaan\GeneratorBundle\Generator\AdminTestsGenerator;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

// TODO: Add the Bundle to assetic:bundles configuration.

// TODO: Modify security.yml

/**
 * Generates a default website using several Kunstmaan bundles using default templates and assets
 */
class DefaultSiteGenerator extends KunstmaanGenerator
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $skeletonDir;

    private $fullSkeletonDir;


    private $rootDir;

    /**
     * @param Filesystem $filesystem  The filesytem
     * @param string     $skeletonDir The skeleton directory

     */
    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
        $this->fullSkeletonDir = GeneratorUtils::getFullSkeletonPath($skeletonDir);
    }

    /**
     * Returns true if we detect ths site uses the locale.
     *
     * @return bool
     */
    private function isMultiLangEnvironment() {
        // This is a pretty silly implementation.
        // It just checks if it can find _locale in the routing.yml
        $routingFile = file_get_contents($this->rootDir . '/config/routing.yml');
        return preg_match('/_locale:/i', $routingFile);
    }


    /** @var Bundle */
    protected $bundle;

    /**
     * @param Bundle          $bundle  The bundle
     * @param string          $prefix  The prefix
     * @param string          $rootDir The root directory
     */
    public function generate(Bundle $bundle, $prefix, $rootDir)
    {
        $this->rootDir = $rootDir;
        $this->parameters = array(
            'namespace'         => $bundle->getNamespace(),
            'bundle'            => $bundle,
            'prefix'            => GeneratorUtils::cleanPrefix($prefix)
        );
        $this->bundle = $bundle;

        if ($this->isMultiLangEnvironment()) {
            $this->executeStep('Generating Code For DefaultLocale Fallback', function() {
                $this->generateDefaultLocaleFallbackCode();
            });
        }

        $this->executeSteps(array(
            'Overriding DefaultController' => 'overrideDefaultController',
            'Generating Entities' => 'generateEntities',
            'Generating Forms' => 'generateForm',
            'Generating Fixtures' => 'generateFixtures',
            'Generating Assets' => 'generateAssets',
        ));

        // CAUTION: Following templates change the skeleton dir array (what's the skeletondir array and why are we changing it?)
        // TODO: Find a better way
        $this->executeSteps(array(
            'Generating PagePart Configurators' => 'generatePagepartConfigs',
            'Generating PageTemplate Configurators' => 'generatePagetemplateConfigs',
            'Generating Templates' => 'generateTemplates',
            'Generating Admin Tests' => 'generateAdminTests',
            'Generating Grunt/NPM files' => 'generateGruntFiles',
            'Updating config.yml' => 'generateConfig',
        ));
    }

    /**
     * Update the global config.yml
     */
    public function generateConfig()
    {
        $configFile = $this->rootDir.'/config/config.yml';

        $data = Yaml::parse($configFile);
        if (!array_key_exists('white_october_pagerfanta', $data)) {
            $ymlData = "\n\nwhite_october_pagerfanta:\n    default_view: twitter_bootstrap\n";
            file_put_contents($configFile, $ymlData, FILE_APPEND);
        }
    }

    public function generateGruntFiles()
    {
        $skeletonDir = sprintf("%s/grunt/", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));
        $dirPath = sprintf("%s/Resources", $this->bundle->getPath());
        
        $this->filesystem->copy($skeletonDir . '/.gitignore', $dirPath . '/.gitignore', true);        
        $this->renderFile('/Gruntfile.js.twig', $this->rootDir .'/../Gruntfile.js', $this->parameters);
        $this->renderFile('/package.json.twig', $this->rootDir .'/../package.json', $this->parameters);
    }

    public function generateAdminTests()
    {
        $adminTests = new AdminTestsGenerator($this->filesystem, '/admintests');
        $adminTests->setAssistant($this->assistant);
        $adminTests->generate($this->bundle);
    }

    public function generateTemplates()
    {
        $dirPath = sprintf("%s/Resources/views", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/views", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));

        $this->renderFile('/Page/layout.html.twig', $dirPath . '/Page/layout.html.twig', $this->parameters);
        $this->renderFile('/Layout/_css.html.twig', $dirPath . '/Layout/_css.html.twig', $this->parameters);
        $this->renderFile('/Layout/_js_footer.html.twig', $dirPath . '/Layout/_js_footer.html.twig', $this->parameters);
        $this->renderFile('/Layout/_js_header.html.twig', $dirPath . '/Layout/_js_header.html.twig', $this->parameters);

        { //ContentPage
            $this->filesystem->copy($skeletonDir . '/Pages/ContentPage/view.html.twig', $dirPath . '/Pages/ContentPage/view.html.twig', true);
            GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Page:layout.html.twig' %}\n", $dirPath . '/Pages/ContentPage/view.html.twig');
            $this->filesystem->copy($skeletonDir . '/Pages/ContentPage/pagetemplate.html.twig', $dirPath . '/Pages/ContentPage/pagetemplate.html.twig', true);
            $this->filesystem->copy($skeletonDir . '/Pages/ContentPage/pagetemplate-singlecolumn.html.twig', $dirPath . '/Pages/ContentPage/pagetemplate-singlecolumn.html.twig', true);
        }

        { //FormPage
            $this->filesystem->copy($skeletonDir . '/Pages/FormPage/view.html.twig', $dirPath . '/Pages/FormPage/view.html.twig', true);
            GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Page:layout.html.twig' %}\n", $dirPath . '/Pages/FormPage/view.html.twig');
            $this->filesystem->copy($skeletonDir . '/Pages/FormPage/pagetemplate.html.twig', $dirPath . '/Pages/FormPage/pagetemplate.html.twig', true);
            GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/Pages/FormPage/pagetemplate.html.twig');
            $this->filesystem->copy($skeletonDir . '/Pages/FormPage/pagetemplate-singlecolumn.html.twig', $dirPath . '/Pages/FormPage/pagetemplate-singlecolumn.html.twig', true);
            GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/Pages/FormPage/pagetemplate-singlecolumn.html.twig');
        }

        { //HomePage
            $this->filesystem->copy($skeletonDir . '/Pages/HomePage/view.html.twig', $dirPath . '/Pages/HomePage/view.html.twig', true);
            GeneratorUtils::prepend("{% extends '" . $this->bundle->getName() .":Page:layout.html.twig' %}\n", $dirPath . '/Pages/HomePage/view.html.twig');
            $this->filesystem->copy($skeletonDir . '/Pages/HomePage/pagetemplate.html.twig', $dirPath . '/Pages/HomePage/pagetemplate.html.twig', true);
        }

        $this->filesystem->copy($skeletonDir  . '/Layout/layout.html.twig', $dirPath . '/Layout/layout.html.twig', true);
        GeneratorUtils::replace("~~~CSS~~~", "{% include '" . $this->bundle->getName() .":Layout:_css.html.twig' %}\n", $dirPath . '/Layout/layout.html.twig');
        GeneratorUtils::replace("~~~TOP_JS~~~", "{% include '" . $this->bundle->getName() .":Layout:_js_header.html.twig' %}\n", $dirPath . '/Layout/layout.html.twig');
        GeneratorUtils::replace("~~~FOOTER_JS~~~", "{% include '" . $this->bundle->getName() .":Layout:_js_footer.html.twig' %}\n", $dirPath . '/Layout/layout.html.twig');
        GeneratorUtils::replace("~~~BUNDLENAME~~~", $this->getBundleNameWithoutBundle($this->bundle), $dirPath . '/Layout/layout.html.twig');

        $this->filesystem->copy($skeletonDir  . '/Form/fields.html.twig', $dirPath . '/Form/fields.html.twig', true);

        $skeletonDir = sprintf("%s/app/KunstmaanSitemapBundle/views/SitemapPage/", $this->fullSkeletonDir);
        $dirPath = $this->rootDir .'/../app/Resources/KunstmaanSitemapBundle/views/SitemapPage/';
        $this->setSkeletonDirs(array($skeletonDir));

        $this->filesystem->copy($skeletonDir . '/view.html.twig', $dirPath . 'view.html.twig', true);
        GeneratorUtils::replace("~~~BUNDLENAME~~~", $this->bundle->getName(), $dirPath . 'view.html.twig');

        $this->assistant->writeLine('Generating Twig Templates : <info>OK</info>');

        $this->executeStep('Generate Error Templates', function() {
            $this->generateErrorTemplates();
        });

        // @todo: should be improved
        GeneratorUtils::replace("[ \"KunstmaanAdminBundle\"", "[ \"KunstmaanAdminBundle\", \"". $this->bundle->getName()  ."\"", $this->rootDir . '/config/config.yml');
    }

    private function getBundleNameWithoutBundle()
    {
        return preg_replace('/bundle$/i', '', strtolower($this->bundle->getName()));
    }

    public function generateErrorTemplates()
    {
        $dirPath = sprintf("%s/Resources/views/Error", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/views/Error", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));

        $this->renderFile('/error.html.twig', $this->rootDir . '/Resources/TwigBundle/views/Exception/error.html.twig', $this->parameters);
        $this->renderFile('/error404.html.twig', $this->rootDir . '/Resources/TwigBundle/views/Exception/error404.html.twig', $this->parameters);
        $this->renderFile('/error500.html.twig', $this->rootDir . '/Resources/TwigBundle/views/Exception/error500.html.twig', $this->parameters);
        $this->renderFile('/error503.html.twig', $this->rootDir . '/Resources/TwigBundle/views/Exception/error503.html.twig', $this->parameters);
    }

    public function generateAssets()
    {
        $dirPath = sprintf("%s/Resources/public", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/public", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));

        $assetsTypes = array(
            'files',
            'img',
            'js',
            'scss'
        );

        foreach ($assetsTypes as $type) {
            $this->generateAssetsForType($skeletonDir, $dirPath, $type);
        }
    }

    public function generateFixtures()
    {
        $dirPath = $this->bundle->getPath() . '/DataFixtures/ORM';
        $skeletonDir = $this->skeletonDir . '/DataFixtures/ORM';

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'DefaultSiteFixtures');
        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'SitemapFixtures');

    }

    public function generatePagepartConfigs()
    {
        $dirPath = sprintf("%s/Resources/config", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/config", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));

        $this->filesystem->copy($skeletonDir . '/pageparts/banners.yml', $dirPath . '/pageparts/banners.yml', true);
        $this->filesystem->copy($skeletonDir . '/pageparts/form.yml', $dirPath . '/pageparts/form.yml', true);
        $this->filesystem->copy($skeletonDir . '/pageparts/home.yml', $dirPath . '/pageparts/home.yml', true);
        $this->filesystem->copy($skeletonDir . '/pageparts/main.yml', $dirPath . '/pageparts/main.yml', true);
        $this->filesystem->copy($skeletonDir . '/pageparts/footer.yml', $dirPath . '/pageparts/footer.yml', true);
    }

    /**
     * @param Bundle $bundle     The bundle
     * @param array  $parameters The template parameters
     *
     * @throws \RuntimeException
     */
    public function generatePagetemplateConfigs()
    {
        $dirPath = sprintf("%s/Resources/config/pagetemplates", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/config/pagetemplates", $this->fullSkeletonDir);
        $this->setSkeletonDirs(array($skeletonDir));

        $this->filesystem->copy($skeletonDir . '/contentpage-singlecolumn.yml', $dirPath . '/contentpage-singlecolumn.yml', true);
        GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/contentpage-singlecolumn.yml');
        $this->filesystem->copy($skeletonDir . '/contentpage.yml', $dirPath . '/contentpage.yml', true);
        GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/contentpage.yml');
        $this->filesystem->copy($skeletonDir . '/formpage-singlecolumn.yml', $dirPath . '/formpage-singlecolumn.yml', true);
        GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/formpage-singlecolumn.yml');
        $this->filesystem->copy($skeletonDir . '/formpage.yml', $dirPath . '/formpage.yml', true);
        GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/formpage.yml');
        $this->filesystem->copy($skeletonDir . '/homepage.yml', $dirPath . '/homepage.yml', true);
        GeneratorUtils::replace("~~~BUNDLE~~~", $this->bundle->getName(), $dirPath . '/homepage.yml');
    }

    public function generateForm()
    {
        $dirPath = $this->bundle->getPath() . '/Form/Pages';
        $skeletonDir = $this->skeletonDir . '/Form/Pages';

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'ContentPageAdminType');

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'FormPageAdminType');

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'HomePageAdminType');
    }

    public function generateEntities()
    {
        $dirPath = sprintf("%s/Entity/Pages", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Entity/Pages", $this->skeletonDir);

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'ContentPage');

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'FormPage');

        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'HomePage');
    }

    public function overrideDefaultController()
    {
        $dirPath = sprintf("%s/Controller", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Controller", $this->skeletonDir);
        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'DefaultController', true);
    }

    public function generateDefaultLocaleFallbackCode()
    {
        $dirPath = sprintf("%s/EventListener", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/EventListener", $this->skeletonDir);
        $this->generateSkeletonBasedClass($skeletonDir, $dirPath, 'DefaultLocaleListener');

        $dirPath = sprintf("%s/Resources/config", $this->bundle->getPath());
        $skeletonDir = sprintf("%s/Resources/config", $this->fullSkeletonDir);
        $this->filesystem->copy($skeletonDir . '/services.yml', $dirPath . '/services.yml', true);
        GeneratorUtils::replace("~~~APPNAME~~~", strtolower($this->bundle->getName()), $dirPath . '/services.yml');
        GeneratorUtils::replace("~~~NAMESPACE~~~", $this->parameters['namespace'], $dirPath . '/services.yml');
    }




    /**
     * @param string $skeletonDir The dir of the entity skeleton.
     * @param string $dirPath     The full fir of where the entity should be created.
     * @param string $className   The class name of the entity to create.
     * @param bool   $override    Override the file or not.
     *
     * @throws \RuntimeException
     */
    private function generateSkeletonBasedClass($skeletonDir, $dirPath, $className, $override = false)
    {
        $classPath = sprintf("%s/%s.php", $dirPath, $className);
        $skeletonPath = sprintf("%s/%s.php", $skeletonDir, $className);
        if (file_exists($classPath)) {
            if ($override) {
                unlink($classPath);
            } else {
                throw new \RuntimeException(sprintf('Unable to generate the %s class as it already exists under the %s file', $className, $classPath));
            }
        }
        $this->renderFile($skeletonPath, $classPath, $this->parameters);
    }

    /**
     * Generate the assets for assetsType
     *
     * @param $skeletonDir
     * @param $dirPath
     * @param $assetsType
     */
    private function generateAssetsForType($skeletonDir, $dirPath, $assetsType)
    {
        $this->filesystem->mirror(sprintf("%s/$assetsType/", $skeletonDir), sprintf("%s/$assetsType/", $dirPath));
    }

}
