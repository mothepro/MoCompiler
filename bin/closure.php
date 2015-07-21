<?php // NOT NECESSARY! I KNOW
array_shift($argv);
passthru('java -jar ".\closure\compiler.jar" '. implode(' ', $argv));