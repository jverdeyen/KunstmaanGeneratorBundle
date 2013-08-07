<?php

namespace Kunstmaan\GeneratorBundle\Generator;


use Kunstmaan\GeneratorBundle\Helper\CommandAssistant;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;

// TODO: Provide a generic way to modify all kinds of files.

class KunstmaanGenerator extends Generator
{

    /** @var CommandAssistant */
    protected $assistant;

    public function setAssistant(CommandAssistant $assistant)
    {
        $this->assistant = $assistant;
    }


    /**
     * @param $stepName
     * @param $callable
     *
     * @return array An array with steps that need to be done.
     *
     * @throws \RuntimeException
     */
    protected function executeStep($stepName, $callable)
    {
        try {
            $this->assistant->write("$stepName: ");

            $ret = $callable();

            $this->assistant->writeLine("<info>OK</info>");

            return $ret;
        } catch (\Exception $error) {
            $this->assistant->writeLine("<error>ERROR</error>");
            $this->assistant->writeError($error->getMessage());
            throw new \RuntimeException($error->getMessage());
        }
    }

    /**
     * @param array $steps The steps as a hash with the key beeing the step name and the value the name of the function on the class.
     *                     A function can't have any arguments.
     *
     * @return array An array with for each step an array with things that need to be done.
     */
    protected function executeSteps(array $steps)
    {
        $ret = array();
        foreach ($steps as $stepName => $funcName) {
            $ret[] = $this->executeStep($stepName, function() use ($funcName) {
               $this->$funcName();
            });
        }

        return $ret;
    }

    /**
     * @var array
     */
    protected $parameters;

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

}