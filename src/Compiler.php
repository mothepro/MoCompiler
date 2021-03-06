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
 * Class which compiles the config and gets it ready to be pushed live
 */
class Compiler {
	// <editor-fold defaultstate="collapsed" desc="Properties">
	/**
	 * Needed binaries
	 * @var string[]
	 */
	private static $binaries = [
		'pscp',
		'plink',
		'sass',
		'twigjs',
		'imagemin',
		'java',
		'r.js.cmd',
	];
	
	/**
	 * Private Putty Key file
	 * @var string
	 */
	private $ppk;

	/**
	 * Hostname of server
	 * @var string
	 */
	private $host;

	/**
	 * Local Core copy of project
	 * @var string
	 */
	private $localProj;

	/**
	 * Action being run
	 * @var \SplStack
	 */
	private $status;

	/**
	 * AWS S3 object
	 * @var S3
	 */
	private $s3;

	/**
	 *
	 * @var string
	 */
	private $localTpl;
	
	/**
	 * @var string
	 */
	private $localDoc;
	
	/**
	 * Remote Core copy of project
	 * @var string
	 */
	private $remoteProj;

	/**
	 * Files or Folders to move to remote project
	 * @var string[]
	 */
	private $localCopy;
	
	/**
	 * Hooks to run
	 * 
	 * $hookWhenLocation
	 * 
	 * @var string[]
	 */
	private $hookPreLocal	= array();
	private $hookPreRemote	= array();
	private $hookPostLocal	= array();
	private $hookPostRemote	= array();

	/**
	 * Local path to static data
	 * PRE processing
	 * @var string[][]
	 */
	private $localStatic = array();

	/**
	 * Paths to where save static data
	 * @var string[]
	 */
	private $remoteStatic = array();
	
	/**
	 * Paths to static data about to be pushed
	 * POST processing
	 * @var string[]
	 */
	private $tmp = array();

	/**
	 * Directories to wipe
	 * @var string[]
	 */
	private $wipe = array();
	
	/**
	 * Verbose Level
	 * 0 Silent
	 * 1 Name of Job
	 * 2 Duration of Job
	 * 3 Commands run in command line
	 * 
	 * @var int
	 */
	private $verbose = 2;

	/**
	 * Compress and minify static files
	 * @var boolean
	 */
	private $compress = false;
	
	/**
	 * Vendor binary folder
	 */
	const BIN = 'vendor\\bin\\';// 'vendor'. DIRECTORY_SEPARATOR .'bin'. DIRECTORY_SEPARATOR;
	
	/**
	 * r.js build config path
	 */
	const RJS_BUILD = 'build.js';
	
