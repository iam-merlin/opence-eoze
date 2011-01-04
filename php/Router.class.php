<?php
/**
 * @author Éric Ortéga <eric@mail.com>
 * @license http://www.planysphere.fr/licenses/psopence.txt
 */

use eoko\module\ModuleManager;
use eoko\module\Module;

if (!isset($GLOBALS['directAccess'])) { header('HTTP/1.0 404 Not Found'); exit('Not found'); }

/**
 * Route HTTP requests to the correct action of the correct module.
 *
 * Two parameters are expected in the request's data (either POST or GET):<ul>
 * <li>'mod' or 'module' specifying the module
 * <li>'act' or 'action' sepcifying the action
 * </ul>
 *
 * If no module is specified, then the request will be routed to the 'root'
 * module. If no action is specified, then the 'index' action of the specified
 * module will be used.
 */
class Router {

	/** @var Router */
	private static $instance = null;

	const ROOT_MODULE_NAME = 'root';
	protected $rootModuleName = 'root';

	public $request;
	public $actionTimestamp;

	/**
	 * @return Router
	 */
	public static function getInstance() {
		if (self::$instance === null) self::$instance = new Router();
		return self::$instance;
	}

	public function __construct() {

		$this->request = new Request($_REQUEST);
		$this->actionTimestamp = time();
		Logger::getLogger($this)->info('Start action #{}', $this->actionTimestamp);

//		if (defined('APP_INDEX_MODULE')) {
//			self::ROOT_MODULE_NAME = APP_INDEX_MODULE;
//		}

		UserMessageService::parseRequest($this->request);
	}

	public static function getActionTimestamp() {
		return self::getInstance()->actionTimestamp;
	}

	/**
	 * Examines the request's params and route to the appropriate action.
	 * @see Router
	 */
	public function route() {

		if (!$this->request->has('controller')) {
			$this->request->override(
				'controller', 
				defined('APP_INDEX_MODULE') ? APP_INDEX_MODULE : self::ROOT_MODULE_NAME
			);
		}

		$action = Module::parseRequestAction($this->request);
		$action();
		
//		if (($controller = $this->getController()) !== null) {
//
//			if (($action = $this->getAction()) !== null) {
//				$this->executeAction($controller, $action);
//			} else {
//				$this->executeAction($controller);
//			}
//
//		} else {
//			// Routing is done after it has been checked that user is logged
////			throw new SystemException('Module info absent from request', lang('Module introuvable'));
//			Logger::getLogger()->warn('No routing information available => reloading application');
//			$this->executeAction(self::ROOT_MODULE_NAME);
//		}
	}

	private function getController() {
		if (false !== $key = $this->request->hasAny(array('controller', 'module', 'mod'), true)) {
			if ($key != 'controller') {
				Logger::getLogger($this)->warn('"module" used instead of "controller" in request');
			}
			$controller = $this->request->getRaw($key);
		} else {
			$controller = defined('APP_INDEX_MODULE') ? 
				APP_INDEX_MODULE : self::ROOT_MODULE_NAME;
		}
		
		Logger::getLogger('router')->debug('Controller is: {}', $controller);
		return $controller;
	}

	private function getAction() {
		$action = $this->request->getFirst(array('action', 'act'), 'index', true);
		Logger::getLogger('router')->debug('Action is: {}', $action);
		return $action;
	}

	/**
	 * Force the routing to the 'login' action of the root module, that is the
	 * action that process login requests.
	 */
	public function executeLogin() {

		$module = $this->getController();
		$action = $this->getAction();

		if ($module !== 'root' && $action !== 'login') {
			Logger::getLogger('Router')->warn('Forcing routing to Login module.'
					. 'Request params are: module={} action={}', $module, $action);
		}

		$this->executeAction('root', 'login');
	}

	/**
	 * Force the routing to the 'get_login_page' action of the 'root' module,
	 * that is the action which presents the user with the page where they can
	 * log in.
	 */
	public function loadLoginPage() {

		$module = $this->getController();
		$action = $this->getAction();

		if ($module !== 'root' && $action !== 'get_login_module') {
			Logger::getLogger('Router')->warn('Forcing routing to Login module.'
					. 'Request params are: module={} action={}', $module, $action);
		}

		$this->executeAction(self::ROOT_MODULE_NAME, 'get_login_module');
	}

	/**
	 * Execute the given action of the specified module.
	 * @param String $module name of the module
	 * @param String $action name of the action
	 */
	public function executeAction($module, $action = 'index', $request = null) {

		if ($request !== null) {
			$request = is_array($request) ? new Request($request) : $request;
		} else {
			$request = $this->request;
		}

		if (preg_match('/^([^.]+)\.(.+)$/', $module, $m)) {
			$module = $m[1];
			$this->request->override('executor', $m[2]);
		}

		ModuleManager::getModule($module)->executeRequest($request);
		
//REM		$module = ModuleManager::createController($module, $action, $request);
//		
//		if ($module instanceof eoko\module\Module) {
//			$module->execute($request);
//		} else {
//			// Execute action
//			$module->beforeAction($action);
//
//	//		if (is_bool($r = $controller->$action())) {
//			if (is_bool($r = $module->execute($action))) {
//				if ($r) {
//					ExtJSResponse::answer();
//				} else {
//					ExtJSResponse::failure();
//				}
//			} else if ($r instanceof TemplateHtml) {
//				$module->engine->processHtmlFragment($r);
//			}
//		}
	}
	
}
