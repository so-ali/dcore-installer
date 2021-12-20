<?php
namespace Devingo\Installer\Console;

/**
 * Class Log
 *
 * @package Devingo\Installer\Console
 */
class Log {
	public $logDir = '';
	public $logFile = '';

	public function __construct () {
		$this->logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
		$this->logFile = 'log_dcore_' . time() . '.txt';

		if(!file_exists($this->logDir)){
			mkdir($this->logDir);
		}
	}

	/**
	 * create new log file
	 *
	 * @param string $log
	 *
	 * @return string
	 */
	public function addLog (string $log) : string {
		$fileDir = $this->logDir . $this->logFile;

		if(file_exists($fileDir)){
			$log = file_get_contents($fileDir) . PHP_EOL . PHP_EOL . PHP_EOL . '---------------------------------------------------------' . PHP_EOL . PHP_EOL . PHP_EOL . $log;
		}

		$logfile = fopen($fileDir, "w") or die("Unable to open log file!");
		fwrite($logfile, $log);
		fclose($logfile);

		echo PHP_EOL . 'Errors have occurred. Please check the log file from the path below.' . PHP_EOL;
		echo realpath($fileDir) . PHP_EOL . PHP_EOL;

		return $fileDir;
	}
}