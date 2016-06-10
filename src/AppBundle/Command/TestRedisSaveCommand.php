<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestRedisSaveCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('test:redis:save')
            ->setDescription('Save a message to a Redis server')
            ->addArgument('id', InputArgument::REQUIRED, 'Message Id')
            ->addArgument('size', InputArgument::REQUIRED, 'Message size');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException('Message Id argument must be integer');
        }

        $size = $input->getArgument('size');
        if (!filter_var($size, FILTER_VALIDATE_INT)) {
            throw new \InvalidArgumentException('Message size argument must be integer');
        }
        
        $str = str_repeat('x', $size * 1024);
        $this->getContainer()
            ->get('snc_redis.default')
            ->hmset(
                $this->getContainer()->getParameter('redis_prefix') . $id,
                ['msg' => $str, 'ts' => microtime(true)]
            );
    }
}
