<?php

namespace eoko\module;

const GET_MODULE_NAMESPACE = 'eoko\\_getModule\\';

use eoko\config\Config, eoko\config\ConfigManager;
use eoko\php\generator\ClassGeneratorManager;
use eoko\util\Files;

use IllegalStateException;
use Logger;

/**
 * Terminology:
 *   - Module _line_: vertical inheritance (eg. r\m\GridModule => eoko\modules\GridModule, ...)
 *   - Module _lineage_: horizontal inheritance (eg. r\m\Membres => r\m\GridModule, ...)
 */
class ModuleManager {

	private static $modulesDirectories = null;
	private static $infoLocked = false;
	
	private static $instance = null;

	private static $moduleFactories = null;
	
	private static $modules = null;

	private $getModuleNamespaceRegex;

	/**
	 * @var FileFinder
	 */
	private $fileFinder = null;
	
	private function __construct() {

		$this->getModuleNamespaceRegex = '/^' . preg_quote(GET_MODULE_NAMESPACE, '/') . '(.+)$/';
		
		$this->loadConfig();
		
		self::$modulesDirectories = array_reverse(self::$modulesDirectories, true);
		self::$infoLocked = true;
	}

	/**
	 * Returns the first ModulesDirectory from the registered ones list.
	 * @return ModulesDirectory
	 */
	public static function getTopLevelDirectory() {
		return self::$modulesDirectories[count(self::$modulesDirectories) - 1];
	}

	private function loadConfig() {
		$config = ConfigManager::get(__NAMESPACE__);
		foreach (array_reverse($config['locations']) as $dirName => $location) {
			self::addModuleLocationInfo($dirName, $location['path'], $location['url'], $location['namespace']);
		}
	}

	/**
	 * Adds a new possible location for Modules. This initialization can
	 * be called only before the ModuleManager actually starts being used. The
	 * locations added first will be the ones with the lowest priority in the
	 * module search algorithm; the modules in the lower locations will also
	 * be considered potential parents for all upper locations (i.e. if a module
	 * from an upper location has the same name as one from a lower location,
	 * it will be considered as extending the latter).
	 * @param string $dirName
	 * @param string $basePath
	 * @param string $baseUrl
	 * @param string $namespace
	 */
	private static function addModuleLocationInfo($dirName, $basePath, $baseUrl, $namespace) {
		if (self::$infoLocked) {
			throw new IllegalStateException(
				'All module locations must be added before the first use of ModuleManager'
			);
		}
		$parent = self::$modulesDirectories === null ? null : self::$modulesDirectories[count(self::$modulesDirectories) - 1];
		self::$modulesDirectories[] = new ModulesDirectory($dirName, $basePath, $baseUrl, $namespace, $parent);
	}

	private function testGetModuleNamespace($classOrNamespace) {
		if (!preg_match($this->getModuleNamespaceRegex, $classOrNamespace, $m)) {
			return false;
		} else {
			return $m[1];
		}
	}

