<?php


namespace Devingo\Installer\Console\Actions;


use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Updater {
	const tempPath = '.dcore';
	public $tempFullPath     = '';
	public $updatePackageDir = '';

	public function __construct () {
		$this->tempFullPath     = getcwd() . DIRECTORY_SEPARATOR . self::tempPath;
		$this->updatePackageDir = $this->tempFullPath . DIRECTORY_SEPARATOR . 'update';
		if ( !file_exists($this->tempFullPath) ) {
			mkdir(self::tempPath);
		}
	}

	public function __destruct () {
		if ( file_exists($this->tempFullPath) ) {
			$removeProcess = new Process(['rm', '-rf', './' . self::tempPath . '/']);
			$removeProcess->run();
		}
	}

	public function cloneByVersion (string $version = '') {
		$processArgs = ['git', 'clone', 'git@github.com:devingo-net/dcore.git', 'update'];
		if ( !empty($version) ) {
			$processArgs[] = '--branch';
			$processArgs[] = $version;
			$processArgs[] = '--single-branch';
		}

		$process = new Process($processArgs);
		$process->setTimeout(30000);
		$process->setWorkingDirectory($this->tempFullPath);
		try {
			$process->mustRun();
			echo $process->getOutput();
		} catch ( ProcessFailedException $exception ) {
			echo $exception->getMessage();
		}
	}

	public function getVersionTags () {
		$process = new Process(['git', 'tag']);
		$process->setWorkingDirectory($this->updatePackageDir);
		try {
			$process->mustRun();
			$tagsList = explode("\n", $process->getOutput());
			if ( isset($tagsList[count($tagsList) - 1]) && empty($tagsList[count($tagsList) - 1]) ) {
				unset($tagsList[count($tagsList) - 1]);
			}
		} catch ( ProcessFailedException $exception ) {
			$tagsList = [];
			echo $exception->getMessage();
		}

		return $tagsList;

	}

	public function getChangesList (string $version = '', string $status = 'replace') {
		$currentVersion = VersionManager::getCoreVersion();
		$processArgs    = ['git', 'diff', 'tags/' . $currentVersion];
		if ( !empty($version) ) {
			$processArgs[] = 'tags/' . $version;
		}
		$processArgs[] = '--name-only';

		if($status === 'removed'){
			$processArgs[] = '--diff-filter=D';
		}else{
			$processArgs[] = '--diff-filter=dr';
		}

		$process = new Process($processArgs);
		$process->setWorkingDirectory($this->updatePackageDir);
		try {
			$process->mustRun();
			$changeList = explode("\n", $process->getOutput());

			$changeList = array_filter($changeList, function ($item) {
				return $item !== 'style.css' && !empty($item);
			});

		} catch ( ProcessFailedException $exception ) {
			$changeList = [];
			echo $exception->getMessage();
		}

		return $changeList;
	}

	public function updateCopy (string $file) {
		$to = str_replace(['/.dcore/update', '\\.dcore\\update'], '', $file);
		Transfers::smartFileCopy($file, $to);
	}

	public function updateHold (string $file) {
		$to = str_replace(['/.dcore/update', '\\.dcore\\update'], ['/_NeedUpdate', '\\_NeedUpdate'], $file);
		Transfers::smartFileCopy($file, $to);
	}
}