<?php

namespace eoko\modules\AccessControl;

use eoko\module\Module;
use eoko\module\HasJavascript;

use eoko\file\FileType;
use eoko\template\Template;

class AccessControl extends Module implements HasJavascript {
	
	protected $defaultExecutor = 'login';

	protected function prepareLoginTemplate(Template $tpl) {
		$tpl->help = false;
		$tpl->text = 'Bonjour, veuillez vous identifier pour accéder à la console d\'administration.';
	}
	
	public function getJavascriptAsString() {
		$path = $this->findPath('login', FileType::JS);
		$tpl = Template::create()->setFile($path);
		$this->prepareLoginTemplate($tpl);
		return $tpl->render(true);
	}
}