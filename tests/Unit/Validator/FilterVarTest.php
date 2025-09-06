<?php declare(strict_types = 1);

namespace Tests\Unit\Validator;

use BackedEnum;
use InvalidArgumentException;
use ReflectionEnum;
use Shredio\RequestMapper\Filter\FilterVar;
use Shredio\RequestMapper\Filter\FilterResult;
use Tests\Fixtures\IntBackedStatus;
use Tests\TestCase;
use ValueError;

final class FilterVarTest extends TestCase
{

    public function testStringFilter(): void
    {
        $filter = new FilterVar(FILTER_CALLBACK, 'string', null, [FilterVar::class, 'stringFilter']);
        
        $result = $filter->call('test');
        $this->assertInstanceOf(FilterResult::class, $result);
        $this->assertEquals('test', $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(123);
        $this->assertEquals('123', $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(true);
        $this->assertEquals('1', $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(false);
        // Note: filter_var with FILTER_CALLBACK might handle boolean differently
        $this->assertEquals('', $result->value); // Actual behavior - filter_var converts false to empty string before callback
        $this->assertTrue($result->isValid);
        
        $result = $filter->call([]);
        $this->assertEquals([], $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testIntFilter(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_INT, 'int');
        
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('456');
        $this->assertEquals(456, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('not_an_int');
        $this->assertEquals('not_an_int', $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testFloatFilter(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_FLOAT, 'float');
        
        $result = $filter->call(123.45);
        $this->assertEquals(123.45, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('123.45');
        $this->assertEquals(123.45, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('not_a_float');
        $this->assertEquals('not_a_float', $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testBoolFilter(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_BOOL, 'bool');
        
        $result = $filter->call(true);
        $this->assertTrue($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('1');
        $this->assertTrue($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('0');
        $this->assertFalse($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('invalid');
        $this->assertEquals('invalid', $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testNullableHandling(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_INT, 'int', null, null, true);
        
        $result = $filter->call(null);
        $this->assertNull($result->value);
        $this->assertTrue($result->isValid);
        
        $nonNullableFilter = new FilterVar(FILTER_VALIDATE_INT, 'int', null, null, false);
        $result = $nonNullableFilter->call(null);
        $this->assertNull($result->value);
        $this->assertFalse($result->isValid);
    }

    public function testCreateFromTypeInt(): void
    {
        $filter = FilterVar::createFromType('int', false);
        
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('456');
        $this->assertEquals(456, $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeFloat(): void
    {
        $filter = FilterVar::createFromType('float', false);
        
        $result = $filter->call(123.45);
        $this->assertEquals(123.45, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('67.89');
        $this->assertEquals(67.89, $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeBool(): void
    {
        $filter = FilterVar::createFromType('bool', false);
        
        $result = $filter->call(true);
        $this->assertTrue($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('1');
        $this->assertTrue($result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeString(): void
    {
        $filter = FilterVar::createFromType('string', false);
        
        $result = $filter->call('test');
        $this->assertEquals('test', $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(123);
        $this->assertEquals('123', $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeWithNullable(): void
    {
        $filter = FilterVar::createFromType('int', true);
        
        $result = $filter->call(null);
        $this->assertNull($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeWithEnum(): void
    {
        $filter = FilterVar::createFromType('Tests\\Fixtures\\Status', false);
        
        $result = $filter->call('draft');
        $this->assertEquals(\Tests\Fixtures\Status::DRAFT, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('published');
        $this->assertEquals(\Tests\Fixtures\Status::PUBLISHED, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('invalid_status');
        $this->assertEquals('invalid_status', $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testCreateFromTypeWithNullableEnum(): void
    {
        $filter = FilterVar::createFromType('Tests\\Fixtures\\Status', true);
        
        $result = $filter->call(null);
        $this->assertNull($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('draft');
        $this->assertEquals(\Tests\Fixtures\Status::DRAFT, $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromTypeThrowsExceptionForUnsupportedType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type specified: UnsupportedType');
        
        FilterVar::createFromType('UnsupportedType', false);
    }

    public function testCreateFromIntFilterBasicTypes(): void
    {
        // Test String (0)
        $filter = FilterVar::createFromIntFilter(FilterVar::String);
        $result = $filter->call('test');
        $this->assertEquals('test', $result->value);
        $this->assertTrue($result->isValid);
        
        // Test Int (1)
        $filter = FilterVar::createFromIntFilter(FilterVar::Int);
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
        
        // Test Float (2)
        $filter = FilterVar::createFromIntFilter(FilterVar::Float);
        $result = $filter->call(123.45);
        $this->assertEquals(123.45, $result->value);
        $this->assertTrue($result->isValid);
        
        // Test Bool (3)
        $filter = FilterVar::createFromIntFilter(FilterVar::Bool);
        $result = $filter->call(true);
        $this->assertTrue($result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromIntFilterArrayTypes(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::StringArray);
        $result = $filter->call(['test1', 'test2']);
        $this->assertEquals(['test1', 'test2'], $result->value);
        $this->assertTrue($result->isValid);
        
        // Test IntArray (11)
        $filter = FilterVar::createFromIntFilter(FilterVar::IntArray);
        $result = $filter->call([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $result->value);
        $this->assertTrue($result->isValid);
        
        // Test FloatArray (12)
        $filter = FilterVar::createFromIntFilter(FilterVar::FloatArray);
        $result = $filter->call([1.1, 2.2, 3.3]);
        $this->assertEquals([1.1, 2.2, 3.3], $result->value);
        $this->assertTrue($result->isValid);
        
        // Test BoolArray (13)
        $filter = FilterVar::createFromIntFilter(FilterVar::BoolArray);
        $result = $filter->call([true, false, true]);
        $this->assertEquals([true, false, true], $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromIntFilterNullableTypes(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::StringOrNull);
        $result = $filter->call(null);
        $this->assertNull($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('test');
        $this->assertEquals('test', $result->value);
        $this->assertTrue($result->isValid);
        
        // Test IntOrNull (101)
        $filter = FilterVar::createFromIntFilter(FilterVar::IntOrNull);
        $result = $filter->call(null);
        $this->assertNull($result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromIntFilterNullableArrayTypes(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::StringOrNullArray);
        $result = $filter->call(['test', null, 'test2']);
        $this->assertEquals(['test', null, 'test2'], $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testCreateFromIntFilterThrowsExceptionForInvalidFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filter specified: 999');
        
        FilterVar::createFromIntFilter(999);
    }

    public function testStringFilterStaticMethod(): void
    {
        $this->assertEquals('test', FilterVar::stringFilter('test'));
        $this->assertEquals('123', FilterVar::stringFilter(123));
        $this->assertEquals('1', FilterVar::stringFilter(true));
        $this->assertEquals('0', FilterVar::stringFilter(false));
        $this->assertNull(FilterVar::stringFilter(null));
        $this->assertNull(FilterVar::stringFilter([]));
        $this->assertNull(FilterVar::stringFilter(new \stdClass()));
    }

    public function testInvokeMethod(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_INT, 'int');
        
        $result = $filter(123);
        $this->assertEquals(123, $result->value);
		$this->assertTrue($result->isValid);
        
        $result = $filter('invalid');
        $this->assertEquals('invalid', $result->value);
		$this->assertFalse($result->isValid);
    }

    public function testPostProcessorCallback(): void
    {
        $postProcessor = function (mixed $value): FilterResult {
            return new FilterResult(strtoupper($value));
        };
        
        $filter = new FilterVar(
            FILTER_CALLBACK,
            'string',
            null,
            [FilterVar::class, 'stringFilter'],
            false,
            $postProcessor
        );
        
        $result = $filter->call('test');
        $this->assertEquals('TEST', $result->value);
        $this->assertTrue($result->isValid);
    }

    public function testWithFlags(): void
    {
        $filter = new FilterVar(FILTER_VALIDATE_INT, 'int', FILTER_FLAG_ALLOW_OCTAL);
        
        $result = $filter->call('0123');
        $this->assertEquals(83, $result->value); // Octal 123 = decimal 83
        $this->assertTrue($result->isValid);
    }

    public function testWithOptions(): void
    {
        $filter = new FilterVar(
            FILTER_VALIDATE_INT,
            'int',
            null,
            ['min_range' => 1, 'max_range' => 100]
        );
        
        $result = $filter->call(50);
        $this->assertEquals(50, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(150);
        $this->assertEquals(150, $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testEnumWithIntBackedType(): void
    {
        $filter = FilterVar::createFromType(IntBackedStatus::class, false);
        
        $result = $filter->call(1);
        $this->assertEquals(IntBackedStatus::Active, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(0);
        $this->assertEquals(IntBackedStatus::Inactive, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(99);
        $this->assertEquals(99, $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testCreateFromTypeWithUnsupportedEnumBackingType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type specified: NonExistentClass');
        
        FilterVar::createFromType('NonExistentClass', false);
    }

    public function testArrayValidationWithScalarValues(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::IntArray);
        
        // Array should pass
        $result = $filter->call([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $result->value);
        $this->assertTrue($result->isValid);
        
        // Scalar should fail with FILTER_REQUIRE_ARRAY flag
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testScalarValidationWithArrayValues(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::Int);
        
        // Scalar should pass
        $result = $filter->call(123);
        $this->assertEquals(123, $result->value);
        $this->assertTrue($result->isValid);
        
        // Array should fail with FILTER_REQUIRE_SCALAR flag
        $result = $filter->call([123]);
        $this->assertEquals([123], $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testEnumPostProcessorHandlesNonStringAndNonInt(): void
    {
        $filter = FilterVar::createFromType('Tests\\Fixtures\\Status', false);
        
        // Test with array (should return original value with isValid = false)
        $result = $filter->call([]);
        $this->assertEquals([], $result->value);
        $this->assertFalse($result->isValid); // Filter fails before postProcessor is called
        
        // Test with object (should return original value with isValid = false)
        $object = new \stdClass();
        $result = $filter->call($object);
        $this->assertEquals($object, $result->value);
        $this->assertFalse($result->isValid); // Filter fails before postProcessor is called
    }

    public function testComplexFilterOptions(): void
    {
        // Test with complex options array structure
        $filter = new FilterVar(
            FILTER_VALIDATE_INT,
            'int',
            FILTER_FLAG_ALLOW_OCTAL | FILTER_FLAG_ALLOW_HEX,
            ['min_range' => 0, 'max_range' => 255]
        );
        
        $result = $filter->call('0xFF'); // Hex 255
        $this->assertEquals(255, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call('0377'); // Octal 255
        $this->assertEquals(255, $result->value);
        $this->assertTrue($result->isValid);
        
        $result = $filter->call(256); // Outside range
        $this->assertEquals(256, $result->value);
        $this->assertFalse($result->isValid);
    }

    public function testFilterVarConstants(): void
    {
        // Verify all constants are properly defined
        $this->assertEquals(0, FilterVar::String);
        $this->assertEquals(1, FilterVar::Int);
        $this->assertEquals(2, FilterVar::Float);
        $this->assertEquals(3, FilterVar::Bool);
        
        $this->assertEquals(10, FilterVar::StringArray);
        $this->assertEquals(11, FilterVar::IntArray);
        $this->assertEquals(12, FilterVar::FloatArray);
        $this->assertEquals(13, FilterVar::BoolArray);
        
        $this->assertEquals(100, FilterVar::StringOrNull);
        $this->assertEquals(101, FilterVar::IntOrNull);
        $this->assertEquals(102, FilterVar::FloatOrNull);
        $this->assertEquals(103, FilterVar::BoolOrNull);
        
        $this->assertEquals(110, FilterVar::StringOrNullArray);
        $this->assertEquals(111, FilterVar::IntOrNullArray);
        $this->assertEquals(112, FilterVar::FloatOrNullArray);
        $this->assertEquals(113, FilterVar::BoolOrNullArray);
    }

    public function testBoundaryIntFilterValues(): void
    {
        $filter = FilterVar::createFromIntFilter(FilterVar::BoolOrNullArray);
        $result = $filter->call([true, null, false]);
        $this->assertEquals([true, null, false], $result->value);
        $this->assertTrue($result->isValid);
        
        // Test that values outside valid range throw exception
        $this->expectException(InvalidArgumentException::class);
        FilterVar::createFromIntFilter(4); // No filter for value 4
    }

    public function testGetType(): void
    {
        $stringFilter = new FilterVar(FILTER_CALLBACK, 'string', null, [FilterVar::class, 'stringFilter']);
        $this->assertEquals('string', $stringFilter->getType());

        $intFilter = new FilterVar(FILTER_VALIDATE_INT, 'int');
        $this->assertEquals('int', $intFilter->getType());

        $floatFilter = new FilterVar(FILTER_VALIDATE_FLOAT, 'float');
        $this->assertEquals('float', $floatFilter->getType());

        $boolFilter = new FilterVar(FILTER_VALIDATE_BOOL, 'bool');
        $this->assertEquals('bool', $boolFilter->getType());

        // Test with factory methods
        $fromTypeFilter = FilterVar::createFromType('string', false);
        $this->assertEquals('string', $fromTypeFilter->getType());

        $fromIntFilter = FilterVar::createFromIntFilter(FilterVar::IntOrNull);
        $this->assertEquals('int', $fromIntFilter->getType());

        // Test with enum
        $enumFilter = FilterVar::createFromType('Tests\\Fixtures\\Status', false);
        $this->assertEquals('string', $enumFilter->getType());

        $intEnumFilter = FilterVar::createFromType('Tests\\Fixtures\\IntBackedStatus', false);
        $this->assertEquals('int', $intEnumFilter->getType());
    }

}
