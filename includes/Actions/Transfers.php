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
			$fileSystem->rename($file,$name,$replace);
		}
	}
}