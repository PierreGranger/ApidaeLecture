<?php

namespace PierreGranger\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PierreGranger\ApidaeException;
use PierreGranger\ApidaeLecture;

class ApidaeLectureTest extends TestCase
{
    private const PROJET_ID = '370';

    private const API_KEY = 'testApiKey123';

    /** @var array<int, array<string, mixed>> */
    private array $guzzleHistory = [];

    public function testConstructSetsCredentials(): void
    {
        $lecture = new ApidaeLecture($this->baseParams());

        $this->assertSame(self::PROJET_ID, $this->getProtectedProperty($lecture, 'projet_consultation_projetId'));
        $this->assertSame(self::API_KEY, $this->getProtectedProperty($lecture, 'projet_consultation_apiKey'));
        $this->assertIsArray(ApidaeLecture::$operations);
        $this->assertIsArray(ApidaeLecture::$queryOptions);
    }

    #[DataProvider('invalidConstructorParamsProvider')]
    public function testConstructThrowsOnInvalidParams(array $params, string $message): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($message);

        new ApidaeLecture($params);
    }

    public static function invalidConstructorParamsProvider(): array
    {
        return [
            'missing projetId' => [
                ['projet_consultation_apiKey' => self::API_KEY],
                'missing projet_consultation_projetId',
            ],
            'invalid projetId' => [
                [
                    'projet_consultation_projetId' => 'abc',
                    'projet_consultation_apiKey' => self::API_KEY,
                ],
                'missing projet_consultation_projetId',
            ],
            'missing apiKey' => [
                ['projet_consultation_projetId' => self::PROJET_ID],
                'missing projet_consultation_apiKey',
            ],
            'invalid apiKey' => [
                [
                    'projet_consultation_projetId' => self::PROJET_ID,
                    'projet_consultation_apiKey' => 'key with spaces',
                ],
                'missing projet_consultation_apiKey',
            ],
        ];
    }

    public function testCallThrowsOnUnknownEndpoint(): void
    {
        $lecture = $this->createLectureWithMockClient([]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionMessage('bad endpoint');

        $lecture->call('endpoint/inexistant');
    }

    public function testCallThrowsOnUnknownParameter(): void
    {
        $lecture = $this->createLectureWithMockClient([]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_PARAMETER);

        try {
            $lecture->call('objet-touristique/get-by-id', [
                'id' => 1234,
                'paramInconnu' => 'valeur',
            ]);
        } catch (ApidaeException $e) {
            $this->assertStringContainsString('Paramètre paramInconnu inconnu', implode(' ', $e->getDetails()['errors']));

            throw $e;
        }
    }

    public function testCallThrowsOnMissingRequiredUriParameter(): void
    {
        $lecture = $this->createLectureWithMockClient([]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_PARAMETER);

        try {
            $lecture->call('objet-touristique/get-by-id', []);
        } catch (ApidaeException $e) {
            $this->assertContains('id required', $e->getDetails()['errors']);

            throw $e;
        }
    }

    public function testCallThrowsOnInvalidParameterType(): void
    {
        $lecture = $this->createLectureWithMockClient([]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_PARAMETER);

        try {
            $lecture->call('objet-touristique/get-by-id', ['id' => 'not-an-int']);
        } catch (ApidaeException $e) {
            $this->assertContains('id must be an integer', $e->getDetails()['errors']);

            throw $e;
        }
    }

    public function testCallUriEndpointBuildsGetRequest(): void
    {
        $lecture = $this->createMockLecture([
            new Response(200, [], '{"id":1234,"nom":"Test"}'),
        ]);

        $result = $lecture->call('objet-touristique/get-by-id', ['id' => 1234], 'array');

        $this->assertSame(['id' => 1234, 'nom' => 'Test'], $result);
        $this->assertCount(1, $this->guzzleHistory);

        $transaction = $this->guzzleHistory[0];
        $request = $transaction['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith(
            'objet-touristique/get-by-id/1234',
            (string) $request->getUri()->getPath()
        );
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame(self::PROJET_ID, $query['projetId']);
        $this->assertSame(self::API_KEY, $query['apiKey']);
    }

    public function testCallQueryEndpointBuildsJsonQueryParameter(): void
    {
        $lecture = $this->createMockLecture([
            new Response(200, [], '[]'),
        ]);

        $lecture->call('referentiel/selections', [
            'selectionIds' => [10, 20],
        ]);

        $this->assertCount(1, $this->guzzleHistory);
        parse_str($this->guzzleHistory[0]['request']->getUri()->getQuery(), $query);

        $apidaeQuery = json_decode($query['query'], true);
        $this->assertSame(self::PROJET_ID, $apidaeQuery['projetId']);
        $this->assertSame(self::API_KEY, $apidaeQuery['apiKey']);
        $this->assertSame([10, 20], $apidaeQuery['selectionIds']);
    }

    public function testCallQueryOptionsEndpointAcceptsSearchParameters(): void
    {
        $lecture = $this->createMockLecture([
            new Response(200, [], '{"numFound":0,"objetsTouristiques":[]}'),
        ]);

        $result = $lecture->call('recherche/list-objets-touristiques', [
            'first' => 0,
            'count' => 10,
            'criteresQuery' => 'type:EQUIPEMENT',
        ], 'array');

        $this->assertSame(0, $result['numFound']);
        parse_str($this->guzzleHistory[0]['request']->getUri()->getQuery(), $query);
        $apidaeQuery = json_decode($query['query'], true);
        $this->assertSame(0, $apidaeQuery['first']);
        $this->assertSame(10, $apidaeQuery['count']);
        $this->assertSame('type:EQUIPEMENT', $apidaeQuery['criteresQuery']);
    }

    public function testCallPostEndpointUsesFormParams(): void
    {
        $lecture = $this->createMockLecture([
            new Response(200, [], '[]'),
        ]);

        $lecture->call('referentiel/elements-reference/autorises', [
            'type' => 'FETE_ET_MANIFESTATION',
            'field' => 'descriptionTarif.modesPaiement',
            'contexte' => [],
        ]);

        $this->assertCount(1, $this->guzzleHistory);
        $request = $this->guzzleHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());

        parse_str((string) $request->getBody(), $formParams);
        $apidaeQuery = json_decode($formParams['query'], true);
        $this->assertSame('FETE_ET_MANIFESTATION', $apidaeQuery['type']);
        $this->assertSame('descriptionTarif.modesPaiement', $apidaeQuery['field']);
        $this->assertSame([], $apidaeQuery['contexte']);
    }

    public function testCallReturnsObjectByDefault(): void
    {
        $lecture = $this->createLectureWithMockClient([
            new Response(200, [], '{"id":1}'),
        ]);

        $result = $lecture->call('referentiel/selections', ['selectionIds' => [1]]);

        $this->assertIsObject($result);
        $this->assertSame(1, $result->id);
    }

    public function testCallReturnsRawBodyWhenNotJson(): void
    {
        $lecture = $this->createLectureWithMockClient([
            new Response(200, [], 'plain text'),
        ]);

        $result = $lecture->call('referentiel/selections', ['selectionIds' => [1]]);

        $this->assertSame('plain text', $result);
    }

    public function testCallThrowsOnHttpErrorWithJsonBody(): void
    {
        $lecture = $this->createLectureWithMockClient([
            new RequestException(
                'Error',
                new Request('GET', 'test'),
                new Response(400, [], '{"error":"bad_request"}')
            ),
        ]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_HTTPCODE);
        $this->expectExceptionMessage('API RequestException : 400');

        $lecture->call('referentiel/selections', ['selectionIds' => [1]]);
    }

    public function testCallThrowsOnNon200Response(): void
    {
        $lecture = $this->createLectureWithMockClient([
            new Response(500, [], 'error'),
        ]);

        $this->expectException(ApidaeException::class);
        $this->expectExceptionCode(ApidaeException::INVALID_HTTPCODE);
        $this->expectExceptionMessage('API RequestException : 500');

        $lecture->call('referentiel/selections', ['selectionIds' => [1]]);
    }

    public function testCallAcceptsNullParams(): void
    {
        $lecture = $this->createLectureWithMockClient([
            new Response(200, [], '[]'),
        ]);

        $result = $lecture->call('referentiel/selections', null, 'array');

        $this->assertSame([], $result);
    }

    /**
     * @param array<int, Response|RequestException> $responses
     */
    private function createLectureWithMockClient(array $responses): ApidaeLectureTestable
    {
        return $this->createMockLecture($responses);
    }

    /**
     * @param array<int, Response|RequestException> $responses
     */
    private function createMockLecture(array $responses): ApidaeLectureTestable
    {
        $this->guzzleHistory = [];
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->guzzleHistory));
        $client = new Client([
            'handler' => $handler,
            'base_uri' => 'http://localhost/api/v002/',
        ]);

        return new ApidaeLectureTestable($this->baseParams(), $client);
    }

    private function baseParams(): array
    {
        return [
            'projet_consultation_projetId' => self::PROJET_ID,
            'projet_consultation_apiKey' => self::API_KEY,
            'timer' => false,
            'url_api' => 'http://localhost/',
        ];
    }

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }
}
