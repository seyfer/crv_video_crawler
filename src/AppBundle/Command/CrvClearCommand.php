<?php

namespace AppBundle\Command;

use AppBundle\Entity\VideoLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class CrvClearCommand extends ContainerAwareCommand
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var VideoLinkRepository
     */
    private $linkRepository;

    /**
     * ClearCommand constructor.
     * @param EntityManagerInterface $entityManager
     * @param VideoLinkRepository $linkRepository
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        VideoLinkRepository $linkRepository)
    {
        // you *must* call the parent constructor
        parent::__construct();

        $this->entityManager  = $entityManager;
        $this->linkRepository = $linkRepository;
    }

    protected function configure()
    {
        $this
            ->setName('crv:clear')
            ->setDescription('Clear db and files')
            ->addArgument('type', InputArgument::REQUIRED, 'Clear type')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Path to files');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getOption('path') ?? '';
        $type = $input->getArgument('type') ?? null;

        switch ($type) {
            case 'all':
                $this->linkRepository->removeAll();

                $this->clearFiles($path);

                break;
            case 'db':
                $this->linkRepository->removeAll();

                break;
            case 'files':
                $this->clearFiles($path);

                break;
            case 'undownload':
                $this->linkRepository->markAll($downloaded = false);

                break;
            default:
                throw new \InvalidArgumentException('Clear type not recognized ' . $type);
        }

        $output->writeln('All done');
    }

    /**
     * @param string $path
     */
    private function clearFiles(string $path)
    {
        if (!$path) {
            throw new \InvalidArgumentException('Please, provide path to files ' . $path);
        }

        $fs = new Filesystem();
        if (!$fs->exists($path)) {
            throw new \InvalidArgumentException('Path doesn\'t exist ' . $path);
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException('Path isn\'t writable ' . $path);
        }

        $fs->remove($path);
    }
}
