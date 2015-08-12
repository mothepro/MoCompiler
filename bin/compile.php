<?php
/**
 * @todo options override neon
 * @todo hooks run at start & finish
 * @todo all pathnames are safe
 */
require 'vendor/autoload.php';

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
 * Giv'em some help
 */
$showHelp = function() {
	echo <<<HELP
Mo's PHP Project Compiler!
	-h		--help	Print this help message.

	Required Options
		-c					Configuration file to use
		--host				Host name of server to compile to
		--ppk				Location of PPK file to connect to server
		--project-remote	Remote Directory to upload project

	Optional Options
		--project-local		Local Directory to load project [Working Directory]
		--project-files		Directories to move from local to remote

		--twig				Twig Template Directory
		--apigen			Documentation Configuration

		--compress			Compress static files
		--quiet				Silent mode

		--upload-sass		Local SASS Directory
		--upload-js			Local JS Directory
		--upload-img		Local Image Directory
		--upload-*			Local Static Directory

		--s3-key			Amazon S3 Access Key Activates compression
		--s3-secret			Amazon S3 Secret Key

		--download-sass		Remote Directory to save sass on server or S3
		--download-js		Remote Directory to save js on server or S3
		--download-img		Remote Directory to save images on server or 
		--download-*		Remote Static Directory to match --local-*

		--hooks-post		Remote Commands to run after uploading

		--constants			List of constants used throughout app
		--constantsOutput	Where to save new constants
	
Options are useful if you want to hide some config settings to your build process.
	```--s3-secret ImNotGoingOnTheVCS```
HELP;
};

/**
 * Returns a value if option is found in global
 * 
 * @global mixed[] $neon
 * @param string $name option to look for
 * @return string|null
 */
$check = function() use (&$neon) {
	// test the options
	$name = implode('-', func_get_args());
	$opt = getopt(null, [$name . ':']);
	
	// check config file
	if(!isset($opt[$name])) {
		$def = function($config, $args) use (&$def) {
			$name = array_shift($args);
			$ret = false;

			if(isset($config[$name])) {
				if(empty($args))
					$ret = $config[$name];
				else
					$ret = $def($config[$name], $args);
			}

			return $ret;
		};

		$ret = $def($neon, func_get_args());
	} else // found in command line
		$ret = $opt[$name];

	return $ret;
};

// no errors wanted
set_time_limit(0);
date_default_timezone_set('UTC');
error_reporting(-1);
$error = true;
	
// options
$neon = null;
$opt = getopt('c:h', ['help']);

// not missing neon or config
if(isset($opt['c'])) {
	$data = file_get_contents($opt['c']);
	$neon = \Nette\Neon\Neon::decode($data);
	
	// root
	if(isset($neon['compiler']))
		$neon = $neon['compiler'];

	//  check errors
	$error = !($check('ppk') && $check('project', 'remote') && $check('host'));
}

// error OR just needs a little help
if($error || isset($opt['h']) || isset($opt['help'])) {
	if($error)
		echo 'Error, missing required option.', PHP_EOL, PHP_EOL;
	$showHelp();
	exit($error);
}

// make the constant
if($check('constants') && $check('constantOutput')) {
	$c = new Mo\Compiler\Constants($check('constants'));
	$c->write($check('constantOutput'), true);
	require $check('constantOutput');
}

// start the compiler
$c = new Mo\Compiler\Compiler;
$c	->setHost($check('host'))
	->setPpk($check('ppk'))
	->setRemote($check('project', 'remote'));

// misc
$c->setCompress($check('compress'));
$c->setSilent($check('silent'));

// add directories
if($check('project', 'files'))
	foreach($check('project', 'files') as $dir)
		$c->addCopy($dir);

if($check('upload'))
	foreach($check('upload') as $type => $dirs) {
		if(!is_array($dirs))
			$c->addLocalStatic($type, $dirs);
		else
			foreach($dirs as $dir)
				$c->addLocalStatic($type, $dir);
	}

if($check('download'))
	foreach($check('download') as $type => $dir)
		$c->setRemoteStatic($type, $dir);

// misc
if($check('project', 'local'))	$c->setLocal($check('project', 'local'));
if($check('twig'))				$c->setLocalTpl($check('twig'));
if($check('apigen'))			$c->setLocalDoc($check('apigen'));

// hooks
if($check('hooks'))
	foreach($check('hooks') as $where => $more)
		foreach($more as $when => $cmds) {
			if(is_array($cmds))
				foreach($cmds as $cmd)
					$c->addHook($where, $when, $cmd);
		}

// S3
if($check('s3', 'key') && $check('s3', 'key'))
	$c->setS3(
		$check('s3', 'key'),
		$check('s3', 'secret')
	);

// start
$c->compile();