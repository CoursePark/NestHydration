<?php

namespace NestHydration;

class NestHydration
{
	const SPL_OBJECT = 'spl_object';
	const ASSOCIATIVE_ARRAY = 'associative_array';
	
	public static function nest($table, $hydrationType = NestHydration::SPL_OBJECT, $propertyMapping = null)
	{
		return static::largeNest($table, $propertyMapping);
	}
	
	public static function largeNest($table, $propertyMapping = null)
	{
		if ($table instanceof \Doctrine\DBAL\Query\QueryBuilder) {
			$table = $table->execute();
		}
		
		if ($table instanceof \Doctrine\DBAL\Driver\PDOStatement) {
			$table = $table->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		if ($table === null) {
			return null;
		}
		
		if (!is_array($table)) {
			throw new \Exception('nest expects param table to be an array');
		}
		
		if (!is_array($propertyMapping) && $propertyMapping !== null) {
			throw new \Exception('nest expects param propertyMapping to be an array');
		}
		
		if (!is_integer(key($table)) || empty($table)) {
			$table = array($table);
		}
		
		if ($propertyMapping === null) {
			$propertyMapping = static::propertyMappingFromColumnHints(array_keys($table[0]));
		}
		
		if (empty($propertyMapping)) {
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
				$lastRow = $row;
			}
			
			static::populateStructure($structure, $diff, $row, $propertyMapping, $identityMapping, $propertyListMap);
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
	public static function populatePropertyListMap(&$propertyListMap, $propertyMapping)
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
	protected static function populateStructure(&$structure, &$diff, $row, $propertyMapping, $identityMapping, $propertyListMap)
	{
		if (empty($propertyMapping)) {
			return;
		}
		if (is_integer(key($identityMapping))) {
			// list of nested structures
			$identityColumn = current($identityMapping[0]);
			
			if ($identityColumn === false || $row[$identityColumn] === null) {
				return;
			}
			
			end($structure);
			$pos = (integer) key($structure);
			if (empty($structure)) {
				$structure[$pos] = array();
			} elseif (isset($diff[$identityColumn])) {
				$structure[++$pos] = array();
			}
			
			static::populateStructure($structure[$pos], $diff, $row, $propertyMapping[0], $identityMapping[0], $propertyListMap);
			return;
		}
		
		// structure
		if ($structure === null) {
			$structure = array();
		}
		
		$identityProperty = key($identityMapping);
		$identityColumn = array_shift($identityMapping);
		if (isset($diff[$identityColumn])) {
			$structure[$identityProperty] = $diff[$identityColumn];
			unset($diff[$identityColumn]);
			
			foreach ($propertyListMap[$identityColumn] as $property) {
				$structure[$property] = $row[$propertyMapping[$property]];
				if (isset($diff[$propertyMapping[$property]])) {
					unset($diff[$propertyMapping[$property]]);
				}
			}
		}
		foreach ($identityMapping as $property => $x) {
			$structure[$property] = array();
			static::populateStructure($structure[$property], $diff, $row, $propertyMapping[$property], $identityMapping[$property], $propertyListMap);
		}
	}
	
	/**
	 * @param array $table must be either an associtative array or a list of
	 *   assoicative arrays. The columns in the table MUST be strings and not
	 *   numeric.
	 * @param array $propertyMapping
	 * @return array returns an associative array containing nested data
	 *   structures or a list of associative arrays containing nested data
	 *   structures depending on the nature of the data passed to the table
	 *   parameter. If null is passed in the table param null is returned.
	 *   If an empty array is passed in the table param an empty array is
	 *   returned.
	 */
	public static function smallNest($table, $propertyMapping = null)
	{
		if ($table instanceof \Doctrine\DBAL\Query\QueryBuilder) {
			$table = $table->execute();
		}
		
		if ($table instanceof \Doctrine\DBAL\Driver\PDOStatement) {
			$table = $table->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		if ($table === null) {
			return null;
		}
		
		if (!is_array($table)) {
			throw new \Exception('nest expects param table to be an array');
		}
		
		if (!is_array($propertyMapping) && $propertyMapping !== null) {
			throw new \Exception('nest expects param propertyMapping to be an array');
		}
		
		if (!is_integer(key($table)) || empty($table)) {
			$table = array($table);
		}
		
		if ($propertyMapping === null) {
			$propertyMapping = static::propertyMappingFromColumnHints(array_keys($table[0]));
		}
		
		// default is either an empty list or null structure
		$structure = is_integer(key($propertyMapping)) ? array() : null;
		
		$lastRow = null;
		foreach ($table as $row) {
			if (!is_array($row)) {
				throw new \Exception('nest expects param table to contain a list of associative arrays (a table)');
			}
			
			foreach ($row as $column => $value) {
				if (!is_string($column)) {
					throw new \Exception('nest expects param table to contain a list of associative arrays (a table)');
				}
				static::addToStructure($value, $column, $propertyMapping, $structure);
			}
		}
		
		return $structure;
	}
	
	/**
	 * the engine of the nest function is this recursive function which
	 * individually takes a value and a column and adds it to the structure
	 * being assembled in accordance with propertyMapping
	 */
	protected static function addToStructure($value, $column, $propertyMapping, &$structure)
	{
		if (is_integer(key($propertyMapping))) {
			$pos = (integer) key($structure); // could be null if empty
			while (true) { // content will be executed once or twice, no more
				if (!array_key_exists($pos, $structure)) {
					$structure[$pos] = null;
				}
				
				$result = static::addToStructure($value, $column, $propertyMapping[0], $structure[$pos]);
				if ($result !== 'need_new_structure') {
					// invalid_property or valid_for_structure
					return $result;
				}
				
				next($structure); // move the internal array pointer
				$pos++;
			}
		}
		
		foreach ($propertyMapping as $mapProperty => $mapColumn) {
			if (!is_string($mapColumn) || $mapColumn !== $column) {
				continue;
			}
			
			if ($structure === null) {
				$structure = array();
			}
			
			if (isset($structure[$mapProperty]) && $structure[$mapProperty] !== $value) {
				return 'need_new_structure';
			}
			
			if (!isset($structure[$mapProperty])) {
				$structure[$mapProperty] = $value;
			}
			
			return 'valid_for_structure';
		}
		
		foreach ($propertyMapping as $mapProperty => $subPropertyMapping) {
			if (!is_array($subPropertyMapping)) {
				continue;
			}
			
			if (!isset($structure[$mapProperty])) {
				$structure[$mapProperty] = is_integer(key($subPropertyMapping)) ? array() : null;
			}
			
			$result = static::addToStructure($value, $column, $subPropertyMapping, $structure[$mapProperty]);
			if ($result === 'valid_for_structure') {
				return $result;
			}
		}
		
		return 'invalid_property';
	}
	
	public static function smallNestObject($table, $propertyMapping = null)
	{
		if ($table instanceof \Doctrine\DBAL\Query\QueryBuilder) {
			$table = $table->execute();
		}
		
		if ($table instanceof \Doctrine\DBAL\Driver\PDOStatement) {
			$table = $table->fetchAll(\PDO::FETCH_ASSOC);
		}
		
		if ($table === null) {
			return null;
		}
		
		if (!is_array($table)) {
			throw new \Exception('nest expects param table to be an array');
		}
		
		if (!is_array($propertyMapping) && $propertyMapping !== null) {
			throw new \Exception('nest expects param propertyMapping to be an array');
		}
		
		if (!is_integer(key($table)) || empty($table)) {
			$table = array($table);
		}
		
		if ($propertyMapping === null) {
			$propertyMapping = static::propertyMappingFromColumnHints(array_keys($table[0]));
		}
		
		// default is either an empty list or null structure
		$structure = is_integer(key($propertyMapping)) ? array() : null;
		
		$lastRow = null;
		foreach ($table as $row) {
			if (!is_array($row)) {
				throw new \Exception('nest expects param table to contain a list of associative arrays (a table)');
			}
			
			foreach ($row as $column => $value) {
				if (!is_string($column)) {
					throw new \Exception('nest expects param table to contain a list of associative arrays (a table)');
				}
				static::addToObj($value, $column, $propertyMapping, $structure);
			}
		}
		
		return $structure;
	}
	
	protected static function addToObj($value, $column, $propertyMapping, &$structure)
	{
		if (is_integer(key($propertyMapping))) {
			$pos = (integer) key($structure); // could be null if empty
			while (true) { // content will be executed once or twice, no more
				if (!array_key_exists($pos, $structure)) {
					$structure[$pos] = null;
				}
				
				$result = static::addToObj($value, $column, $propertyMapping[0], $structure[$pos]);
				if ($result !== 'need_new_structure') {
					// invalid_property or valid_for_structure
					return $result;
				}
				
				next($structure); // move the internal array pointer
				$pos++;
			}
		}
		
		foreach ($propertyMapping as $mapProperty => $mapColumn) {
			if (!is_string($mapColumn) || $mapColumn !== $column) {
				continue;
			}
			
			if ($structure === null) {
				$structure = (object) array();
			}
			
			if (isset($structure->$mapProperty) && $structure->$mapProperty !== $value) {
				return 'need_new_structure';
			}
			
			if (!isset($structure->$mapProperty)) {
				$structure->$mapProperty = $value;
			}
			
			return 'valid_for_structure';
		}
		
		foreach ($propertyMapping as $mapProperty => $subPropertyMapping) {
			if (!is_array($subPropertyMapping)) {
				continue;
			}
			
			if (!isset($structure->$mapProperty)) {
				$structure->$mapProperty = is_integer(key($subPropertyMapping)) ? array() : null;
			}
			
			$result = static::addToObj($value, $column, $subPropertyMapping, $structure->$mapProperty);
			if ($result === 'valid_for_structure') {
				return $result;
			}
		}
		
		return 'invalid_property';
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