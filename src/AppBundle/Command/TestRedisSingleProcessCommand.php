<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestRedisSingleProcessCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('test:redis:single')
            ->setDescription('Test message write to Redis using a single process')
            ->addArgument('count', InputArgument::REQUIRED, 'How many messages to send')
            ->addArgument('size', InputArgument::OPTIONAL, 'Message size in KB', 10);
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

        $redisService = $this->getContainer()->get('snc_redis.default');
        $keyPrefix = $this->getContainer()->getParameter('redis_prefix');

        // Clear Redis
        $output->writeln('Cleaning...');
        $progressClean = new ProgressBar($output, $count);
        $progressClean->start();
        for ($i = 1; $i <= $count; $i++) {
            $redisService->del($keyPrefix . $i);
            $progressClean->advance();
        }

        $output->writeln('');
        $output->write('Saving to Redis...');
        $str = str_repeat('x', $size * 1024);
        $ts1 = microtime(true);
        for ($i = 1; $i <= $count; $i++) {
            $redisService->hmset(
                $this->getContainer()->getParameter('redis_prefix') . $i,
                ['msg' => $str, 'ts' => microtime(true)]
            );
        }
        $ts2 = microtime(true);

        $output->writeln(' done in ' . round(($ts2 - $ts1), 2) . ' sec');
    }
}
