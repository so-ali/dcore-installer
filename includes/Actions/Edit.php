<?php


namespace Devingo\Installer\Console\Actions;


use SplFileObject;
use Symfony\Component\Filesystem\Filesystem;

class Edit {
	/**
	 * replace string in file
	 *
	 * @param array|string $search
	 * @param array|string $replace
	 * @param array|string $files
	 */
	public static function replace ($search, $replace, $files) {
		if ( is_array($files) ) {
			foreach ( $files as $file ) {
				self::replace($search, $replace, $file);
			}

			return;
		}
		$filesystem = new Filesystem();
		if ( !$filesystem->exists($files) ) {
			return;
		}

		$fileContent = file_get_contents($files);
		$fileContent = str_replace($search, $replace, $fileContent);
		file_put_contents($files, $fileContent);
	}

	/**
	 * append string to file
	 *
	 * @param string       $string
	 * @param array|string $files
	 */
	public static function append (string $string, $files) {
		if ( is_array($files) ) {
			foreach ( $files as $file ) {
				self::append($string, $file);
			}

			return;
		}
		$filesystem = new Filesystem();
		if ( !$filesystem->exists($files) ) {
			return;
		}

		$fileContent = file_get_contents($files);
		$fileContent .= PHP_EOL . $string;
		file_put_contents($files, $fileContent);
	}

	/**
	 * prepend string to file
	 *
	 * @param string       $string
	 * @param array|string $files
	 */
	public static function prepend (string $string, $files) {
		if ( is_array($files) ) {
			foreach ( $files as $file ) {
				self::append($string, $file);
			}

			return;
		}
		$filesystem = new Filesystem();
		if ( !$filesystem->exists($files) ) {
			return;
		}

		$fileContent = file_get_contents($files);
		$fileContent = $string . PHP_EOL . $fileContent;
		file_put_contents($files, $fileContent);
	}

	/**
	 * add string to file by line
	 *
	 * @param string       $string
	 * @param int          $line
	 * @param array|string $files
	 */
	public static function addToLine (string $string, int $line, $files) {
		if ( is_array($files) ) {
			foreach ( $files as $file ) {
				self::addToLine($string,$line, $file);
			}

			return;
		}
		$filesystem = new Filesystem();
		if ( !$filesystem->exists($files) ) {
			return;
		}


		$file            = new SplFileObject($files);
		$fileLine        = 1;
		$fileNewContents = '';
		while ( !$file->eof() ) {
			if ( $fileLine === $line ) {
				$fileNewContents .= $string . PHP_EOL;
			}
			$fileNewContents .= $file->fgets();
			$fileLine++;
		}
		if ( 0 === $line ) {
			$fileNewContents .= PHP_EOL . $string;
		}
		file_put_contents($files, $fileNewContents);
	}

	/**
	 * remove line from file
	 *
	 * @param int          $line
	 * @param array|string $files
	 */
	public static function removeLine (int $line, $files) {
		if ( is_array($files) ) {
			foreach ( $files as $file ) {
				self::removeLine($line, $file);
			}

			return;
		}
		$filesystem = new Filesystem();
		if ( !$filesystem->exists($files) ) {
			return;
		}


		$file            = new SplFileObject($files);
		$fileLine        = 1;
		$fileNewContents = '';
		while ( !$file->eof() ) {
			if ( $fileLine === $line ) {
				$fileLine++;
				continue;
			}
			$fileNewContents .= $file->fgets();
			$fileLine++;
		}
		file_put_contents($files, $fileNewContents);
	}
}