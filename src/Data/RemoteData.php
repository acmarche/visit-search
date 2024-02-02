<?php

namespace AcMarche\PivotSearch\Data;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemoteData
{
    public function __construct(
        #[Autowire(env: 'VISIT_URL_DATA')]
        private readonly string $url,
        private readonly HttpClientInterface $client
    ) {

    }

    /**
     * @return \stdClass
     * @throws \JsonException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getAllData(): \stdClass
    {
        $response = $this->client->request(
            'GET',
            $this->url
        );

        return json_decode($response->getContent(), flags: JSON_THROW_ON_ERROR);
    }

}
