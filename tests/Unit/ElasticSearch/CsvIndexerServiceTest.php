<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use App\ElasticSearch\CsvIndexerService;
use App\ElasticSearch\HttpClientInterface;
use PHPUnit\Framework\TestCase;

class CsvIndexerServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private CsvIndexerService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->service    = new CsvIndexerService('http://localhost:9200', $this->httpClient);
    }

    public function testCreateIndexSendsPutRequest(): void
    {
        $mapping = ['mappings' => ['properties' => []]];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('PUT', 'http://localhost:9200/employees', $mapping)
            ->willReturn('{"acknowledged":true}');

        $this->service->createIndex('employees', $mapping);
    }

    public function testIndexRowsSendsOneRequestPerRow(): void
    {
        $rows = [
            ['Name' => 'Alice', 'Age' => '29', 'Salary' => '55000.50'],
            ['Name' => 'Bob',   'Age' => '34', 'Salary' => '62000.00'],
        ];

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('request')
            ->willReturn('{"result":"created"}');

        $this->service->indexRows('employees', $rows);
    }

    public function testIndexRowsCastsTypesCorrectly(): void
    {
        $rows = [['Name' => 'Alice', 'Age' => '29', 'Salary' => '55000.50']];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'PUT',
                'http://localhost:9200/employees/_doc/1',
                $this->callback(function (array $doc): bool {
                    return $doc['name']   === 'Alice'
                        && $doc['age']    === 29
                        && $doc['salary'] === 55000.50
                        && $doc['id']     === 1;
                })
            )
            ->willReturn('{"result":"created"}');

        $this->service->indexRows('employees', $rows);
    }

    public function testSearchSendsFuzzyMultiMatchQuery(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'http://localhost:9200/employees/_search',
                $this->callback(fn(array $body): bool =>
                    $body['query']['multi_match']['query']     === 'Alice'
                    && $body['query']['multi_match']['fuzziness'] === 'AUTO'
                )
            )
            ->willReturn('{"hits":{"total":{"value":1}}}');

        $this->service->search('employees', 'Alice');
    }
}