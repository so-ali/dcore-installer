<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\Transfers;
use Devingo\Installer\Console\Actions\Updater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpdateCoreCommand extends Command {
	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure () {
		$this->setName('update')
		     ->setDescription('Update the devingo core')
		     ->addArgument('version', InputArgument::OPTIONAL);
	}

	/**
	 * Execute the command.
	 *
	 * @param \Symfony\Component\Console\Input\InputInterface   $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute (InputInterface $input, OutputInterface $output) {
		if ( !file_exists(getcwd() . DIRECTORY_SEPARATOR . 'dcore.json') ) {
			$output->writeln('dcore.json file is not found!');
			$output->writeln('Please run dcore commands in the project directory.');
			return 0;
		}


		// Helpers
		$questionHelper  = $this->getHelper('question');
		$formatterHelper = $this->getHelper('formatter');

		$filesQuestion = new ConfirmationQuestion('Do you want replace file? (Y/n) (default:n) : ', false);
		$holdQuestion  = new ConfirmationQuestion('Do you want the files that were not replaced to be stored in the "_NeedUpdate" folder? (Y/n) (default:Y) : ', true);

		// Arguments
		$version = $input->getArgument('version') ?? '';

		// Flight
		$updater = new Updater();

		$output->writeln('Downloading update package...');
		$updater->cloneByVersion($version); // empty to get latest version

		// Listing changed files
		$changeList = $updater->getChangesList($version);

		if ( is_array($changeList) && !empty($changeList) ) {
			$formattedBlock = $formatterHelper->formatSection('Listing Files', 'List of files who changed/added.');
			$output->writeln($formattedBlock);
			foreach ( $changeList as $item ) {
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

		foreach ( $changeList as $item ) {
			$output->writeln(PHP_EOL . '  -.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-.-');
			$output->writeln('  File: ' . $item);

			if ( $questionHelper->ask($input, $output, $filesQuestion) ) {

				$updatePackageDir = $updater->updatePackageDir . DIRECTORY_SEPARATOR;
				$updater->updateCopy($updatePackageDir . $item);

				$formattedBlock = $formatterHelper->formatBlock([$item, 'This file has been replaced!'], 'info');

				// do copy file
			} else {
				$notReplacedFiles[] = $item;
				$formattedBlock     = $formatterHelper->formatBlock([
					$item,
					'You have stopped replacing this file!'
				], 'error');
			}
			$output->writeln($formattedBlock);
		}

		// Move not replaced files

		$output->writeln(PHP_EOL . 'Please wait ...' . PHP_EOL);
		sleep(1);

		if ( !empty($notReplacedFiles) && $questionHelper->ask($input, $output, $holdQuestion) ) {
			foreach ( $notReplacedFiles as $item ) {
				$updatePackageDir = $updater->updatePackageDir . DIRECTORY_SEPARATOR;
				$updater->updateHold($updatePackageDir . $item);
				$output->writeln('"' . $item . '"' . ' file moved to the _NeedUpdate folder.');
			}
		}

		// deleting removed files
		$removeList = $updater->getChangesList($version, 'removed');

		if ( is_array($removeList) && !empty($removeList) ) {
			$formattedBlock = $formatterHelper->formatSection('Deleting Files', 'List of files who removed.');
			$output->writeln($formattedBlock);
			foreach ( $removeList as $item ) {
				Transfers::remove([getcwd() . DIRECTORY_SEPARATOR . $item]);
				$output->writeln($item . ' removed!');
			}
		}


		$output->writeln(PHP_EOL . 'Your template is updated successfully!');

		return 0;
	}
}
