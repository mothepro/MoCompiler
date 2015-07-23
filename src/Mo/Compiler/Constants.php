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
	private $global = array();
	
	/**
	 *
	 * @var string[][]
	 */
	private $class = array();
	
	/**
	 *
	 * @var string
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

	public function __construct(array $const) {
		foreach ($const as $name => $val) {
			if($name === 'public')
				continue;
				
			// global
			if(!is_array($val)) {
				$this->global[ $name ] = $val;
			} else {
			
			// class const
			//	list($const, $val) = self::classConst($val);
				foreach ($val as $mem => $v)
				$this->class[ $name ][ $mem ] = $v;
			}
		}

		// public const
		if(isset($const['public'])) {
			foreach($const['public'] as $name) {
				$names = explode('.', $name);
				
				// global const
				if(count($names) === 1)
					$this->public[ $name ] = $this->global[ $names[0] ];
				
				// class
				elseif(count($names) === 2)
					$this->public[ $name ] = $this->class[ $names[0] ][ $names[1] ];
				
				// class with underscore
				elseif(count($names) === 3)
					$this->public[ $name ] = $this->class[ $names[0] ][ $names[1] ][ $names[2] ];
				
				/**
				 * not in the mood to write more, sorry :'[
				 * @todo support recursive class
				 */
			}
		}
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
		
		foreach($this->global as $name => $val)
			$str[] = 'define("'. strtoupper($name) .'", '. self::encode($val) .');';
		
		foreach($this->class as $class => $tmp) {
			$str[] = 'abstract class '. strtoupper($class) .' {';
			foreach($tmp as $name => $val)
				$str[] = 'const '. strtoupper ($name) .' = '. self::encode($val) .';';
			$str[] = '}';
		}
		
		$str[] = '$__PUBLIC_CONST = '. var_export($this->public, true).';';
		//foreach($this->global as $name => $val)
		//	$str[] = 'define("'. strtoupper($name) .'", '. $val .');';
		
		return file_put_contents($location, implode(PHP_EOL, $str));
	}
	
	protected static function encode($val) {
		$ret = json_encode($val);
				
		if($val instanceof \Nette\Neon\Entity)
			$ret = \Nette\Neon\Neon::encode($val);
				
		return $ret;
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
