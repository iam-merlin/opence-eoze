<?php

namespace eoko\config;

use eoko\file, eoko\file\Finder as FileFinder, eoko\file\FileType;
use eoko\util\Files;
use eoko\config\ConfigManager;
use eoko\php\SessionManager;

class Application implements FileFinder {
	
	private static $instance = null;

	private $config;

	/**
	 * @var FileFinder
	 */
	private $fileFinder = null;

	/**
	 * @var bool[MODE]
	 */
	private $modes;
	
	/**
	 * @var SessionManager
	 */
	private $sessionManager;
	
	private static $defaultSessionManager;
	
	private function __construct(SessionManager $sessionManager) {
		$this->sessionManager = $sessionManager;
		
		// Configure modes
		$this->modes = $this->getConfig()->get('modes');
		if (!isset($this->modes['dev'])) {
			$this->modes['dev'] = $this->findDevMode();
		}
	}

	private function getConfig() {
		if (!$this->config) {
			$this->config = ConfigManager::get('eoze/application');
		}
		return new Config($this->config);
	}
	
	public static function setDefaultSessionManager(SessionManager $sessionManager) {
		self::$defaultSessionManager = $sessionManager;
	}
	
	/**
	 * @return SessionManager
	 */
	public function getSessionManager() {
		return $this->sessionManager;
	}
	
	private function findDevMode() {
		$config = $this->getConfig();
		if (isset($config['devMode']) && $config['devMode'] !== 'auto') {
			return $config['devMode'];
		} else if (isset($_SERVER['HTTP_HOST'])) {
			return $_SERVER['HTTP_HOST'] === 'localhost'
					|| substr($_SERVER['HTTP_HOST'], 0, 4) === 'dev.';
		} else {
			return false;
		}
	}

	/**
	 * Shortcut for `$this->isMode('dev')`.
	 * @see isMode()
	 * @return bool
	 */
	public function isDevMode() {
		return $this->isMode('dev');
	}

	/**
	 * Returns `true` if the specified tag matches an active execution mode.
	 * A unique execution mode may have multiple aliases tags.
	 *
	 * 19/07/12 00:46 : only 'dev' and 'development' are currently supported.
	 *
	 * @param string $tag
	 * @return bool
	 */
	public function isMode($tag) {
		return isset($this->modes[$tag]) && $this->modes[$tag];
	}
	
	/**
	 * @return Application
	 */
	public static function getInstance() {
		if (self::$instance) {
			return self::$instance;
		} else {
			return self::$instance = new Application(self::$defaultSessionManager);
		}
	}
	
	public function resolveRelativePath($relativePath, $type = null, $forbidUpward = null) {
		return $this->getFileFinder()->resolveRelativePath($relativePath, $type, $forbidUpward);
	}

	public function searchPath($name, $type = null, &$getUrl = false, $forbidUpward = null, $require = false) {
		return $this->getFileFinder()->searchPath($name, $type, $getUrl, $forbidUpward, $require);
	}
	
	public function findPath($name, $type = null, &$getUrl = false, $forbidUpward = null) {
		return $this->getFileFinder()->findPath($name, $type, $getUrl, $forbidUpward);
	}
	
	private function getCssPathsUrl($urlPrefix = null) {
		$r = array();
		if (defined('APP_CSS_PATH')) $r[APP_CSS_PATH] = $urlPrefix . APP_CSS_URL;
		$r[CSS_PATH] = $urlPrefix . CSS_URL;
		return $r;
	}
	
	private function getJSPathsUrl($urlPrefix = null) {
		$r = array();
		if (defined('APP_JS_PATH')) $r[APP_JS_PATH] = $urlPrefix . APP_JS_URL;
		$r[JS_PATH] = $urlPrefix . JS_URL;
		return $r;
	}
	
	public function resolveFileFinderAlias($alias) {
		if ($alias === '@ext') {
			return array(
				FileType::JS => array(
					'ext/ext-base-debug' => -10,
					'ext/ext-all-debug-w-comments' => -9,
					'ext/ext-basex' => -8,
					'ext/ext-lang-fr' => -7,
				),
				FileType::CSS => array(
					'ext-all' => 1,
				)
			);
		} else if ($alias === '@oce-core') {
			$js = array();
			$js['init/init.js'] = 0;
			foreach (Files::listFiles(JS_PATH . 'init', 'glob:*.js') as $file) {
				$js['init/' . $file] = 1;
			}
			foreach (Files::listFiles(JS_PATH . 'core', 'glob:*.js', true) as $file) {
				$js['core/' . $file] = 2;
			}
			if ($this->isDevMode()) {
				foreach (Files::listFiles(JS_PATH . 'Eoze-tests', 'glob:*.js', true) as $file) {
					$js['Eoze-tests/' . $file] = 3;
				}
			}
			return array(
				FileType::JS => $js,
				FileType::CSS => array(
					'reset-min' => -1,
					'layout.css' => 0,
					'menu.css' => 1,
					'icons.css' => 2,
					'help.css' => 3,
					'opence.css' => 4,
					'custom.css' => 5,
				)
			);
		} else if ($alias === '@oce-components') {
			$js = array();
			$js['init/init.js'] = 0;
			foreach (Files::listFiles(JS_PATH . 'components', 'glob:*.js', true) as $file) {
				$js['components/' . $file] = 3;
			}
			$css = array();
			foreach (Files::listFiles(CSS_PATH . 'auto', 'glob:*.css', true) as $file) {
				$css['auto/' . $file] = 10;
			}
			return array(
				FileType::JS => $js,
				FileType::CSS => $css,
			);
		} else if ($alias === '@oce') {
			return array('@oce-core', '@oce-components');
		}
	}
	
	private function getFileFinder() {
		if ($this->fileFinder) return $this->fileFinder;
		
		return $this->fileFinder = new file\ObjectFinder(
			$this, 
			null,
			file\TypeFinder::createAbsolute(
				array(
					FileType::CSS => $this->getCssPathsUrl('/'),
					FileType::JS => $this->getJSPathsUrl('/'),
				),
				array(
				),
				array(
					'forbidUpwardResolution' => true,
				)
			)
		);
	}

	/**
	 * Gets the application internal name (that is, its code friendly name,
	 * not meant to be displayed to users).
	 * @return string
	 */
	public function getName() {
		return $this->getConfig()->get('name');
	}

	/**
	 * Gets the code base version unique identifier.
	 * @return string
	 */
	public function getVersionId() {
		try {
			$hg = new \eoko\hg\Mercurial(ROOT);
			return $hg->getId();
		} catch (\Exception $ex) {
			Logger::get($this)->error($ex);
			// TODO implement a fallback if no repo is available
			throw $ex;
		}
	}

}