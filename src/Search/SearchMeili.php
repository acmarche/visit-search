<?php

namespace AcMarche\PivotSearch\Search;

use Meilisearch\Search\SearchResult;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SearchMeili
{
    use MeiliTrait;

    public function __construct(
        #[Autowire(env: 'MEILI_PIVOT_INDEX_NAME')]
        private string $indexName,
        #[Autowire(env: 'MEILI_PIVOT_MASTER_KEY')]
        private string $masterKey,
    ) {
    }

    /**
     * https://www.meilisearch.com/docs/learn/fine_tuning_results/geosearch
     * @param float $latitude
     * @param float $longitude
     * @param int $distance in meters
     * @return SearchResult
     */
    public function searchGeo(float $latitude, float $longitude, int $distance = 20000): SearchResult
    {
        $this->init();
        return $this->client
            ->index($this->indexName)
            ->search('', [
                'filter' => "_geoRadius($latitude, $longitude, $distance)",
            ]);
    }

    /**
     * https://www.meilisearch.com/docs/learn/fine_tuning_results/filtering
     * @param string $keyword
     * @param string|null $localite
     * @return iterable|SearchResult
     */
    public function search(string $keyword, string $id = null): iterable|SearchResult
    {
        $this->init();
        $index = $this->client->index($this->indexName);
        $filters = ['filter' => ['type = fiche']];
        $filters=[];
        if ($id) {
            $filters['filter'] = ['id = '.$id];
        }

        return $index->search($keyword, $filters);
    }

}