<?php

namespace AcMarche\PivotSearch\Search;

use AcMarche\PivotSearch\Data\DocumentElastic;
use AcMarche\PivotSearch\Data\ElasticData;
use Meilisearch\Contracts\DeleteTasksQuery;
use Meilisearch\Endpoints\Keys;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class MeiliServer
{
    use MeiliTrait;

    private string $primaryKey = 'id';

    public function __construct(
        #[Autowire(env: 'MEILI_PIVOT_INDEX_NAME')]
        private string $indexName,
        #[Autowire(env: 'MEILI_PIVOT_MASTER_KEY')]
        private string $masterKey,
    ) {}

    /**
     *
     * @return array<'taskUid','indexUid','status','enqueuedAt'>
     */
    public function createIndex(): array
    {
        $this->init();
        $this->client->deleteTasks((new DeleteTasksQuery())->setStatuses(['failed', 'canceled', 'succeeded']));
        $this->client->deleteIndex($this->indexName);

        return $this->client->createIndex($this->indexName, ['primaryKey' => $this->primaryKey]);
    }

    /**
     * https://raw.githubusercontent.com/meilisearch/meilisearch/latest/config.toml
     * @return array
     */
    public function settings(): array
    {
        return $this->client->index($this->indexName)->updateFilterableAttributes($this->facetFields);
    }

    /**
     * @return void
     * @throws \Exception
     * @throws TransportExceptionInterface
     */
    public function addContent(): void
    {
        $documents = $this->getAllData();
        $this->init();
        $index = $this->client->index($this->indexName);
        $index->addDocuments($documents, $this->primaryKey);
    }

    /**
     * @return DocumentElastic[]
     * @throws \Exception
     * @throws TransportExceptionInterface
     */
    private function getAllData(): array
    {
        $elasticData = new ElasticData();
        $documents = [];
        foreach ($elasticData->getPosts() as $document) {
            $documents[] = $document;
        }
        foreach ($elasticData->getCategories() as $document) {
            $documents[] = $document;
        }

        foreach ($elasticData->getOffres() as $document) {
            $documents[] = $document;
        }

        return $documents;
    }

    public function createKey(): Keys
    {
        $this->init();

        return $this->client->createKey([
            'description' => 'Pivot API key',
            'actions' => ['*'],
            'indexes' => [$this->indexName],
            'expiresAt' => '2042-04-02T00:42:42Z',
        ]);
    }
}