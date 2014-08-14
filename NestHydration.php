<?php

namespace NestHydration;

class NestHydration
{
	const SPL_OBJECT = 'object';
	const ASSOCIATIVE_ARRAY = 'associative';
	const ARRAY_ACCESS_OBJECT = 'array_access_object';
	
	/**
	 * Creates a data structure containing nested objects and/or arrays from
	 * tabular data based on a structure definition provided by
	 * structPropToColumnMap. If structPropToColumnMap is not provided but
	 * the data has column names that follow a particular convention then nested
	 * nested structures can also be created.
	 *
	 * @param array $data must be either an associtative array or a list of
	 *   assoicative arrays. The columns in the table MUST be strings and not
	 *   numeric.
	 * @param SPL_OBJECT|ASSOCIATIVE_ARRAY $resultType
	 * @param array|null|true $propertyMapping
	 * @return array returns a nested data structure that in accordance to
	 *   that specified by the $propertyMapping param and populated with data
	 *   from the $data parameter. Support output of nested associative arrays
	 *   and lists or nested spl objects and lists.
	 */
	public static function nest($data, $resultType = NestHydration::ASSOCIATIVE_ARRAY, $structPropToColumnMap = null) {
		// VALIDATE PARAMS AND BASIC INITIALIZATION
		
		$listOnEmpty = false;
		$columnList = null;
		
		if ($data === null) {
			return null;
		}
		
		if (!in_array($resultType, array(NestHydration::SPL_OBJECT, NestHydration::ASSOCIATIVE_ARRAY, NestHydration::ARRAY_ACCESS_OBJECT))) {
			// invalid result type specified, use default
			$resultType = NestHydration::ASSOCIATIVE_ARRAY;
		}
		
		if (!is_array($structPropToColumnMap) && $structPropToColumnMap !== null && $structPropToColumnMap !== true) {
			throw new \Exception('nest expects param propertyMapping to be an array, plain object, null, or true');
		}
		
		// propertyMapping can be set to true as a tie break between
		// returning null (empty structure) or an empty list
		if ($structPropToColumnMap === true) {
			$listOnEmpty = true;
			$structPropToColumnMap = null;
		} elseif (is_array($structPropToColumnMap) && is_integer(key($structPropToColumnMap))) {
			$listOnEmpty = true;
		}
		
		if (empty($data)) {
			return $listOnEmpty ? array() : null;
		}
		
		if (is_array($data) && !is_integer(key($data))) {
			// internal table should be a table format but an associative
			// array could be passed as the first (and only) row of that table
			$table = array($data);
		} elseif (is_array($data) && is_integer(key($data))) {
			$table = $data;
		} else {
			throw new \Exception('nest expects param data to form an plain object or an array of plain objects (forming a table)');
		}
		
		if ($structPropToColumnMap === null && !empty($table)) {
			// property mapping not specified, determine it from column names
			$structPropToColumnMap = self::structPropToColumnMapFromColumnHints(array_keys($table[0]));
		}
		
		if ($structPropToColumnMap === null) {
			// properties is empty, can't form structure or determine content
			// for a list. Assume a structure unless listOnEmpty
			return $listOnEmpty ? array() : null;
		} elseif (empty($table)) {
			// table is empty, return the appropriate empty result based on input definition
			return is_integer(key($structPropToColumnMap)) ? array() : null;
		}
		
		// COMPLETE VALIDATING PARAMS AND BASIC INITIALIZATION
		
		$meta = self::buildMeta($structPropToColumnMap);
		
		// BUILD FROM TABLE
		
		// struct is populated inside the build function
		$struct = array('base' => null);
		
		foreach ($table as $row) {
			foreach ($meta->primeIdColumnList as $primeIdColumn) {
				// for each prime id column (corresponding to a to many relation or
				// the top level) attempted to build an object
				self::_nest($row, $primeIdColumn, $struct, $meta, $resultType);
			}
		}
		
		return $struct['base'];
	}
	
