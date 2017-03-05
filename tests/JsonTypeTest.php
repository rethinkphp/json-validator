<?php

namespace rethink\jsv\tests;

use PHPUnit_Framework_TestCase;
use rethink\jsv\Validator;

/**
 * Class JsonTypeTest
 *
 * @package utl\tests
 */
class JsonTypeTest extends PHPUnit_Framework_TestCase
{
    protected function createValidator()
    {
        $validator = new Validator();

        $validator->defineType('timestamp', $this->timestampValidator());

        return $validator;
    }

    public function basicTypes()
    {
        return [
            ['string', 'foobar', true],
            ['string', 123, false],
            ['integer', 123, true],
            ['double', 123.1, true],

            ['number', 123.1, true],
            ['number', 234, true],
            ['number', '234', false],

            ['array', [], true],
            ['array', [1, 2, 3], true],
            ['array', [1 => 1, 2, 3], false],

            ['object', [1 => 1, 2, 3], true],
            ['object', [], false],
            ['object', ['a' => 'b'], true],
        ];
    }

    /**
     * @dataProvider basicTypes
     */
    public function testMatchBasicTypes($type, $data, $result)
    {
        $json = $this->createValidator();

        $method = $result ? 'assertTrue' : 'assertFalse';

        $this->$method($json->matches($data, $type));
    }

    protected function userType()
    {
        return [
            'name' => 'string',
            'gender' => 'string',
            'age' => 'integer',
            'tags' => ['string'],
        ];
    }

    protected function userData()
    {
        return [
            'name' => 'John',
            'gender' => 'Male',
            'age' => 18,
            'tags' => ['Foo', 'Bar'],
        ];
    }

    public function testMatchCustomType()
    {
        $json = $this->createValidator();

        $json->defineType('user', $this->userType());

        $this->assertTrue($json->matches($this->userData(), 'user'));
    }


    public function testMatchArrayOfCustomType()
    {
        $json = $this->createValidator();

        $json->defineType('user', $this->userType());

        $this->assertTrue($json->matches([$this->userData()], ['user']));
    }

    protected function timestampValidator()
    {
        return function ($value) {
            if ((!is_string($value) && !is_numeric($value)) || strtotime($value) === false) {
                return false;
            }

            $date = date_parse($value);

            return checkdate($date['month'], $date['day'], $date['year']);
        };
    }

    public function testAddCustomTypeThroughCallable()
    {
        $json = $this->createValidator();


        $this->assertTrue($json->matches('2017-01-01 00:00:00', 'timestamp'));
        $this->assertFalse($json->matches('2017-01-91 00:00:00', 'timestamp'));
    }

    public function typeErrorMessages()
    {
        return [
            // Basic Types
            [
                123,
                'string',
                '$',
                "The path of '$' requires to be a string, integer is given",
            ],
            [
                [],
                'object',
                '$',
                "The path of '$' requires to be a object, array is given",
            ],
            [
                new \stdClass(),
                'array',
                '$',
                "The path of '$' requires to be a array, object is given",
            ],
            [
                ['a' => 'b'],
                'array',
                '$',
                "The path of '$' requires to be a array, object is given",
            ],
            [
                null,
                'string',
                '$',
                "The path of '$' requires to be a string, null is given",
            ],

            // Complex Types
            [
                ['foo' => 123],
                ['foo' => 'string'],
                '$.foo',
                "The path of '$.foo' requires to be a string, integer is given",
            ],
            [
                [1, 2, 3],
                ['string'],
                '$[0]',
                "The path of '$[0]' requires to be a string, integer is given",
            ],
            [
                [
                    'foo' => [3, 2, 1],
                ],
                [
                    'foo' => ['string'],
                ],
                '$.foo[0]',
                "The path of '$.foo[0]' requires to be a string, integer is given",
            ],

            // Custom Types
            [
                'foo',
                'timestamp',
                '$',
                "The path of '$' requires to be a timestamp, string is given",
            ],
            [
                ['foo' => 'bar'],
                ['foo' => 'timestamp'],
                '$.foo',
                "The path of '$.foo' requires to be a timestamp, string is given",
            ],

        ];
    }

    /**
     * @dataProvider typeErrorMessages
     */
    public function testErrorMessages($value, $type, $key, $message)
    {
        $json = $this->createValidator();

        $this->assertFalse($json->matches($value, $type));
        $this->assertEquals($message, $json->getErrors()[$key] ?? '');
    }

    public function typeStrictData()
    {
        return [
            [
                ['key1' => 'v1', 'key3' => 'v3'],
                [
                    'key1' => 'string',
                    'key2' => 'string',
                ],
                '$',
                "The object keys doesn't match the type definition: 'key2' are absent: 'key3' are not required",
            ]
        ];
    }

    /**
     * @dataProvider typeStrictData
     */
    public function testStrictMode($value, $type, $key, $message)
    {
        $json = $this->createValidator();

        $this->assertFalse($json->matches($value, $type, true));
        $this->assertEquals($message, $json->getErrors()[$key] ?? '');
    }

    public function typeNullableData()
    {
        return [
            [
                [
                    'name' => 'foo',
                    'gender' => null,
                ],
                [
                    'name' => 'string',
                    'gender' => '?string',
                ],
                true,
            ],
            [
                ['foo', 'bar', null],
                ['?string'],
                true,
            ],
            [
                [
                    'dt' =>  null,
                ],
                [
                    'dt' => '?timestamp',
                ],
                true,
            ],
            [
                'foo',
                '?integer',
                false,
                '$',
                'The path of \'$\' requires to be a integer, string is given',
            ]
        ];
    }

    /**
     * @dataProvider typeNullableData
     */
    public function testNullableTypes($value, $type, $matched, $key = null, $message = null)
    {
        $json = $this->createValidator(true);

        $method = $matched ? 'assertTrue' : 'assertFalse';
        $this->$method($json->matches($value, $type));

        if (!$matched) {
            $this->assertEquals($message, $json->getErrors()[$key] ?? null);
        }
    }
}
