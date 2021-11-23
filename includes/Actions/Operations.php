<?php


namespace Devingo\Installer\Console\Actions;


class Operations {
	private $file;

	public function __construct ($file) {
		if ( !file_exists($file) ) {
			die($file . ' is not found!');
		}
		$this->file = $file;
	}

	public function requireFile (string $toRequire, int $line = 0) {
		$pattern     = '/' . preg_quote($toRequire, '/') . '/i';
		$fileContent = file_get_contents($this->file);
		if ( preg_match($pattern, $fileContent, $matches) === 1 ) {
			echo PHP_EOL . '"' . $toRequire . '" already included in "' . realpath($this->file) . '" file!' . PHP_EOL;

			return;
		}
		$codeString = 'require_once __DIR__ . "/' . $toRequire . '";';
		Edit::addToLine($codeString, $line, [$this->file]);
		echo PHP_EOL . '"' . $toRequire . '" included to ' . realpath($this->file) . ' file!' . PHP_EOL;
	}

	/**
	 * @param array|string $changes
	 */
	public function styleCSSChange ($changes,string $value = '') {

		if(is_array($changes)){
			foreach ($changes as $key => $val){
				$this->styleCSSChange($key,$val);
			}
			return;
		}



		$fileContent = file_get_contents($this->file);
		if ( strpos($fileContent,$changes) === false) {
			echo PHP_EOL . '"' . $changes . '" not exists in "' . realpath($this->file) . '" file!' . PHP_EOL;
			return;
		}
		$fileNewContent = '';

		$fileSplit = explode($changes.':',$fileContent);
		if(count($fileSplit) === 2){
			$fileNewContent = $fileSplit[0] . $changes . ':';
			$fileSplit2 = explode(PHP_EOL , $fileSplit[1]);
			unset($fileSplit2[0]);
			$fileNewContent .= ' ' . $value . PHP_EOL;
			$fileNewContent .= implode(PHP_EOL,$fileSplit2);
		}

		file_put_contents($this->file,$fileNewContent);
		echo PHP_EOL . '"' . $changes . '" values changed in ' . realpath($this->file) . ' file!' . PHP_EOL;
	}
}