<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\Transfers;
use Devingo\Installer\Console\Log;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class NewCommand extends Command {
	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure () {
		$this->setName('new')
		     ->setDescription('Create a new wordpress theme')
		     ->addArgument('name', InputArgument::REQUIRED);
	}

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute (InputInterface $input, OutputInterface $output) {
		$directoryName = $input->getArgument('name');
		$parsedName    = str_replace([' ', '@', '*', '\\', '/'], '-', $directoryName);

		if ( $directoryName !== $parsedName ) {
			$output->writeln('"' . $directoryName . '"' . ' converted to "' . $parsedName . '".');
			$directoryName = $parsedName;
		}

		$process = new Process(['git', 'clone', 'https://github.com/devingo-net/dcore.git', $directoryName]);
		$process->setTimeout(30000);
		$output->writeln('Please wait, creating new project...');

		$log = new Log();

		try {
			$process->mustRun();
			echo $process->getOutput();

            sleep(1);
			Transfers::remove([getcwd() . DIRECTORY_SEPARATOR . $directoryName . '/.git/']);
			Transfers::remove([getcwd() . DIRECTORY_SEPARATOR . $directoryName . '/.gitignore']);

			$output->writeln('Project is created successfully!');
			$output->writeln('');
			$output->writeln('run "cd ./' . $directoryName . '" command!');

		} catch ( ProcessFailedException $exception ) {
			echo PHP_EOL . 'An unexpected error occurred while creating the project!' . PHP_EOL;
			$log->addLog('Error when creating new project (Code: 103)' . PHP_EOL . PHP_EOL . $exception->getMessage());
		}

		return $process->getExitCode();
	}

}
