<?php

// This must remain as much as possible dependenceless, so that it can be used in any
// simple script used without loading eoze.

namespace eoko\php;

use eoko\log\Logger;

/**
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Éric Ortega <eric@planysphere.fr>
 * @since 30 nov. 2011
 */
class SessionManager {

	protected $savePath;
	protected $sessionName;

	private $listeners;

	private $sessionId = null;
	private $modified = false;
	private $started  = false;

	private $data = null;

	public function __construct($path) {

		if (!file_exists($path)) {
			mkdir($path, 0700);
		}

		session_save_path($path);

		session_set_save_handler(
			array($this, "open"), 
			array($this, "close"), 
			array($this, "read"), 
			array($this, "write"), 
			array($this, "destroy"), 
			array($this, "gc")
		);
	}

	public function __destruct() {
		if ($this->modified) {
			$this->commit();
		}
	}

	public function getId() {
		if ($this->sessionId === null) {
			$this->getData();
		}
		return $this->sessionId;
	}

	public function getData($close = true) {
		if ($this->data === null) {
			$this->start();
			$this->data = $_SESSION;
			if ($close) {
				$this->closeSession();
			}
		}
		return $this->data;
	}

	public function commit() {
		$this->start();
		foreach ($this->data as $k => $v) {
			$_SESSION[$k] = $v;
		}
		foreach ($_SESSION as $k => $v) {
			if (!array_key_exists($k, $this->data)) {
				unset($_SESSION[$k]);
			}
		}
		$this->modified = false;
		$this->closeSession();
	}

	private function start() {
		if (!$this->started) {
			if ($this->sessionId === null) {
				session_start();
				$this->sessionId = session_id();
			} else {
				session_id($this->sessionId);
				session_start();
			}
			$this->started = true;
		}
	}

	private function closeSession() {
		if ($this->started) {
			session_write_close();
			$this->started = false;
		}
	}

	public function destroySession() {
		$this->start();
		session_destroy();
		$this->closeSession();
	}

	/**
	 * Save some data in the session.
	 * 
	 * @param string $key
	 * @param string $data
	 * @return SessionManager 
	 */
	public function put($key, $data) {
		if (!isset($this->data[$key]) || is_object($data) || $this->data[$key] !== $data) {
			$this->data[$key] = $data;
			$this->modified = true;
		}
		return $this;
	}

	/**
	 * Unset a key in the session data.
	 * 
	 * @param string $key
	 * @return SessionManager 
	 */
	public function clear($key) {
		$this->getData(false);
		if (array_key_exists($key, $this->data)) {
			unset($this->data[$key]);
			$this->modified = true;
		}
		return $this;
	}

	public function addListener($event, $fn) {
		$this->listeners[$event][] = $fn;
	}

	private function fireEvent($event, $args = null) {
		if (isset($this->listeners[$event])) {
			$args = array_slice(func_get_args(), 1);
			foreach ($this->listeners[$event] as $fn) {
				call_user_func_array($fn, $args);
			}
		}
	}

	public function open($savePath, $sessionName) {
		$this->savePath = $savePath;
		$this->sessionName = $sessionName;
		return true;
	}

	public function close() {
		// your code if any
		return true;
	}

	public function read($id) {
		$file = "$this->savePath/sess_$id";
		return (string) @file_get_contents($file);
	}

	public function write($id, $data) {
		$filename = "$this->savePath/sess_$id";
		$file = @fopen($filename, "w");
		if ($file) {
			$return = fwrite($file, $data);
			fclose($file);
			return $return;
		} else {
			return false;
		}
	}

	public function destroy($id) {
		$filename = "$this->savePath/sess_$id";
		$this->fireEvent('delete', $id);
		return(@unlink($filename));
	}

	public function gc($maxlifetime) {

		if ($maxlifetime < \UserSession::$SESSION_LENGTH) {
			Logger::get($this)->warn(
				'PHP max session lifetime is less than configured max user session length ('
				. "$maxlifetime < " . \UserSession::$SESSION_LENGTH
				. '). PHP configuration is ignored in favor of Eoze configuration.'
			);
			$maxlifetime = \UserSession::$SESSION_LENGTH;
		}

		foreach (glob("$this->savePath/sess_*") as $filename) {
			if (filemtime($filename) + $maxlifetime < time()) {
				@unlink($filename);
				preg_match('/sess_(?P<id>.+)$/', $filename, $matches);
				$this->fireEvent('delete', $matches['id']);
			}
		}
		return true;
	}

}