	public static function autoLoad($class, $suffix) {

		// The class autoloader may be called before the ModuleManager instance
		// has been created, or in the process of its creation. In this case,
		// these are not Modules classes that are searched.
		if (!self::$instance) return false;

		if (false !== $module = self::$instance->testGetModuleNamespace($class)) {
			$module = self::getModule($module);
			class_extend(GET_MODULE_NAMESPACE . $module, get_class($module));
			return true;
		}

		foreach (self::$modulesDirectories as $location) {
			$location instanceof ModulesDirectory;
			if ($location->testNamespace($class)) {

				$classPath = substr($class, strlen($location->namespace));
				$classPath = $location->path . str_replace('\\', DS, $classPath);

				if (file_exists($path = "$classPath$suffix.php")) {
					require_once $path;
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return array[ModulesLocation]
	 * @deprecated this method should follow the listModulesLocations naming
	 * convention
	 */
	private static function getModulesLocations() {
		self::getInstance();
		return self::$modulesDirectories;
	}

	public static function listModules($onlyWithDir = false) {
		$self = self::getInstance();
		$config = ConfigManager::get(__NAMESPACE__);
		
//		dump($config);
		$r = array();

		foreach (self::$modulesDirectories as $modulesDir) {
			//$modulesDir instanceof ModulesDirectory;
			$usedModules = isset($config['used'][$modulesDir->name]) ? $config['used'][$modulesDir->name] : null;
			foreach($modulesDir->listModules($usedModules, $onlyWithDir) as $module) {
				$r[$module->getName()] = $module;
			}
		}

		return array_values($r);
	}

	public static function registerModuleFactory(ModuleFactory $factory) {
		self::$moduleFactories[] = $factory;
	}

	/**
	 * @return ModuleManager
	 */
	private static function createInstance() {
		return self::$instance = new ModuleManager();
	}
	
	/**
	 * @return ModuleManager
	 */
	public static function getInstance() {
		if (self::$instance) return self::$instance;
		else return self::createInstance();
	}
	
	/**
	 * Gets the module with the given name. Module are searched in each
	 * registered {@link ModulesLocation}, starting from the application level
	 * and going down to the eoko library level.
	 *
	 *
	 * Module inheritance mecanisme allows for recursive overriding of:
	 * - the module class itself
	 * - the module configuration
	 * - any of the module's executors
	 *
	 * It doesn't work however for overriding normal PHP classes. If this is
	 * needed, the top modules must extend the fully qualified (ie. named with
	 * its full namespace) parent class.
	 *
	 *
	 * Module Inheritance
	 *
	 * Modules which class is explicitely declared in a {ModuleName}.class.php
	 * file can override another module by extending it without importing
	 * any specific class with the <b>use</b> keyword. The ModuleManager will
	 * resolve the highest (the nearest to the application level) implementation
	 * of the Module.
	 *
	 * Modules of which the class is implicitely created (that is, which do not
	 * have a {ModuleName}.class.php file) must declare the name of their base
	 * module in their config file.
	 *
	 * A Module with the same name as a Module found lower in the hierarchy will
	 * automatically extend it. It doesn't need to have an explicit class file
	 * declaration, or to have a configuration file. A Module with the same
	 * name as a lower Module is not allowed to extend from another Module
	 * (that is, a module with another name). That would be problematic, since
	 * the lower Module could not be accessed by its name anymore, which could
	 * be unexpected by some part of the application. If you want to do that,
	 * that means that you don't need the lower Module, so you can just disable
	 * it.
	 *
	 *
	 * Configuration Inheritance
	 *
	 * The configuration will always be inherited from lower modules, with
	 * configuration from higher level overriding configuration from lower
	 * levels.
	 *
	 *
	 * Executor Inheritance
	 *
	 * Executors of the same type will override lowser executors if they extend
	 * {ExecutorType}Base.
	 *
	 * 
	 * @param string $name
	 * @param boolean $required
	 * @return Module
	 */
	public static function getModule($name, $required = true) {
		if ($name instanceof Module) {
			return $name;
		} else if (isset(self::$modules[$name])) {
			return self::$modules[$name];
		} else {
			return self::$modules[$name] = self::getInstance()->doGetModule($name, $required);
		}
	}
	
	private function doGetModule($name, $required) {

		if (strstr($name, '\\')) {
			$ns = get_namespace($name, $relName);
			try {
				return $this->getModuleInNamespace($relName, $ns);
			} catch (MissingModuleException $ex) {
				throw new MissingModuleException($name, $ex);
			}
		}

		// try to delegate
		if (self::$moduleFactories) foreach (self::$moduleFactories as $factory) {
			if (null !== $module = $factory->generateModule($name)) {
				Logger::get($this)->debug('Module generated by factory: {}', $name);
				return $module;
			}
		}

		// ... or do the job
		foreach (self::$modulesDirectories as $location) {
			if (($module = $this->tryGetModule($name, $location))) {
				return $module;
			}
		}

		if ($required) throw new MissingModuleException($name);
		else return null;
	}

	private function getModuleInNamespace($name, $ns) {

		throw new \Exception('DEPRECATED');
			
		Logger::get($this)->warn(
			'GetModule used to retrieve absolute class: {}. This is wrong. '
			. 'Do not do that!',
			$name
		);
		
		foreach (self::$modulesDirectories as $location) {
			$location instanceof ModulesDirectory;
			if ($location->testNamespace($ns)) {
				$module = $this->tryGetModule($name, $location);
				if ($module) {
					return $module;
				}
			}
		}
		
		throw new MissingModuleException($name);
	}

	/**
	 *
	 * @param string $name    the module name
	 * @param ModulesDirectory $inLocation the location to be searched for the
	 * module class
	 * @param ModulesDirectory $dir the base location of the module.
	 * This parameter should be given if a location for this module has already
	 * been found (that is a directory that could contains module information),
	 * but this location doesn't contains the information to instanciate the
	 * module.
	 * @return class
	 */
	private function tryGetModule($name, ModulesDirectory $dir) {

		$location = new ModuleLocation($dir, $name);
		$config = $location->loadConfig();
		
		if ($location->isDisabled()) return null;

		if (!$location->isActual()) {
			$namespace = "$dir->namespace$name\\";
			return $this->createDefaultModule($location, $config);
		} else {

			$module = null;
			if (null !== $class = $location->searchModuleClass()) {
				$module = new $class($location);
			}

			// try to create the module from the config file information
			if ($config && ($m = $this->createDefaultModule($location, $config))) {
				return $m;
			}

			if (!$module) {
				// generate the module class in the namespace
				$superclass = $location->searchModuleSuperclass();
				if ($superclass === null) {
					$superclass = __NAMESPACE__ . '\\Module';
				}
				class_extend($class = "$location->namespace$name", $superclass);
				$module = new $class($location);
			}

			// if the method has not returned yet, that means that the module
			// has not been created from config: the config must be set!
			$module->setConfig($config);
			return $module;
		}
	}

	/**
	 * Generates a default module class and instanciates it, according to its
	 * configuration file "class" item.
	 */
	public function createDefaultModule(ModuleLocation $location, $config) {

		$config = Config::create($config);
		if (isset($config[$location->moduleName])) $config = $config->node($location->moduleName, true);

		if (!isset($config['class'])) {
			// this is a base module, in the vertical hierarchy (direct descendant
			// of Module) -- it cannot be created from config, let's return false
			// to let the ModuleManager find and instanciate the module class
			return false;
		}

		$class = $location->namespace . $location->moduleName;

		// Generate the module class, if needed
		if (!class_exists($class)) {
			$baseModule = self::getModule($config->class);
			// use the base module to generate the default class
			$baseModule->generateDefaultModuleClass($class, $config);
		}
		
		// create an instance of the newly created class
		$module = new $class($location);
		$module->setConfig($config);
		return $module;
	}
	
}

class MissingModuleException extends \SystemException {
	
	public function __construct($name, \Exception $previous = null) {
		parent::__construct('Missing module: ' . $name, '', $previous);
	}
}

class InvalidModuleException extends \SystemException {
	
	public function __construct($name, $cause, \Exception $previous = null) {
		parent::__construct("Invalid module: $name ($cause)", '', $previous);
	}
}

class Location {

	public $path;
	public $url;
	public $namespace;

	function __construct($path, $url, $namespace) {
		$this->path = $path;
		$this->url = $url;
		$this->namespace = $namespace;
	}

	public function __toString() {
		return "Path: $this->path; URL: $this->url; Namespace: $this->namespace";
	}
}

/**
 * Represents the different locations of one named module.
 */
class ModuleLocation extends Location {

	public $moduleName;
	/** @var ModulesDirectory */
	public $directory;

	/** @var array[ModuleLocation] Cache for actual locations */
	private $actualLocations = null, $locations = null;

	/**
	 * @var mixed Cache for the {@link loadConfig()} method. FALSE if the config
	 * has not been loaded yet, else the value of loadConfig() (that can be NULL).
	 */
	private $configCache = false;

	/**
	 * Creates a new ModuleLocation. If no directory for the module exists in
	 * the location, the $path is set to NULL. This test can be bypassed by
	 * passing a $path to the constructor -- which should be avoided anyway,
	 * except if you know what you are doing.
	 * @param ModulesDirectory $dir
	 * @param string $moduleName
	 * @param string|boolean $path TRUE to let the constructor search for the
	 * module's path, or a value to force the location's path to be set
	 */
	function __construct(ModulesDirectory $dir, $moduleName, $path = true) {

		$this->directory = $dir;
		$this->moduleName = $moduleName;

		if ($path === true) {
			$path = is_dir($path = "$dir->path$moduleName") ? $path . DS : null;
		}

		parent::__construct(
			$path,
			$path !== null && $dir->url !== null ? "$dir->url$moduleName/" : null,
			"$dir->namespace$moduleName\\"
		);
	}

	/**
	 * Finds the module's config file path. The directory parents are not
	 * searched by this method.
	 * @return string The path of the found config file, or NULL if no config
	 * file is found in this location.
	 */
	public function searchConfigFile() {
		if ($this->isActual()) {
			if (file_exists($file = "$this->path$this->moduleName.yml")
					|| file_exists($file = "{$this->path}config.yml")) {

				return $file;
			} else {
				return null;
			}
		} else if (file_exists($file = "{$this->directory->path}$this->moduleName.yml")) {
			return $file;
		} else {
			return false;
		}
	}

	/**
	 * Load the config of all the module's {@link ModuleManager line}. The 
	 * returned Config object is cached for subsequent call to the method.
	 * @return Config or NULL
	 */
	public function loadConfig() {
		if ($this->configCache === false) {
			$this->configCache = $this->doLoadConfig();
		}
		return $this->configCache;
	}
	
	private function doLoadConfig() {
		$r = null;
		foreach ($this->getLocations() as $location) {
			$config = $location->searchConfigFile();
			if ($config) {
				$config = Config::create($config);
				if ($r === null) {
					$r = $config;
				} else {
					$r->apply($config, false);
				}
			}
		}
		return $r;
	}

	/**
	 * An actual ModuleLocation is a location that actually contains a directory
	 * for its module.
	 * @return boolean
	 * @see $moduleName
	 */
	public function isActual() {
		return $this->path !== null;
	}

	/**
	 * A module location can be ignored by placing a file named "disabled" in
	 * its directory. This method will return FALSE if such a file is present,
	 * OR if the module doesn't exist in the current location (independantly of
	 * its existence in parent locations). Namely, a module will exists, if it
	 * has a directory with its name in the location, or a configuration file
	 * in the root of the location directory.
	 * @return boolean
	 */
	public function isDisabled() {
		return $this->path === null ? 
//				($this->directory->path === null || !file_exists("{$this->directory->path}$this->moduleName.yml"))
				($this->directory->path === null || !$this->loadConfig())
				: file_exists($this->path . 'disabled');
	}
	
	/**
	 * Gets the ModuleLocations of this location's module, starting from this
	 * locations and including only location in which a directory for this
	 * module exists.
	 * @return array[ModuleLocation]
	 */
	public function getActualLocations($includeSelf = true) {
		
		if ($this->actualLocations !== null) {
			if ($includeSelf || !$this->path !== null) {
				return $this->actualLocations;
			} else {
				$r = $this->actualLocations;
				array_unshift($r);
				return $r;
			}
		}

		$this->actualLocations = array();

		// if _this_ is an actual location
		if ($this->path !== null) {
			$this->actualLocations[] = $this;
		}

		// search parents
		$dir = $this->directory->parent;
		while ($dir) {
			if (is_dir($path = "$dir->path$this->moduleName")) {
				$this->actualLocations[] = new ModuleLocation($dir, $this->moduleName, $path . DS);
			}
			$dir = $dir->parent;
		}

		return $this->getActualLocations($includeSelf);
	}

	public function getLineActualLocations($includeSelf = true) {
		$r = array();
		foreach ($this->getActualLocations($includeSelf) as $loc) {
			if ($loc->moduleName === $this->moduleName) $r[] = $loc;
		}
		return $r;
	}

	public function getLocations($includeSelf = true) {

		if ($this->locations !== null) {
			if ($includeSelf) {
				return $this->locations;
			} else {
				$r = $this->locations;
				array_unshift($r);
				return $r;
			}
		}

		$this->locations = array($this);

		$dir = $this->directory->parent;
		while ($dir) {
			$this->locations[] = new ModuleLocation($dir, $this->moduleName);
			$dir = $dir->parent;
		}

		return $this->locations;
	}

	/**
	 * Searches the location for a file matching the module class file pattern
	 * and, if one is found, returns the module class' name.
	 * @return string The qualified class name, or NULL.
	 * @throws InvalidModuleException if a matching file is found but doesn't
	 * contain a class that matches the module classes naming pattern.
	 */
	public function searchModuleClass() {

		foreach (array("$this->moduleName.class.php", 'module.class.php', "{$this->moduleName}Module.class.php") as $file) {
			if (file_exists($file = "$this->path$file")) {
				require_once $file;
				foreach (array($this->moduleName, 'module', "{$this->moduleName}module") as $class) {
					$class = $this->namespace . $class;
					if (class_exists($class, false)) {
						return $class;
					}
				}
				// A file matching the module filename pattern
				// (eg. GridModule.class.php) has been found and included, but
				// we cannot find the matching class...
				throw new InvalidModuleException($this->moduleName, 'cannot find module class');
			}
		}

		return null;
	}

	public function searchModuleSuperclass() {
		foreach ($this->getActualLocations(false) as $location) {
			if (null !== $class = $location->searchModuleClass()) {
				return $class;
			}
		}
		return null;
	}

}

class ModulesDirectory extends Location {

	/** @var ModulesDirectory */
	public $parent;
	/**
	 * @var string The module name, as it is referred in the config. This name
	 * is either extracted from the "locations" param in the config, or an
	 * arbitrary bame can be given when the ModulesDirectory instance is
	 * created. This name can be used in other configuration items to refer to
	 * this particular ModulesDirectory, so it must be unique.
	 */
	public $name;

	function __construct($name, $path, $url, $namespace, ModulesDirectory $prev = null) {
		if (substr($path, -1) !== DS) $path .= DS;
		if (substr($url, -1) !== '/') $url .= '/';
		if (substr($namespace, -1) !== '\\') $namespace .= '\\';
		parent::__construct($path, $url, $namespace);
		$this->parent = $prev;
		$this->name = $name;
	}

	/**
	 * Tests if the passed namespace is the same as this ModuleInfo's one.
	 * @param string $namespaceOrClass
	 * @return bool  TRUE if the passed namespace is the same this ModuleInfo's
	 * one, else FALSE.
	 */
	public function testNamespace($namespaceOrClass) {
		$ns = substr($namespaceOrClass, 0, strlen($this->namespace));
		if (substr($ns, -1) !== '\\') $ns .= '\\';
		return $ns === $this->namespace;
	}

	public function getLineagePathsUrl($names) {
		$pathsUrl = array();
		foreach ($names as $name) {
			$pathsUrl = $pathsUrl + $this->getPathsUrl($name);
		}
		return $pathsUrl;
	}

	public function getPathsUrl($moduleName) {
		$pathsUrl = array();
		if (file_exists($path = "$this->path$moduleName" . DS)) {
			$pathsUrl[$path] = $this->url !== null ? "$this->url$moduleName/" : null;
		}
		if ($this->parent) {
			$pathsUrl = $pathsUrl + $this->parent->getPathsUrl($moduleName);
		}
		return $pathsUrl;
	}
	
	public function getLineageLocations($names) {
		$locations = array();
		foreach ($names as $name) {
			$locations = array_merge($locations, $this->getLocations($name));
		}
		return $locations;
	}

	private function getLocations($moduleName) {
		$locations = array();
		if (file_exists($path = "$this->path$moduleName" . DS)) {
			$locations[] = new ModuleLocation($this, $moduleName);
		}
		if ($this->parent) {
			$locations = array_merge($locations, $this->parent->getLocations($moduleName));
		}
		return $locations;
	}

	/**
	 * List the Modules in this directory. This method should be avoided when
	 * performance is desired, since it will instanciate all the modules in the
	 * directory, which requires quite a bit of file parsing and config file
	 * reading...
	 * @return array[Module]
	 */
	public function listModules($usedModules = null, $onlyWithDir = false) {
		if ($usedModules === '*') $usedModules = null;
		$r = array();
		if ($this->path) {
			foreach (Files::listDirs($this->path) as $dir) {
				if (($usedModules === null || array_search($dir, $usedModules, true) !== false)
						&& null !== $module = ModuleManager::getModule($dir, false)) {
					$r[] = $module;
				}
			}
			if (!$onlyWithDir) {
				foreach (Files::listFiles($this->path, 'glob:*.yml') as $file) {
					if (($usedModules === null || array_search($dir, $usedModules, true) !== false)
							&& null !== $module = ModuleManager::getModule(substr($file, 0, -4), false)) {
						$r[] = $module;
					}
				}
			}
		}
		return $r;
	}

	/**
	 * Gets parent path urls.
	 * @param string $moduleName
	 * @return array or NULL if there is no parent paths for the given module.
	 */
	public function getParentPathsUrl($moduleName) {
		if ($this->parent && count($pathUrls = $this->parent->doGetParentPathsUrl($moduleName))) {
			return $pathUrls;
		} else {
			return null;
		}
	}

	private function doGetParentPathsUrl($moduleName) {
		$pathUrls = array();
		if (file_exists($path = "$this->path$moduleName" . DS)) {
			$pathUrls[$path] = $this->url !== null ? "$this->url$moduleName/" : null;
		}
		if ($this->parent) {
			$pathUrls = $pathUrls + $this->parent->doGetParentPathsUrl($moduleName);
		}
		return $pathUrls;
	}

	public function __toString() {
		return "Path: $this->path, URL: $this->url, Namespace: $this->namespace, Parent: "
				. ($this->parent !== null ? "\n\t\t$this->parent" : "NULL");
	}
}

interface ModuleFactory {
	function generateModule($name);
}

class Module_ModuleFactory implements ModuleFactory {

	private $moduleName;

	function __construct($moduleName) {
		$this->moduleName = $moduleName;
	}

	public function generateModule($name) {
		// avoid infinite recursion
		if ($name !== $this->moduleName) {
			$module = ModuleManager::getModule($this->moduleName);
			return $module->generateModule($name);
		} else {
			return null;
		}
	}
}
