<?php

namespace NestHydration;

class NestHydration
{
	const SPL_OBJECT = 0;
	const ASSOCIATIVE_ARRAY = 1;
	
	/**
	 * @param array $table must be either an associtative array or a list of
	 *   assoicative arrays. The columns in the table MUST be strings and not
	 *   numeric.
	 * @param SPL_OBJECT|ASSOCIATIVE_ARRAY $resultType
	 * @param array $propertyMapping
	 * @return array returns a nested data structure that in accordance to
	 *   that specified by the $propertyMapping param and populated with data
	 *   from the $table parameter. Support output of nested associative arrays
	 *   and lists or nested spl objects and lists.
	 */
	public static function nest($table, $resultType = NestHydration::SPL_OBJECT, $propertyMapping = null)
	{
		if ($table === null) {
			return null;
		}
		
		if (!is_array($table)) {
			throw new \Exception('nest expects param table to be an array');
		}
		
		if (!is_array($propertyMapping) && $propertyMapping !== null) {
			throw new \Exception('nest expects param propertyMapping to be an array');
		}
		
		if (!in_array($resultType, array(NestHydration::SPL_OBJECT, NestHydration::ASSOCIATIVE_ARRAY))) {
			$resultType = NestHydration::SPL_OBJECT;
		}
		
		if (!is_integer(key($table)) || empty($table)) {
			$table = array($table);
		}
		
		if ($propertyMapping === null) {
			$propertyMapping = static::propertyMappingFromColumnHints(array_keys($table[0]));
		}
		
		if (empty($propertyMapping)) {
			// properties is empty, can't form object to return
			return null;
		}
		if (isset($propertyMapping[0]) && empty($propertyMapping[0])) {
			return array();
		}
		
		$identityMapping = array_merge_recursive($propertyMapping);
		static::filterToIdentityMapping($identityMapping);
		
		$propertyListMap = array();
		static::populatePropertyListMap($propertyListMap, $propertyMapping);
		
		// default is either an empty list or null structure
		$structure = is_integer(key($propertyMapping)) ? array() : null;
		
		$lastRow = null;
		foreach ($table as $row) {
			if ($lastRow === null) {
				$diff = $row;
			} else {
				$diff = array_diff_assoc($row, $lastRow);
			}
			static::populateStructure($structure, $diff, $row, $resultType, $propertyMapping, $identityMapping, $propertyListMap);
			
			$lastRow = $row;
		}
		
		return $structure;
	}
	
	// creates identityMapping by filtering out non identity properties
	// from propertyMapping. used later for efficient iteration
	protected static function filterToIdentityMapping(&$identityMapping)
	{
		if (is_integer(key($identityMapping))) {
			static::filterToIdentityMapping($identityMapping[0]);
			return;
		}
		$propertyList = array_keys($identityMapping);
		array_shift($propertyList);
		foreach ($propertyList as $property) {
			if (is_array($identityMapping[$property])) {
				static::filterToIdentityMapping($identityMapping[$property]);
			} else {
				unset($identityMapping[$property]);
			}
		}
	}
	
	// create a list of non identity properties by identity column
	protected static function populatePropertyListMap(&$propertyListMap, $propertyMapping)
	{
		if (is_integer(key($propertyMapping))) {
			static::populatePropertyListMap($propertyListMap, $propertyMapping[0]);
			return;
		}
		$identityColumn = array_shift($propertyMapping);
		foreach ($propertyMapping as $property => $column) {
			if (is_array($column)) {
				static::populatePropertyListMap($propertyListMap, $column);
			} else {
				if (!isset($propertyListMap[$identityColumn])) {
					$propertyListMap[$identityColumn] = array();
				}
				$propertyListMap[$identityColumn][] = $property;
			}
		}
	}
	
	// populate structure with new data from row diff
	protected static function populateStructure(&$structure, &$diff, $row, $resultType, $propertyMapping, $identityMapping, $propertyListMap)
	{
		if (empty($propertyMapping)) {
			return;
		}
		if (is_integer(key($identityMapping))) {
			// list of nested structures
			$identityColumn = current($identityMapping[0]);
			
			if ($identityColumn === false) {
				return;
			}
			
			if ($row[$identityColumn] === null) {
				// structure is empty
				$structure = array();
				return;
			}
			
			end($structure);
			$pos = (integer) key($structure);
			if (empty($structure)) {
				$structure[$pos] = $resultType === NestHydration::SPL_OBJECT ? new \StdClass : array();
			} elseif (isset($diff[$identityColumn])) {
				$structure[++$pos] = $resultType === NestHydration::SPL_OBJECT ? new \StdClass : array();
			}
			
			static::populateStructure($structure[$pos], $diff, $row, $resultType, $propertyMapping[0], $identityMapping[0], $propertyListMap);
			return;
		}
		
		$identityProperty = key($identityMapping);
		$identityColumn = array_shift($identityMapping);
		
		if ($row[$identityColumn] === null) {
			$structure = null;
		}
		
		if (isset($diff[$identityColumn])) {
			if ($resultType === NestHydration::SPL_OBJECT) {
				$structurePropertyPointer = &$structure->$identityProperty;
			} else {
				$structurePropertyPointer = &$structure[$identityProperty];
			}
			$structurePropertyPointer = $diff[$identityColumn];
			unset($diff[$identityColumn]);
			
			foreach ($propertyListMap[$identityColumn] as $property) {
				if ($resultType === NestHydration::SPL_OBJECT) {
					$structurePropertyPointer = &$structure->$property;
				} else {
					$structurePropertyPointer = &$structure[$property];
				}
				$structurePropertyPointer = $row[$propertyMapping[$property]];
				if (isset($diff[$propertyMapping[$property]])) {
					unset($diff[$propertyMapping[$property]]);
				}
			}
		}
		foreach ($identityMapping as $property => $x) {
			if ($resultType === NestHydration::SPL_OBJECT) {
				$structurePropertyPointer = &$structure->$property;
			} else {
				$structurePropertyPointer = &$structure[$property];
			}
			if (!isset($structurePropertyPointer)) {
				$structurePropertyPointer = is_integer(key($identityMapping[$property])) ? array() : null;
			}
			static::populateStructure($structurePropertyPointer, $diff, $row, $resultType, $propertyMapping[$property], $identityMapping[$property], $propertyListMap);
		}
	}
	
	/**
	 * used internally by nest to build the propertyMapping structure based
	 * on column names
	 */
	protected static function propertyMappingFromColumnHints($columnList)
	{
		$propertyMapping = array();
		
		foreach ($columnList as $column) {
			$pointer =& $propertyMapping;
			
			$navList = explode('_', $column);
			$leaf = array_pop($navList);
			foreach ($navList as $nav) {
				if ($nav === '') {
					$nav = 0;
				}
				if (!array_key_exists($nav, $pointer)) {
					$pointer[$nav] = array();
				}
				$pointer =& $pointer[$nav];
			}
			$pointer[$leaf] = $column;
		}
		
		return $propertyMapping;
	}
}