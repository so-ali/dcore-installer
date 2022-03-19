<?php


namespace Devingo\Installer\Console\Actions;


class CoreManager {
	public static function setDCoreFileContent (array $configs, string $dcorePath = '') {
		if ( empty($dcorePath) ) {
			$dcorePath = getcwd() . DIRECTORY_SEPARATOR . 'dcore.json';
		}
		if ( !file_exists($dcorePath) ) {
			echo 'dcore.json file is not found!';
			die();
		}
		file_put_contents($dcorePath, json_encode($configs));
	}

	public static function getDCoreFileContent (string $dcorePath = '') {
		if ( empty($dcorePath) ) {
			$dcorePath = getcwd() . DIRECTORY_SEPARATOR . 'dcore.json';
		}
		if ( !file_exists($dcorePath) ) {
			echo 'dcore.json file is not found!';
			die();
		}
		$file = file_get_contents($dcorePath);
		$file = json_decode($file, true);
		if ( $file === null ) {
			echo 'dcore.json file is not valid!';
			die();
		}

		return $file;
	}


	public static function setCoreVersion (string $version, string $dcorePath = '') : void {
		$configs = self::getDCoreFileContent($dcorePath);

		$configs['version'] = $version;

		self::setDCoreFileContent($configs, $dcorePath);
	}

    public static function getCoreVersion (string $dcorePath = '') {
        $dcoreConfigs = self::getDCoreFileContent($dcorePath);

        return $dcoreConfigs['version'] ?? 'unknown';
    }

    public static function setCoreVersionTag (string $version, string $dcorePath = '') : void {
        $configs = self::getDCoreFileContent($dcorePath);

        $configs['version-tag'] = $version;

        self::setDCoreFileContent($configs, $dcorePath);
    }

    public static function getCoreVersionTag (string $dcorePath = '') {
        $dcoreConfigs = self::getDCoreFileContent($dcorePath);

        return $dcoreConfigs['version-tag'] ?? 'unknown';
    }

	public static function getAddons (string $dcorePath = '') : array {
		$dcoreConfigs = self::getDCoreFileContent($dcorePath);

		return $dcoreConfigs['addons'] ?? [];
	}

	public static function getAddonsSlug (string $dcorePath = '') : array {
		$addons = self::getAddons($dcorePath);

		if ( empty($addons) ) {
			return [];
		}

		return array_keys($addons);
	}

	public static function getAddonManifest (string $addonPath) : array {
		if ( !file_exists($addonPath . DIRECTORY_SEPARATOR . 'installer.json') ) {
			return [];
		}
		$manifest = file_get_contents($addonPath . DIRECTORY_SEPARATOR . 'installer.json');
		$manifest = json_decode($manifest, true);
		if ( empty($manifest) || $manifest === false || !is_array($manifest) ) {
			return [];
		}

		return $manifest;
	}

	public static function setAddon (string $slug, string $version, string $dcorePath = '') : void {
		$configs = self::getDCoreFileContent($dcorePath);

		$configs['addons'] = $configs['addons'] ?? [];

		$configs['addons'][$slug] = $version;

		self::setDCoreFileContent($configs, $dcorePath);
	}

	public static function getLicense () {
		$license = '';
		if ( file_exists(getcwd() . DIRECTORY_SEPARATOR . 'License.txt') ) {
			$license = sha1_file(getcwd() . DIRECTORY_SEPARATOR . 'License.txt');
		}

		return $license;
	}
}