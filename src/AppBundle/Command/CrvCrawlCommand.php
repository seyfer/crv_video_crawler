<?php

namespace AppBundle\Command;


use AppBundle\Entity\VideoLink;
use AppBundle\Entity\VideoLinkRepository;
use AppBundle\Service\FileDownloadService;
use Doctrine\ORM\EntityManagerInterface;
use Goutte\Client as GoutteClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
     * @var OutputInterface
     */
    private $output;

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
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'crv username')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'crv password')
            ->addOption(
                'not-update-db', 'nud',
                InputOption::VALUE_OPTIONAL, 'Flag to not update links db', 0
            )
            ->addOption(
                'not-download', 'nd',
                InputOption::VALUE_OPTIONAL, 'Flag to not download videos', 0
            )
            ->addOption(
                'download-path', 'dp', InputOption::VALUE_OPTIONAL, 'where to download'
            )
            ->addOption(
                'from-page', 'fp', InputOption::VALUE_OPTIONAL, 'start from page'
            )
            ->addOption(
                'overwrite', 'o', InputOption::VALUE_OPTIONAL, 'overwrite videos', 0
            )
            ->addOption(
                'from-id', 'fi', InputOption::VALUE_OPTIONAL, 'download from id'
            )
            ->addOption(
                'id', 'i', InputOption::VALUE_OPTIONAL, 'download only id'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $username = $input->getOption('username') ?? 'seyfer';
        $password = $input->getOption('password') ?? null;

        $this->output->writeln(implode(' ', [$username, $password]));

        $downloadPath = $this->initDownloadPath($input->getOption('download-path'), $username);

        $this->output->writeln($downloadPath);

        $videosCount = $this->getFilesCount($downloadPath);
        $this->output->writeln("videos count in folder: " . $videosCount);

        $linksCount = $this->getLinksCount();
        $this->output->writeln("links count in db: " . $linksCount);

        $fromPage = (int)$input->getOption('from-page') ?? 1;

        if (!$input->getOption('not-update-db')) {
            //update links db
            $this->updateLinksDB($username, $password, $fromPage);

            $linksCount = $this->getLinksCount();
            $this->output->writeln("links count in db AFTER: " . $linksCount);
        }

        if (!$input->getOption('not-download')) {

            $links = $this->getLinksForDownload($input);

            $overwrite = (boolean)$input->getOption('overwrite') ?? false;

            $this->downloadLinks($links, $downloadPath, $overwrite);

            $videosCount = $this->getFilesCount($downloadPath);
            $this->output->writeln("videos count in folder AFTER: " . $videosCount);
        }
    }

    /**
     * @param InputInterface $input
     * @return array
     */
    private function getLinksForDownload(InputInterface $input)
    {
        $params = [
            'downloaded' => false,
        ];

        $fromId = $input->getOption('from-id') ?? null;
        if ($fromId) {
            $params['fromId'] = $fromId;
        }

        $id = $input->getOption('id') ?? null;
        if ($id) {
            $params['id'] = $id;
        }

        //get not downloaded links
        $links = $this->getLinks($params);

        return $links;
    }

    /**
     * @return int
     */
    private function getLinksCount()
    {
        $links      = $this->getLinks();
        $linksCount = count($links);

        return $linksCount;
    }

    /**
     * @param $downloadPath
     * @return int
     */
    private function getFilesCount($downloadPath)
    {
        //count videos in folder recursively
        $finder      = new Finder();
        $videosCount = $finder->files()->in($downloadPath)->count();

        return $videosCount;
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

            $fs = new Filesystem();
            if (!$fs->exists($downloadPath)) {
                $fs->mkdir($downloadPath);
            }
        }

        if (!is_writable($downloadPath)) {
            throw new \InvalidArgumentException('Provide valid and writable download path ' .
                                                $downloadPath);
        }

        return $downloadPath;
    }

    /**
     * @param array $links
     * @param $downloadPath
     * @param bool $overwrite
     */
    private function downloadLinks(array $links, $downloadPath, $overwrite = false)
    {
        /** @var VideoLink $link */
        foreach ($links as $link) {
            $videoUrl = $link->getLink();

            $fs        = new Filesystem();
            $courseDir = $downloadPath . DIRECTORY_SEPARATOR .
                         $link->getCourseName();

            if (!$fs->exists($courseDir)) {
                $fs->mkdir($courseDir);
            }

            $videoName = $link->getVideoName();
            $videoName = str_replace('?', '', $videoName);
            $videoName = str_replace('/','-', $videoName);

            $localFile = $courseDir . DIRECTORY_SEPARATOR . $videoName . ".mp4";

            if ($fs->exists($localFile) && $overwrite) {
                $fs->remove($localFile);
            }

            if (!$fs->exists($localFile)) {
                $fs->touch($localFile);
            }

//            exit(dump($videoUrl, $localFile));

            $this->output->writeln("id " . $link->getId());

            $this->downloadVideo($videoUrl, $localFile);

            $link->setDownloaded(true);
            $this->entityManager->flush();
        }
    }

    /**
     * @param $videoUrl
     * @param $localFile
     */
    private function downloadVideo($videoUrl, $localFile)
    {
        $this->output->writeln('downloading from: ' . $videoUrl);
        $this->output->writeln('to: ' . $localFile);

        FileDownloadService::downloadWithProgress($videoUrl, $localFile, $this->output);

        //make pauses
        usleep(500000);
    }

    /**
     * @param array $params
     * @return array
     */
    private function getLinks(array $params = [])
    {
        return $this->linkRepository->getLinksBy($params);
    }

    /**
     * @param $page
     * @return string
     */
    private function preparePageUrl($page)
    {
        return self::BASE_URL . '/courses/text/' . $page;
    }

    /**
     * @param $username
     * @param $password
     * @param int $fromPage
     */
    private function updateLinksDB($username, $password, $fromPage = 1)
    {
        $crawler = $this->login($username, $password);

        //Start Watching
//        $coursesLink = $crawler->selectLink('Start Watching')->link();
//        $crawler     = $this->client->request('GET', $coursesLink->getUri());
//        $output->writeln('Start Watching');

        $pageCacheKey = 'crv_page_' . $fromPage;
        $crawler      = $this->loadUrlWithCache($pageCacheKey, $this->preparePageUrl($fromPage));
        $this->output->writeln($pageCacheKey . PHP_EOL);

        $pagesCount = 1;
        $crawler->filter('.pagination')->filter('a')
                ->each(function (Crawler $node) use (&$pagesCount) {
                    if (is_numeric(trim($node->text()))) {
                        $pagesCount = (int)$node->text();
                    }
                });
        $this->output->writeln('pagesCount ' . $pagesCount . PHP_EOL);

        for ($i = $fromPage; $i <= $pagesCount; $i++) {

            $pageCacheKey = 'crv_page_' . $i;
            $crawler      = $this->loadUrlWithCache($pageCacheKey, $this->preparePageUrl($i));
            $this->output->writeln(PHP_EOL . $pageCacheKey);

            $crawler->filter('.course-text-list-container')
                    ->each(function (Crawler $node) {
//                        exit(dump($node));

                        $courseName = $node->filter('h2')->text();
                        $this->output->writeln(PHP_EOL . $courseName . PHP_EOL);

//                        exit(dump($courseName));

                        $node->filter('.table')->filter('tr')
                             ->each(function (Crawler $node) use ($courseName) {

                                 $tds = $node->filter('td');

//                                 exit(dump($tds));
//                                 exit(dump($tds->getNode(0)->textContent));

                                 $position  = $tds->getNode(0)->textContent;
                                 $videoName = $tds->getNode(1)->textContent;
                                 $duration  = $tds->getNode(2)->textContent;

                                 /** @var \DOMElement $a */
                                 $a    = $tds->getNode(1)
                                             ->getElementsByTagName('a')[0];
                                 $aUrl = $a->getAttributeNode('href')->value;

                                 $videoContentUrl = self::BASE_URL . $aUrl;

                                 $videoCacheKey = 'crv_video_' . $courseName . "_" . $videoName;
                                 $crawler       = $this->loadUrlWithCache($videoCacheKey, $videoContentUrl);

//                                 exit(dump());

                                 $videoUrl = $crawler->filter('.code-review-video')
                                                     ->children()->children()->attr('src');

//                                 exit(dump(
//                                     $position,
//                                     $videoName,
//                                     $duration
//                                     , $videoContentUrl, $videoUrl
//                                 ));

                                 $this->output->writeln($videoName);

                                 $durationTime = \DateTime::createFromFormat('H:i', $duration);

                                 $this->createOrUpdateLink(
                                     $courseName, $videoName, $videoUrl, $position, $durationTime
                                 );

                                 $this->entityManager->flush();
                             });

                        //not safe
//                        $this->entityManager->flush();
                    });
        }
    }

    /**
     * @param $courseName
     * @param $videoName
     * @param $videoUrl
     * @param $position
     * @param $durationTime
     */
    private function createOrUpdateLink($courseName, $videoName, $videoUrl, $position, $durationTime)
    {
        /** @var VideoLink $linkEntity */
        $linkEntity = $this->linkRepository->findOneBy([
                                                           'courseName' => $courseName,
                                                           'videoName'  => $videoName,
                                                       ]);
        if ($linkEntity) {
            $linkEntity->setCourseName($courseName);
            $linkEntity->setVideoName($videoName);
            $linkEntity->setDuration($durationTime);
            $linkEntity->setLink($videoUrl);
            $linkEntity->setPosition($position);

            $this->output->writeln('updated');
        }

        if (!$linkEntity) {
            $linkEntity = new VideoLink(
                $courseName, $videoName, $videoUrl, $position, $durationTime
            );

            $this->entityManager->persist($linkEntity);

            $this->output->writeln('added');
        }
    }

    /**
     * @param $cacheKey
     * @param $url
     * @return Crawler
     */
    private function loadUrlWithCache($cacheKey, $url)
    {
        if ($this->redis->get($cacheKey)) {
            $html    = $this->redis->get($cacheKey);
            $crawler = new Crawler($html);
        } else {
            //in order to make pauses
            usleep(500000);

            try {
                $crawler = $this->client->request('GET', $url);
            } catch (ConnectException $e) {
                //well, try again
                $crawler = $this->client->request('GET', $url);
            }

            $this->redis->set($cacheKey, $crawler->html());
        }

        return $crawler;
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