	/**
	 * Extentions => Mime Types
	 * 
	 * @link http://www.phpclasses.org/browse/file/2743.html
	 * @var string[]
	 */
	protected static $mimes = [
		'ez' => 'application/andrew-inset', 
		'hqx' => 'application/mac-binhex40', 
		'cpt' => 'application/mac-compactpro', 
		'doc' => 'application/msword', 
		'bin' => 'application/octet-stream', 
		'dms' => 'application/octet-stream', 
		'lha' => 'application/octet-stream', 
		'lzh' => 'application/octet-stream', 
		'exe' => 'application/octet-stream', 
		'class' => 'application/octet-stream', 
		'so' => 'application/octet-stream', 
		'dll' => 'application/octet-stream', 
		'oda' => 'application/oda', 
		'pdf' => 'application/pdf', 
		'ai' => 'application/postscript', 
		'eps' => 'application/postscript', 
		'ps' => 'application/postscript', 
		'smi' => 'application/smil', 
		'smil' => 'application/smil', 
		'wbxml' => 'application/vnd.wap.wbxml', 
		'wmlc' => 'application/vnd.wap.wmlc', 
		'wmlsc' => 'application/vnd.wap.wmlscriptc', 
		'bcpio' => 'application/x-bcpio', 
		'vcd' => 'application/x-cdlink', 
		'pgn' => 'application/x-chess-pgn', 
		'cpio' => 'application/x-cpio', 
		'csh' => 'application/x-csh', 
		'dcr' => 'application/x-director', 
		'dir' => 'application/x-director', 
		'dxr' => 'application/x-director', 
		'dvi' => 'application/x-dvi', 
		'spl' => 'application/x-futuresplash', 
		'gtar' => 'application/x-gtar', 
		'hdf' => 'application/x-hdf', 
		'js' => 'application/javascript', 
		'skp' => 'application/x-koan', 
		'skd' => 'application/x-koan', 
		'skt' => 'application/x-koan', 
		'skm' => 'application/x-koan', 
		'latex' => 'application/x-latex', 
		'nc' => 'application/x-netcdf', 
		'cdf' => 'application/x-netcdf', 
		'sh' => 'application/x-sh', 
		'shar' => 'application/x-shar', 
		'swf' => 'application/x-shockwave-flash', 
		'sit' => 'application/x-stuffit', 
		'sv4cpio' => 'application/x-sv4cpio', 
		'sv4crc' => 'application/x-sv4crc', 
		'tar' => 'application/x-tar', 
		'tcl' => 'application/x-tcl', 
		'tex' => 'application/x-tex', 
		'texinfo' => 'application/x-texinfo', 
		'texi' => 'application/x-texinfo', 
		't' => 'application/x-troff', 
		'tr' => 'application/x-troff', 
		'roff' => 'application/x-troff', 
		'man' => 'application/x-troff-man', 
		'me' => 'application/x-troff-me', 
		'ms' => 'application/x-troff-ms', 
		'ustar' => 'application/x-ustar', 
		'src' => 'application/x-wais-source', 
		'xhtml' => 'application/xhtml+xml', 
		'xht' => 'application/xhtml+xml', 
		'zip' => 'application/zip', 
		'au' => 'audio/basic', 
		'snd' => 'audio/basic', 
		'mid' => 'audio/midi', 
		'midi' => 'audio/midi', 
		'kar' => 'audio/midi', 
		'mpga' => 'audio/mpeg', 
		'mp2' => 'audio/mpeg', 
		'mp3' => 'audio/mpeg', 
		'aif' => 'audio/x-aiff', 
		'aiff' => 'audio/x-aiff', 
		'aifc' => 'audio/x-aiff', 
		'm3u' => 'audio/x-mpegurl', 
		'ram' => 'audio/x-pn-realaudio', 
		'rm' => 'audio/x-pn-realaudio', 
		'rpm' => 'audio/x-pn-realaudio-plugin', 
		'ra' => 'audio/x-realaudio', 
		'wav' => 'audio/x-wav', 
		'pdb' => 'chemical/x-pdb', 
		'xyz' => 'chemical/x-xyz', 
		'bmp' => 'image/bmp', 
		'gif' => 'image/gif', 
		'ief' => 'image/ief', 
		'jpeg' => 'image/jpeg', 
		'jpg' => 'image/jpeg', 
		'jpe' => 'image/jpeg', 
		'png' => 'image/png', 
		'tiff' => 'image/tiff', 
		'tif' => 'image/tif', 
		'djvu' => 'image/vnd.djvu', 
		'djv' => 'image/vnd.djvu', 
		'wbmp' => 'image/vnd.wap.wbmp', 
		'ras' => 'image/x-cmu-raster', 
		'pnm' => 'image/x-portable-anymap', 
		'pbm' => 'image/x-portable-bitmap', 
		'pgm' => 'image/x-portable-graymap', 
		'ppm' => 'image/x-portable-pixmap', 
		'rgb' => 'image/x-rgb', 
		'xbm' => 'image/x-xbitmap', 
		'xpm' => 'image/x-xpixmap', 
		'xwd' => 'image/x-windowdump', 
		'igs' => 'model/iges', 
		'iges' => 'model/iges', 
		'msh' => 'model/mesh', 
		'mesh' => 'model/mesh', 
		'silo' => 'model/mesh', 
		'wrl' => 'model/vrml', 
		'vrml' => 'model/vrml', 
		'css' => 'text/css', 
		'html' => 'text/html', 
		'htm' => 'text/html', 
		'asc' => 'text/plain', 
		'txt' => 'text/plain', 
		'rtx' => 'text/richtext', 
		'rtf' => 'text/rtf', 
		'sgml' => 'text/sgml', 
		'sgm' => 'text/sgml', 
		'tsv' => 'text/tab-seperated-values', 
		'wml' => 'text/vnd.wap.wml', 
		'wmls' => 'text/vnd.wap.wmlscript', 
		'etx' => 'text/x-setext', 
		'xml' => 'text/xml', 
		'xsl' => 'text/xml', 
		'mpeg' => 'video/mpeg', 
		'mpg' => 'video/mpeg', 
		'mpe' => 'video/mpeg', 
		'qt' => 'video/quicktime', 
		'mov' => 'video/quicktime', 
		'mxu' => 'video/vnd.mpegurl', 
		'avi' => 'video/x-msvideo', 
		'movie' => 'video/x-sgi-movie', 
		'ice' => 'x-conference-xcooltalk' 
	];
	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Reporting">
	/**
	 * Report current action being taken
	 * @param string $message
	 */
	private function start($message, $hook = true) {
		if (!isset($this->status))
			$this->status = new \SplStack;

		if($hook)
			$this->applyHooks ('Pre', $message);
		
		$this->status->push([
			microtime(true),
			$message,
		]);

		if ($this->verbose >= 1)
			echo PHP_EOL, str_repeat("\t", $this->status->count() - 1), $message, '... ';


		return $this;
	}

