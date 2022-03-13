<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\CoreManager;
use Devingo\Installer\Console\Actions\ServerManager;
use Devingo\Installer\Console\Actions\Transfers;
use Devingo\Installer\Console\Actions\Updater;
use Devingo\Installer\Console\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class UpdateCoreCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('update')
            ->setDescription('Update toolbox')
            ->addArgument('type', InputArgument::REQUIRED)
            ->addArgument('value', InputArgument::OPTIONAL);
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (!file_exists(getcwd() . DIRECTORY_SEPARATOR . 'dcore.json')) {
            $output->writeln('dcore.json file is not found!');
            $output->writeln('Please run dcore commands in the project directory.');

            return 0;
        }


        $formatterHelper = $this->getHelper('formatter');

        $updateTypes = [
            'core',
            'addon-slug'
        ];


        if ($input->getArgument('type') === 'core') {
            return $this->coreUpdater($input, $output);
        }

        $installedAddons = CoreManager::getAddonsSlug();
        if ($input->getArgument('type') !== 'core' && (empty($input->getArgument('type')) || !in_array($input->getArgument('type'), $installedAddons))) {
            $formattedBlock = $formatterHelper->formatBlock([
                'Please enter a valid installed addons slug!',
                '   Installed addons:',
                '   ' . implode(' ,', $installedAddons)
            ], 'error');
            $output->writeln($formattedBlock);

            return 1;
        }


        if ($input->getArgument('type') !== 'core') {
            return $this->addonUpdater($input, $output);
        }


        $formattedBlock = $formatterHelper->formatBlock([
            'Please enter a valid slug!',
            '   Valid slugs:',
            '   ' . implode(' ,', $updateTypes)
        ], 'error');
        $output->writeln($formattedBlock);

        return 0;
    }

    private function coreUpdater(InputInterface $input, OutputInterface $output): int
    {
        // Helpers
        $questionHelper = $this->getHelper('question');
        $formatterHelper = $this->getHelper('formatter');

        $filesQuestion = new ConfirmationQuestion('Do you want replace file? (Y/n) (default:n) : ', false);
        $holdQuestion = new ConfirmationQuestion('Do you want the files that were not replaced to be stored in the "_NeedUpdate" folder? (Y/n) (default:Y) : ', true);

        // Arguments
        $version = $input->getArgument('value') ?? '';


        $log = new Log();
        $updater = new Updater($log);

        $output->writeln('Downloading update package...');
        $updater->cloneByVersion($version); // empty to get latest version

        // Listing changed files
        $changeList = $updater->getChangesList($version);

        if (is_array($changeList) && !empty($changeList)) {
            $formattedBlock = $formatterHelper->formatSection('Listing Files', 'List of files who changed/added.');
            $output->writeln($formattedBlock);
            foreach ($changeList as $item) {
                $output->writeln($item);
            }
        } else {
            $output->writeln('Your dcore is already up to date!');

            return 0;
        }
        $output->writeln(PHP_EOL . 'Please wait ...' . PHP_EOL);
        sleep(2);

        $formattedBlock = $formatterHelper->formatSection('Let\'s update', 'Confirm files...');
        $output->writeln($formattedBlock);

        $notReplacedFiles = [];

        foreach ($changeList as $item) {
            $output->writeln(PHP_EOL . '  -.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-');
            $output->writeln('  File: ' . $item);

            if ($questionHelper->ask($input, $output, $filesQuestion)) {

                $updatePackageDir = $updater->updatePackageDir . DIRECTORY_SEPARATOR;
                $updater->updateCopy($updatePackageDir . $item);

                $formattedBlock = $formatterHelper->formatBlock([$item, 'This file has been replaced!'], 'info');

                // do copy file
            } else {
                $notReplacedFiles[] = $item;
                $formattedBlock = $formatterHelper->formatBlock([
                    $item,
                    'You have stopped replacing this file!'
                ], 'error');
            }
            $output->writeln($formattedBlock);
        }

        // Move not replaced files

        $output->writeln(PHP_EOL . 'Please wait ...' . PHP_EOL);
        sleep(1);

        if (!empty($notReplacedFiles) && $questionHelper->ask($input, $output, $holdQuestion)) {
            foreach ($notReplacedFiles as $item) {
                $updatePackageDir = $updater->updatePackageDir . DIRECTORY_SEPARATOR;
                $updater->updateHold($updatePackageDir . $item);
                $output->writeln('"' . $item . '"' . ' file moved to the _NeedUpdate folder.');
            }
        }

        // deleting removed files
        $removeList = $updater->getChangesList($version, 'removed');

        if (is_array($removeList) && !empty($removeList)) {
            $formattedBlock = $formatterHelper->formatSection('Deleting Files', 'List of files who removed.');
            $output->writeln($formattedBlock);
            foreach ($removeList as $item) {
                Transfers::remove([getcwd() . DIRECTORY_SEPARATOR . $item]);
                $output->writeln($item . ' removed!');
            }
        }

        $newVersion = CoreManager::getCoreVersion(getcwd() . DIRECTORY_SEPARATOR . '.dcore' . DIRECTORY_SEPARATOR . 'dcore' . DIRECTORY_SEPARATOR . 'dcore.json');
        CoreManager::setCoreVersion($newVersion);

        $output->writeln(PHP_EOL . 'Your template is updated successfully!');

        return 0;
    }

    private function addonUpdater(InputInterface $input, OutputInterface $output): int
    {
        $formatterHelper = $this->getHelper('formatter');
        $questionHelper = $this->getHelper('question');

        $version = $input->getArgument('value') ?? '';
        $slug = $input->getArgument('type');
        $license = CoreManager::getLicense();

        $currentVersion = CoreManager::getAddons();
        $currentVersion = $currentVersion[$slug];


        if (!empty($version) && version_compare($version, $currentVersion, '<=')) {
            $formattedBlock = $formatterHelper->formatBlock([
                'You can\'t downgrade addons!',
            ], 'error');
            $output->writeln($formattedBlock);

            return 0;
        }


        if (empty($license)) {
            $formattedBlock = $formatterHelper->formatBlock([
                'Please install "license" addon from https://license.devingo.net before update any things!',
            ], 'error');
            $output->writeln($formattedBlock);

            return 0;
        }


        $output->writeln('Getting the addon versions information...');

        $serverManager = new ServerManager($slug, $version, $license);

        $addonVersions = $serverManager->getAddonVersions();

        if ($addonVersions['status'] === false) {
            $formattedBlock = $formatterHelper->formatBlock([
                $addonVersions['data'],
            ], 'error');
            $output->writeln($formattedBlock);

            return 0;
        }

        if (!is_array($addonVersions['data']) || empty($addonVersions['data'])) {
            $formattedBlock = $formatterHelper->formatBlock([
                'There is not any update for this addon!',
            ], 'error');
            $output->writeln($formattedBlock);

            return 0;
        }

        $versions = $addonVersions['data'];


        if (count($versions) === 1 && $versions[0]['version'] === $currentVersion) {
            $formattedBlock = $formatterHelper->formatBlock([
                'Your installed version is already updated!',
            ], 'info');
            $output->writeln($formattedBlock);

            return 0;
        }

        $canUpdate = false;
        foreach ($versions as $index => $update){
            if ($version === $update['version']){
                $canUpdate = true;
                break;
            }
            if ($index === array_key_last($versions)){
                $version = $update['version'];
                $canUpdate = true;
            }
        }





        if (version_compare($version, $currentVersion, '<=')) {
            $formattedBlock = $formatterHelper->formatBlock([
                'Your installed version is already updated!',
            ], 'info');
            $output->writeln($formattedBlock);

            return 0;
        }

        if ($canUpdate) {
            $formattedBlock = $formatterHelper->formatBlock([
                sprintf('Updating %s addon to version %s!',$slug,$version),
            ], 'info');
            $output->writeln($formattedBlock);
            InstallCommand::addonInstaller($this, $input, $output, $slug . '@' . $version, $license, true);
        }else{
            $formattedBlock = $formatterHelper->formatBlock([
                'Required version not found!',
            ], 'error');
            $output->writeln($formattedBlock);
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
