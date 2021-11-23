<?php


namespace Devingo\Installer\Console\Actions;


class Cache {

	public function getFileData ($file, $default_headers) {
		// We don't need to write to the file, so just open for reading.
		$fp = fopen($file, 'r');

		if ( $fp ) {
			// Pull only the first 8 KB of the file in.
			$file_data = fread($fp, 8 * 1024);

			// PHP will close file handle, but we are good citizens.
			fclose($fp);
		} else {
			$file_data = '';
		}

		// Make sure we catch CR-only line endings.
		$file_data = str_replace("\r", "\n", $file_data);

		/**
		 * Filters extra file headers by context.
		 *
		 * The dynamic portion of the hook name, `$context`, refers to
		 * the context where extra headers might be loaded.
		 *
		 * @param array $extra_context_headers Empty array by default.
		 *
		 * @since 2.9.0
		 *
		 */
		$extra_headers = [];
		if ( $extra_headers ) {
			$extra_headers = array_combine($extra_headers, $extra_headers); // Keys equal values.
			$all_headers   = array_merge($extra_headers, (array) $default_headers);
		} else {
			$all_headers = $default_headers;
		}

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match('/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_data, $match) && $match[1] ) {
				$all_headers[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
			} else {
				$all_headers[$field] = '';
			}
		}

		return $all_headers;
	}

	public function arrayToString ($str) : ?string {
		return var_export($str, true);
	}

	/**
	 * Get Directory Files Array
	 *
	 * @param string $dirPath
	 *
	 * @return array
	 */
	public static function getDirFiles (string $dirPath) : array {
		if ( !file_exists($dirPath) ) {
			return [];
		}

		return array_diff(scandir($dirPath), ['.', '..']);
	}

