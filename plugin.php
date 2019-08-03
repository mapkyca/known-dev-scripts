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

// Create new console application
$console = new Application('Known version tool');


$console
        ->register('enable-composer')
        ->setDescription('Enable composer installation for a plugin')
        ->setDefinition([
            new InputArgument('repository', InputArgument::REQUIRED, 'Repository address e.g. https://github.com/mapkyca/known-dev-scripts'),
	    new InputArgument('type', InputArgument::OPTIONAL, 'Plugin type: known-plugin, known-theme or known-console', 'known-plugin'),
            new InputArgument('location', InputArgument::OPTIONAL, 'Path to plugin', '.'),
	])
        ->setCode(function (InputInterface $input, OutputInterface $output) {
	  
	    $plugin_ini = trim($input->getArgument('location'), ' /') . '/plugin.ini';

            if (!file_exists($plugin_ini)) {
                throw new RuntimeException("Could not find $plugin_ini, is this a valid plugin?");
	    }
             
            $plugin_ini = parse_ini_file($plugin_ini, false);
            $version = $plugin_ini['version'];
            if (empty($version)) {
                $version = '1.0.0'; // Set default version
            }
           
            $name = $plugin_ini['name'];
            if (empty($name)) {
                throw new RuntimeException("Could not find plugin name from plugin.ini");
            }
            
            if (!in_array($input->getArgument('type'), [
                'known-plugin',
                'known-console',
                'known-theme'
            ])) {
                throw new RuntimeException('Unsupported plugin type - needs to be either known-plugin, known-theme or known-console');
            }
            
            $repository = $input->getArgument('repository');
            $repository = explode(':', $repository)[1];
            $repository = explode('.', $repository)[0];
            
            
            // Default composer
            $composer_default = [
                'name' => $repository,
                'description' => $plugin_ini['description'],
                'type' => $input->getArgument('type'),
                'version' => $version,
                'require' => [
                    "composer/installers" => "dev-master"
                ],
                'extra' => [
                    "installer-name" => $name
                ]
            ];
            
            $composerfile = trim($input->getArgument('location'), ' /') . '/composer.json';
            if (file_exists($composerfile)) {
                $composer_default = array_merge($composer_default, json_decode(file_get_contents($composerfile), true));
            }
            
            if (is_array($composer_default)) {
                $output->writeln('Writing new composer.json:');
                $output->writeln(json_encode($composer_default, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                file_put_contents($composerfile, json_encode($composer_default, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            }
        });

	
    $console->run();