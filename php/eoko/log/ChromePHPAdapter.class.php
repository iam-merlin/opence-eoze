<?php

namespace eoko\log;

require_once __DIR__ . '/ChromePHP.php';

use LogEntry;
use ChromePHP;

/**
 *
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @author Éric Ortega <eric@planysphere.fr>
 * @since 8 nov. 2011
 */
class ChromePHPAdapter {
	
	public function __construct() {
		// TODO this should not be hardcoded for my computer......
		$homeTmpDir = `echo ~/tmp`;
		if (is_dir($homeTmpDir)) {
			$tmpDir = $homeTmpDir . '/chromelogs';
		} else {
			$tmpDir = '/tmp/chromelogs';
		}
		
		if (!is_dir($tmpDir)) {
			mkdir($tmpDir);
		}
		
		$ln = ROOT . '/chromelogs';
		if (!file_exists($ln)) {
			symlink($tmpDir, $ln);
		}
		
		ChromePhp::useFile($tmpDir, 'chromelogs');
	}
	
	public function process(LogEntry $entry) {
		switch ($entry->level) {
			case Logger::INFO:
				ChromePHP::info("{$entry->getLevelName()} {$entry->fileLine}\n{$entry->msg}");
				break;
			case Logger::WARNING:
				ChromePHP::warn("{$entry->getLevelName()} {$entry->fileLine}\n{$entry->msg}");
				break;
			case Logger::ASSERTION:
			case Logger::ERROR:
				ChromePHP::error("{$entry->getLevelName()} {$entry->fileLine}\n{$entry->msg}");
				break;
			default:
				ChromePHP::log("{$entry->getLevelName()} {$entry->fileLine}\n{$entry->msg}");
				break;
		}
	}
}
