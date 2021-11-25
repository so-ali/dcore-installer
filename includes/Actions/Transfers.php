<?php


namespace Devingo\Installer\Console\Actions;


use Symfony\Component\Filesystem\Filesystem;

class Transfers {
	/**
	 * copy files
	 *
	 * @param array $files
	 * @param bool  $replace
	 */
	public static function copy (array $files, bool $replace = false) {
		$fileSystem = new Filesystem();
		foreach ( $files as $from => $to ) {
			$fileSystem->copy($from, $to, $replace);
		}
	}

	/**
	 * remove files
	 *
	 * @param array $files
	 */
	public static function remove (array $files) {
		$fileSystem = new Filesystem();
		foreach ( $files as $file ) {
			$fileSystem->remove($file);
		}
	}

	/**
	 * rename files
	 *
	 * @param array $files
	 * @param bool  $replace
	 */
	public static function rename (array $files, bool $replace = false) {
		$fileSystem = new Filesystem();
		foreach ( $files as $file => $name ) {
			$fileSystem->rename($file, $name, $replace);
		}
	}

	public static function smartFileCopy (string $from, string $to, string $toMainDir = '') {
		if ( empty($toMainDir) ) {
			$toMainDir = getcwd();
		}

		$toDirectories = explode('/', str_replace(['/', '\\'], '/', str_replace($toMainDir, '', $to)));

		$filesystem = new Filesystem();

		if ( isset($toDirectories[count($toDirectories) - 1]) ) {
			unset($toDirectories[count($toDirectories) - 1]);
		}

		if ( !empty($toDirectories) ) {
			$folder = '';
			foreach ( $toDirectories as $directory ) {
				$folder .= $directory . DIRECTORY_SEPARATOR;
				if ( !$filesystem->exists($folder) ) {
					$filesystem->mkdir($folder);
				}
			}
		}
		self::copy([$from => $to], true);
	}
}