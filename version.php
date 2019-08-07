#!/usr/bin/php -q
<?php
// Load external libraries
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once(dirname(__FILE__) . '/vendor/autoload.php');
} else {
    if (file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php')) {
        require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php');
    } else {
        die('Could not find autoload.php, did you run "composer install" ..?');
    }
}

// Register console namespace
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

function writeIni($f, array $ini) {
    foreach ($ini as $key => $value) {
        if (is_array($value)) {
            fputs($f, "\n[$key]\n");
            writeIni($f, $value);
        } else if (is_numeric($value))
            fputs($f, "$key = $value\n");
        else 
            fputs($f, "$key = '$value'\n");
    }
}

function findInArray(array $array, $key) {
    
    foreach ($array as $ak => $value) {
        if ($ak == $key) {             return $value;
        } else if (is_array($value)) {
            if ($return = findInArray($value, $key)) {
                return $return;
            }
        }
    }
}

function setInArray(array &$array, $key, $newvalue) {
    foreach ($array as $ak => $value) {
        
        if ($ak == $key) {
            $array[$ak] = $newvalue;
        } else if (is_array($value)) {
            setInArray($value, $key, $newvalue);
            $array[$ak] = $value;
        }
    }
}

// Create new console application
$console = new Application('Known version tool');

$console
        ->register('bump')
        ->setDescription('Version bump tool')
        ->setDefinition([
	    new InputArgument('version-point', InputArgument::OPTIONAL, 'Version to bump [major|minor|patch]', 'patch'),
	    new InputArgument('known-root', InputArgument::OPTIONAL, '/path/to/known', '.'),
	])
        ->setCode(function (InputInterface $input, OutputInterface $output) {
	  
	    $type = 'core';
	    $versionfile = $input->getArgument('known-root') . '/version.known';

            if (!file_exists($versionfile)) {
		$versionfile = $input->getArgument('known-root') . '/plugin.ini';
		$type = 'plugin';

		if (!file_exists($versionfile)) {
	
                    $versionfile = $input->getArgument('known-root') . '/theme.ini';
                    $type = 'theme';

                    if (!file_exists($versionfile)) {
                        throw new \RuntimeException('No plugin or core version file could be found.');
                    }

		}
	    }
                

            $details = @parse_ini_file($versionfile, true);
	    if (!is_array($details))
		throw new \RuntimeException('Version ini file is invalid');
		    
	    $version = explode('.', findInArray($details, 'version'));
	    
	    if ($type == 'core')
	    {
		$build = findInArray($details, 'build');
	    
	    
		// Build
		$datepart = date('Ymd');
		$versionpart = 1;

		if (preg_match("/([0-9]{8})([0-9]{2})/", $build, $matches)) {
		    if ($matches[1] == $datepart) {

			$versionpart = (int)$matches[2];
			$versionpart++;


		    }
		}
		//$details['build'] = $datepart . sprintf('%02d', $versionpart);
                setInArray($details, 'build', $datepart . sprintf('%02d', $versionpart));
	    }
	    
	    
	    // Version
	    switch (strtolower($input->getArgument('version-point'))) {
		case 'major':
		    $version[0]++;
		    $version[1] = 0;
		    $version[2] = 0;
		    break;
		case 'minor':
		    $version[1]++;
		    $version[2] = 0;
		    break;
		case 'patch':
		default:
		    $version[2]++;
	    }
	    
	    //$details['version'] = implode('.', $version);
	    setInArray($details, 'version', implode('.', $version));
            
	    // Write ini
	    $f = fopen($versionfile, 'w');
            writeIni($f, $details);
	    
	    fclose($f);
	    
	    // Update composer
	    $composer_json = $input->getArgument('known-root') . '/composer.json';
	    if (file_exists($composer_json)) {
		$composer = json_decode(file_get_contents($composer_json), true);
		$composer['version'] = findInArray($details, 'version');
		file_put_contents($composer_json, json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	    }
	    
	    // Update package
	    $package_json = $input->getArgument('known-root') . '/package.json';
	    if (file_exists($package_json)) {
		$package = json_decode(file_get_contents($package_json), true);
		$package['version'] = findInArray($details, 'version');
		file_put_contents($package_json, json_encode($package, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	    }
	    
	    $output->writeln("New version details are:");
            $output->writeln("\t version => " . findInArray($details, 'version'));
	    if ($type == 'core')
                $output->writeln("\t build => " . findInArray($details, 'build'));
            
	    
        });

	
    $console->run();
