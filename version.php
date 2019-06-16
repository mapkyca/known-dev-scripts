#!/usr/bin/php -q
<?php
// Load external libraries
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once(dirname(__FILE__) . '/vendor/autoload.php');
} else {
    die('Could not find autoload.php, did you run "composer install" ..?');
}

// Register console namespace
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

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
		if (!file_exists($versionfile)) {
		    throw new \RuntimeException('No plugin or core version file could be found.');
		}
		
		$type = 'plugin';
	    }
                

            $details = @parse_ini_file($versionfile);
	    if (!is_array($details))
		throw new \RuntimeException('Version ini file is invalid');
		    
	    $version = explode('.', $details['version']);
	    
	    if ($type == 'core')
	    {
		$build = $details['build'];
	    
	    
		// Build
		$datepart = date('Ymd');
		$versionpart = 1;

		if (preg_match("/([0-9]{8})([0-9]{2})/", $build, $matches)) {
		    if ($matches[1] == $datepart) {

			$versionpart = (int)$matches[2];
			$versionpart++;


		    }
		}
		$details['build'] = $datepart . sprintf('%02d', $versionpart);
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
	    
	    $details['version'] = implode('.', $version);
	    
	    // Write ini
	    $f = fopen($versionfile, 'w');
	    foreach ($details as $key => $value) {
		if (is_int($value))
		    fputs($f, "$key = $value\n");
		else 
		    fputs($f, "$key = '$value'\n");
	    }
	    fclose($f);
	    
	    // Update composer
	    $composer_json = $input->getArgument('known-root') . '/composer.json';
	    if (file_exists($composer_json)) {
		$composer = json_decode(file_get_contents($composer_json), true);
		$composer['version'] = $details['version'];
		file_put_contents($composer_json, json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	    }
	    
	    // Update package
	    $package_json = $input->getArgument('known-root') . '/package.json';
	    if (file_exists($package_json)) {
		$package = json_decode(file_get_contents($package_json), true);
		$package['version'] = $details['version'];
		file_put_contents($package_json, json_encode($package, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
	    }
	    
	    $output->writeln("New version details are:");
	    
	    foreach ($details as $key => $value) {
		if (in_array($key, ['build', 'version'])) {
		    if (is_int($value))
			$output->writeln("\t $key => $value");
		    else
			$output->writeln("\t $key => '$value'");
		}
	    }
	    
        });

	
    $console->run();