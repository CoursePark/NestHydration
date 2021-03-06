<?php

namespace NestHydration;

class ArrayAccess
	implements \ArrayAccess, \Countable
{
	public function offsetSet($offset, $value)
	{
		$this->$offset = $value;
	}
	
	public function offsetGet($offset)
	{
		return $this->$offset;
	}
	
	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}
	
	public function offsetExists($offset)
	{
		return isset($this->$offset);
	}
	
	public function count()
	{
		return count((array) $this);
	}
}