	public function cacheAllOptions ($dir) : int {
		$classesPath = $dir . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;

		if (!file_exists($classesPath)){
			echo "\e[39m    Please run devingo core commands in project directory.";
			return 1;
		}


		echo "\e[39m##Running cache:" . PHP_EOL;
		$file = fopen($classesPath . 'Configs.php', "w");
		echo "      \e[33m+Caching configs.json file\e[32m" . PHP_EOL . PHP_EOL;
		$configJson     = file_get_contents($dir . DIRECTORY_SEPARATOR . 'config.json');
		$configJson     = json_decode($configJson, true);
		$shortcodesPath = $dir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'shortcodes' . DIRECTORY_SEPARATOR;

		$shortcodesList = self::getDirFiles($shortcodesPath);
		$shortcodes     = [];

		if ( !empty($shortcodesList) ) {
			echo "      \e[33m-Caching shortcodes configs:\e[32m" . PHP_EOL;
			$i = 1;
			foreach ( $shortcodesList as $folder ) {
				echo "          \e[39m" . $i . ") Caching " . $folder . " shortcode\e[32m" . PHP_EOL;

				$folderPath = $shortcodesPath . $folder . DIRECTORY_SEPARATOR;

				if ( !file_exists($folderPath . 'index.php') ) {
					continue;
				}

				$shortcodes[$folder] = [
					'templates' => [
						'default' => [
							'dir' => str_replace($dir, '', $folderPath)
						]
					]
				];

				if ( file_exists($folderPath . 'style.css') ) {
					$shortcodes[$folder]['css-file'] = str_replace([$dir, '\\'], [
							'',
							'/'
						], $folderPath) . 'style.css';
				}
				if ( file_exists($folderPath . 'script.js') ) {
					$shortcodes[$folder]['js-file'] = str_replace([$dir, '\\'], [
							'',
							'/'
						], $folderPath) . 'script.js';
				}


				if ( file_exists($folderPath . 'manifest.json') ) {
					$shortcodes[$folder]['templates']['default']['manifest'] = json_decode(file_get_contents($folderPath . 'manifest.json'), true);
					$shortcodes[$folder]['templates']['default']['name']     = $shortcodes[$folder]['templates']['default']['manifest']['name'] ?? 'default';
					echo "          \e[94m  -manifest.json cached\e[32m" . PHP_EOL;
				} else if ( file_exists($folderPath . 'index.php') ) {
					$fileheader                                          = self::getFileData($folderPath . 'index.php', [
						'template' => 'Template Name'
					]);
					$shortcodes[$folder]['templates']['default']['name'] = !empty($fileheader['template']) ? $fileheader['template'] : 'default';
					echo "          \e[94m  -index.php cached\e[32m" . PHP_EOL;
				}


				if ( file_exists($folderPath . 'style.json') ) {
					$shortcodes[$folder]['style'] = json_decode(file_get_contents($folderPath . 'style.json'), true);
					echo "          \e[94m  -style.json cached\e[32m" . PHP_EOL;
				}


				$folderSubs = self::getDirFiles($folderPath);
				if ( !empty($folderSubs) ) {
					foreach ( $folderSubs as $sub ) {
						$subFolderPath = $folderPath . $sub . DIRECTORY_SEPARATOR;

						if ( !file_exists($subFolderPath . 'index.php') ) {
							continue;
						}
						$shortcodes[$folder]['templates'][$sub]        = [];
						$shortcodes[$folder]['templates'][$sub]['dir'] = str_replace($dir, '', $subFolderPath);

						if ( file_exists($subFolderPath . 'manifest.json') ) {
							$shortcodes[$folder]['templates'][$sub]['manifest'] = json_decode(file_get_contents($subFolderPath . 'manifest.json'), true);
							$shortcodes[$folder]['templates'][$sub]['name']     = $shortcodes[$folder]['templates'][$sub]['manifest']['name'] ?? $sub;
							echo "          \e[94m  -" . $sub . "/manifest.json cached\e[32m" . PHP_EOL;
						} else if ( file_exists($subFolderPath . 'index.php') ) {
							$fileheader                                     = self::getFileData($subFolderPath . 'index.php', [
								'template' => 'Template Name'
							]);
							$shortcodes[$folder]['templates'][$sub]['name'] = !empty($fileheader['template']) ? $fileheader['template'] : $sub;
							echo "          \e[94m  -" . $sub . "/index.php cached\e[32m" . PHP_EOL;
						}


					}
				}
				$i++;
			}
		}


		echo PHP_EOL . "      \e[33m-Caching widgets cards\e[32m" . PHP_EOL;
		$globalCardsPath = $dir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'globals' . DIRECTORY_SEPARATOR . 'cards' . DIRECTORY_SEPARATOR;
		$globalCardsList = self::getDirFiles($globalCardsPath);
		$globalCards     = [];

		if ( !empty($globalCardsList) ) {
			foreach ( $globalCardsList as $item ) {
				if ( !is_dir($globalCardsPath . $item) ) {
					continue;
				}

				$globalCards[$item] = [];

				$globalCardsSubs = self::getDirFiles($globalCardsPath . $item . DIRECTORY_SEPARATOR);
				foreach ( $globalCardsSubs as $sub ) {
					if ( is_dir($globalCardsPath . $item . DIRECTORY_SEPARATOR . $sub) ) {
						continue;
					}
					echo "          \e[94m  -" . $item . DIRECTORY_SEPARATOR . $sub . " cached\e[32m" . PHP_EOL;
					$fileheader               = self::getFileData($globalCardsPath . $item . DIRECTORY_SEPARATOR . $sub, ['template' => 'Template Name']);
					$globalCards[$item][$sub] = empty($fileheader['template']) ? $sub : $fileheader['template'];
				}

			}
		}


		$fontsPath = $dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'font' . DIRECTORY_SEPARATOR;
		$fontsDirs = self::getDirFiles($fontsPath);
		$fonts     = [];
		if ( !empty($fontsDirs) ) {
			echo PHP_EOL . "      \e[33m-Caching fonts\e[32m" . PHP_EOL;
			foreach ( $fontsDirs as $template_file ) {
				if ( !is_dir($fontsPath . $template_file) ) {
					continue;
				}
				if ( !file_exists($fontsPath . $template_file . DIRECTORY_SEPARATOR . 'style.css') ) {
					continue;
				}
				$fileHeaders = self::getFileData($fontsPath . $template_file . DIRECTORY_SEPARATOR . 'style.css', [
					'name'   => 'Font Name',
					'family' => 'Font Family',
				]);

				echo "          \e[94m  -" . $template_file . " font cached\e[32m" . PHP_EOL;

				$fonts[$fileHeaders['family'] ?? $template_file] = [
					'name'   => $fileHeaders['name'] ?? $template_file,
					'family' => $fileHeaders['family'] ?? $template_file,
					'url'    => '/assets/font/' . $template_file . '/style.css',
				];
			}
		}


		$content = '<?php
/**
 * Cached configs
 *
 * @category   Configs
 * @version    1.4.2
 * @since      1.4.2
 */

namespace DCore;

class Configs {
    static $configJSON = ' . self::arrayToString($configJson) . ';
    static $shortcodes = ' . self::arrayToString($shortcodes) . ';
    static $globalCards = ' . self::arrayToString($globalCards) . ';
    static $fonts = ' . self::arrayToString($fonts) . ';
}
';
		fwrite($file, $content);
		fclose($file);


		echo "\e[39m";
		return 0;
	}
}