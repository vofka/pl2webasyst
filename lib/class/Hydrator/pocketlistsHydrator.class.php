<?php

/**
 * Class pocketlistsHydrator
 */
class pocketlistsHydrator implements pocketlistsHydratorInterface
{
    /**
     * @var array
     */
    private $reflectionClassMap = [];

    /**
     * @param pocketlistsHydratableInterface $object
     * @param array                          $fields
     * @param array                          $dbFields
     *
     * @return array|mixed
     * @throws ReflectionException
     */
    public function extract(pocketlistsHydratableInterface $object, array $fields = [], $dbFields = [])
    {
        $reflection = $this->getReflectionClass(get_class($object));

        $result = [];

        if (empty($fields)) {
            $fields = $reflection->getProperties();
        }

        $object->beforeExtract($fields);

        foreach ($fields as $name) {
            $toExtractField = $name->getName();
            if (!isset($dbFields[$toExtractField])) {
                continue;
            }

            $methodName = 'get'.$this->getMethodName($toExtractField);

            try {
                $method = $reflection->getMethod($methodName);

                if ($method && $method->isPublic()) {
                    $result[$toExtractField] = $method->invoke($object);
                }
            } catch (ReflectionException $ex) {

            }
        }

        return $result;
    }

    /**
     * @param pocketlistsHydratableInterface $object
     * @param array                      $data
     *
     * @return object|pocketlistsHydratableInterface
     * @throws ReflectionException
     */
    public function hydrate(pocketlistsHydratableInterface $object, array $data)
    {
        $reflection = $this->getReflectionClass($object);

        $object = is_object($object) ? $object : $reflection->newInstanceWithoutConstructor();

        foreach ($data as $name => $value) {
            $methodName = 'set'.$this->getMethodName($name);
            try {
                $method = $reflection->getMethod($methodName);

                if ($method && $method->isPublic()) {
                    $method->invoke($object, $value);
                }
            } catch (ReflectionException $ex) {

            }
        }

        $object->afterHydrate();

        return $object;
    }


    /**
     * @param string|object $target
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    private function getReflectionClass($target)
    {
        $className = is_object($target) ? get_class($target) : $target;
        if (!isset($this->reflectionClassMap[$className])) {
            $this->reflectionClassMap[$className] = new ReflectionClass($className);
        }

        return $this->reflectionClassMap[$className];
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    private function getMethodName($name)
    {
        return str_replace('_', '', ucwords($name, '_'));
    }
}