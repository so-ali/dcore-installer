<?php


namespace Devingo\Installer\Console\Actions;


class VersionManager {
	public static function getDCoreFileContent (string $dcorePath = '') {
		if (empty($dcorePath)) {
			$dcorePath = getcwd() . DIRECTORY_SEPARATOR . 'dcore.json';
		}
		if ( !file_exists($dcorePath) ) {
			echo 'dcore.json file is not found!';
			die();
			return [];
		}
		$file = file_get_contents($dcorePath);
		$file = json_decode($file, true);
		if($file === null){
			echo 'dcore.json file is not valid!';
			die();
		}
		return $file;
	}

	public static function getCoreVersion (string $dcorePath = '') {
		$dcoreConfigs = self::getDCoreFileContent($dcorePath);

		return $dcoreConfigs['version'] ?? 'unknown';
	}
}