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
    protected $strict;

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
            'null' => 'is_null',
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
            $validators[$type] = $this->createValidator($type, $func);
        }

        return $validators;
    }

    protected function createValidator($type, callable $callable)
    {
        return function ($value) use ($callable, $type) {
            if ($callable($value)) {
                return true;
            }

            $givenType = $this->getType($value);

            $path = $this->getNormalizedPath();

            $this->addError($path, "The path of '$path' requires to be a $type, $givenType is given");

            return false;
        };
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
     * Define a new type.
     *
     * @param string $type
     * @param mixed $definition
     */
    public function defineType(string $type, $definition)
    {
        if (isset($this->types[$type])) {
            throw new \InvalidArgumentException("The type: $type is already exists");
        }

        $this->types[$type] = $this->buildTypeValidator($type, $definition);
    }

    protected $errors = [];
    protected $path = ['$'];

    protected function getNormalizedPath()
    {
        return strtr(implode('.', $this->path), [
            '.[' => '[',
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
     * @param $strict
     * @return bool
     */
    public function matches($data, $type, $strict = false)
    {
        $this->reset();

        $this->strict = $strict;

        return $this->matchInternal($data, $type);
    }

    protected function buildTypeValidator($type, $definition)
    {
        if (is_callable($definition)) {
            $validator = $this->createValidator($type, $definition);
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
        if (is_object($data)) {
            $data = (array)$data;
        }

        if ($this->strict && !$this->matchObjectKeys($data, $definition)) {
            return false;
        }

        $hasErrors = false;
        foreach ($definition as $name => $type) {
            array_push($this->path, $name);
            $result = $this->matchInternal($data[$name] ?? null, $type);
            array_pop($this->path);

            if (!$result) {
                $hasErrors = true;
            }
        }

        return !$hasErrors;
    }

    protected function matchObjectKeys($data, $definition)
    {
        $requiredKeys = array_keys($definition);
        $providedKeys = array_keys($data);

        sort($requiredKeys);
        sort($providedKeys);

        if ($requiredKeys === $providedKeys) {
            return true;
        }

        $absenceKeys     = array_diff($requiredKeys, $providedKeys);
        $notRequiredKeys = array_diff($providedKeys, $requiredKeys);

        $message = "The object keys doesn't match the type definition";

        if ($absenceKeys) {
            $absenceKeys = implode(',', $absenceKeys);
            $message .= ": '$absenceKeys' are absent";
        }

        if ($notRequiredKeys) {
            $notRequiredKeys = implode(',', $notRequiredKeys);
            $message .= ": '$notRequiredKeys' are not required";
        }

        $this->addError($this->getNormalizedPath(), $message);

        return false;
    }

    protected function matchInternal($data, $type)
    {
        if (is_string($type) && $type[0] === '?') {
            if ($data === null) {
                return true;
            }
            $type = substr($type, 1);
        }

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
