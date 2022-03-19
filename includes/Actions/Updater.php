<?php


namespace Devingo\Installer\Console\Actions;

use Devingo\Installer\Console\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class Updater
{
    const tempPath = '.dcore';
    public $tempFullPath = '';
    public $updatePackageDir = '';
    public $log;

    /**
     * Updater constructor.
     *
     * @param Log $log
     */
    public function __construct(Log $log)
    {
        $this->log = $log;
        $this->tempFullPath = getcwd() . DIRECTORY_SEPARATOR . self::tempPath;
        $this->updatePackageDir = $this->tempFullPath . DIRECTORY_SEPARATOR . 'update';
        if (!file_exists($this->tempFullPath)) {
            mkdir(self::tempPath);
        }
    }

    public function __destruct()
    {
        Transfers::remove([$this->tempFullPath]);
    }

    public function cloneByVersion(string $version = '')
    {
        if ($version === 'nogit') {
            $version = '';
        }

        $processArgs = ['git', 'clone', 'https://github.com/devingo-net/dcore.git', 'update'];
        if (!empty($version)) {
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
        } catch (ProcessFailedException $exception) {
            echo PHP_EOL . 'An error occurred while cloning. Please make sure the entered version is correct!' . PHP_EOL;
            $this->log->addLog('Error on cloning the project (Code: 101)' . PHP_EOL . $exception->getMessage());
            die();
        }
    }

    public function getNoGitChangesList(): array
    {
        $updateDirScan = Transfers::getDirectoryAllFiles($this->updatePackageDir,['.git','vendor']);
        if (empty($updateDirScan)){
            return [];
        }
        $changeList = [];
        foreach ($updateDirScan as $item){
            $inProject = str_replace($this->updatePackageDir,getcwd(),$item);
            if (!file_exists($inProject) || (file_exists($inProject) && sha1_file($item) !== sha1_file($inProject))){
                $changeList[] = str_replace($this->updatePackageDir . DIRECTORY_SEPARATOR,'',$item);
            }
        }

        return $changeList;
    }

    public function getChangesList(string $version = '', string $status = 'replace')
    {
        if ($version === 'nogit') {
            if ($status === 'removed') {
                return [];
            }

            return $this->getNoGitChangesList();
        }

        $currentVersion = CoreManager::getCoreVersionTag();
        $processArgs = ['git', 'diff', 'tags/' . $currentVersion];
        if (!empty($version)) {
            $processArgs[] = 'tags/' . $version;
        }
        $processArgs[] = '--name-only';

        if ($status === 'removed') {
            $processArgs[] = '--diff-filter=D';
        } else {
            $processArgs[] = '--diff-filter=dr';
        }

        $process = new Process($processArgs);
        $process->setWorkingDirectory($this->updatePackageDir);
        try {
            $process->mustRun();
            $changeList = explode("\n", $process->getOutput());

            $changeList = array_filter($changeList, function ($item) {
                return $item !== 'style.css' && !empty($item) && $item !== 'dcore.json';
            });

        } catch (ProcessFailedException $exception) {
            $changeList = [];
            echo PHP_EOL . 'Error when getting change list!' . PHP_EOL;
            $this->log->addLog('Error when getting change list (Code: 102)' . PHP_EOL . PHP_EOL . $exception->getMessage());
        }

        return $changeList;
    }

    public function updateCopy(string $file)
    {
        $to = str_replace(['/.dcore/update', '\\.dcore\\update'], '', $file);
        Transfers::smartFileCopy($file, $to);
    }

    public function updateHold(string $file, string $search = '/.dcore/update', string $replace = '/_NeedUpdate')
    {
        $to = str_replace([
            $search,
            str_replace('/', '\\', $search)
        ], [
            $replace,
            str_replace('/', '\\', $replace)
        ], $file);

        Transfers::smartFileCopy($file, $to);
    }

}