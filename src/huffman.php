﻿<?php

class Huffman {

	public function __construct($dictionary = null) {

		if($dictionary)
			$this->setDictionary($dictionary);
			
	}


	protected $root = null;

	protected $leaves = array();

	public function setDictionary($dictionary) {
		if(!$dictionary)
			die("No dictionary provided.");
		$this->root = new Node();
		if(is_string($dictionary))
			$dictionary = str_split($dictionary);
		$dictionary["nextIndex"] = 0;
		$this->root->setDictionary($dictionary,0);
		$size = $dictionary["nextIndex"];
		unset($dictionary["nextIndex"]);
		return $size;
	}
	
	/**
	 * Return a string or an array of objects which represents the tree.
	 * For more information, see description of constructor
	 *
	 * @param asArray (Optional) Whether or not the dictionary should
	 *					be returned as an array (default = false). If this
	 *					value is false, a string is returned.
	 * @return the dictionary.
	 */
	public function getDictionary($asArray = false) {
		$dictionary = array();
		if(!$this->root)
			throw "Impossible to extract dictionary from non-existing tree.";
		$this->root->getDictionary($dictionary,0);
		if(!$asArray)
			return implode($dictionary);
		else
			return $dictionary;
	}
	
	/**
	 * Builds the tree according to the Huffman algorithm. There is
	 * lots of information about this out there (e.g. Wikipedia).
	 *
	 * @param data This is the data from which the tree is built. This
	 *				can be either a string or an array of objects/values.
	 *				If objects are contained, their toString() function
	 *				must return a unique string used to distinguish the objects.
	 */
	public function buildTree($data) {
		// The root nodes while we have more than one of them.
		// This is an associative array with valueToString(value)
		// as the key and the node as the value.
		$roots = array();

		if(is_string($data))
			$data = str_split($data);
		
		// Determine frequencies.
		for($index=0;$index < count($data);$index++) {
			$key = $data[$index];

			// Add value if new.
			if(!isset($roots[$key])) {
				$roots[$key] = new Node($key);
				$this->leaves[$key] = $roots[$key];
			}
				
			$roots[$key]->frequency++;
		}
		
		// We want at least two different items.
		if(count($roots) === 1) {
			$key = strlen($key) === 1 ? chr(255 - ord($key)) : $key."+";
			$artificial = new Node($key);
			$roots[$key] = $artificial;
			$this->leaves[$key] = $artificial;
		}		

		// Convert to a regular array.
		$roots = array_values($roots);
		
		// Create a tree.
		while(count($roots) > 1) {
			// Find two nodes with the lowest frequency.
			if($roots[0]->frequency < $roots[1]->frequency) {
				$leastOften = 0;
				$secondLeastOften = 1;
			} else {
				$leastOften = 1;
				$secondLeastOften = 0;
			}
			for($index=2;$index < count($roots);$index++)
				if($roots[$index]->frequency < $roots[$leastOften]->frequency) {
					$secondLeastOften = $leastOften;
					$leastOften = $index;
				} else if($roots[$index]->frequency < $roots[$secondLeastOften]->frequency)
					$secondLeastOften = $index;
					
			// Merge those two nodes.
			$node = new Node();
			$leastZero = true;
			if($roots[$leastOften]->height > $roots[$secondLeastOften]->height)
				$leastZero = false;
			else if($roots[$leastOften]->height == $roots[$secondLeastOften]->height
				&& $roots[$leastOften]->value > $roots[$secondLeastOften]->value)
				$leastZero = false;
			if($leastZero) {
				$node->zeroChild = $roots[$leastOften];
				$node->oneChild = $roots[$secondLeastOften];
			} else {
				$node->zeroChild = $roots[$secondLeastOften];
				$node->oneChild = $roots[$leastOften];
			}
			$node->frequency = $node->zeroChild->frequency + $node->oneChild->frequency;
			$node->height = 1 + max($node->zeroChild->height,$node->oneChild->height);
			$node->zeroChild->myParent = $node;
			$node->oneChild->myParent = $node;
			$roots[$leastOften] = $node;
			unset($roots[$secondLeastOften]);
			$roots = array_values($roots);
		}
		
		$this->root = $roots[0];
	}
	
	/**
	 * Converts a 32-bit integer to a 4-letter string.
	 *
	 * @param value The 32-bit integer.
	 * @return The 4-letter string.
	 */
	protected function intToString($value) {
		return chr(($value >> 24) & 0xFF)
					.chr(($value >> 16) & 0xFF)
					.chr(($value >> 8) & 0xFF)
					.chr($value & 0xFF);
	}
	
