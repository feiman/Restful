<?php
namespace Drahak\Restful\DI;

use Drahak\Restful\Application\Routes\ResourceRoute;;
use Nette\Caching\Storages\FileStorage;
use Nette\Config\CompilerExtension;
use Nette\Config\Configurator;
use Nette\DI\Statement;
use Nette\Diagnostics\Debugger;
use Nette\Loaders\RobotLoader;

/**
 * Drahak\Restful Extension
 * @package Drahak\Restful\DI
 * @author Drahomír Hanák
 */
class Extension extends CompilerExtension
{

	/**
	 * Default DI settings
	 * @var array
	 */
	protected $defaults = array(
		'cacheDir' => '%tempDir%/cache',
		'routes' => array(
			'presentersRoot' => '%appDir%',
			'autoGenerated' => TRUE,
			'module' => '',
			'panel' => TRUE
		),
		'security' => array(
			'privateKey' => NULL,
			'requestTimeKey' => 'timestamp',
			'requestTimeout' => 0,
		)
	);

	/**
	 * Load DI configuration
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$container->addDefinition($this->prefix('responseFactory'))
			->setClass('Drahak\Restful\ResponseFactory');

		$container->addDefinition($this->prefix('resourceFactory'))
			->setClass('Drahak\Restful\ResourceFactory');
		$container->addDefinition($this->prefix('resource'))
			->setFactory($this->prefix('@resourceFactory') . '::create');

		// Mappers
		$container->addDefinition($this->prefix('xmlMapper'))
			->setClass('Drahak\Restful\Mapping\XmlMapper');
		$container->addDefinition($this->prefix('jsonMapper'))
			->setClass('Drahak\Restful\Mapping\JsonMapper');
		$container->addDefinition($this->prefix('queryMapper'))
			->setClass('Drahak\Restful\Mapping\QueryMapper');

		// Add input parser
		$container->addDefinition($this->prefix('input'))
			->setClass('Drahak\Restful\Input')
			->addSetup('$service->setMapper(?)', array($this->prefix('@queryMapper')));

		// Annotation parsers
		$container->addDefinition($this->prefix('routeAnnotation'))
			->setClass('Drahak\Restful\Application\RouteAnnotation');

		// Security
		$container->addDefinition($this->prefix('hashCalculator'))
			->setClass('Drahak\Restful\Security\HashCalculator');

		$container->addDefinition($this->prefix('hashAuthenticator'))
			->setClass('Drahak\Restful\Security\Authentication\HashAuthenticator')
			->setArguments(array($config['security']['privateKey']));
		$container->addDefinition($this->prefix('timeoutAuthenticator'))
			->setClass('Drahak\Restful\Security\Authentication\TimeoutAuthenticator')
			->setArguments(array($config['security']['requestTimeKey'], $config['security']['requestTimeout']));

		$container->addDefinition($this->prefix('authenticationProcess'))
			->setClass('Drahak\Restful\Security\NullAuthentication');

		// Generate routes from presenter annotations
		if ($config['routes']['autoGenerated']) {
			$container->addDefinition($this->prefix('routeListFactory'))
				->setClass('Drahak\Restful\Application\Routes\RouteListFactoryProxy')
				->setArguments(array($config['routes']));

			$container->getDefinition('router')
				->addSetup('offsetSet', array(
					NULL,
					new Statement($this->prefix('@routeListFactory') . '::create')
				));
		}

		// Create resource routes debugger panel
		if ($config['routes']['panel']) {
			$container->addDefinition($this->prefix('panel'))
				->setClass('Drahak\Restful\Diagnostics\ResourceRouterPanel')
				->addSetup('Nette\Diagnostics\Debugger::$bar->addPanel(?)', array('@self'));

			$container->getDefinition('application')
				->addSetup('$service->onStartup[] = ?', array(array($this->prefix('@panel'), 'getTab')));
		}
	}


	/**
	 * Register REST API extension
	 * @param Configurator $configurator
	 */
	public static function install(Configurator $configurator)
	{
		$configurator->onCompile[] = function($configurator, $compiler) {
			$compiler->addExtension('restful', new Extension);
		};
	}

}