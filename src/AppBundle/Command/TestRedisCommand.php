<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class TestRedisCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('test:redis')
            ->setDescription('Test message write to Redis using parallel processes')
            ->addArgument('count', InputArgument::REQUIRED, 'How many messages to send')
            ->addArgument('size', InputArgument::OPTIONAL, 'Message size in KB', 10)
            ->addArgument('processes', InputArgument::OPTIONAL, 'How many parallel processes to spawn', 10);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $count = $input->getArgument('count');
        if (!filter_var($count, FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException('Message count argument must be integer');
        }

        $size = $input->getArgument('size');
        if (!filter_var($size, FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException('Message size argument must be integer');
        }

        $maxProcesses = $input->getArgument('processes');
        if (!filter_var($count, FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException('Maximum processes argument must be integer');
        }

        $redisService = $this->getContainer()->get('snc_redis.default');
        $keyPrefix = $this->getContainer()->getParameter('redis_prefix');

        // Clear Redis
        $output->write('Cleaning...');
        for ($i = 1; $i <= $count; $i++) {
            $redisService->del($keyPrefix . $i);
        }
        $output->writeln('<info>done</info>');

        // Some init
        $currentProcesses = 0;
        $leftProcesses = $count;
        $spawnedProcesses = 0;
        $processes = [];
        $i = 1;

        // Process builder
        $builder = new ProcessBuilder();
        $builder->setPrefix($this->getContainer()->get('kernel')->getRootDir() . '/../bin/console');
        $env = $this->getContainer()->get('kernel')->getEnvironment();

        $output->writeln('Spawned:');
        $progressSpawned = new ProgressBar($output, $count);
        $progressSpawned->start();

        while ($leftProcesses > 0) {
            if ($currentProcesses < $maxProcesses) {
                $builder->setArguments(
                    [
                        '--env=' . $env,
                        'test:redis:save',
                        $i++,
                        $size
                    ]
                );
                $process = $builder->getProcess();
                $process->start();
                $processes[$i] = $process;
                $spawnedProcesses++;
                $leftProcesses--;
            }

            $currentProcesses = count($processes);

            // If there are under 10 available slots,
            // remove the closed processes from the list to make room for new ones
            if ($maxProcesses - $currentProcesses < 10) {
                /** @var Process $running */
                foreach ($processes as $key => $running) {
                    if (!$running->isRunning()) {
                        unset($processes[$key]);
                    }
                }
            }
            $progressSpawned->setProgress($spawnedProcesses);
        };

        // Check saved data in Redis
        $output->writeln('');
        $output->writeln('Redis check:');
        $progressComplete = new ProgressBar($output, $count);

        $completed = 0;
        $isComplete = false;
        $hashes = [];

        while ($isComplete == false) {
            for ($i = 1; $i <= $count; $i++) {
                if (!array_key_exists($i, $hashes) && ($val = $redisService->hget($keyPrefix . $i, 'ts'))) {
                    $hashes[$i] = $val;
                    $completed++;
                }
            }
            if ($completed == $count) {
                $isComplete = true;
            }
            $progressComplete->setProgress($completed);
        };

        $output->writeln('');
        $output->writeln('Done in ' . round((max($hashes) - min($hashes)), 2) . ' sec');
    }
}