	/**
	 * Compresses the given data using the currently present
	 * Huffman tree.
	 *
	 * @param data An array of 32-bit values or a string which is
	 *				compressed.
	 * @param asArray (Optional) Whether or not the compressed data
	 *				is returned as an array of 32-bit values. If this
	 *				value is false (=default), a string is returned.
	 * @return The compressed form of the data.
	 */
	public function compressData($data,$asArray = false) {
		$dword = 0;		// Current 32-bit $value.
		$bitsLeft = 32;	// Number of bits left in $dword.

		if(is_string($data))
			$data = str_split($data);
			
		if(!$asArray)
			$compressed = $this->intToString(count($data));
		else {
			$compressed = array();
			$compressed[] = count($data);
		}
		for($index=0;$index < count($data);$index++) {
			// Match $data with $node.
			$key = $data[$index];				
			$node = $this->leaves[$key];
			if(!$node)
				throw "Huffman tree does not match input data.";
			
			// If this leaf has no bit $value yet, do it
			// now by moving towards the root.
			if($node->bitLength == 0) {
				$node->bits = 0;
				$current = $node;
				while($current->myParent) {
					if($current->myParent->oneChild == $current)
						$node->bits |= (1 << $node->bitLength);
					$node->bitLength++;
					$current = $current->myParent;
				}
			}
			
			// Add bits of $node to the $data stream.
			if($bitsLeft >= $node->bitLength) {
				// It fits into the $dword.
				$dword = ($dword << $node->bitLength) | $node->bits;
				$bitsLeft -= $node->bitLength;
			} else {
				// It it doesn't fit, split.
				$dword = ($dword << $bitsLeft) | ($node->bits >> ($node->bitLength - $bitsLeft));
					// For this bit shifting to work properly, the assumption is
					// that there are fewer than 2^32 - 1 values in the dictionary.
				$value = $dword & 0xffffffff;
				if(!$asArray)
					$compressed .= $this->intToString($value);
				else
					$compressed[] = $value;
				$dword = $node->bits;
				$bitsLeft = 32 - ($node->bitLength - $bitsLeft);
			}

		}
		$value = ($dword << $bitsLeft) & 0xffffffff;
		if(!$asArray)
			$compressed .= $this->intToString($value);
		else
			$compressed[] = $value;
		
		return $compressed;
	}
	
	/**
	 * Compresses data. The resulting compressed data will
	 * contain all that's needed to decompress it, i.e. it
	 * will include the dictionary as well. The data type is
	 * maintained. That is, if a string is input, a string
	 * is output. If an array of objects/values is input,
	 * an array of objects/values is output.
	 *
	 * @param data A string or an array of objects/values to be compressed.
	 * @return The compressed data (string or array).
	 * @see compressData()
	 */
	public function compress($data) {
		$this->buildTree($data);
		if(is_string($data))
			return $this->getDictionary(false).$this->compressData($data,false);
		else
			return array_merge($this->getDictionary(true),$this->compressData($data,true));
	}
	
	/**
	 * Converts a 4-letter string into a 32-bit integer.
	 *
	 * @param str The 4-letter string.
	 * @return The 32-bit integer.
	 */
	protected function stringToInt($str) {
		return (ord(substr($str,0,1)) << 24)
				| (ord(substr($str,1,1)) << 16)
				| (ord(substr($str,2,1)) << 8)
				| ord(substr($str,3,1));
	}
	
	/**
	 * Decompresses data which was compressed with the
	 * compress function and returns the original data.
	 *
	 * @param compressed The compressed data stream. This is either an
	 *			array of 32-bit values or a string.
	 * @param asArray (optional) Whether or not the resulting data should be
	 *			an array of objects/values. If false, a string is returned.
	 *			If the original data was not a string, decompressing to a
	 *			string may lead to unexpected behaviour. Default is false.
	 * @param startIndex (optional) If the decompression data doesn't start
	 *			at index 0 (the default), for example if the dictionary
	 *			precedes the decompression data, use this index to indicate
	 *			the start.
	 * @return The decompressed values, an array of 32-bit values or
	 *			a string.
	 */
	public function decompressData($compressed,$asArray = false,$startIndex = 0) {
		// Some initialization.
		$index = 0;
		$bitIndex = 32;
		$data = array();
		if(!$startIndex)
			$compressedIndex = 0;
		else
			$compressedIndex = $startIndex;
		if(is_string($compressed)) {
			$count = $this->stringToInt(substr($compressed,$compressedIndex,4));
			$compressedIndex += 4;
		} else {
			$count = $compressed[$compressedIndex];
			$compressedIndex++;
		}
		
		// Process incoming bits.
		while($index < $count) {
			// Traverse the tree until we hit a leaf.
			$node = $this->root;
			while($node->value === null) {
				// Get the next $bit.
				if(is_string($compressed))
					$value = $this->stringToInt(substr($compressed,$compressedIndex,4));
				else
					$value = $compressed[$compressedIndex];
				$bit = ($value >> ($bitIndex - 1)) & 1;
				$bitIndex--;
				if($bitIndex==0) {
					if(is_string($compressed))
						$compressedIndex += 4;
					else
						$compressedIndex++;
					$bitIndex = 32;
				}

				if($bit)
					$node = $node->oneChild;
				else
					$node = $node->zeroChild;
			}
			
			// We have a $value.
			$data[] = $node->value;
			$index++;
		}
		
		if(!$asArray)
			return implode($data);
		else
			return $data;
	}
	
