<?php

namespace NestHydration\Test;

require __DIR__ . '/../NestHydration.php';

use NestHydration\NestHydration;

class NestHydrationTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_null()
	{
		$table = null;
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, array());
		
		$this->assertNull($nested, 'should return null when passed null as table param');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 * @expectedException \Exception
	 */
	public function testNest_invalidTable()
	{
		$table = 'not a table';
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, array());
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_empty()
	{
		$table = array();
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, array());
		
		$this->assertEmpty($nested, 'should return empty array when passed empty array as table param');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_noProperties()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3');
		$propertyMapping = array();
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertNull($nested, 'should be null');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_listNoProperties()
	{
		$table = array(
			array('col1' => '1', 'col2' => '2', 'col3' => '3'),
		);
		$propertyMapping = array(array());
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertCount(0, $nested, 'should be an empty list');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_isStructure()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3');
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have different value for property');
		$this->assertCount(3, $nested, 'should have 3 properties in structure');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_isStructureObj()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3');
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
		);
		
		$nested = NestHydration::nest($table, NestHydration::SPL_OBJECT, $propertyMapping);
		
		$this->assertObjectHasAttribute('col1', $nested, 'should be an object with specified property');
		$this->assertEquals('1', $nested->col1, 'should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_isListOfStructures()
	{
		$table = array(
			array('col1' => '1_1', 'col2' => '1_2', 'col3' => '1_3'),
			array('col1' => '2_1', 'col2' => '2_2', 'col3' => '2_3'),
			array('col1' => '3_1', 'col2' => '3_2', 'col3' => '3_3'),
			array('col1' => '4_1', 'col2' => '4_2', 'col3' => '4_3'),
			array('col1' => '5_1', 'col2' => '5_2', 'col3' => '5_3'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
		));
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertCount(5, $nested, 'should be a list of structures 5 long');
		$this->assertArrayHasKey('col1', $nested[0], 'should be an associative array');
		$this->assertEquals('1_1', $nested[0]['col1'], 'should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_isListOfStructuresObj()
	{
		$table = array(
			array('col1' => '1_1', 'col2' => '1_2', 'col3' => '1_3'),
			array('col1' => '2_1', 'col2' => '2_2', 'col3' => '2_3'),
			array('col1' => '3_1', 'col2' => '3_2', 'col3' => '3_3'),
			array('col1' => '4_1', 'col2' => '4_2', 'col3' => '4_3'),
			array('col1' => '5_1', 'col2' => '5_2', 'col3' => '5_3'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
		));
		
		$nested = NestHydration::nest($table, NestHydration::SPL_OBJECT, $propertyMapping);
		
		$this->assertCount(5, $nested, 'should be a list of structures 5 long');
		$this->assertObjectHasAttribute('col1', $nested[0], 'should have the specified property');
		$this->assertEquals('1_1', $nested[0]->col1, 'should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_listCondensing()
	{
		$table = array(
			array('col1' => '1', 'col2' => '2', 'col3' => '3'),
			array('col1' => '1', 'col2' => '2', 'col3' => '3'),
			array('col1' => '2', 'col2' => '2', 'col3' => '3'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
		));
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertCount(2, $nested, 'should be list with two items because one item was condensed');
		$this->assertEquals(1, $nested[0]['col1'], 'first list item should have column value of 1');
		$this->assertEquals(2, $nested[1]['col1'], 'first list item should have column value of 2');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_singleNestedOneToOne()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1', 'sub_col2' => 'sub 2', 'sub_col3' => 'sub 3');
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		$this->assertArrayHasKey('col1', $nested['sub'], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 1', $nested['sub']['col1'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_listNestedOneToOne()
	{
		$table = array(
			array('col1' => '1a', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1a', 'sub_col2' => 'sub 2', 'sub_col3' => 'sub 3'),
			array('col1' => '1b', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1b', 'sub_col2' => 'sub 2', 'sub_col3' => 'sub 3'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			),
		));
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested[0], 'should be an associative array');
		$this->assertEquals('1a', $nested[0]['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested[0], 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested[0], 'nested sub should be an associative array');
		$this->assertArrayHasKey('col1', $nested[0]['sub'], 'nested sub should be an associative array');
		$this->assertCount(3, $nested[0]['sub'], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 1a', $nested[0]['sub']['col1'], 'nested sub should have different value for property');
		
		$this->assertArrayHasKey('sub', $nested[1], 'nested sub should be an associative array');
		$this->assertArrayHasKey('col1', $nested[1]['sub'], 'nested sub should be an associative array');
		$this->assertCount(3, $nested[1]['sub'], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 1b', $nested[1]['sub']['col1'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_singleNestedNullOneToOne()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => null, 'sub_col2' => null, 'sub_col3' => null);
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		$this->assertNull($nested['sub'], 'nested sub should be null');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_singleNestedOneToMany()
	{
		$table = array(
			array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1a', 'sub_col2' => 'sub 2a', 'sub_col3' => 'sub 3a'),
			array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1b', 'sub_col2' => 'sub 2b', 'sub_col3' => 'sub 3b'),
		);
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			)),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		
		$this->assertCount(2, $nested['sub'], 'should have list of structures as sub');
		
		$this->assertArrayHasKey('col2', $nested['sub'][0], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'][0], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 2a', $nested['sub'][0]['col2'], 'nested sub should have different value for property');
		
		$this->assertArrayHasKey('col3', $nested['sub'][1], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'][1], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 3b', $nested['sub'][1]['col3'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_singleNestedEmptyOneToMany()
	{
		$table = array(
			array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => null, 'sub_col2' => null, 'sub_col3' => null),
		);
		$propertyMapping = array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			)),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		$this->assertCount(0, $nested['sub'], 'should have an empty list');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_listWithNestedOneToMany()
	{
		$table = array(
			array('col1' => '1a', 'col2' => '2a', 'col3' => '3a', 'sub_col1' => 'sub 1a', 'sub_col2' => 'sub 2a', 'sub_col3' => 'sub 3a'),
			array('col1' => '1a', 'col2' => '2a', 'col3' => '3a', 'sub_col1' => 'sub 1b', 'sub_col2' => 'sub 2b', 'sub_col3' => 'sub 3b'),
			array('col1' => '1b', 'col2' => '2b', 'col3' => '3b', 'sub_col1' => 'sub 1a', 'sub_col2' => 'sub 2a', 'sub_col3' => 'sub 3a'),
			array('col1' => '1b', 'col2' => '2b', 'col3' => '3b', 'sub_col1' => 'sub 1b', 'sub_col2' => 'sub 2b', 'sub_col3' => 'sub 3b'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'col2' => 'col2',
			'col3' => 'col3',
			'sub' => array(array(
				'col1' => 'sub_col1',
				'col2' => 'sub_col2',
				'col3' => 'sub_col3',
			)),
		));
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertCount(2, $nested, 'should be a list of structures 2 long');
		
		$this->assertArrayHasKey('col3', $nested[0], 'should be an associative array');
		$this->assertEquals('3a', $nested[0]['col3'], 'should have different value for property');
		
		$this->assertArrayHasKey('sub', $nested[0], 'nested sub should be an associative array');
		$this->assertEquals('sub 3a', $nested[0]['sub'][0]['col3'], 'nested sub should have different value for property');
		
		$this->assertArrayHasKey('sub', $nested[1], 'nested sub should be an associative array');
		$this->assertEquals('sub 2b', $nested[1]['sub'][1]['col2'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_manyToOneRelation()
	{
		$table = array(
			array('col1' => 'a', 'sub_col1' => 'g'),
			array('col1' => 'b', 'sub_col1' => 'g'),
		);
		$propertyMapping = array(array(
			'col1' => 'col1',
			'sub' => array(
				'col1' => 'sub_col1',
			),
		));
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping);
		
		$this->assertCount(2, $nested, 'should have one item in list');
		$this->assertArrayHasKey('col1', $nested[0]['sub'], 'should have property in sub item');
		$this->assertArrayHasKey('col1', $nested[1]['sub'], 'should have property in sub item');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_emptyStructure()
	{
		$table = array();
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertNull($nested, 'should return null (empty structure) when auto determination of structure can not be determined because table is empty and propertyMapping is null');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_emptyList()
	{
		$table = array();
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY, true);
		
		$this->assertCount(0, $nested, 'should return empty array when auto determination of structure can not be determined because table is empty and propertyMapping is true');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_isStructure()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3');
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have different value for property');
		$this->assertCount(3, $nested, 'should have 3 properties in structure');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_isListOfStructures()
	{
		$table = array(
			array('_col1' => '1_1', '_col2' => '1_2', '_col3' => '1_3'),
			array('_col1' => '2_1', '_col2' => '2_2', '_col3' => '2_3'),
			array('_col1' => '3_1', '_col2' => '3_2', '_col3' => '3_3'),
			array('_col1' => '4_1', '_col2' => '4_2', '_col3' => '4_3'),
			array('_col1' => '5_1', '_col2' => '5_2', '_col3' => '5_3'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertCount(5, $nested, 'should be a list of structures 5 long');
		$this->assertEquals('col1', key($nested[0]), 'should be an associative array');
		$this->assertEquals('1_1', $nested[0]['col1'], 'should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_singleNestedOneToOne()
	{
		$table = array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub_col1' => 'sub 1', 'sub_col2' => 'sub 2', 'sub_col3' => 'sub 3');
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		$this->assertArrayHasKey('col1', $nested['sub'], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 1', $nested['sub']['col1'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_singleNestedOneToMany()
	{
		$table = array(
			array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub__col1' => 'sub 1a', 'sub__col2' => 'sub 2a', 'sub__col3' => 'sub 3a'),
			array('col1' => '1', 'col2' => '2', 'col3' => '3', 'sub__col1' => 'sub 1b', 'sub__col2' => 'sub 2b', 'sub__col3' => 'sub 3b'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertArrayHasKey('col1', $nested, 'should be an associative array');
		$this->assertEquals('1', $nested['col1'], 'should have value of 1 in property col1');
		$this->assertCount(4, $nested, 'should have 4 properties in structure');
		
		$this->assertArrayHasKey('sub', $nested, 'nested sub should be an associative array');
		
		$this->assertCount(2, $nested['sub'], 'should have list of structures as sub');
		
		$this->assertArrayHasKey('col2', $nested['sub'][0], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'][0], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 2a', $nested['sub'][0]['col2'], 'nested sub should have different value for property');
		
		$this->assertArrayHasKey('col3', $nested['sub'][1], 'nested sub should be an associative array');
		$this->assertCount(3, $nested['sub'][1], 'nested sub should have 3 properties in structure');
		$this->assertEquals('sub 3b', $nested['sub'][1]['col3'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_listWithNestedOneToMany()
	{
		$table = array(
			array('_col1' => '1a', '_col2' => '2a', '_col3' => '3a', '_sub__col1' => 'sub 1a', '_sub__col2' => 'sub 2a', '_sub__col3' => 'sub 3a'),
			array('_col1' => '1a', '_col2' => '2a', '_col3' => '3a', '_sub__col1' => 'sub 1b', '_sub__col2' => 'sub 2b', '_sub__col3' => 'sub 3b'),
			array('_col1' => '1b', '_col2' => '2b', '_col3' => '3b', '_sub__col1' => 'sub 1a', '_sub__col2' => 'sub 2a', '_sub__col3' => 'sub 3a'),
			array('_col1' => '1b', '_col2' => '2b', '_col3' => '3b', '_sub__col1' => 'sub 1b', '_sub__col2' => 'sub 2b', '_sub__col3' => 'sub 3b'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertCount(2, $nested, 'should be a list of structures 2 long');
		
		$this->assertArrayHasKey('col3', $nested[0], 'should be an associative array');
		$this->assertEquals('3a', $nested[0]['col3'], 'should have different value for property');
		
		$this->assertArrayHasKey('sub', $nested[0], 'nested sub should be an associative array');
		$this->assertEquals('sub 3a', $nested[0]['sub'][0]['col3'], 'nested sub should have different value for property');
		
		$this->assertArrayHasKey('sub', $nested[1], 'nested sub should be an associative array');
		$this->assertEquals('sub 2b', $nested[1]['sub'][1]['col2'], 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_listWithNestedOneToManyObj()
	{
		$table = array(
			array('_col1' => '1a', '_col2' => '2a', '_col3' => '3a', '_sub__col1' => 'sub 1a', '_sub__col2' => 'sub 2a', '_sub__col3' => 'sub 3a'),
			array('_col1' => '1a', '_col2' => '2a', '_col3' => '3a', '_sub__col1' => 'sub 1b', '_sub__col2' => 'sub 2b', '_sub__col3' => 'sub 3b'),
			array('_col1' => '1b', '_col2' => '2b', '_col3' => '3b', '_sub__col1' => 'sub 1a', '_sub__col2' => 'sub 2a', '_sub__col3' => 'sub 3a'),
			array('_col1' => '1b', '_col2' => '2b', '_col3' => '3b', '_sub__col1' => 'sub 1b', '_sub__col2' => 'sub 2b', '_sub__col3' => 'sub 3b'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::SPL_OBJECT);
		
		$this->assertCount(2, $nested, 'should be a list of structures 2 long');
		
		$this->assertObjectHasAttribute('col3', $nested[0], 'should have the specified property');
		$this->assertEquals('3a', $nested[0]->col3, 'should have different value for property');
		
		$this->assertObjectHasAttribute('sub', $nested[0], 'nested sub should have the specified property');
		$this->assertEquals('sub 3a', $nested[0]->sub[0]->col3, 'nested sub should have different value for property');
		
		$this->assertObjectHasAttribute('sub', $nested[1], 'nested sub should have the specified property');
		$this->assertEquals('sub 2b', $nested[1]->sub[1]->col2, 'nested sub should have different value for property');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_singleWithMultipleOneToMany()
	{
		$table = array(
			array('id' => 'a', 'subA__id' => 'g', 'subB__id' => 'x'),
			array('id' => 'a', 'subA__id' => 'g', 'subB__id' => 'y'),
			array('id' => 'a', 'subA__id' => 'h', 'subB__id' => 'x'),
			array('id' => 'a', 'subA__id' => 'h', 'subB__id' => 'y'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertCount(2, $nested['subA'], 'should have two item in list');
		$this->assertCount(2, $nested['subB'], 'should have two item in list');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_listWithMultipleOneToMany()
	{
		// 3 table each with ids 1 and 2. The first table is join with the
		// other two by a one-to-many relation. The resulting select ordered
		// that table by subB.id, subA.id, id.
		$table = array(
			array('_id' => 'a', '_subA__id' => 'g', '_subB__id' => 'x'),
			array('_id' => 'b', '_subA__id' => 'g', '_subB__id' => 'x'),
			array('_id' => 'a', '_subA__id' => 'h', '_subB__id' => 'x'),
			array('_id' => 'b', '_subA__id' => 'h', '_subB__id' => 'x'),
			array('_id' => 'a', '_subA__id' => 'g', '_subB__id' => 'y'),
			array('_id' => 'b', '_subA__id' => 'g', '_subB__id' => 'y'),
			array('_id' => 'a', '_subA__id' => 'h', '_subB__id' => 'y'),
			array('_id' => 'b', '_subA__id' => 'h', '_subB__id' => 'y'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertCount(2, $nested, 'should have one item in list');
		$this->assertCount(2, $nested[0]['subA'], 'should have two item in list');
		$this->assertCount(2, $nested[0]['subB'], 'should have two item in list');
		$this->assertCount(2, $nested[1]['subA'], 'should have two item in list');
		$this->assertCount(2, $nested[1]['subB'], 'should have two item in list');
	}
	
	/**
	 * @covers CoursePark\Service\BaseService::nest
	 */
	public function testNest_autoNesting_manyToOneRelation()
	{
		$table = array(
			array('_id' => 'a', '_sub_id' => 'g'),
			array('_id' => 'b', '_sub_id' => 'g'),
		);
		
		$nested = NestHydration::nest($table, NestHydration::ASSOCIATIVE_ARRAY);
		
		$this->assertCount(2, $nested, 'should have one item in list');
		$this->assertArrayHasKey('id', $nested[0]['sub'], 'should have property in sub item');
		$this->assertArrayHasKey('id', $nested[1]['sub'], 'should have property in sub item');
	}
}