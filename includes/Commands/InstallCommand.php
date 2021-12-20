<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\CoreManager;
use Devingo\Installer\Console\Actions\ServerManager;
use Devingo\Installer\Console\Actions\Transfers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Class InstallCommand
 *
 * @package Devingo\Installer\Console\Commands
 */
class InstallCommand extends Command {
	public function __destruct () {
		$removeProcess = new Process(['rm', '-rf', './.dcore']);
		$removeProcess->run();
	}

	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure () {
		$this->setName('install')
		     ->setDescription('Install new addons')
		     ->addArgument('slug', InputArgument::REQUIRED)
		     ->addArgument('license', InputArgument::OPTIONAL);
	}

	/**
	 * Execute the command.
	 *
	 * @param \Symfony\Component\Console\Input\InputInterface   $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute (InputInterface $input, OutputInterface $output) : int {
		return self::addonInstaller($this, $input, $output);
	}

	public static function addonInstaller ($selfObj, InputInterface $input, OutputInterface $output, $slug = '', $license = '', bool $update = false) : int {
		$slugInputParse = explode('@', !empty($slug) ? $slug : $input->getArgument('slug'));

		$addonSlug = $slugInputParse[0];
		$version   = $slugInputParse[1] ?? '';
		$license   = !empty($license) ? $license : $input->getArgument('license');

		if ( empty($license) ) {
			$license = CoreManager::getLicense();
		}

		// Helpers
		$questionHelper  = $selfObj->getHelper('question');
		$formatterHelper = $selfObj->getHelper('formatter');

		if ( empty($license) ) {
			$formattedBlock = $formatterHelper->formatBlock([
				'You are not allowed to install addons!'
			], 'error');

			$output->writeln($formattedBlock);

			return 0;
		}


		$cachePath = getcwd() . DIRECTORY_SEPARATOR . '.dcore';
		if ( !file_exists($cachePath) ) {
			mkdir($cachePath);
		}

		$addonsManifestPath = getcwd() . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'manifest.php';
		if ( !file_exists($addonsManifestPath) ) {
			$formattedBlock = $formatterHelper->formatBlock([
				'Your template is not support devingo addons!'
			], 'error');

			$output->writeln($formattedBlock);

			return 0;
		}

		$addonsManifest = require_once $addonsManifestPath;

		if ( !is_array($addonsManifest) ) {
			$output->writeln('Addons manifest not exists!');

			return 1;
		}

		if ( !$update && in_array($addonSlug, $addonsManifest) ) {
			$output->writeln('This addon is already installed!');

			return 1;
		}


		$serverManager = new ServerManager($addonSlug, $version, $license);
		$downloadUrl   = $serverManager->getAddonDownloadUrl();

		$output->writeln('Getting addon information...');

		if ( !$downloadUrl['status'] ) {
			$formattedBlock = $formatterHelper->formatBlock([
				$downloadUrl['data']
			], 'error');

			$output->writeln($formattedBlock);

			return 0;
		}


		$output->writeln('Downloading addon file...');
		$addonZipFile = $cachePath . DIRECTORY_SEPARATOR . $addonSlug . '.zip';

		if ( file_exists($addonZipFile) ) {
			unlink($addonZipFile);
		}

		$downloader = $serverManager->fileDownloader($downloadUrl['data'], $addonZipFile);

		if ( $downloader === false ) {
			$output->writeln('Sorry, we could not download the addon file!');

			return 0;
		} else {
			$output->writeln('The addon file is downloaded successfully!');
		}


		$filesystem = new Filesystem();

		$zip = new ZipArchive;
		if ( $zip->open($downloader) === true ) {
			$zip->extractTo($cachePath . DIRECTORY_SEPARATOR . $addonSlug);
			$zip->close();
			unlink($downloader);
			$filesystem->remove($downloader);
		} else {
			$output->writeln('Sorry, we could not extract the addon file!');

			return 0;
		}


		$addonDir = getcwd() . DIRECTORY_SEPARATOR . '.dcore' . DIRECTORY_SEPARATOR . $addonSlug;
		if ( !file_exists($addonDir) ) {
			$output->writeln('Addons folder not found!');

			return 1;
		}

		$addOnFilesList = Transfers::getDirectoryAllFiles($addonDir . DIRECTORY_SEPARATOR . 'data');
		if ( !empty($addOnFilesList) ) {

			$output->writeln(PHP_EOL . 'Checking files:');
			sleep(2);

			$filesQuestion = new ConfirmationQuestion('Do you want replace file? (Y/n) (default:Y) : ', true);

			$progressBar = new ProgressBar($output, count($addOnFilesList));
			$progressBar->start();
			foreach ( $addOnFilesList as $item ) {
				$itemPath   = str_replace($addonDir, '', $item);
				$targetPath = getcwd() . DIRECTORY_SEPARATOR . ltrim($itemPath, DIRECTORY_SEPARATOR . 'data');

				if ( file_exists($targetPath) ) {
					$output->writeln(PHP_EOL);
					$formattedBlock = $formatterHelper->formatBlock([
						$targetPath,
						'This file is exists in your project! Please replace it or skip installing addon!'
					], 'error');

					$output->writeln($formattedBlock);
					if ( !$questionHelper->ask($input, $output, $filesQuestion) ) {
						return 0;
					}
				}

				$progressBar->advance();
				usleep(500);
			}
			$progressBar->finish();

			$output->writeln(PHP_EOL . PHP_EOL . 'Transferring files:');
			sleep(2);

			$progressBar = new ProgressBar($output, count($addOnFilesList));
			$progressBar->start();

			foreach ( $addOnFilesList as $item ) {
				$itemPath   = str_replace($addonDir . DIRECTORY_SEPARATOR . 'data', '', $item);
				$targetPath = getcwd() . $itemPath;
				Transfers::smartFileCopy($item, $targetPath);
				$progressBar->advance();
				usleep(500);
			}
			$progressBar->finish();
			if ( $update ) {
				$output->writeln(PHP_EOL . PHP_EOL . 'Updating addon:');
			} else {
				$output->writeln(PHP_EOL . PHP_EOL . 'Installing addon:');
			}
			sleep(2);
		}


		$addOnFilesList = Transfers::getDirectoryAllFiles($addonDir . DIRECTORY_SEPARATOR . 'script');
		if ( !empty($addOnFilesList) ) {

			$installTarget = getcwd() . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . $addonSlug;

			if ( !file_exists($installTarget) && !mkdir($installTarget) ) {
				$formattedBlock = $formatterHelper->formatBlock([
					'An error occurred while installing the addon!'
				], 'error');

				$output->writeln($formattedBlock);

				return 0;
			}

			$progressBar = new ProgressBar($output, count($addOnFilesList));
			$progressBar->start();

			foreach ( $addOnFilesList as $item ) {
				$itemPath   = str_replace($addonDir . DIRECTORY_SEPARATOR . 'script', '', $item);
				$targetPath = $installTarget . $itemPath;
				Transfers::smartFileCopy($item, $targetPath);
				$progressBar->advance();
				usleep(500);
			}
			$progressBar->finish();


			$addonsManifest[] = $addonSlug;
			file_put_contents($addonsManifestPath, '<?php' . PHP_EOL . 'return ' . var_export($addonsManifest, true) . ';');
		}


		$output->writeln(PHP_EOL . PHP_EOL);
		$formattedBlock = $formatterHelper->formatBlock([
			$update ? 'The addon is updated to ' . $version . '!' : 'The addon is installed successfully!'
		], 'info');
		$output->writeln($formattedBlock);


		$addonManifest = CoreManager::getAddonManifest($addonDir);

		CoreManager::setAddon($addonSlug, $addonManifest['version'] ?? '');

		if ( $update ) {
			return 0;
		}

		$output->writeln(PHP_EOL . 'Running composer dump-autoload ...');

		$composerProcess = new Process(['composer', 'dump-autoload']);
		try {
			$composerProcess->mustRun();
			$output->writeln($composerProcess->getOutput());
		} catch ( ProcessFailedException $exception ) {
			$output->writeln(PHP_EOL . 'Please run "composer dump-autoload" as manually!');
		}

		return 0;
	}
}
