<?php

namespace AppBundle\Command;


use AppBundle\Entity\VideoLink;
use AppBundle\Entity\VideoLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Goutte\Client as GoutteClient;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class CrvCrawlCommand extends ContainerAwareCommand
{
    /**
     * @var \Predis\Client
     */
    protected $redis;

    /**
     * @var GoutteClient
     */
    protected $client;

    private const BASE_URL = 'https://codereviewvideos.com';

    private const LOGIN_URL = self::BASE_URL . '/login';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var VideoLinkRepository
     */
    private $linkRepository;

    /**
     * GithubCrawlCommand constructor.
     * @param \Predis\Client $redis
     * @param GoutteClient $client
     * @param EntityManagerInterface $entityManager
     * @param VideoLinkRepository $linkRepository
     */
    public function __construct(
        \Predis\Client $redis,
        \Goutte\Client $client,
        EntityManagerInterface $entityManager,
        VideoLinkRepository $linkRepository
    )
    {
        // you *must* call the parent constructor
        parent::__construct();

        $this->redis = $this->initRedis($redis);

        $this->client = $this->initClient($client);

        $this->entityManager  = $entityManager;
        $this->linkRepository = $linkRepository;
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
            ->setName('crv:crawl')
            ->setDescription('crawl crv video links db and download videos')
            ->addArgument('username', InputArgument::REQUIRED, 'crv username')
            ->addArgument('password', InputArgument::REQUIRED, 'crv password')
            ->addOption(
                'not-update-db', 'nudb',
                InputOption::VALUE_OPTIONAL, 'Flag to not update links db', 0
            )
            ->addOption(
                'not-download', 'nd',
                InputOption::VALUE_OPTIONAL, 'Flag to not download videos', 0
            )
            ->addOption('download-path', 'dp', InputOption::VALUE_OPTIONAL, '');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username') ?? 'seyfer';
        $password = $input->getArgument('password');

        $output->writeln(implode(' ', [$username, $password]));

        $downloadPath = $this->initDownloadPath($input->getOption('download-path'), $username);

        $output->writeln($downloadPath);

        //count videos in folder recursively
        $videosCount = 0;
        $output->writeln("videos count in folder: " . $videosCount);

        //get links
        $links      = $this->getLinks();
        $linksCount = count($links);
        $output->writeln("links count in db: " . $linksCount);

        if (!$input->getOption('not-update-db')) {
            //update links db
            $this->updateLinksDB($username, $password, $output);
        }

        if (!$input->getOption('not-download')) {
            $this->downloadLinks($links);
        }
    }

    /**
     * @param $providedPath
     * @param $username
     * @return string
     */
    private function initDownloadPath($providedPath, $username)
    {
        $downloadPath = $providedPath;
        if (!$downloadPath) {
            $downloadPath = '/home/' . $username . '/Downloads/';

            if (!is_writable($downloadPath)) {
                throw new \InvalidArgumentException('Provide valid and writable download path ' .
                                                    $downloadPath);
            }

            $downloadPath .= 'crv_videos';

            if (!file_exists($downloadPath)) {
                mkdir($downloadPath, 0777, true);
            }
        }

        if (!is_writable($downloadPath)) {
            throw new \InvalidArgumentException('Provide valid and writable download path ' .
                                                $downloadPath);
        }

        return $downloadPath;
    }

    private function downloadLinks($links)
    {

    }

    /**
     * @return array
     */
    private function getLinks()
    {
        return $this->linkRepository->findAll();
    }

    /**
     * @param $username
     * @param $password
     * @param $output
     */
    private function updateLinksDB($username, $password, $output)
    {
        $crawler = $this->login($username, $password);

        //Start Watching
        $coursesLink = $crawler->selectLink('Start Watching')->link();
        $crawler     = $this->client->request('GET', $coursesLink->getUri());
        $output->writeln('Start Watching');

        $pagesCount = 1;
        $crawler->filter('.pagination')->filter('a')
                ->each(function (Crawler $node) use (&$pagesCount) {
                    if (is_numeric(trim($node->text()))) {
                        $pagesCount = (int)$node->text();
                    }
                });
        $output->writeln('pagesCount ' . $pagesCount);

        for ($i = 1; $i <= $pagesCount; $i++) {
            $currentPage = self::BASE_URL . '/courses/text/' . $i;

            $pageCacheKey = 'crv_page_' . $i;
            if ($this->redis->get($pageCacheKey)) {
                $html    = $this->redis->get($pageCacheKey);
                $crawler = new Crawler($html);
            } else {
                $crawler = $this->client->request('GET', $currentPage);
                $this->redis->set($pageCacheKey, $crawler->html());
            }

            $crawler->filter('.course-text-list-container')
                    ->each(function (Crawler $node) {
//                        exit(dump($node));

                        $courseName = $node->filter('h2')->text();

//                        exit(dump($courseName));

                        $node->filter('.table')->filter('tr')
                             ->each(function (Crawler $node) use ($courseName) {

                                 $tds = $node->filter('td');

//                                 exit(dump($tds));
//                                 exit(dump($tds->getNode(0)->textContent));

                                 $position  = $tds->getNode(0)->textContent;
                                 $videoName = $tds->getNode(1)->textContent;
                                 $duration  = $tds->getNode(2)->textContent;

                                 $aUrl = $tds->getNode(1)
                                             ->getElementsByTagName('a')[0]
                                     ->getAttributeNode('href')->value;

                                 $videoContentUrl = self::BASE_URL . $aUrl;

                                 $crawler = $this->client->request('GET', $videoContentUrl);

                                 $videoUrl = $crawler->filter('.vjs-tech')
                                                     ->children()
                                                     ->attr('src');

                                 exit(dump(
                                     $position,
                                     $videoName,
                                     $duration
                                     , $videoContentUrl, $videoUrl
                                 ));

                                 $durationTime = \DateTime::createFromFormat('H:i', $duration);
                                 $linkEntity   = new VideoLink(
                                     $courseName, $videoName, $videoUrl, $position, $durationTime
                                 );

                                 $this->entityManager->persist($linkEntity);
                             });

                        $this->entityManager->flush();
                    });
        }
    }

    /**
     * @param $username
     * @param $password
     * @return Crawler
     */
    private function login($username, $password)
    {
        $loginCacheKey = 'crv_' . $username . '_login';

        $crawler = $this->client->request('GET', self::LOGIN_URL);

        // select the form and fill in some values
        $form              = $crawler->selectButton('Sign in')->form();
        $form['_username'] = $username;
        $form['_password'] = $password;

        // submit that form
        $crawler = $this->client->submit($form);

        $html = $crawler->html();

        //check invalid login
        $crawler->filter('.alert-danger')->each(function (Crawler $node) use ($username, $password) {
            $text = trim($node->text());
            if (strpos($text, 'invalid') !== false) {
                throw new \Exception($text . " " . $username . " " . $password);
            }
        });

        $this->redis->set($loginCacheKey, $html);

        return $crawler;
    }
}
