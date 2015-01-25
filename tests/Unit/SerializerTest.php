<?php namespace SuperClosure\Test\Unit;

use SuperClosure\Analyzer\TokenAnalyzer;
use SuperClosure\Serializer;

/**
 * @covers \SuperClosure\Serializer
 */
class SerializerTest extends \PHPUnit_Framework_TestCase
{
    public function testSerializingAndUnserializing()
    {
        $serializer = new Serializer(new TokenAnalyzer());
        $originalFn = function ($n) {return $n +  5;};
        $serializedFn = $serializer->serialize($originalFn);
        $unserializedFn = $serializer->unserialize($serializedFn);

        $this->assertEquals(10, $originalFn(5));
        $this->assertEquals(10, $unserializedFn(5));
    }

    public function testGettingClosureData()
    {
        $adjustment = 2;
        $fn = function ($n) use (&$fn, $adjustment) {
            $result = $n > 1 ? $n * $fn($n - 1) : 1;
            return $result + $adjustment;
        };

        $serializer = new Serializer(new TokenAnalyzer());

        // Test getting full closure data.
        $data = $serializer->getData($fn);
        $this->assertCount(9, $data);
        $this->assertInstanceOf('ReflectionFunction', $data['reflection']);
        $this->assertGreaterThan(0, strpos($data['code'], '$adjustment'));
        $this->assertFalse($data['hasThis']);
        $this->assertCount(2, $data['context']);
        $this->assertTrue($data['hasRefs']);
        $this->assertInstanceOf(__CLASS__, $data['binding']);
        $this->assertEquals(__CLASS__, $data['scope']);
        $this->assertInternalType('array', $data['tokens']);

        // Test getting serializable closure data.
        $data = $serializer->getData($fn, true);
        $this->assertCount(5, $data);
        $this->assertTrue(in_array(Serializer::RECURSION, $data['context']));
        $this->assertNull($data['binding']);
        $this->assertEquals(__CLASS__, $data['scope']);
        $this->assertArrayNotHasKey('reflection', $data);
    }

    public function testWrappingClosuresWithinVariables()
    {
        $serializer = new Serializer(
            $this->getMockForAbstractClass('SuperClosure\Analyzer\ClosureAnalyzer')
        );

        $value1 = function () {};
        Serializer::wrapClosures($value1, $serializer);
        $this->assertInstanceOf('SuperClosure\SerializableClosure', $value1);

        $value2 = ['fn' => function () {}];
        Serializer::wrapClosures($value2, $serializer);
        $this->assertInstanceOf('SuperClosure\SerializableClosure', $value2['fn']);

        $value3 = new \stdClass;
        $value3->fn = function () {};
        Serializer::wrapClosures($value3, $serializer);
        $this->assertInstanceOf('SuperClosure\SerializableClosure', $value3->fn);

        if (!defined('HHVM_VERSION')) {
            $value4 = new \ArrayObject([function () {}]);
            Serializer::wrapClosures($value4, $serializer);
            $this->assertInstanceOf('SuperClosure\SerializableClosure', $value4[0]);
        }

        $thing = new Serializer();
        $fn = function () {return $this->analyzer;};

        $value5 = $fn->bindTo($thing, 'SuperClosure\Serializer');
        Serializer::wrapClosures($value5, $serializer);
        $reflection = new \ReflectionFunction($value5->getClosure());
        $this->assertSame($thing, $reflection->getClosureThis());
        $this->assertEquals(get_class($thing), $reflection->getClosureScopeClass()->getName());

        $value6 = $fn->bindTo($thing);
        Serializer::wrapClosures($value6, $serializer);
        $reflection = new \ReflectionFunction($value6->getClosure());
        $this->assertEquals(__CLASS__, $reflection->getClosureScopeClass()->getName());
    }
}
