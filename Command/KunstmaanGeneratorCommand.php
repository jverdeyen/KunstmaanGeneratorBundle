<?php

namespace Kunstmaan\GeneratorBundle\Command;


use Kunstmaan\GeneratorBundle\Helper\GeneratorUtils;
use Kunstmaan\GeneratorBundle\Helper\CommandAssistant;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand,
    Sensio\Bundle\GeneratorBundle\Command\Validators;

use Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\HttpKernel\Kernel;


abstract class KunstmaanGeneratorCommand extends GenerateDoctrineCommand
{

    /**
     * @var CommandAssistant
     */
    protected $assistant;


    /**
     * The text to be displayed on top of the generator.
     *
     * @return string|array
     */
    protected abstract function getWelcomeText();

    /**
     * Interacts with the user.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->setInputAndOutput($input, $output);

        $this->assistant->writeSection($this->getWelcomeText());

        $this->doInteract();
    }

    abstract protected function doInteract();


    private function setInputAndOutput(InputInterface $input, OutputInterface $output)
    {
        if (is_null($this->assistant)) {
            $this->assistant = new CommandAssistant();
            $this->assistant->setDialog($this->getDialogHelper());
            $this->assistant->setKernel($this->getApplication()->getKernel());
        }
        $this->assistant->setOutput($output);
        $this->assistant->setInput($input);
    }


    /**
     * @return Kernel
     */
    private function getKernel()
    {
        return $this->getApplication()->getKernel();
    }





    /**
     * A list of strings that are required.
     *
     * @return array
     */
    protected abstract function getOptionsRequired();

    /**
     * When one of the options is missing an error will be thrown.
     *
     * @param array $options
     *
     * @throws \RuntimeException
     */
    private function ensureOptionsProvided(array $options)
    {
        foreach ($options as $option) {
            if (null === $this->assistant->getOption($option)) {
                throw new \RuntimeException(sprintf('The "%s" option must be provided.', $option));
            }
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws \RuntimeException
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setInputAndOutput($input, $output);

        $this->ensureOptionsProvided($this->getOptionsRequired());

        return $this->doExecute();
    }



    /**
     * This function implements the final execution of the Generator.
     * It calls the execute function with the correct parameters.
     */
    protected abstract function doExecute();

    /**
     * Asks for the namespace and sets it on the InputInterface as the 'namespace' option, if this option is not set yet.
     *
     * @param array $text    What you want printed before the namespace is asked.
     *
     * @return string The namespace. But it's also been set on the InputInterface.
     */
    protected function askForNamespace(array $text = null)
    {
        $namespace = $this->assistant->getOptionOrDefault('namespace', null);

        try {
            $namespace = $namespace ? Validators::validateBundleNamespace($namespace) : null;
        } catch (\Exception $error) {
            $this->assistant->writeError($error->getMessage());
        }

        $namespaces = $this->getNamespaceAutoComplete($this->getKernel());

        while (true) {
            if (!is_null($text) && (count($text) > 0)) {
                $this->assistant->writeLine($text);
            }

            $namespace = $this->assistant->askAndValidate('Bundle Namespace', array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateBundleNamespace'), null, $namespaces);

            try {
                Validators::validateBundleNamespace($namespace);
                break;
            }  catch (\Exception $e) {
                $this->assistant->writeLine(sprintf('<bg=red>Namespace "%s" does not exist.</>', $namespace));
            }
        }

        $this->assistant->setOption('namespace', $namespace);

        return $namespace;
    }

    /**
     * Asks for the prefix and sets it on the InputInterface as the 'prefix' option, if this option is not set yet.
     * Will set the default to a snake_cased namespace when the namespace has been set on the InputInterface.
     *
     * @param array  $text What you want printed before the prefix is asked. If null is provided it'll write a default text.
     * @param string $namespace An optional namespace. If this is set it'll create the default based on this prefix.
     *  If it's not provided it'll check if the InputInterface already has the namespace option.
     *
     * @return string The prefix. But it's also been set on the InputInterface.
     */
    protected function askForPrefix(array $text = null, $namespace = null)
    {
        $prefix = $this->assistant->getOptionOrDefault('prefix', null);

        if (is_null($text)) {
            $text = array(
                '',
                'You can add a prefix to the table names of the generated entities for example: <comment>projectname_bundlename_</comment>',
                'Enter an underscore \'_\' if you don\'t want a prefix.',
                ''
            );
        }

        if (is_null($prefix)) {
            if (count($text) > 0) {
                $this->assistant->writeLine($text);
            }

            if (is_null($namespace) || empty($namespace)) {
                $namespace = $this->assistant->getOption('namespace');
            } else {
                $namespace = $this->fixNamespace($namespace);
            }
            $defaultPrefix = GeneratorUtils::cleanPrefix($this->convertNamespaceToSnakeCase($namespace));
            $prefix = GeneratorUtils::cleanPrefix($this->assistant->ask('Tablename prefix', $defaultPrefix));
            $this->assistant->setOption('prefix', $prefix);
        }

        return $prefix;
    }

    /**
     * Converts something like Namespace\BundleNameBundle to namspace_bundlenamebundle.
     *
     * @param string $namespace
     * @return string
     */
    private function convertNamespaceToSnakeCase($namespace)
    {
        if (is_null($namespace)) {
            return null;
        }

        $namespace = $this->fixNamespace($namespace);

        $parts = explode('/', $namespace);
        $parts = array_map(function($k) {
            return strtolower($k);
        }, $parts);

        return implode('_', $parts);
    }

    /**
     * Returns a list of namespaces as array with a forward slash to split the namespace & bundle.
     *
     * @param Kernel $kernel
     * @return array
     */
    private function getNamespaceAutoComplete(Kernel $kernel)
    {
        $ret = array();
        foreach ($kernel->getBundles() as $k => $v) {
            $ret[] = $this->fixNamespace($v->getNamespace());
        }

        return $ret;
    }

    /**
     * Replaces '\' with '/'.
     *
     * @param $namespace
     * @return mixed
     */
    private function fixNamespace($namespace)
    {
        return str_replace('\\', '/', $namespace);
    }
}