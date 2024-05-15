<?php

namespace AcMarche\PivotSearch\Command;

use AcMarche\PivotSearch\Search\MeiliServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'pivot:meili-server',
    description: 'Mise à jour du moteur de recherche'
)]
class MeiliServerCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private MeiliServer $meiliServer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('key', "key", InputOption::VALUE_NONE, 'Create a key');
        $this->addOption('tasks', "tasks", InputOption::VALUE_NONE, 'Display tasks');
        $this->addOption('reset', "reset", InputOption::VALUE_NONE, 'Search engine reset');
        $this->addOption('update', "update", InputOption::VALUE_NONE, 'Update data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $key = (bool)$input->getOption('key');
        $tasks = (bool)$input->getOption('tasks');
        $reset = (bool)$input->getOption('reset');
        $update = (bool)$input->getOption('update');

        if ($key) {
            dump($this->meiliServer->createKey());

            return Command::SUCCESS;
        }

        if ($tasks) {
            $this->tasks($output);

            return Command::SUCCESS;
        }

        if ($reset) {
            $result = $this->meiliServer->createIndex();
            dump($result);
            $result = $this->meiliServer->settings();
            dump($result);
        }

        if ($update) {
            require_once($this->projectDir.'/wp-load.php');
            try {
                $this->meiliServer->addContent($this->io);
            } catch (\Exception|TransportExceptionInterface $e) {
                $this->io->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function tasks(OutputInterface $output): void
    {
        $this->meiliServer->init();
        $tasks = $this->meiliServer->client->getTasks();
        $data = [];
        foreach ($tasks->getResults() as $result) {
            $t = [$result['uid'], $result['status'], $result['type'], $result['startedAt']];
            $t['error'] = null;
            $t['url'] = null;
            if ($result['status'] == 'failed') {
                if (isset($result['error'])) {
                    $t['error'] = $result['error']['message'];
                    $t['link'] = $result['error']['link'];
                }
            }
            $data[] = $t;
        }
        $table = new Table($output);
        $table
            ->setHeaders(['Uid', 'status', 'Type', 'Date', 'Error', 'Url'])
            ->setRows($data);
        $table->render();
    }

}
