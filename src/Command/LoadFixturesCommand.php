<?php

namespace AppBundle\Command;

use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LoadFixturesCommand extends Command
{
    private ?StyleInterface $io;

    public function __construct(
        private readonly LoaderInterface $fixturesLoader,
        private readonly string $projectDir,
        private readonly string $environment,
        private readonly LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('coopcycle:fixtures:load')
            ->setDescription('Load fixtures')
            ->addOption(
                'setup',
                's',
                InputOption::VALUE_REQUIRED,
                'The platform setup files to load fixtures from.'
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The files to load fixtures from.'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ('test' !== $this->environment) {
            $this->io->error('This command can only be executed in test environment');
            return 1;
        }

        $setupFile = $input->getOption('setup');

        $hasSetupFile = null !== $setupFile;
        if ($hasSetupFile) {
            $this->logger->info('Loading fixtures from setup file: ' . $setupFile);

            $filePaths = [
                $this->projectDir . '/' . $setupFile
            ];

            $this->fixturesLoader->load($filePaths, $_SERVER);
        }

        $files = $input->getOption('file');

        if (empty($files)) {
            $this->io->error('No files specified');
            return 1;
        }

        $this->logger->info('Loading fixtures from files: ' . implode(', ', $files));

        $filePaths = array_map(fn($file) => $this->projectDir . '/' . $file, $files);

        $this->fixturesLoader->load($filePaths, $_SERVER, [], $hasSetupFile ? PurgeMode::createNoPurgeMode() : null);

        return 0;
    }
}
