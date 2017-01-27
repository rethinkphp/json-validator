<?php

namespace rethink\jsv;

/**
 * Class Validator
 *
 * @package rethink\jsv
 */
class Validator
{
    protected $types = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->types = $this->getBuiltInTypeValidators();
    }

    protected function builtInTypes()
    {
        return [
            'integer' => 'is_integer',
            'double' => 'is_double',
            'boolean' => 'is_bool',
            'string' => 'is_string',
            'number' => [$this, 'isNumber'],
            'array' =>  [$this, 'isArray'],
            'object' =>  [$this, 'isObject'],
        ];
    }

    protected function getBuiltInTypeValidators()
    {
        $types = $this->builtInTypes();

        $validators = [];
        foreach ($types as $type => $func) {
            $validators[$type] = function ($value) use ($func, $type) {
                if ($func($value))  {
                    return true;
                }

                $givenType = $this->getType($value);

                $path = $this->getNormalizedPath();

                $this->addError($path, "The path of '$path' requires to be a $type, $givenType is given");

                return false;
            };
        }

        return $validators;
    }

    protected function isNumber($data)
    {
        return is_integer($data) || is_float($data);
    }

    protected function isArray($data)
    {
        return is_array($data) && (empty($data) || array_keys($data) === range(0, count($data) - 1));
    }

    protected function isObject($data)
    {
        return is_object($data) || (is_array($data) && !empty($data) && array_keys($data) !== range(0, count($data) - 1));
    }

    protected function getType($value)
    {
        if (is_null($value)) {
           return 'null';
        } else if (is_scalar($value)) {
            return gettype($value);
        } else if ($this->isArray($value)) {
            return 'array';
        } else {
            return 'object';
        }
    }

    /**
     * Add a new type.
     *
     * @param string $type
     * @param mixed $definition
     */
    public function addType(string $type, $definition)
    {
        if (isset($this->types[$type])) {
            throw new \InvalidArgumentException("The type: $type is already exists");
        }

        $this->types[$type] = $this->buildTypeValidator($definition);
    }

    protected $errors = [];
    protected $path = ['$'];

    protected function getNormalizedPath()
    {
        return strtr(implode('.', $this->path), [
            '.[' => '[',
            '].' => ']',
        ]);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function addError($key, $message)
    {
        $this->errors[$key] = $message;
    }

    protected function reset()
    {
        $this->errors = [];
    }

    /**
     * Return whether the data match the given type.
     *
     * @param $data
     * @param $type
     * @return bool
     */
    public function matches($data, $type)
    {
        $this->reset();

        return $this->matchInternal($data, $type);
    }

    protected function buildTypeValidator($definition)
    {
        if (is_callable($definition)) {
            $validator = $definition;
        } else if ($this->isObject($definition)) {
            $validator = function ($data) use ($definition) {
                return $this->matchObject($data, $definition);
            };
        } else if ($this->isArray($definition)) {
            $validator = function ($data) use ($definition) {
                return $this->matchArray($data, $definition);
            };
        }

        return $validator;
    }

    protected function matchArray(array $data, $definition)
    {
        $definition = $definition[0];

        foreach ($data as $index => $row) {
            array_push($this->path, '[' . $index . ']');
            $result = $this->matchInternal($row, $definition);
            array_pop($this->path);

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    protected function matchObject($data, $definition)
    {
        foreach ($definition as $name => $type) {
            array_push($this->path, $name);
            $result = $this->matchInternal($data[$name] ?? null, $type);
            array_pop($this->path);

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    protected function matchInternal($data, $type)
    {
        if (!is_string($type)) {
            $definition = $type;
        } else if (isset($this->types[$type])) {
            $definition = $this->types[$type];
        } else {
            throw new \InvalidArgumentException('The definition can not be recognized');
        }

        if (is_callable($definition)) {
            return $definition($data);
        } else if ($this->isObject($definition)) {
            return $this->matchObject($data, $definition);
        } else {
            return $this->matchArray($data, $definition);
        }
    }
}