	/**
	 * Decompresses a self-contained compressed
	 * bit stream. That is, the dictionary is already
	 * contained in the bit stream. If the bit stream
	 * itself is a string, a string is returned. If it
	 * is an array, an array is returned.
	 *
	 * @param bitStream The compressed data including dictionary.
	 * @return The decompressed (original) data.
	 * @see compress()
	 */
	public function decompress($bitStream) {
		$index = $this->setDictionary($bitStream);
		$asArray = !is_string($bitStream);
		return $this->decompressData($bitStream,$asArray,$index);
	}
	
	/**
	 * Returns a string representation of this tree.
	 *
	 * @return The tree as a string.
	 */
	public function __toString() {
		if(!$this->root)
			return "no tree";
		else
			return $this->root->__toString();
	}

}

/**
 * A node in the Huffman tree.
 */
class Node {

	/**
	 * If not null, this represents the uncompressed 32-bit value
	 * at this node. If null, it is an intermediary node which
	 * contains at least one child.
	 */
	public $value = null;
	
	/**
	 * The frequency of the value in the dataset or the accumulated
	 * frequency of all children if this is not a leaf node.
	 */
	public $frequency = 0;
	
	/** If not null, this is a reference to the child node representin a zero bit. */
	public $zeroChild = null;
	
	/** If not null, this is a reference to the child node representin a one bit. */
	public $oneChild = null;

	/** If not null, the parent node. */
	public $myParent = null;
	
	/** The distance from the lowest leaf below this node. */
	public $height = 0;
	
	/**
	 * If not null, the bit value for this node if it is a leaf.
	 * Initialization is lazy during compression. The number of bits
	 * is given in bitLength.
	 */
	public $bits = null;
	
	/** The number of bits for this leave node. Only valid if not zero. */
	public $bitLength = 0;
	
	/**
	 * Constructor.
	 *
	 * @param value The value of the node. Can be theoretically anything.
	 */
	public function __construct($value = null) {
		$this->value = $value;
	}
	
	/**
	 * Adds new leaves from the dictionary below this node. See
	 * Huffman class for more information.
	 *
	 * @param dictionary The value array which contains the values.
	 * @param bitLength The bit length of this node.
	 */
	public function setDictionary(&$dictionary,$bitLength) {
		// Left branch.
		if($dictionary[$dictionary["nextIndex"] + 1] == $bitLength + 1) {
			$this->zeroChild = new Node($dictionary[$dictionary["nextIndex"]]);
			$this->zeroChild->myParent = $this;
			$dictionary["nextIndex"] += 2;
		} else {
			$this->zeroChild = new Node();
			$this->zeroChild->myParent = $this;
			$this->zeroChild->setDictionary($dictionary,$bitLength + 1);
		}
		
		// Right branch.
		if($dictionary[$dictionary["nextIndex"] + 1] == $bitLength + 1) {
			$this->oneChild = new Node($dictionary[$dictionary["nextIndex"]]);
			$this->oneChild->myParent = $this;
			$dictionary["nextIndex"] += 2;
		} else {
			$this->oneChild = new Node();
			$this->oneChild->myParent = $this;
			$this->oneChild->setDictionary($dictionary,$bitLength + 1);
		}
	}
	
	/**
	 * Adds any leaves below this node to the dictionary. See
	 * Huffman class for more information.
	 *
	 * @param dictionary The array to add the leaves to.
	 * @param bitLength The bit length of this node.
	 */
	public function getDictionary(&$dictionary,$bitLength) {
		if($this->value === null) {
			// No leaf, recurse.
			$this->zeroChild->getDictionary($dictionary,$bitLength + 1);
			$this->oneChild->getDictionary($dictionary,$bitLength + 1);
		} else {
			// Leaf, add.
			$dictionary[] = $this->value;
			$dictionary[] = $bitLength;
		}
	}
	
	/**
	 * Returns a string representation of this node and its child nodes.
	 *
	 * @return The node as a string.
	 */
	public function __toString() {
		$str = "";
		if($this->zeroChild)
			$str .= "[" . ($this->value===null?"null":$this->value) . "," . $this->frequency . "," . $this->height . "]"
					. "(" . $this->zeroChild->__toString() . "," . $this->oneChild->__toString() . ")";
		else
			$str .= "[" . $this->value . "," . $this->frequency . "," . $this->height . "]";
		return $str;
	}

}

?>
