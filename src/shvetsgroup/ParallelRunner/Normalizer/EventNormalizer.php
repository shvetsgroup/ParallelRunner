<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup\ParallelRunner\Normalizer;

use Behat\Behat\Definition\DefinitionSnippet;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer,
  Symfony\Component\Serializer\Normalizer\NormalizerInterface,
  Symfony\Component\Serializer\Normalizer\DenormalizerInterface,
  Symfony\Component\Serializer\Exception\RuntimeException,

  Behat\Gherkin\Node\ExampleStepNode,
  Behat\Gherkin\Node\FeatureNode,
  Behat\Gherkin\Node\ScenarioNode,
  Behat\Gherkin\Node\StepNode,
  Behat\Gherkin\Node\BackgroundNode,
  Behat\Gherkin\Node\OutlineNode,
  Behat\Gherkin\Filter\LineFilter;

/**
 * Event normalizer.
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class EventNormalizer extends GetSetMethodNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * Differs from parent by ability to fill optional parameters with default values.
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $data = $this->denormalizeData($data, $class, $format, $context);

        $reflectionClass = new \ReflectionClass($class);
        $constructor = $reflectionClass->getConstructor();

        if ($constructor) {
            $constructorParameters = $constructor->getParameters();

            $params = array();
            foreach ($constructorParameters as $constructorParameter) {
                $paramName = lcfirst($constructorParameter->name);

                if (isset($data[$paramName])) {
                    $params[] = $data[$paramName];
                    // don't run set for a parameter passed to the constructor
                    unset($data[$paramName]);
                } elseif (!$constructorParameter->isOptional()) {
                    throw new RuntimeException('Cannot create an instance of ' . $class .
                        ' from serialized data because its constructor requires ' .
                        'parameter "' . $constructorParameter->name . '" to be present.');
                } else {
                    $params[] = $constructorParameter->getDefaultValue();
                }
            }
            $object = $reflectionClass->newInstanceArgs($params);
        } else {
            $object = new $class;
        }

        foreach ($data as $attribute => $value) {
            $setter = 'set' . $attribute;
            if (method_exists($object, $setter)) {
                $object->$setter($value);
            }
        }

        return $object;
    }

    /**
     * Do some pre-processing before standard denormalization.
     *
     * @param $data
     * @param $class
     * @param null $format
     * @param array $context
     * @return mixed
     */
    function denormalizeData($data, $class, $format = null, array $context = array())
    {
        $container = $context['container'];
        $gherkin = $container->get('gherkin');
        if (isset($data['dispatcher'])) {
            $data['dispatcher'] = $container->get('behat.event_dispatcher');
        }
        if (isset($data['context'])) {
            $data['context'] = $container->get('behat.context.dispatcher')->createContext();
        }
        if (isset($data['feature'])) {
            $feature = reset($gherkin->load($data['feature']));
            $data['feature'] = $feature;
        }
        foreach (array('logicalParent', 'parent', 'scenario') as $key) {
            if (isset($data[$key])) {
                $feature = reset($gherkin->load($data[$key]['feature'], array(new LineFilter($data[$key]['line']))));
                $data[$key] = reset($feature->getScenarios());
            }
        }
        if (isset($data['outline'])) {
            $gherkin->setFreeze(false);
            $feature = reset(
                $gherkin->load($data['outline']['feature'], array(new LineFilter($data['outline']['line'])))
            );
            $outline = reset($feature->getScenarios());

            $steps = $outline->getSteps();
            foreach ($steps as $i => $step) {
                $steps[$i] = new ExampleStepNode($step, array());
            }
            $outline->setSteps($steps);
            $data['outline'] = $outline;
        }
        if (isset($data['background'])) {
            $feature = reset($gherkin->load($data['background']['feature']));
            $data['background'] = $feature->getBackground();
        }
        if (isset($data['step'])) {
            $feature = reset($gherkin->load($data['step']['feature']));
            $data['step'] = $this->findStep($feature, $data['step']['line']);
        }
        if (isset($data['snippet'])) {
            $definitionDispatcher = $container->get('behat.definition.dispatcher');
            $data['snippet'] = $definitionDispatcher->proposeDefinition($data['context'], $data['step']);
        }
        if (isset($data['exception'])) {
            $data['exception'] = new \Exception($data['exception']['message'], $data['exception']['code']);
        }

        return $data;
    }

    /**
     * Finds a step by it's line number.
     *
     * @param $feature
     * @param $line
     * @return null
     */
    private function findStep(FeatureNode $feature, $line)
    {
        $scenarios = $feature->getScenarios();
        // Background also have steps, so we should check them in as well.
        $scenarios[] = $feature->getBackground();
        $target_step = null;
        foreach ($scenarios as $scenario) {
            $steps = $scenario->getSteps();
            foreach ($steps as $step) {
                if ($step->getLine() == $line) {
                    if ($scenario instanceof OutlineNode) {
                        $target_step = new ExampleStepNode($step, array());
                    } else {
                        $target_step = $step;
                    }
                    break;
                }
            }
            if (!is_null($target_step)) {
                break;
            }
        }

        return $target_step;
    }

    /**
     * @return array
     */
    static public function normalizeEmpty()
    {
        return array();
    }

    /**
     * @return null
     */
    static public function normalizeNull()
    {
        return null;
    }

    /**
     * @param FeatureNode $feature
     * @return string
     */
    public function normalizeFeature(FeatureNode $feature)
    {
        return $feature->getFile();
    }

    /**
     * @param ScenarioNode|StepNode|BackgroundNode $parent
     * @return array
     */
    public function normalizeNode($parent)
    {
        $normalized_node = array(
            'feature' => $parent->getFile(),
            'line' => $parent->getLine(),
        );
        return $normalized_node;
    }

    /**
     * @param \Exception $exception
     * @return string
     */
    public function normalizeException($exception)
    {
        if (is_null($exception)) {
            $normalized_exception = null;
        } else {
            $normalized_exception = array(
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
            );
        }
        return $normalized_exception;
    }
}
