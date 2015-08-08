<?php
namespace Mo\Compiler;

/*
 * The MIT License
 *
 * Copyright 2015 Mo.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Converts array of constants to a PHP file of constants
 *
 * @author Maurice Prosper <maurice.prosper@ttu.edu>
 */
class Constants {
	/**
	 *
	 * @var string[]
	 */
	private $const = array();
	
	/**
	 *
	 * @var string[]
	 */
	private $public = array();
	
	/**
	 *
	 * @var mixed[]
	 */
	private $ini = array();

	/**
	 * Autoloading in PHP
	 */
	const AUTOLOAD = 'require "vendor/autoload.php";';

	/**
	 * Namespace Seperator in PHP
	 */
	const NAMESPACE_SEPERATOR = '\\';
	
	/**
	 * Public Modifier Prefix
	 */
	const PUB_PREFIX = '+';

	public function __construct(array $const) {
		$this->const = self::nameVals($const);
		$this->public = self::findPublic($const);
	}
	
	private static function nameVals($arr) {
		$ret = array();
		
		foreach($arr as $name => $val) {
			// remove public prefix
			if(substr($name, 0, strlen(self::PUB_PREFIX)) === self::PUB_PREFIX)
				$name = substr($name, strlen(self::PUB_PREFIX));
				
			// there is more
			if(is_array($val))
				foreach(self::nameVals($val) as $newName => $newVal)
					$ret[ $name . self::NAMESPACE_SEPERATOR . $newName ] = $newVal;
			else
				$ret[ $name ] = $val;
		}
		
		return $ret;
	}
	
	private static function findPublic($arr, $public = false) {
		$ret = array();
		
		foreach($arr as $name => $val) {
			$pub = $public; // inherit
			
			// make public?
			if(substr($name, 0, strlen(self::PUB_PREFIX)) === self::PUB_PREFIX) {
				$name = substr($name, strlen(self::PUB_PREFIX));
				$pub = true;
			}
			
			// there is more
			if(is_array($val))
				foreach(self::findPublic($val, $pub) as $newName => $newVal) {
					if($pub)
						$ret[ $name . self::NAMESPACE_SEPERATOR . $newName ] = $newVal;
				}
			elseif($pub)
				$ret[ $name ] = $val;
		}
		
		return $ret;
	}
	
	public function getGlobal() {
		return $this->global;
	}

	public function getClass() {
		return $this->class;
	}
	
	public function write($location, $requireAutoloader = false) {
		$str = array('<?php');
		
		if($requireAutoloader)
			$str[] = self::AUTOLOAD;
		
		foreach($this->const as $name => $val)
			$str[] = 'define("\\'. self::nameEncode($name) .'", '. self::encode($val) .');';

		// encode public array
		$tmp = array();
		foreach($this->public as $k => $v)
			$tmp[ self::nameEncode ($k) ] = $v;
		$this->public = $tmp;
		
		// how should I do this?
		$str[] = '$GLOBALS["constants"] = '. var_export($this->public, true) .';';
		
		return file_put_contents($location, implode(PHP_EOL, $str));
	}
	
	protected static function encode($val) {
		$ret = var_export($val, true);
				
		if($val instanceof \Nette\Neon\Entity)
			$ret = \Nette\Neon\Neon::encode($val);
				
		return $ret;
	}
	
	protected static function nameEncode($name) {
		$names = explode(self::NAMESPACE_SEPERATOR, $name);
		
		// First letter
		foreach($names as $k => &$v)
			$v = strtoupper ($v); // ucfirst(strtolower($v));
		
		// WHOLE WORD
		//$v = strtoupper($v);
		
		return implode(self::NAMESPACE_SEPERATOR, $names);
	}

	/**
	 * @todo Make this work!
	 * test1:
	 *		simple: data
	 *		test2:
	 *			test3a: good
	 *			test3b:
	 *				ok: 123
	 *
	 * test1:test2_test3a = good
	 * test1:test2_test3b_ok = 123 
	 * @param array $arr
	 */
	private static function classConst(array $arr) {
		foreach($arr as $k => $v) {
			if(is_array($v))
				list($const, $more) = self::classConst($v);
			$const = $k .'_'. $const;
		}
		
		//$a = function() use($a) {
		//	if(is_array($arr))
		//}
	}
}