	// defines function that can be called recursively
	protected static function _nest($row, $idColumn, &$struct, &$meta, $resultType) {
		$value = $row[$idColumn];
		
		if ($value === null) {
			// nothing to build
			return;
		}
		
		// only really concerned with the meta data for this identity column
		$objMeta = $meta->idMap[$idColumn];
		
		if (array_key_exists($value . '', $objMeta->cache)) {
			// object already exists in cache
			if ($objMeta->containingIdUsage === null) {
				// at the top level, parent is root
				return;
			}
			
			$containingId = $row[$objMeta->containingColumn];
			if (array_key_exists($value . '', $objMeta->containingIdUsage)
				&& array_key_exists($containingId . '', $objMeta->containingIdUsage[$value . ''])
			) {
				// already placed as to many relation in container, done
				return;
			}
			
			// not already placed as to many relation in container
			$obj = &$objMeta->cache[$value . ''];
		} else {
			// don't have an object defined for this yet, create it
			
			// create new structure in the list
			if ($resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
				$obj = new \NestHydration\ArrayAccess();
			} elseif ($resultType === NestHydration::SPL_OBJECT) {
				$obj = new \StdClass;
			} else { // ASSOCIATIVE_ARRAY
				$obj = array();
			}
			
			$objMeta->cache[$value . ''] = &$obj;
			
			// copy in properties from table data
			foreach ($objMeta->valueList as $frag) {
				if ($resultType === NestHydration::SPL_OBJECT) {
					$prop = $frag->prop;
					$obj->$prop = $row[$frag->column];
				} else { // ASSOCIATIVE_ARRAY, ARRAY_ACCESS_OBJECT
					$obj[$frag->prop] = $row[$frag->column];
				}
			}
			
			// initialize empty to many relations, they will be populated when
			// those objects build themselve and find this containing object
			foreach ($objMeta->toManyPropList as $prop) {
				if ($resultType === NestHydration::SPL_OBJECT) {
					$obj->$prop = array();
				} else { // ASSOCIATIVE_ARRAY, ARRAY_ACCESS_OBJECT
					$obj[$prop] = array();
				}
			}
			
			// intialize null to one relations and then recursively build them
			foreach ($objMeta->toOneList as $frag) {
				if ($resultType === NestHydration::SPL_OBJECT) {
					$prop = $frag->prop;
					$obj->$prop = null;
				} else { // ASSOCIATIVE_ARRAY, ARRAY_ACCESS_OBJECT
					$obj[$frag->prop] = null;
				}
				self::_nest($row, $frag->column, $struct, $meta, $resultType);
			}
		}
		
		// link from the parent
		if ($objMeta->containingColumn === null) {
			// parent is the top level
			if ($objMeta->isOneOfMany) {
				// it is an array
				if ($struct === null) {
					$struct = array();
				}
				$struct['base'][] = &$obj;
			} else {
				// it is this object
				$struct['base'] = &$obj;
			}
		} else {
			$containingId = $row[$objMeta->containingColumn];
			$container = &$meta->idMap[$objMeta->containingColumn]->cache[$containingId . ''];
			
			if ($objMeta->isOneOfMany) {
				// it is an array
				if ($resultType === NestHydration::SPL_OBJECT || $resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
					$prop = $objMeta->ownProp;
					$propObj = &$container->$prop;
					$propObj[] = &$obj;
				} else { // ASSOCIATIVE_ARRAY
					$container[$objMeta->ownProp][] = &$obj;
				}
			} else {
				// it is this object
				if ($resultType === NestHydration::SPL_OBJECT || $resultType === NestHydration::ARRAY_ACCESS_OBJECT) {
					$prop = $objMeta->ownProp;
					$container->$prop = &$obj;
				} else { // ASSOCIATIVE_ARRAY
					$container[$objMeta->ownProp] = &$obj;
				}
			}
			
			// record the containing id
			if (!array_key_exists($value . '', $objMeta->containingIdUsage)) {
				$objMeta->containingIdUsage[$value . ''] = array();
			}
			$objMeta->containingIdUsage[$value . ''][$containingId . ''] = true;
		}
	}
	
	/* Create a data structure that contains lookups and cache spaces for quick
	 * reference and action for the workings of the nest method.
	 */
	protected static function buildMeta($structPropToColumnMap) {
		// this data structure is populated by the _buildMeta function
		$meta = (object) array(
			'primeIdColumnList' => array(),
			'idMap' => array(),
		);
		
		if (empty($structPropToColumnMap)) {
			throw new \Exception('invalid structPropToColumnMap format');
		}
		
		if (is_integer(key($structPropToColumnMap))) {
			// call with first object, but inform _buidMeta it is an array
			self::_buildMeta($structPropToColumnMap[0], true, null, null, $meta);
		} else {
			// register first column as prime id column
			$meta->primeIdColumnList[] = key($structPropToColumnMap);
			
			// construct the rest
			self::_buildMeta($structPropToColumnMap, false, null, null, $meta);
		}
		
		return $meta;
	}
	
	// recursive internal function
	protected static function _buildMeta($structPropToColumnMap, $isOneOfMany, $containingColumn, $ownProp, &$meta) {
		if (empty($structPropToColumnMap)) {
			throw new \Exception('invalid structPropToColumnMap format');
		}
		
		$idColumn = $structPropToColumnMap[key($structPropToColumnMap)];
		
		if ($isOneOfMany) {
			$meta->primeIdColumnList[] = $idColumn;
		}
		
		$objMeta = (object) array(
			'valueList' => array(),
			'toOneList' => array(),
			'toManyPropList' => array(),
			'containingColumn' => $containingColumn,
			'ownProp' => $ownProp,
			'isOneOfMany' => $isOneOfMany === true,
			'cache' => array(),
			'containingIdUsage' => $containingColumn === null ? null : array(),
		);
		
		foreach ($structPropToColumnMap as $prop => $column) {
			if (is_string($column)) {
				// value property
				$objMeta->valueList[] = (object) array(
					'prop' => $prop,
					'column' => $column,
				);
			} elseif (is_array($column) && is_integer(key($column))) {
				// list of objects / to many relation
				$objMeta->toManyPropList[] = $prop;
				
				self::_buildMeta($column[0], true, $idColumn, $prop, $meta);
			} else {
				// object / to one relation
				$subIdColumn = current($column);
				
				$objMeta->toOneList[] = (object) array(
					'prop' => $prop,
					'column' => $subIdColumn,
				);
				
				self::_buildMeta($column, false, $idColumn, $prop, $meta);
			}
		}
		
		$meta->idMap[$idColumn] = $objMeta;
	}
	
	/**
	 * Returns a property mapping data structure based on the names of columns
	 * in columnList. Used internally by nest when its propertyMapping param
	 * is not specified.
	 */
	public static function structPropToColumnMapFromColumnHints($columnList)
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