	/**
	 * Finish report
	 */
	private function finish($hook = true) {
		list($begin, $message) = $this->status->pop();
		$end = microtime(true);
		
		if ($this->verbose >= 2)
			echo PHP_EOL, str_repeat("\t", $this->status->count()), ' > ', number_format($end - $begin, 4), ' seconds';

		if($hook)
			$this->applyHooks ('Post', $message);

		return $this;
	}

	private function applyHooks($prepost, $message) {
		// where to run them
		foreach(['Local', 'Remote'] as $where) {
			$name = 'hook'. $prepost . $where;
			
			// list of hooks for this time
			foreach($this->$name as $name => $cmds) {
				
				// should use regex test?
				// MATCH - these are the hooks to run
				if(stripos($message, $name) !== false) {
					$this->start(($prepost === 'Pre' ? 'Before' : 'After') .' '. $message, false);
					foreach ($cmds as $cmd) {
						if($where === 'Local')
							$this->runLocal ($cmd);
						elseif($where === 'Remote')
							$this->runRemote ($cmd);
					}
					$this->finish(false);
				}
			}
		}
	}

// </editor-fold>
	
	// <editor-fold defaultstate="collapsed" desc="Directory Methods">
	/**
	 * Recursively gets files in path
	 * @param string $pattern
	 * @param byte $flags
	 * @return array
	 */
	protected final static function rglob($pattern, $flags = 0) {
		$files = glob($pattern, $flags);
		foreach (glob(dirname($pattern) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir)
			$files = array_merge(self::rglob($dir . DIRECTORY_SEPARATOR . basename($pattern), $flags), $files);
		return $files;
	}


	/**
	 * Full file name with trailing directory separator is a folder
	 * @param type $name
	 * @return type
	 */
	protected final static function path($name) {
		return rtrim(realpath($name), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}


	/**
	 * Makes all directories leading up to given file or folder
	 * @param string $dir path
	 * @return boolean successful
	 */
	protected final static function readyDir($dir) {
		if(is_file($dir))
			return false;
		
		if (!is_dir(dirname($dir)))
			return mkdir(dirname($dir), 0777, true);
		
		return true;
	}


	/**
	 * Remove everything in a directory
	 * @param string $dir directiory to wipe
	 * @param boolean $rmdir remove directory also?
	 */
	protected final static function wipeDir($dir, $rmdir = false) {
		if (file_exists($dir)) {
			$it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
			$files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
			foreach ($files as $file)

				if ($file->isDir())
					self::wipeDir($file->getRealPath(), true); //rmdir($file->getRealPath());
				else
					unlink($file->getRealPath());


			if ($rmdir)
				rmdir($dir);
		}
	}

	/**
	 * A File or Dir ready to be put in the command line
	 * @param string $dir
	 * @return string
	 */
	protected final static function safeDir($dir) {
		return '"'. addslashes($dir) .'"';
	}
// </editor-fold>
	
	// <editor-fold defaultstate="collapsed" desc="Testing">

	/**
	 * Determines if a command exists on the current environment
	 *
	 * @link http://stackoverflow.com/a/18540185
	 * @param string $command The command to check
	 * @return bool True if the command has been found ; otherwise, false.
	 */
	private static function commandExists($command) {
		$whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';
		$process = proc_open("$whereIsCommand $command", [
			0 => array("pipe", "r"), //STDIN
			1 => array("pipe", "w"), //STDOUT
			2 => array("pipe", "w"), //STDERR
				], $pipes);

		if ($process !== false) {
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($process);

			return $stdout != '';
		}

		return false;
	}


	/**
	 * Makes sure all binaries are on system
	 * @param \Composer\Script\Event $event
	 * @throws Exception
	 */
	public static function checkBinaries(\Composer\Script\Event $event) {
		foreach (self::$binaries as $cmd) {
			if (!self::commandExists($cmd))
				throw new \Exception('Binary "' . $cmd . '" not found in PATH');

			// $event->getIO()->write($cmd . ' found.');
		}
	}

// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Generate">

	/**
	 * Make Twig Templates in Javascript
	 * 
	 * Makes JS files in current directory then moves them to static JS
	 */
	private function makeTpl() {
		$this->localTpl = rtrim($this->localTpl, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$this->wipe[] = $output = self::path(sys_get_temp_dir()) . 'twigjs' . time() . DIRECTORY_SEPARATOR;
		self::readyDir($output);
		
		$data = array();
		
		foreach (self::rglob($this->localTpl . '*.twig') as $file) {
			$id = substr($file, strlen($this->localTpl));
			$data[ $id ] = file_get_contents($file);
		}
		
		file_put_contents($output . 'tpl.js', 'var templates = '. json_encode($data) .';');

		$this->addLocalStatic('js', $output);
		//$this->addCopy($this->localTpl);
		
		return $this;
	}


	/**
	 * Make Documentation for site
	 */
	private function makeDoc() {
		//wipe destination directory manually
		$neon = file_get_contents($this->localDoc);
		$data = \Nette\Neon\Neon::decode($neon);

		$dest = $data['destination'];

		// wipe before and after
		self::wipeDir($dest, true);
		$this->wipe[] = $dest;


		return $this->runLocal([self::BIN . 'apigen.bat',
					'generate',
					'--config', $this->localDoc,
				]);
	}

// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Processors">

	/**
	 * Combines SASS to a file foreach folder
	 */
	private function sass() {
		// get all sass dirs
		foreach ($this->localStatic[ __FUNCTION__ ] as $dir) {
			$this->start('Converting SASS to CSS in ' . $dir);

			foreach (glob($dir . DIRECTORY_SEPARATOR . '*.{scss,sass}', GLOB_BRACE) as $file) {
				//skip if partial
				$output = basename($file);
				if (substr($output, 0, 1) === '_')
					continue;

				$this->start('Working on ' . $output);
				
				$scss = (substr($file, -5) === '.scss');
				
				// Add Constants variables 
				if(isset($GLOBALS["constants"])) {
					$old = $file;
					// sass which will actuall be compiled [hidden]
					$file .= md5(time()) . '.sex';
					$f = fopen($file, 'w');
					exec('attrib +H ' . escapeshellarg($file));

					// get the URL Constants
					$const = array();
					foreach ($GLOBALS["constants"] as $name => $val)
						$const[] = '$const-' . str_replace('\\', '-', strtolower($name)) . ': \'' . $val . '\' !default;';
					
					// add URL vars to temp sass file
					fwrite($f, implode(PHP_EOL, $const));
					fwrite($f, file_get_contents($old));
					fclose($f);
				}
				
				// the correct path for output
				echo $output = $this->tmp[ __FUNCTION__ ] . $output;
				$output = substr($output, 0, -4) . 'css';

				// make sure path is ready
				self::readyDir($output);


				// run sass
				$this->runLocal(['sass',
					($scss ? '--scss' : null),
					'--trace',
					'--unix-newlines',
					
					'--style',
					($this->compress ? 'compressed --sourcemap=none' : 'expanded -l'),
					
					$file,
					$output,
				]);

				$this->finish();
			}
			
			foreach (glob($dir . DIRECTORY_SEPARATOR . '*.sex') as $file)
				unlink($file);

			$this->finish();
		}
		
		return $this;
	}


	/**
	 * Merges all files
	 */
	private function js() {
		foreach ($this->localStatic[ __FUNCTION__ ] as $jswip) {
			
			// r.js optimization
			if($this->compress) {
				$rjsBuild = $jswip .DIRECTORY_SEPARATOR. self::RJS_BUILD;
				if(is_file($rjsBuild)) {
					$this	->start('Optimizing '. self::RJS_BUILD .' with r.js')
							->runLocal(['r.js.cmd',
								'-o', $rjsBuild,
							])->finish();

					// temp destination directory
					$jswip .= DIRECTORY_SEPARATOR . 'dist';
					$this->wipe[] = $jswip;
				}
			}
			
			// make file for each sub folder
			foreach (glob($jswip . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $v) {
				if ($this->compress) {
					$output = $this->tmp[ __FUNCTION__ ] . basename($v) . '.js';
					self::readyDir($output);

					$this->start('Minifing ' . basename($v) . ' with closure');

					$this->runLocal([self::BIN . 'closure.bat',
						'--language_in', 'ECMASCRIPT5',
						'--js_output_file', $output,
						$v . DIRECTORY_SEPARATOR . '**',
					]);

					$this->finish();
				} else {
					$this->start('Merging ' . basename($v) . '\'s JS files');

					$full = $this->tmp[ __FUNCTION__ ] . basename($v) . '.js';

					//remove compiled file
					if (is_file($full))
						unlink($full);

					// append to new js file
					self::readyDir($full);
					$f = fopen($full, 'a'); //match with $g!
										// get js files
					$g = self::rglob($v . DIRECTORY_SEPARATOR . '*.js');

					// add js files
					foreach ($g as $vv) {
						//$this->start('Adding in '. basename($vv) .' to '. basename($v));
												// start buffer
						ob_start();

						// nice title
						echo "\n\n\n/*** ", basename($vv), " ***/\n\n";

						// output file
						require $vv;

						// save the executed css file
						$file = ob_get_clean();

						// add to file
						fwrite($f, $file);

						//$this->finish();
					}

					fclose($f);
					$this->finish();
				}
			}
		}
		
		return $this;
	}


	/**
	 * Move images to upload dir
	 * Optimize them if needed
	 */
	protected function img() {
		foreach ($this->localStatic[ __FUNCTION__ ] as $imgDir) {
			//if(is_file($imgDir . DIRECTORY_SEPARATOR . 'Thumbs.db'))
			//	unlink($imgDir . DIRECTORY_SEPARATOR . 'Thumbs.db');
			/*
			  $this->start('Optimizing '. $imgDir)
			  ->runLocal([
			  self::IMGMIN,
			  '-o', 7,
			  $imgDir,// . DIRECTORY_SEPARATOR. '*',
			  $this->localStatic . 'img'
			  ])->finish();
			 */
			foreach (self::rglob($imgDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,gif,svg,bmp}', GLOB_BRACE) as $image) {
				$output = $this->tmp[ __FUNCTION__ ] . substr($image, strlen($imgDir) + 1);
				self::readyDir($output);


				if ($this->compress)
					$this->start('Optimizing ' . $image)
							->runLocal(['imagemin',
								//'-o', 7,
								$image,
								'>',
								$output
							])->finish();
				else
					copy($image, $output); // symlink
			}
		}
		
		return $this;
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Useful">

	/**
	 * Run some commands on the remote server
	 * @param string|array $command commands to run
	 * @return \Compiler this
	 */
	private function runRemote($command) {
		// make commands a list
		if (!is_array($command))
			$command = array($command);
		$command = strval(implode(';', $command));
		
		return $this->runLocal(['plink',
					'-ssh',					// interacting method
					'-i', static::safeDir($this->ppk),		// Private key file to access server
					$this->host,			// username and hostname to connect to
			
					'"' . $command . '"',	// Commands to run
		]);
	}


	/**
	 * Run some commands on this computer
	 * @param array $command commands to run
	 * @return \Compiler $this
	 */
	private function runLocal($command) {
		// make commands a list
		if (!is_array($command))
			$command = array($command);
		$command = strval(implode(' ', $command));


		// run
		if ($this->verbose >= 3) {
			echo "\n$ $command\n";
			passthru($command);
		} else
			exec($command);

		return $this;
	}
// </editor-fold>

	/**
	 * Uploads to S3 or remote server
	 */
	protected function uploadStatic() {
		// copy to all destination folders
		foreach($this->remoteStatic as $type => $destDir) {			
			// copy from all static
			if(isset($this->s3)) {
				foreach(self::rglob($this->tmp[ $type ] . '*') as $file) {
					$info = new \SplFileInfo($file);
					
					if($info->isDir() || !$info->isReadable())
						continue;
					
					$this->start('Putting '. $info->getBasename() .' on '. $destDir);
					
					// get mime
					$mime = 'text/plain'; // 'application/octet-stream';
					if(isset(static::$mimes[ $info->getExtension() ]))
						$mime = static::$mimes[ $info->getExtension() ];
					
					// headers
					$headers = [
						'Content-Type'		=> $mime,
						'Cache-Control'		=> 'max-age=315360000',
						'Expires'			=> 'Thu, 31 Dec 2037 23:55:55 GMT', //gmdate(DateTime::RFC1123, strtotime('+5 years'))
						'Vary'				=> 'Accept-Encoding',
					];
					
					$data = file_get_contents($file);
						
					// gzip
					if(substr($mime, 0, 5) === 'text/'
					|| $mime === 'application/javascript'
					|| $mime === 'application/x-javascript'
					|| $mime === 'application/json') {
						$data = gzencode($data, 9);
						$headers['Content-Encoding'] = 'gzip';
					}
					
					// data length
					$headers['Content-Length'] = mb_strlen($data, '8bit');
					
					// push it
					$this->s3->putObject(
						$data,
						$destDir,
						$info->getBasename(),
						\S3::ACL_PUBLIC_READ,
						array(),
						$headers
					);

					$this->finish();
				}
			} else {
				$this	->start('Local object '. $type .' -> '. $destDir)
//						->runRemote('rm -r '. $destDir .'; mkdir -p '. $destDir)
						->runLocal(['pscp',
							'-p',									// preserve attributes
							'-r',									// copy recursively
							'-q',									// silent
							//'-sftp',								// for use of SFTP protocal
							'-batch',								// non interactive
							'-C',									// enable compression
							'-i', static::safeDir($this->ppk),		// Private key file to access server
							static::safeDir($this->tmp[ $type ]),	// Directory to upload
							$this->host .':'. $destDir,	// host:path on server to save data
						])->finish();
			}
		}
		
		return $this;
	}

	/**
	 * Puts everything on the chosen server
	 */
	public function compile() {
		$this->start('Compiling to '. $this->host);

		// fix path
		$this->localProj	= self::path($this->localProj);
		$this->remoteProj	= rtrim($this->remoteProj, DIRECTORY_SEPARATOR);
		
		// Work from project
		if(isset($this->localProj) && is_dir($this->localProj))
			chdir($this->localProj);
		
		// documentation
		if(isset($this->localDoc))
			$this->start('Creating Documentation from ' . $this->localDoc)->makeDoc()->finish();
		
		// twig templates
		if(isset($this->localTpl))
			$this->start('Creating JS Templates from ' . $this->localTpl)->makeTpl()->finish();
		
		
		// static files
		$localStatic = self::path(sys_get_temp_dir()) . 'data' . time() . DIRECTORY_SEPARATOR;
		$this->wipe[] = $localStatic;
		
		foreach($this->remoteStatic as $type => $trash) {
			if(method_exists($this, $type)) {
				$this->tmp[ $type ] = $localStatic . $type . DIRECTORY_SEPARATOR;
				self::readyDir( $this->tmp[ $type ] );

				// process static files
				$this->start('Processing '. $type)->$type()->finish();
				
			} elseif(!empty($this->localStatic[ $type ])) {
					// just moved the folder
					$this->tmp[ $type ] = self::path(current ($this->localStatic[ $type ]));
					
					// remove if not there
					if(!file_exists($this->tmp[ $type ])) 
						unset ($this->remoteStatic[ $type ]);
			}
		}

		// run through static files then upload them
		$this->start('Uploading Static')->uploadStatic()->finish();

		// upload project
		$cmd = [
			'pscp',
			'-p',									// preserve attributes
			'-r',									// copy recursively
			'-q',									// silent
			//'-sftp',								// for use of SFTP protocal
			'-batch',								// non interactive
			'-C',									// enable compression
			'-i', static::safeDir($this->ppk),						// Private key file to access server
		];
		
		// Directory to upload
		foreach($this->localCopy as $name)
			$cmd[] = static::safeDir($this->localProj . $name);
		
		// host:path on server to save data
		$cmd[] = $this->host .':'. $this->remoteProj;
		
		$this->start('Uploading Project')->runLocal($cmd)->finish();

		// config
		if(!isset($this->s3) && !empty($this->remoteStatic)) {
			// reset file permissions
			$cmd = array();
			foreach($this->remoteStatic as $type => $path) {
				$cmd[] = 'chmod 774 -R '. $path;
				// $cmd[] = 'chown mo:www-data -R '. $path;
			}
			
			$this->start('Updating Server Enviroment')->runRemote( $cmd )->finish();
		}

		// clean up
		$this	->start('Cleaning up');
			foreach($this->wipe as $dir)
				self::wipeDir($dir, true);
		$this	->finish();
		
		// finally
		$this->finish();
	}

	// <editor-fold defaultstate="collapsed" desc="Setters">
	public function setLocalTpl($tpl) {
		$this->localTpl = $tpl;
		return $this;
	}
	public function setLocalDoc($localDoc) {
		$this->localDoc = $localDoc;
		return $this;
	}

	public function setPpk($ppk) {
		$this->ppk = $ppk;
		return $this;
	}

	public function setHost($host) {
		$this->host = $host;
		return $this;
	}
	public function setVerbose($v) {
		$this->verbose = $v;
		return $this;
	}

	public function setRemote($remote) {
		$this->remoteProj = $remote;
		return $this;
	}
	public function setLocal($local) {
		$this->local = $local;
		return $this;
	}

	/**
	 * Uploads the static files S3
	 * @param string $key Amazon S3 Key
	 * @param string $secret Amazon S3 Secret
	 */
	public function setS3($key = null, $secret = null) {
		$this->s3 = new \S3($key, $secret);
		$this->setCompress(true);
	}	

	public function setCompress($compress) {
		$this->compress = $compress;
		return $this;
	}
	
	public function addLocalStatic($type, $dirs) {
		$this->localStatic[$type][] = $dirs;
		return $this;
	}
	
	public function setRemoteStatic($type, $dir) {
		$this->remoteStatic[$type] = $dir;
		return $this;
	}
	
	public function addCopy($dir) {
		$this->localCopy[] = $dir;
		return $this;
	}
	
	public function addHook($when, $prepost, $where, $hook) {
		$name = 'hook' . ucfirst(strtolower($prepost)) . ucfirst(strtolower($where));
		$this->{$name}[$when][] = $hook;
		return $this;
	}


// </editor-fold>
}