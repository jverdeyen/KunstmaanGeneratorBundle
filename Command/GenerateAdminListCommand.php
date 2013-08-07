<?php

namespace Kunstmaan\GeneratorBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Sensio\Bundle\GeneratorBundle\Generator;

use Kunstmaan\GeneratorBundle\Generator\AdminListGenerator;
use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;

/**
 * Generates a KunstmaanAdminList
 */
class GenerateAdminListCommand extends KunstmaanGeneratorCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to create an admin list for (shortcut notation)'),))
            ->setDescription('Generates a KunstmaanAdminList')
            ->setHelp(<<<EOT
The <info>kuma:generate:adminlist</info> command generates an AdminList for a Doctrine ORM entity.

<info>php app/console kuma:generate:adminlist Bundle:Entity</info>
EOT
            )
            ->setName('kuma:generate:adminlist');
    }

    /**
     * @return int|null|void
     */
    protected function doExecute()
    {
        // TODO: Extract validations of Entity/Namespace/etc at the end Should be in the parent class.
        $entity = Validators::validateEntityName($this->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $entityClass = $this->getContainer()->get('doctrine')->getEntityNamespace($bundle).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);

        $this->assistant->writeSection('AdminList Generation');

        /** @var $generator AdminListGenerator */
        $generator = $this->getGenerator($this->getApplication()->getKernel()->getBundle("KunstmaanGeneratorBundle"));
        $generator->setAssistant($this->assistant);
        $generator->generate($bundle, $entityClass, $metadata[0]);

        $parts = explode('\\', $entity);
        $entityClass = array_pop($parts);
        $this->updateRouting($bundle, $entityClass);
    }



    protected function getWelcomeText()
    {
        return 'Welcome to the Kunstmaan admin list generator';
    }

    protected function getOptionsRequired()
    {
        return array('entity');
    }

    protected function doInteract()
    {
        // TODO: Extract Entity Logic.
        $entity = null;
        try {
            $entity = $this->assistant->getOption('entity') ? Validators::validateEntityName($this->assistant->getOption('entity')) : null;
        } catch (\Exception $error) {
            $this->assistant->writeError($error->getMessage());
        }

        if (is_null($entity)) {
            $this->assistant->writeLine(array(
                '',
                'This command helps you to generate an admin list for your entity.',
                '',
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
                '',
            ));

            $entity = $this->assistant->askAndValidate('The entity shortcut name', array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
            $this->assistant->setOption('entity', $entity);
        }
    }

    /**
     * @param Bundle $bundle      The bundle
     * @param string $entityClass The classname of the entity
     */
    protected function updateRouting(Bundle $bundle, $entityClass)
    {
        $auto = true;
        $isMultiLanguage = false;
        if ($this->assistant->isInteractive()) {
            $isMultiLanguage = $this->assistant->askConfirmation('Is it a multilanguage site', 'yes');
            $auto = $this->assistant->askConfirmation('Do you want to update the routing automatically', 'yes');
        }

        $prefix = $isMultiLanguage ? '/{_locale}' : '';

        $code = sprintf("%s:\n", $bundle->getName() . '_' . strtolower($entityClass) . '_admin_list');
        $code .= sprintf("    resource: @%s/Controller/%sAdminListController.php\n", $bundle->getName(), $entityClass);
        $code .= "    type:     annotation\n";
        $code .= sprintf("    prefix:   %s/admin/%s/\n", $prefix, strtolower($entityClass));
        if ($isMultiLanguage) {
            $code .= "    requirements:\n";
            $code .= "         _locale: %requiredlocales%\n";
        }

        if ($auto) {
            $file = $bundle->getPath() . '/Resources/config/routing.yml';
            $content = '';

            if (file_exists($file)) {
                $content = file_get_contents($file);
            } elseif (!is_dir($dir = dirname($file))) {
                mkdir($dir, 0777, true);
            }

            $content .= "\n";
            $content .= $code;

            if (false === file_put_contents($file, $content)) {
                $this->assistant->writeError("Failed adding the content automatically");
            } else {
                return;
            }
        }

        $this->assistant->writeLine(
            array(
                'Add the following to your routing.yml',
                '/*******************************/',
                $code,
                '/*******************************/'
            )
        );
    }

    protected function createGenerator()
    {
        return new AdminListGenerator($this->getContainer()->get('filesystem'), GeneratorUtils::getFullSkeletonPath('adminlist'));
    }
}
