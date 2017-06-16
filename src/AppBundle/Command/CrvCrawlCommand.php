<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CrvCrawlCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('crv:crawl')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argument = $input->getArgument('argument');
        if ($input->getOption('option')) {
            // ...
        }

        $client = new Client();
        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_SSL_VERIFYHOST, FALSE);
        $client->getClient()->setDefaultOption('config/curl/'.CURLOPT_SSL_VERIFYPEER, FALSE);
        $crawler = $client->request('GET', 'https://github.com/login');

// select the form and fill in some values
        $form = $crawler->selectButton('Sign in')->form();
        $form['login'] = 'symfonyfan';
        $form['password'] = 'anypass';

// submit that form
        $crawler = $client->submit($form);
        echo $crawler->html();

        $output->writeln('Command result.');
    }

}
