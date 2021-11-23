<?php

namespace Devingo\Installer\Console\Commands;

use Devingo\Installer\Console\Actions\Edit;
use Devingo\Installer\Console\Actions\Operations;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
        $this
            ->setName('update')
            ->setDescription('Update the devingo core')
            ->addArgument('version', InputArgument::OPTIONAL);
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
    	$ops = new Operations(__DIR__ . '/../../bin/style.css');
    	$ops->styleCSSChange([
    		'Theme Name'    =>  'heloooo',
		    'Version'       =>  '1.0.2',
		    'Requires PHP'       =>  '7.4'
	    ]);

    	return 0;
    	/**
    	$directoryName = $input->getArgument('name');

	    $process = new Process(['git','clone','git@github.com:symfony/process.git',$directoryName]);
	    try {
		    $process->mustRun();

		    echo $process->getOutput();
	    } catch (ProcessFailedException $exception) {
		    echo $exception->getMessage();
	    }
    	return $process->getExitCode();
	     *
	     */
    }
}
