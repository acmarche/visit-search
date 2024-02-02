<?php

namespace AcMarche\PivotSearch\Command;

use AcMarche\PivotSearch\Search\SearchMeili;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pivot:meili-search',
    description: 'Mise à jour du moteur de recherche'
)]
class MeiliSearchCommand extends Command
{
    private OutputInterface $ouput;

    public function __construct(
        private SearchMeili $searchMeili
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('keyword', InputArgument::REQUIRED, 'mot clef');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->ouput = $output;

        $keyword = (bool)$input->getArgument('keyword');

        if ($keyword) {
            $result = $this->searchMeili->search($keyword);

            $io->section("Result ".$result->count());
            $this->display($result->getHits());

            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    private function display(array $results): void
    {
        $data = [];
        foreach ($results as $result) {
            $data[] = [$result['id'], trim($result['name']),  $result['url']];
        }
        $table = new Table($this->ouput);
        $table
            ->setHeaders(['id', 'name', 'url'])
            ->setRows($data);
        $table->render();
    }

}
