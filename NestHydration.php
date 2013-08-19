<?php

namespace NestHydration;

class NestHydration
{
	const SPL_OBJECT = 'object';
	const ASSOCIATIVE_ARRAY = 'associative';
	const ARRAY_ACCESS_OBJECT = 'array_access_object';
	
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
	public static function nest($table, $resultType = NestHydration::ASSOCIATIVE_ARRAY, $propertyMapping = null)
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
		
		if (!in_array($resultType, array(NestHydration::SPL_OBJECT, NestHydration::ASSOCIATIVE_ARRAY, NestHydration::ARRAY_ACCESS_OBJECT))) {
			// invalid result type specified, use default
			$resultType = NestHydration::ASSOCIATIVE_ARRAY;
		}
		
		if (!is_integer(key($table)) || empty($table)) {
			// internall table should be a table format but an associative
			// array could be passed as the first (and only) row of that table
			$table = array($table);
		}
		
		if ($propertyMapping === null) {
			// property mapping not specified, determine it from column names
			$propertyMapping = static::propertyMappingFromColumnHints(array_keys($table[0]));
		}
		
		if (empty($propertyMapping)) {
			// properties is empty, can't form object to return
			return null;
		}
		if (isset($propertyMapping[0]) && empty($propertyMapping[0])) {
			// should return a list but don't know anything about the structure
			// of the items in the list, return empty list
			return array();
		}
		
		// precalculate identity columns in advance to row processing, works
		// by removing columns that don't belong
		$identityMapping = array_merge_recursive($propertyMapping);
		static::filterToIdentityMapping($identityMapping);
		
		// precalculate list of contained properties for each possible
		// structure, indexed by identity columns
		$propertyListMap = array();
		static::populatePropertyListMap($propertyListMap, $propertyMapping);
		
		// default is either an empty list or null structure
		$structure = is_integer(key($propertyMapping)) ? array() : null;
		
		// initialize map for keys of identity columns to the nested structures
		$mapByIndentityKeyToStruct = array();
		
		// row by row build up the data structure using the recursive
		// populate function.
		$lastRow = null;
		foreach ($table as $row) {
			if ($lastRow === null) {
				$diff = $row;
			} else {
				// by knowning the changes populateStructure can be faster
				$diff = array_diff_assoc($row, $lastRow);
			}
			static::populateStructure($structure, $row, $resultType, $propertyMapping, $diff, $identityMapping, $propertyListMap, $mapByIndentityKeyToStruct);
			
			$lastRow = $row;
		}
		
		return $structure;
	}
	
	/**
	 * Creates identityMapping by filtering out non identity properties from
	 * from propertyMapping. Used by nest for efficient iteration.
	 */
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
	
	/**
	 * Create a list of non identity properties by identity column. Used by
	 * nest for efficient iteration.
	 */
	protected static function populatePropertyListMap(&$propertyListMap, $propertyMapping)
	{
		if (is_integer(key($propertyMapping))) {
			static::populatePropertyListMap($propertyListMap, $propertyMapping[0]);
			return;
		}
		$identityColumn = current($propertyMapping);
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
	
	/**
	 * Populate structure with row data based propertyMapping with useful hints
	 * coming from diff, identityMapping and propertyListMap
	 */
	protected static function populateStructure(&$structure, $row, $resultType, $propertyMapping, $diff, $identityMapping, $propertyListMap, &$mapByIndentityKeyToStruct)
	{
		if (empty($propertyMapping)) {
			// nothing to do
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
			
			if (isset($mapByIndentityKeyToStruct[$row[$identityColumn]])) {
				// structure has already been started, further changes would
				// be nested in deeper structure, get the position in the
				// list of existing structure
				$pos = $mapByIndentityKeyToStruct[$row[$identityColumn]][0];
			} else {
				if (empty($structure)) {
					// first in the list
					$pos = 0;
				} else {
					// add to end of the list
					end($structure);
					$pos = key($structure) + 1;
				}
				// create new structure in the list
				if ($resultType === NestHydration::ASSOCIATIVE_ARRAY) {
					$newStructure = array();
				} elseif ($resultType === NestHydration::SPL_OBJECT) {
					$newStructure = new \StdClass;
				} elseif ($resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
					$newStructure = new \NestHydration\ArrayAccess();
				} else {
					throw new \Exception('invalid result type');
				}
				$structure[$pos] = $newStructure;
				
				// store structure identity key for quick reference if needed later
				$mapByIndentityKeyToStruct[$row[$identityColumn]] = array($pos, array());
			}
			
			// populate the structure in the list
			static::populateStructure($structure[$pos], $row, $resultType, $propertyMapping[0], $diff, $identityMapping[0], $propertyListMap, $mapByIndentityKeyToStruct[$row[$identityColumn]][1]);
			return;
		}
		
		// not a list, so a structure
		
		// get the identity column and property, move identity mapping along
		$identityProperty = key($identityMapping);
		$identityColumn = array_shift($identityMapping);
		
		if ($row[$identityColumn] === null) {
			// the identity column null, the structure doesn't exist
			return;
		} elseif ($structure === null) {
			if ($resultType === NestHydration::ASSOCIATIVE_ARRAY) {
				$structure = array();
			} elseif ($resultType === NestHydration::SPL_OBJECT) {
				$structure = new \StdClass;
			} elseif ($resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
				$structure = new \NestHydration\ArrayAccess();
			} else {
				throw new \Exception('invalid result type');
			}
		}
		
		if (isset($diff[$identityColumn])) {
			// identity is different so this structure is new
			
			// go through properties for structure and copy from row
			foreach ($propertyListMap[$identityColumn] as $property) {
				// pointer to the structure property
				if ($resultType === NestHydration::SPL_OBJECT || $resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
					$structurePropertyPointer = &$structure->$property;
				} elseif ($resultType === NestHydration::ASSOCIATIVE_ARRAY) {
					$structurePropertyPointer = &$structure[$property];
				} else {
					throw new \Exception('invalid result type');
				}
				
				$structurePropertyPointer = $row[$propertyMapping[$property]];
			}
		}
		
		// go through the nested structures remaining in identityMapping
		foreach ($identityMapping as $property => $x) {
			// pointer to the structure property
			if ($resultType === NestHydration::SPL_OBJECT || $resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
				$structurePropertyPointer = &$structure->$property;
			} elseif ($resultType === NestHydration::ASSOCIATIVE_ARRAY) {
				$structurePropertyPointer = &$structure[$property];
			} else {
				throw new \Exception('invalid result type');
			}
			
			if (!isset($structurePropertyPointer)) {
				// nested structure doesn't already exist, initialize
				$structurePropertyPointer = is_integer(key($identityMapping[$property])) ? array() : null;
				$mapByIndentityKeyToStruct[$property] = array();
			}
			
			// go into the nested structure
			static::populateStructure($structurePropertyPointer, $row, $resultType, $propertyMapping[$property], $diff, $identityMapping[$property], $propertyListMap, $mapByIndentityKeyToStruct[$property]);
		}
	}
	
	/**
	 * Returns a property mapping data structure based on the names of columns
	 * in columnList. Used internally by nest when its propertyMapping param
	 * is not specified.
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