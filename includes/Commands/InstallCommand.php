<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\CoreManager;
use Devingo\Installer\Console\Actions\ServerManager;
use Devingo\Installer\Console\Actions\Transfers;
use Devingo\Installer\Console\Actions\Updater;
use Devingo\Installer\Console\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
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
class InstallCommand extends Command
{
    public function __destruct()
    {
        Transfers::remove([getcwd() . DIRECTORY_SEPARATOR . '.dcore']);
        $removeProcess = new Process(['rm', '-rf', './.dcore']);
        $removeProcess->run();
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('install')
            ->setDescription('Install new addons')
            ->addArgument('slug', InputArgument::REQUIRED)
            ->addArgument('license', InputArgument::OPTIONAL);
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::addonInstaller($this, $input, $output);
    }

    public static function addonInstaller($selfObj, InputInterface $input, OutputInterface $output, $slug = '', $license = '', bool $update = false): int
    {
        $slugInputParse = explode('@', !empty($slug) ? $slug : $input->getArgument('slug'));

        $addonSlug = $slugInputParse[0];
        $version = $slugInputParse[1] ?? '';
        $license = !empty($license) ? $license : $input->getArgument('license');

        if (empty($license)) {
            $license = CoreManager::getLicense();
        }

        // Helpers
        $questionHelper = $selfObj->getHelper('question');
        $formatterHelper = $selfObj->getHelper('formatter');

        if (empty($license)) {
            $formattedBlock = $formatterHelper->formatBlock([
                'You are not allowed to install addons!'
            ], 'error');

            $output->writeln($formattedBlock);

            return 0;
        }


        $cachePath = getcwd() . DIRECTORY_SEPARATOR . '.dcore';
        if (!file_exists($cachePath)) {
            mkdir($cachePath);
        }

        $addonsManifestPath = getcwd() . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . 'manifest.php';
        if (!file_exists($addonsManifestPath)) {
            $formattedBlock = $formatterHelper->formatBlock([
                'Your template is not support devingo addons!'
            ], 'error');

            $output->writeln($formattedBlock);

            return 0;
        }

        $addonsManifest = eval(str_replace('<?php', '', file_get_contents($addonsManifestPath)));

        if (!is_array($addonsManifest)) {
            $output->writeln('Addons manifest is broken!');

            return 1;
        }

        if (!$update && in_array($addonSlug, $addonsManifest)) {
            $output->writeln('This addon is already installed!');

            return 1;
        }


        $serverManager = new ServerManager($addonSlug, $version, $license);
        $downloadUrl = $serverManager->getAddonDownloadUrl();

        $output->writeln('Getting addon information...');

        if (!$downloadUrl['status']) {
            $formattedBlock = $formatterHelper->formatBlock([
                $downloadUrl['data']
            ], 'error');

            $output->writeln($formattedBlock);

            return 0;
        }


        $output->writeln('Downloading addon file...');
        $addonZipFile = $cachePath . DIRECTORY_SEPARATOR . $addonSlug . '.zip';

        if (file_exists($addonZipFile)) {
            unlink($addonZipFile);
        }

        $downloader = $serverManager->fileDownloader($downloadUrl['data'], $addonZipFile);

        if ($downloader === false) {
            $output->writeln('Sorry, we could not download the addon file!');

            return 0;
        } else {
            $output->writeln('The addon file is downloaded successfully!');
        }


        $filesystem = new Filesystem();

        $zip = new ZipArchive;
        if ($zip->open($downloader) === true) {
            $zip->extractTo($cachePath . DIRECTORY_SEPARATOR . $addonSlug);
            $zip->close();
            unlink($downloader);
            $filesystem->remove($downloader);
        } else {
            $output->writeln('Sorry, we could not extract the addon file!');

            return 0;
        }


        $addonDir = getcwd() . DIRECTORY_SEPARATOR . '.dcore' . DIRECTORY_SEPARATOR . $addonSlug;
        if (!file_exists($addonDir)) {
            $output->writeln('Addon cache folder not found!');

            return 1;
        }


        $addonManifest = CoreManager::getAddonManifest($addonDir);

        $output->writeln('');
        if (isset($addonManifest['name'])) {
            $output->writeln(sprintf('<fg=yellow>Addon Name:</> <fg=green>%s</>', $addonManifest['name']));
        }
        if (isset($addonManifest['author'])) {
            $output->writeln(sprintf('<fg=yellow>Author:</> <fg=green>%s</>', $addonManifest['author']));
        }
        if (isset($addonManifest['version'])) {
            $output->writeln(sprintf('<fg=yellow>Version:</> <fg=green>%s</>', $addonManifest['version']));
        }
        $output->writeln('');


        $getInstalledAddons = CoreManager::getAddons();
        $getRequiresAddons = $addonManifest['requires'] ?? [];

        $requiredAddons = [];
        if (!empty($getRequiresAddons)) {
            foreach ($getRequiresAddons as $slug => $addOnversion) {
                if ($slug === 'dcore') {
                    if (!empty($addOnversion) && $addOnversion !== CoreManager::getCoreVersion()) {
                        $output->writeln(PHP_EOL . 'This addon requires another version of dcore, please update/downgrade dcore to version ' . $addOnversion);
                        return 0;
                    }
                    continue;
                }
                if (isset($getInstalledAddons[$slug]) && ($getInstalledAddons[$slug] === $addOnversion || empty($addOnversion))) {
                    continue;
                }

                $requiredAddons[$slug] = [
                    'version' => empty($addOnversion) ? '' : $addOnversion,
                    'update' => isset($getInstalledAddons[$slug])
                ];
            }
        }

        if (!empty($requiredAddons)) {
            $output->writeln(PHP_EOL . '<fg=yellow>The addon requires some another addons:</>');
            foreach ($requiredAddons as $slug => $require) {
                $output->writeln('  <fg=red>+ ' . $slug . '@' . $require['version'] . '</>');
            }
            $output->writeln(PHP_EOL);


            foreach ($requiredAddons as $slug => $require) {
                $filesQuestion = new ConfirmationQuestion(sprintf(
                    'Before installing version %s of %s addon, you need to upgrade your %s addon to version %s.' . PHP_EOL . 'Do you want to perform this operation? (Y/n) (default:Y) : ',
                    $version,
                    $addonSlug,
                    $slug,
                    $require['version']
                ), true);
                if (!$questionHelper->ask($input, $output, $filesQuestion)) {
                    return 0;
                }
                $output->writeln('     <fg=green> >>>Installing ' . $slug . ' version ' . $require['version'] . ':</>');
                self::addonInstaller($selfObj, $input, $output, $slug . '@' . $require['version'], $license, $require['update']);
                $output->writeln('');
            }
            $output->writeln('');
        }


        $log = new Log();
        $updater = new Updater($log);

        $addOnFilesList = Transfers::getDirectoryAllFiles($addonDir . DIRECTORY_SEPARATOR . 'data');
        if (!empty($addOnFilesList)) {

            $output->writeln(PHP_EOL . 'Checking files:');
            sleep(2);

            $filesQuestion = new ConfirmationQuestion('Do you want replace file? (Y/n) (default:Y) : ', true);

            $progressBar = new ProgressBar($output, count($addOnFilesList));
            $progressBar->start();
            foreach ($addOnFilesList as $item) {
                $itemPath = str_replace($addonDir, '', $item);
                $targetPath = getcwd() . DIRECTORY_SEPARATOR . ltrim($itemPath, DIRECTORY_SEPARATOR . 'data');

                if (!$update && file_exists($targetPath) && sha1_file($targetPath) !== sha1_file($item)) {
                    $output->writeln(PHP_EOL);
                    $formattedBlock = $formatterHelper->formatBlock([
                        $targetPath,
                        'This file is exists in your project! Please replace it or skip installing addon!'
                    ], 'error');

                    $output->writeln($formattedBlock);
                    if (!$questionHelper->ask($input, $output, $filesQuestion)) {
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

            $replaceAll = false;
            foreach ($addOnFilesList as $item) {
                $itemPath = str_replace($addonDir . DIRECTORY_SEPARATOR . 'data', '', $item);
                $targetPath = getcwd() . $itemPath;

                $updateFormattedBlock = $formatterHelper->formatBlock([
                    $targetPath, '    ',
                    'This file is exists in your project! Please replace it or skip.',
                    'If you do not replace this file, it will be moved to the _NeedUpdate folder.'
                ], 'error');
                $filesQuestion = new Question('Do you want replace the file? (Y/n/a) (a= Replace All) (default:Y) : ', 'Y');
                if ($update && file_exists($targetPath) && sha1_file($targetPath) !== sha1_file($item)) {
                    $questionValue = 'Y';
                    if (!$replaceAll) {
                        $output->writeln(PHP_EOL);
                        $output->writeln($updateFormattedBlock);
                        $questionValue = $questionHelper->ask($input, $output, $filesQuestion);
                    }
                    if ($questionValue === 'a') {
                        $replaceAll = true;
                    }
                    if ($replaceAll || $questionValue === 'Y') {
                        Transfers::smartFileCopy($item, $targetPath);
                    } else {
                        $updater->updateHold($item, '/.dcore/' . $addonSlug, '/_NeedUpdate/' . $addonSlug);
                    }
                } else {
                    Transfers::smartFileCopy($item, $targetPath);
                }

                $progressBar->advance();
                usleep(500);
            }
            $progressBar->finish();
            if ($update) {
                $output->writeln(PHP_EOL . PHP_EOL . 'Updating addon:');
            } else {
                $output->writeln(PHP_EOL . PHP_EOL . 'Installing addon:');
            }
            sleep(2);
        }


        $addOnFilesList = Transfers::getDirectoryAllFiles($addonDir . DIRECTORY_SEPARATOR . 'script');
        if (!empty($addOnFilesList)) {

            $installTarget = getcwd() . DIRECTORY_SEPARATOR . 'addons' . DIRECTORY_SEPARATOR . $addonSlug;

            if (!file_exists($installTarget) && !mkdir($installTarget)) {
                $formattedBlock = $formatterHelper->formatBlock([
                    'An error occurred while installing the addon!'
                ], 'error');

                $output->writeln($formattedBlock);

                return 0;
            }

            $progressBar = new ProgressBar($output, count($addOnFilesList));
            $progressBar->start();

            foreach ($addOnFilesList as $item) {
                $itemPath = str_replace($addonDir . DIRECTORY_SEPARATOR . 'script', '', $item);
                $targetPath = $installTarget . $itemPath;
                Transfers::smartFileCopy($item, $targetPath);
                $progressBar->advance();
                usleep(500);
            }
            $progressBar->finish();

            if (!in_array($addonSlug, $addonsManifest)) {
                $addonsManifest[] = $addonSlug;
            }
            file_put_contents($addonsManifestPath, '<?php' . PHP_EOL . 'return ' . var_export($addonsManifest, true) . ';');
        }

        $addOnDeletedFiles = $addonManifest['removed'] ?? [];
        if (is_array($addOnDeletedFiles) && !empty($addOnDeletedFiles)) {
            $filesQuestion = new ConfirmationQuestion('Do you want to delete it in your project as well? (Y/n) (default:Y) : ', true);

            $output->writeln(PHP_EOL . PHP_EOL . 'Removing extra files:');
            sleep(2);

            $progressBar = new ProgressBar($output, count($addOnDeletedFiles));
            $progressBar->start();

            foreach ($addOnDeletedFiles as $deletedFile) {
                $fileRealPath = getcwd() . DIRECTORY_SEPARATOR . $deletedFile;
                if (file_exists($fileRealPath)) {
                    $output->writeln(PHP_EOL);
                    $formattedBlock = $formatterHelper->formatBlock([
                        $deletedFile,
                        'This file has been removed in the installation version!'
                    ], 'error');

                    $output->writeln($formattedBlock);
                    $progressBar->advance();
                    $output->writeln(PHP_EOL);
                    if (!$questionHelper->ask($input, $output, $filesQuestion)) {
                        continue;
                    }

                    // Remove file
                    Transfers::remove([$fileRealPath]);

                    usleep(500);
                }
            }
            $progressBar->finish();
        }


        $addOnMovedFiles = $addonManifest['moved'] ?? [];
        if (is_array($addOnMovedFiles) && !empty($addOnMovedFiles)) {

            $output->writeln(PHP_EOL . PHP_EOL . 'Moving some files:');
            sleep(2);

            $progressBar = new ProgressBar($output, count($addOnMovedFiles));
            $progressBar->start();

            foreach ($addOnMovedFiles as $oldPath => $newPath) {
                $oldRealPath = getcwd() . DIRECTORY_SEPARATOR . $oldPath;
                $newRealPath = getcwd() . DIRECTORY_SEPARATOR . $newPath;
                if (file_exists($oldRealPath)){
                    Transfers::copy([$oldRealPath=>$newRealPath],true);
                    Transfers::remove([$oldRealPath]);
                }
                $progressBar->advance();
                usleep(500);
            }
            $progressBar->finish();
        }

        $output->writeln(PHP_EOL . PHP_EOL);
        $formattedBlock = $formatterHelper->formatBlock([
            $update ? 'The ' . $addonSlug . ' addon is updated to ' . $version . '!' : 'The ' . $addonSlug . ' addon is installed successfully!'
        ], 'info');
        $output->writeln($formattedBlock);

        CoreManager::setAddon($addonSlug, $addonManifest['version'] ?? '');

        if ($update) {
            return 0;
        }

        $output->writeln(PHP_EOL . 'Running composer dump-autoload ...');

        $composerProcess = new Process(['composer', 'dump-autoload']);
        try {
            $composerProcess->mustRun();
            $output->writeln($composerProcess->getOutput());
        } catch (ProcessFailedException $exception) {
            $output->writeln(PHP_EOL . 'Please run "composer dump-autoload" as manually!');
        }



        $output->writeln(PHP_EOL . 'Running dcore cache ...');

        $composerProcess = new Process(['dcore', 'cache']);
        try {
            $composerProcess->mustRun();
            $output->writeln($composerProcess->getOutput());
        } catch ( ProcessFailedException $exception ) {
            $output->writeln(PHP_EOL . 'Please run "dcore cache" as manually!');
        }

        return 0;
    }
}
