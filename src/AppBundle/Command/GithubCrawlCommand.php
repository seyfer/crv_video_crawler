<?php

namespace AppBundle\Command;

use Goutte\Client as GoutteClient;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;


class GithubCrawlCommand extends Command
{
    /**
     * @var \Predis\Client
     */
    private $redis;

    /**
     * @var bool
     */
    private $withAuth = false;

    /**
     * @var GoutteClient
     */
    private $client;

    private const BASE_URL = 'https://github.com/';

    private const LOGIN_URL = self::BASE_URL . 'login';

    /**
     * GithubCrawlCommand constructor.
     * @param \Predis\Client $redis
     * @param GoutteClient $client
     */
    public function __construct(\Predis\Client $redis, \Goutte\Client $client)
    {
        // you *must* call the parent constructor
        parent::__construct();

        $this->redis = $this->initRedis($redis);

        $this->client = $this->initClient($client);
    }

    /**
     * @param \Predis\Client $redis
     * @return \Predis\Client
     */
    private function initRedis(\Predis\Client $redis)
    {
        $redis->connect();
        if (!$redis->isConnected()) {
            throw new \RuntimeException('Please check Redis config and installation');
        }

        return $redis;
    }

    /**
     * @return GoutteClient
     */
    private function initClient(\Goutte\Client $client)
    {
        $guzzleClient = new GuzzleClient([
                                             'timeout' => 60,
                                         ]);
        $client->setClient($guzzleClient);
        $client->setHeader(
            'User-Agent',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.104 Safari/537.36');

        return $client;
    }

    protected function configure()
    {
        $this
            ->setName('github:crawl')
            ->setDescription('...')
            ->addArgument('username', InputArgument::REQUIRED, 'Github username')
            ->addOption(
                'password', 'p', InputOption::VALUE_OPTIONAL, 'Github password', null
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username') ?? 'seyfer';
        $password = $input->getOption('password');

        $output->writeln(implode(' ', [$username, $password]));

        if (!$password) {
            $this->withAuth = false;
        } else {
            $this->withAuth = true;
        }

        if ($this->withAuth) {
            $crawler = $this->withAuth($username, $password, $output);
        } else {
            $crawler = $this->withoutAuth($username, $output);
        }

        $reposLink = $crawler->selectLink('Repositories')->link();
        $crawler   = $this->client->request('GET', $reposLink->getUri());
        $output->writeln('Repositories');

        $pagesCount = 1;
        $crawler->filter('.pagination')->filter('a')
                ->each(function (Crawler $node) use (&$pagesCount) {
                    if (is_numeric(trim($node->text()))) {
                        $pagesCount = (int)$node->text();
                    }
                });
        $output->writeln('pagesCount');

        for ($i = 1; $i <= $pagesCount; $i++) {
            $pageLink = self::BASE_URL . 'seyfer?page=' . $i . '&tab=repositories';

            $crawler = $this->client->request('GET', $pageLink);

            $output->writeln('Page ' . $i . PHP_EOL);

            $crawler->filter('a')->filter('a[itemprop="name codeRepository"]')
                    ->each(function (Crawler $node) use ($output) {
                        $output->writeln(trim($node->text()));
                    });

            $output->writeln(PHP_EOL);
        }

//        $html = $crawler->html();
//        $output->writeln($html);
    }

    /**
     * @param $username
     * @param OutputInterface $output
     * @return Crawler
     * @throws \Exception
     */
    private function withoutAuth($username, OutputInterface $output)
    {
        $userCacheKey = 'github_' . $username . '_user';

        if (!$this->redis->get($userCacheKey)) {

            $crawler = $this->client->request('GET', self::BASE_URL . $username);

//            $description = $crawler->filterXpath('//meta[@name="description"]')
//                             ->extract(['content']);

            $title = $crawler->filterXpath('//title')->text();

            if (strpos($title, 'Page not found') !== false) {
                throw new \Exception($title . " " . $username . " not exists");
            }

            $html = $crawler->html();

            $this->redis->set($userCacheKey, $html);

        } else {
            $html = $this->redis->get($userCacheKey);

            if (!$html) {
                $this->redis->del([$userCacheKey]);
            }

            $output->writeln('from cache');

            $crawler = new Crawler($html, self::BASE_URL . $username);
        }

        return $crawler;
    }

    /**
     * @param $username
     * @param $password
     * @param OutputInterface $output
     * @return Crawler
     */
    private function withAuth($username, $password, OutputInterface $output)
    {
        $loginCacheKey = 'github_' . $username . '_login';

        if (!$this->redis->get($loginCacheKey)) {

            $crawler = $this->client->request('GET', self::LOGIN_URL);

            // select the form and fill in some values
            $form             = $crawler->selectButton('Sign in')->form();
            $form['login']    = $username;
            $form['password'] = $password;

            // submit that form
            $crawler = $this->client->submit($form);

            $html = $crawler->html();

            //check invalid login
            $crawler->filter('.flash-error')->each(function (Crawler $node) use ($username, $password) {

                $text = trim($node->text());
                if (strpos($text, 'Invalid') !== false) {
                    throw new \Exception($text . " " . $username . " " . $password);
                }

            });

            $crawler->filter('p')->filter('.create-account-callout')
                    ->each(function (Crawler $node) use ($output) {
                        $output->writeln(trim($node->text()));
                    });

            if ($crawler->filter('p')->filter('.create-account-callout')->count() == 0) {
                $this->redis->set($loginCacheKey, $html);
            } else {
                $this->redis->del([$loginCacheKey]);
            }
        } else {
            $html = $this->redis->get($loginCacheKey);

            if (!$html) {
                $this->redis->del([$loginCacheKey]);
            }

            $output->writeln('from cache');

            $crawler = new Crawler($html, self::BASE_URL);
        }

        $profileLink = $crawler->selectLink('Your profile')->link();
        $crawler     = $this->client->request('GET', $profileLink->getUri());
        $output->writeln('Your profile');

        return $crawler;
    }

}
