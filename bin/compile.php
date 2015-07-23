<?php
require 'vendor/autoload.php';

/**
 * Giv'em some help
 */
function help() {
	echo <<<HELP
Mo's PHP Project Compiler!
	-h		--help	Print this help message.

	Required Options
		-c		Configuration file to use
		-s		Host name of server to compile to
		-p		Location of PPK file to connect to server
		-d		Remote Directory to upload project

	Optional Options
	
		-l				Local Directory to load project [Working Directory]
		
		--upload		Directories to move from local to remote

		--twig			Twig Template Directory
		--apigen		Documentation Configuration

		--compress		Compress static files
		--quiet			Silent mode

		--local-sass	Local SASS Directory
		--local-js		Local JS Directory
		--local-img		Local Image Directory

		--s3-key		Amazon S3 Access Key Activates compression
		--s3-secret		Amazon S3 Secret Key

		--remote-sass	Remote Directory to save sass on server or S3
		--remote-js		Remote Directory to save js on server or S3
		--remote-img	Remote Directory to save images on server or S3

	Composer Packages for Compression & Uploading
		tpyo/amazon-s3-php-class
		apigen/apigen
		nette/neon
HELP;
}

/**
 * Returns a value if option is found in global
 * 
 * @global mixed[] $neon
 * @param string $name option to look for
 * @return string|null
 */
function check($name) {
	// screw it
	global $neon;
	
	$def = function($config, $args) use (&$def) {
		$name = array_shift($args);
		$ret = false;
		
		if(isset($config[$name])) {
			if(empty($args))
				$ret = $config[$name];
			else
				$def($config[$name], $args);
		}
		
		return $ret;
	};
	
	return $def($neon, func_get_args());
}

/**
 * Replaces config with command line options recursively
 * 
 * @param mixed[] $origin config array
 * @param string $name name
 * @param mixed $newValue value
 */
function replace(&$origin, $name, $newValue) {
	$names = explode('-', $name);
	$curr = array_shift($names);
	
	if(empty($names))
		$origin[ $curr ] = $newValue;
	else
		replace($origin[$curr], implode('-', $names), $newValue);
}

// no errors wanted
set_time_limit(0);
date_default_timezone_set('UTC');
error_reporting(-1);

// options
$neon = null;
$opt = getopt('c:s:p:l:d:h', [
	'help',

	'compress',
	'quiet',

	'upload:',
	'twig:',
	'apigen:',
	'local-sass:',
	'local-js:',
	'local-img:',
	's3-key:',
	's3-secret:',
	'remote-sass:',
	'remote-js:',
	'remote-img:',
]);

// not missing neon or config
if(isset($opt['c'])) {
	$data = file_get_contents($opt['c']);
	$neon = \Nette\Neon\Neon::decode($data);
	
	if(isset($neon['compiler']))
		$neon = $neon['compiler'];
	
	// move legacy cmd line options
	if(isset($opt['s']))	$opt['host']	= $opt['s'];
	if(isset($opt['p']))	$opt['ppk']		= $opt['p'];
	if(isset($opt['d']))	$opt['remote']	= $opt['d'];
	if(isset($opt['l']))	$opt['project']	= $opt['l'];

	// overwrite neon with cmd line opts
	if(!is_array($neon))
		$neon = array();

	print_r($neon);

	// replace $neon[ $name ] with $val
	foreach($opt as $name => $val)
		replace($neon, $name, $val);

	print_r($neon);

	//  check errors
	$error = (empty($neon) || !isset($neon['s']) || !isset($neon['p']) || !isset($neon['d']));
} else
	$error = true;

// error OR just needs a little help
if($error || isset($opt['h']) || isset($opt['help'])) {
	if($error)
		echo 'Error, missing required option.', PHP_EOL, PHP_EOL;
	help();
	exit($error);
}
exit;
$c = new Mo\Compiler\Compiler;
$c	->setHost(check('host'))
	->setPpk(check('ppk'))
	->setRemote(check('remote'));

// misc
$c->setCompress(check('compress'));
$c->setSilent(check('silent'));

// add directories
if(check('local', 'sass'))	foreach(check('local', 'sass')	as $dir)	$c->addSASS($dir);
if(check('local', 'js'))	foreach(check('local', 'js') 	as $dir)	$c->addJS($dir);
if(check('local', 'img'))	foreach(check('local', 'img') 	as $dir)	$c->addImage($dir);
if(check('upload'))			foreach(check('upload') 		as $dir)	$c->addMove($dir);

// misc
if(check('project'))	$c->setLocal(check('project'));
if(check('twig'))		$c->setLocalTpl(check('twig'));
if(check('apigen'))		$c->setLocalDoc(check('apigen'));

// S3
if(check('s3', 'key') && check('s3', 'key'))
	$c->setS3(
		check('s3', 'key'),
		check('s3', 'secret')
	);

// remote
if(check('remote-sass'))	$c->setRemoteSASS(check('remote-sass'));
if(check('remote-js'))		$c->setRemoteJS(check('remote-js'));
if(check('remote-img'))		$c->setRemoteImage(check('remote-img'));

// start
$c->compile();