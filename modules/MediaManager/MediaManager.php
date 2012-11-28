<?php

namespace eoko\modules\MediaManager;

//use eoko\module\Module;
use eoko\_getModule\GridModule;
use eoko\module\ModuleLocation;
use eoko\module\traits\HasRoutes;
use Zend\Mvc\Router\Http\Regex;
use eoko\log\Logger;

use RuntimeException;

/**
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Éric Ortega <eric@planysphere.fr>
 * @since 1 août 2012
 */
class MediaManager extends GridModule implements HasRoutes {

	/**
	 * @var Regex
	 */
	private $downloadRoute;

	protected function construct(ModuleLocation $location) {
		parent::construct($location);
		if (!$this->isAbstract()) {

			$downloadUrl = $this->getConfig()->get('downloadUrl');
			$quotedDownloadUrl = preg_quote($downloadUrl, '/');

			$this->downloadRoute = Regex::factory(array(
				'regex' => "/$quotedDownloadUrl/(?<path>.+)",
				'spec' => "/$downloadUrl/%path%",
				'defaults' => array(
					'_RequestReader' => 'eoko\mvc\LegacyRequestReader',
					'_Router' => 'eoko\mvc\LegacyRouter',
					'controller' => $this->getName() . '.download',
					'action' => 'download',
				),
			));
		}
	}

	public function getRoutesConfig() {
		if ($this->downloadRoute) {
			return array(
				$this->getName() . '/download-route' => $this->downloadRoute,
			);
		}
	}

	/**
	 * @return Regex
	 */
	public function getDownloadRoute() {
		return $this->downloadRoute;
	}

	public function getDownloadPath($subPath = null) {
		// Resolve download path
		$configPath = $this->getConfig()->get('downloadPath');
		if ($configPath !== null) {
			$downloadPath = str_replace(array('%ROOT%/', '%ROOT%'), ROOT, $configPath);
		} else if (defined(MEDIA_PATH)) {
			Logger::get($this)->error('Deprecated feature flagged for imminent removal');
			$downloadPath = MEDIA_PATH;
		} else {
			throw new RuntimeException('Missing configuration: downloadPath');
		}
		// Trim
		$downloadPath = rtrim($downloadPath, '\\/');
		// Subpath
		if ($subPath) {
			return $downloadPath . DS . $subPath;
		} else {
			return $downloadPath;
		}
	}
}
