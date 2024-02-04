<?php

namespace AcMarche\PivotSearch\Search;

use AcMarche\PivotSearch\Data\DocumentElastic;
use AcMarche\PivotSearch\Data\RemoteData;
use Meilisearch\Contracts\DeleteTasksQuery;
use Meilisearch\Endpoints\Keys;
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
        private readonly RemoteData $remoteData
    ) {
    }

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
        $documents = $this->treatment();
        $this->init();
        $index = $this->client->index($this->indexName);
        $index->addDocuments($documents, $this->primaryKey);
    }

    /**
     * @return array
     * @throws \Exception
     * @throws TransportExceptionInterface
     */
    private function treatment(): array
    {
        $remoteData = $this->remoteData->getAllData();

        if (isset($remoteData->error)) {
            throw new \Exception('Erreur sync tourisme', $remoteData->error);
        }

        $documents = [];
        foreach ($remoteData->posts as $data) {
            $documents[] = $this->createDocumentElasticFromX($data, 'post');
        }

        foreach ($remoteData->categories as $data) {
            $documents[] = $this->createDocumentElasticFromX($data, 'category');
        }

        foreach ($remoteData->offres as $data) {
            $documents[] = $this->createDocumentElasticFromX($data, 'offer');
        }

        return $documents;
    }

    private function createDocumentElasticFromX(\stdClass $object, string $type): DocumentElastic
    {
        $document = new DocumentElastic();
        $document->id = $this->createId($object->id, $type);
        $document->name = $object->name;
        $document->excerpt = $object->excerpt;
        $document->content = $object->content;
        $document->tags = $object->tags;
        $document->date = $object->date;
        $document->url = $object->url;
        $document->image = $object->image;

        return $document;
    }

    private function createId(int|string $postId, string $type): string
    {
        return $type.'_'.$postId;
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