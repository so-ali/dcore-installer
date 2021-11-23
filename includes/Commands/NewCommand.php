<?php

namespace Devingo\Installer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class NewCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new wordpress theme')
            ->addArgument('name', InputArgument::REQUIRED);
    }

    /**
     * Execute the command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

    	$directoryName = $input->getArgument('name');
    	$parsedName = str_replace([' ','@','*','\\','/'],'-',$directoryName);

	    if ($directoryName !== $parsedName){
		    $output->writeln('"' . $directoryName . '"' . ' converted to "' . $parsedName . '".');
		    $directoryName = $parsedName;
	    }

	    $process = new Process(['git','clone','git@github.com:devingo-net/dcore.git',$directoryName]);


	    try {
		    $process->mustRun();

		    echo $process->getOutput();



		    $removeProcess = new Process(['rm','-rf','./'.$directoryName.'/.git/']);
		    $removeProcess->run();
		    echo $removeProcess->getOutput();


		    $output->writeln('project is created successfully!');
		    $output->writeln('');
		    $output->writeln('run "cd ./'.$directoryName.'" command!');

	    } catch (ProcessFailedException $exception) {
		    echo $exception->getMessage();
	    }
    	return $process->getExitCode();
    }

}
