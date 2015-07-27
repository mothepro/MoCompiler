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
	 * Local path to css data folder
	 * @var string
	 */
	private $localStaticCSS;

	/**
	 * Local path to js data folder
	 * @var string
	 */
	private $localStaticJS;

	/**
	 * Local path to image data folder
	 * @var string
	 */
	private $localStaticIMG;


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
	 * AWS Bucket File
	 * @var string
	 */
	private $remoteSASS;


	/**
	 * AWS Bucket File
	 * @var string
	 */
	private $remoteJS;


	/**
	 * AWS Bucket File
	 * @var string
	 */
	private $remoteImage;


	/**
	 * Directories of sass to upload
	 * @var string[]
	 */
	private $localMove= array();
	
	/**
	 * Directories of sass to upload
	 * @var string[]
	 */
	private $localSASS = array();


	/**
	 * Directories of js to upload
	 * @var string[]
	 */
	private $localJS = array();


	/**
	 * Directories of images to upload
	 * @var string[]
	 */
	private $localImage = array();

	/**
	 * Directories to wipe
	 * @var string[]
	 */
	private $wipe = array();
	
	/**
	 * Output reports
	 * @var boolean
	 */
	private $silent = false;


	/**
	 * Compress and minify static files
	 * @var boolean
	 */
	private $compress = false;
	
	/**
	 * Vendor binary folder
	 */
	const BIN = 'vendor\\bin\\';// 'vendor'. DIRECTORY_SEPARATOR .'bin'. DIRECTORY_SEPARATOR;
	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Reporting">
	/**
	 * Report current action being taken
	 * @param string $message
	 */
	private function start($message) {
		if (!isset($this->status))
			$this->status = new \SplStack;


		$this->status->push(microtime(true));


		if (!$this->silent)
			echo PHP_EOL, str_repeat("\t", $this->status->count() - 1), $message;


		return $this;
	}

	/**
	 * Finish report
	 */
	private function finish() {
		$begin = $this->status->pop();
		$end = microtime(true);


		if (!$this->silent)
			echo PHP_EOL, str_repeat("\t", $this->status->count()), '   ', number_format($end - $begin, 4), ' seconds';


		return $this;
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
					rmdir($file->getRealPath());
				else
					unlink($file->getRealPath());


			if ($rmdir)
				rmdir($dir);
		}
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
		$this->start('Creating JS Templates from ' . $this->localTpl);

		$this->wipe[] = $output = self::path(sys_get_temp_dir()) . 'twigjs' . time() . DIRECTORY_SEPARATOR;
		$cwd = getcwd();


		foreach (self::rglob($this->localTpl . '*.twig') as $file) {
			// go directly to file
			chdir(dirname($file));


			// output flag doesn't work
			$this->runLocal(['twigjs', basename($file)]);
		}


		chdir($cwd);


		// prepare the dir and move js files there
		foreach (self::rglob($this->localTpl . '*.js') as $file) {
			$outFile = $output . substr($file, strlen($cwd));


			self::readyDir($outFile);
			rename($file, $outFile);
		}

		$this->finish();


		$this->addJS($output);
		$this->addMove($this->localTpl);
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


		$this->start('Creating Documentation from ' . $this->localDoc)
				->runLocal([self::BIN . 'apigen.bat',
					'generate',
					'--config', $this->localDoc,
				])
				->finish();
	}

// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Convert">

	/**
	 * Combines SASS to a file foreach folder
	 * 
	 * @global string[] $__PUBLIC_CONST list of constants to be used
	 * @param boolean $compress compress the sass
	 */
	private function sass($compress = false) {
		global $__PUBLIC_CONST;
		//self::wipeDir($this->localStatic . 'css');
		
		// get all sass dirs
		foreach ($this->localSASS as $dir) {
			$this->start('Converting SASS to CSS in ' . $dir);


			foreach (glob($dir . DIRECTORY_SEPARATOR . '*.scss') as $file) {
				//skip if partial
				$output = basename($file);
				if (substr($output, 0, 1) === '_')
					continue;

				$this->start('Working on ' . $output);
				
				// Add Constants variables 
				if(isset($_GLOBALS["constants"])) {
					// sass which will actuall be compiled [hidden]
					$file .= md5(time()) . '.sex';
					$f = fopen($file, 'w');
					exec('attrib +H ' . escapeshellarg($file));

					// get the URL Constants
					$urls = array();
					foreach ($__PUBLIC_CONST as $name => $val) {
						if(!is_array($val))
							$urls[] = '$const-' . strtolower($name) . ': \'' . $val . '\' !default;';
						else
							foreach($val as $meth => $trueVal)
								$urls[] = '$const-'. strtolower($name) .'-'. strtolower($meth) .': \''. $trueVal .'\' !default;'; // $bootstrap-sass-asset-helper: (function-exists(twbs-font-path)) !default;
					}
					
					// add URL vars to temp sass file
					fwrite($f, implode(PHP_EOL, $urls));
					fwrite($f, file_get_contents($file));
				}
				
				// the correct path for output
				$output = $this->localStaticCSS . $output;
				$output = substr($output, 0, -4) . 'css';

				// make sure path is ready
				self::readyDir($output);


				// run sass
				$this->runLocal(['sass',
					'--scss',
					'--trace',
					'--unix-newlines',
					
					'--style',
					($compress ? 'compressed --sourcemap=none' : 'expanded -l'),
					
					$file,
					$output,
				]);

				// new file was made
				if(isset($f)) {
					fclose($f);
					unlink($file);
				}

				$this->finish();
			}


			$this->finish();
		}
	}


	/**
	 * Merges all files
	 * @param boolean $compress
	 */
	private function javascript($compress = false) {
		foreach ($this->localJS as $jswip) {
			// make file for each sub folder
			foreach (glob($jswip . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) as $v) {
				if ($compress) {
					$output = $this->localStaticJS . basename($v) . '.js';
					self::readyDir($output);


					$this->start('Minifing ' . basename($v) . ' with closure');


					/*
					  foreach (self::rglob($v . DIRECTORY_SEPARATOR . '*.js') as $vv) 
					  $f = fopen($output, 'a');
					  ob_start();
					  require $vv;
					  $data = ob_get_clean();

					  // add to file
					  fwrite($f, $file);
					  }
					  fclose($f);
					*/


					$this->runLocal([self::BIN . 'closure.bat',
						'--language_in', 'ECMASCRIPT5',
						'--js_output_file', $output,
						$v . DIRECTORY_SEPARATOR . '**',
					]);


					$this->finish();
				} else {
					$this->start('Merging ' . basename($v) . '\'s JS files');

					$full = $this->localStaticJS . basename($v) . '.js';

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
	}


	/**
	 * Move images to upload dir
	 * Optimize them if needed
	 * @param boolean $compress
	 */
	protected function images($compress = false) {
		foreach ($this->localImage as $imgDir) {
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
				$output = $this->localStaticIMG . substr($image, strlen($imgDir) + 1);
				self::readyDir($output);


				if ($compress)
					$this->start('Optimizing ' . $image)
							->runLocal(['imagemin',
								//'-o', 7,
								$image,
								'>',
								$this->localStaticIMG . substr($image, strlen($imgDir) + 1)
							])->finish();
				else
					copy($image, $output); // symlink
			}
		}
	}

	// </editor-fold>

	// <editor-fold defaultstate="collapsed" desc="Useful">

	/**
	 * Run some commands on the remote server
	 * @param string $command commands to run
	 * @return \Compiler this
	 */
	private function runRemote($command) {
		return $this->runLocal(['plink',
					'-ssh', //
					'-i ', $this->ppk, // Private key file to access server
					$this->host, // username and hostname to connect to
					'"' . strval($command) . '"', // Commands to run
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
		// echo "\n`$command`\n";
		exec($command);


		return $this;
	}

// </editor-fold>

	/**
	 * @todo more control over static dirs
	 * Uploads to S3 or remote server
	 */
	protected function upload() {
		foreach([
			$this->remoteSASS	=> $this->localStaticCSS,
			$this->remoteJS		=> $this->localStaticJS,
			$this->remoteImage	=> $this->localStaticIMG,
		] as $bucket => $localDir) {
			if(isset($this->s3)) {
				foreach(glob($localDir . '*') as $file) {
					$info = new \SplFileInfo($file);
					
					$this->start('Putting '. $info->getBasename() .' on '. $bucket);

					$data = file_get_contents($file);
					$data = gzencode($data, 9);

					switch ($info->getExtension()) {
					case 'css':
						$mime = 'text/css';
						break;
					case 'js':
						$mime = 'application/javascript';
						break;
					case 'png':
						$mime = 'image/png';
						break;
					case 'gif':
						$mime = 'image/gif';
						break;
					case 'jpg':
					case 'jpeg':
						$mime = 'image/jpeg';
						break;
					default:
						break;
					}
					// full MIME type
					//$mime = 'text/' . $type;
					//if($type === 'js')
					//	$mime = 'text/javascript';

					$this->s3->putObject(
						$data,
						$bucket,
						$info->getBasename(),
						\S3::ACL_PUBLIC_READ,
						array(),
						[
							'Content-Type'		=> $mime,
							'Cache-Control'		=> 'max-age=315360000',
							'Expires'			=> 'Thu, 31 Dec 2037 23:55:55 GMT', //gmdate('D, d M Y H:i:s T', strtotime('+5 years'))
							'Vary'				=> 'Accept-Encoding',
							'Content-Encoding'	=> 'gzip',
							'Content-Length'	=> strlen($data),
						]
					);

					$this->finish();
				}
			} else {
				$this	->start('Uploading '. $localDir .' to '. $bucket)
						->runRemote('rm -r '. $bucket .'; mkdir -p '. $bucket)
						->runLocal(['pscp',
							'-r',						// copy recursively
							'-sftp',					// for use of SFTP protocal
							'-C',						// enable compression
							'-i', $this->ppk,			// Private key file to access server
							$localDir,					// Directory to upload
							$this->host .':'. $bucket,	// host:path on server to save data
						])->finish();
			}
		}
	}

	/**
	 * Puts everything on the chosen server
	 */
	public function compile() {
		$this->start('Compiling to '. $this->host);

		// fix path
		$this->localProj	= self::path($this->localProj);
		$this->remoteProj	= rtrim($this->remoteProj, DIRECTORY_SEPARATOR);
		
		// static files
		$localStatic			= self::path(sys_get_temp_dir()) . 'data' . time() . DIRECTORY_SEPARATOR;
		$this->localStaticCSS	= $localStatic . 'css' . DIRECTORY_SEPARATOR;	self::readyDir($this->localStaticCSS);
		$this->localStaticJS	= $localStatic . 'js' . DIRECTORY_SEPARATOR;	self::readyDir($this->localStaticJS);
		$this->localStaticIMG	= $localStatic . 'img' . DIRECTORY_SEPARATOR;	self::readyDir($this->localStaticIMG);
		
		$this->wipe[] = $localStatic;
		
		// Work from project
		if(isset($this->localProj) && is_dir($this->localProj))
			chdir($this->localProj);

		if(isset($this->localTpl))
			$this->makeTpl();
		
		if(isset($this->localDoc))
			$this->makeDoc();

		// run through static files then upload them
		$this->sass($this->compress);
		$this->javascript($this->compress);
		$this->images($this->compress);
		$this->upload();

		// upload project
		$this->addMove('composer.json');
		foreach($this->localMove as $name) {
			$this	->start('Uploading Project '. $name)
						->runLocal(['pscp',
							'-r',									// copy recursively
							'-sftp',								// for use of SFTP protocal
							'-C',									// enable compression
							'-i '. $this->ppk,						// Private key file to access server
							$this->localProj . $name,				// Directory to upload
							$this->host .':'. $this->remoteProj,	// host:path on server to save data
						])
					->finish();
		}

		// config
		$this	->start('Updating Server Permissions')
					->runRemote('composer update --no-dev -d '. $this->remoteProj); // -o [optimize autoloader]
		
		if(!isset($this->s3)) {
			if(isset($this->remoteImage))
				$this->runRemote('chmod 774 -R '. $this->remoteImage);
			
			if(isset($this->remoteSASS))
				$this->runRemote('chmod 774 -R '. $this->remoteSASS);
			
			if(isset($this->remoteJS))
				$this->runRemote('chmod 774 -R '. $this->remoteJS);
					//->runRemote('chmod 774 '. $this->remoteProj . DIRECTORY_SEPARATOR .'* -R
		}
		
		$this	->finish();

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
	public function setSilent($silent) {
		$this->silent = $silent;
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
	
	public function addSASS($dir) {
		$this->localSASS[] = $dir;
		return $this;
	}


	public function addJS($dir) {
		$this->localJS[] = $dir;
		return $this;
	}

	public function addMove($dir) {
		$this->localMove[] = $dir;
		return $this;
	}


	public function addImage($dir) {
		$this->localImage[] = $dir;
		return $this;
	}
	public function setRemoteSASS($remoteSASS) {
		$this->remoteSASS = $remoteSASS;
		return $this;
	}

	public function setRemoteJS($remoteJS) {
		$this->remoteJS = $remoteJS;
		return $this;
	}

	public function setRemoteImage($remoteImage) {
		$this->remoteImage = $remoteImage;
		return $this;
	}

	public function setCompress($compress) {
		$this->compress = $compress;
		return $this;
	}



// </editor-fold>
}