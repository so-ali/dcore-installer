<?php


namespace Devingo\Installer\Console\Actions;


use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class Transfers
{
    /**
     * copy files
     *
     * @param array $files
     * @param bool $replace
     */
    public static function copy(array $files, bool $replace = false)
    {
        $fileSystem = new Filesystem();
        foreach ($files as $from => $to) {
            $fileSystem->copy($from, $to, $replace);
        }
    }


    /**
     * remove files
     *
     * @param array $files
     */
    public static function remove(array $files)
    {
        $fileSystem = new Filesystem();
        foreach ($files as $file) {
            try {
                if (!file_exists($file)) {
                    continue;
                }
                if (file_exists($file)) {
                    $fileSystem->remove($file);
                }
                if (file_exists($file)) {
                    $removeProcess = new Process(['rm', '-rf', '.' . str_replace(getcwd(), '', $file)]);
                    $removeProcess->run();
                }
                if (file_exists($file)) {
                    if (is_dir($file)) {
                        @rmdir($file);
                    } else if (is_writable($file)) {
                        @unlink($file);
                    }
                }
            } catch (\Exception $exception) {
            }
        }
    }

    /**
     * rename files
     *
     * @param array $files
     * @param bool $replace
     */
    public static function rename(array $files, bool $replace = false)
    {
        $fileSystem = new Filesystem();
        foreach ($files as $file => $name) {
            $fileSystem->rename($file, $name, $replace);
        }
    }

    public static function smartFileCopy(string $from, string $to, string $toMainDir = '')
    {
        if (empty($toMainDir)) {
            $toMainDir = getcwd();
        }

        $toDirectories = explode('/', str_replace(['/', '\\'], '/', str_replace($toMainDir, '', $to)));

        $filesystem = new Filesystem();

        if (isset($toDirectories[count($toDirectories) - 1])) {
            unset($toDirectories[count($toDirectories) - 1]);
        }

        if (!empty($toDirectories)) {
            $folder = '';
            foreach ($toDirectories as $directory) {
                $folder .= $directory . DIRECTORY_SEPARATOR;
                if (!$filesystem->exists(getcwd() . $folder)) {
                    $filesystem->mkdir(getcwd() . $folder);
                }
            }
        }
        self::copy([$from => $to], true);
    }

    /**
     * @param string $dir
     *
     * @return array
     */
    public static function getDirectoryAllFiles(string $dir, array $excludes = []): array
    {
        if (!file_exists($dir)) {
            return [];
        }
        $scannedDirectory = array_diff(scandir($dir), array_merge(['..', '.'], $excludes));

        if (empty($scannedDirectory)) {
            return [];
        }

        $filesPath = [];

        foreach ($scannedDirectory as $item) {
            if (is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
                $filesPath = array_merge($filesPath, self::getDirectoryAllFiles($dir . DIRECTORY_SEPARATOR . $item));
            } else {
                $filesPath[] = $dir . DIRECTORY_SEPARATOR . $item;
            }
        }

        return $filesPath;
    }
}