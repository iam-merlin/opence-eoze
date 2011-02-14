/**
 * Proxy of the <?php echo $modelName ?> Table.
 * @package <?php echo $package ?>
 * @subpackage models_base
 * @version <?php echo date('Y-m-d h:i:s') ?>
 */
class <?php echo $className ?>Proxy extends ModelTableProxy {

	private static $tableVars = array();

	public static $tableName = '<?php echo $className ?>';
	public static $modelName = '<?php echo $modelName ?>';
	public static $dbTableName = '<?php echo $tableName ?>';
	
	private static $instance = null;

	public static function get() {
		if (self::$instance === null) self::$instance = new <?php echo $className ?>Proxy;
		return self::$instance;
	}

	public static function getInstance() {
		$table = <?php echo $className ?>::getInstance();
		foreach (self::$tableVars as &$pointer) {
			$pointer = $table;
		}
		return $table;
	}

	/**
	 * @return ModelTableProxy
	 */
	public function attach(&$pointer) {
		self::$tableVars[] =& $pointer;
		return $pointer = $this;
	}

	public static function __callStatic($name, $arguments) {
		$instance = self::getInstance();
		return call_user_func_array(array($instance, $name), $arguments);
	}

	public function __call($name, $arguments) {
		$instance = self::getInstance();
		return call_user_func_array(array($instance, $name), $arguments);
	}

	public function __isset($name) {
		$instance = self::getInstance();
		return isset($instance->$name);
	}

	public function __get($name) {
		$instance = self::getInstance();
		return $instance->$name;
	}

	public function __set($name, $value) {
		$instance = self::getInstance();
		$instance->$name = $value;
	}

	public static function getTableName() {
		return '<?php echo $className ?>';
	}

	public static function getDBTableName() {
		return self::$dbTableName;
	}

	public static function getModelName() {
		return self::$modelName;
	}

	public function addAssocWhere(QueryWhere &$where) {
		return $this->getInstance()->addAssocWhere($where);
	}
}
