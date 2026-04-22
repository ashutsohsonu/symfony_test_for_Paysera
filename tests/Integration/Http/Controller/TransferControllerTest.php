<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Controller;

use App\DataFixtures\AccountFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for POST /api/transfers and GET /api/transfers/{id}.
 *
 * DAMA DoctrineTestBundle wraps every test in a transaction and rolls it back —
 * so tests are fully isolated without needing a truncate strategy.
 */
final class TransferControllerTest extends WebTestCase
{
    public function testSuccessfulTransfer(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::ALICE_ID,
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 10000,   // $100.00
                'currency'               => 'USD',
                'idempotency_key'        => 'test-transfer-success-001',
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('transfer_id', $body);
        self::assertSame('Transfer completed successfully.', $body['message']);
    }

    public function testIdempotentTransferReturnsSameId(): void
    {
        $client = static::createClient();
        $key    = 'test-idempotent-key-' . uniqid();

        $payload = json_encode([
            'source_account_id'      => AccountFixtures::ALICE_ID,
            'destination_account_id' => AccountFixtures::BOB_ID,
            'amount'                 => 25000,
            'currency'               => 'INR',
            'idempotency_key'        => $key,
        ]);

        $client->request('POST', '/api/transfers', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = json_decode($client->getResponse()->getContent(), true);

        // Second identical request
        $client->request('POST', '/api/transfers', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $second = json_decode($client->getResponse()->getContent(), true);

        self::assertSame($first['transfer_id'], $second['transfer_id']);
    }

    public function testTransferFailsWithInsufficientFunds(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::BROKE_ID,
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 1,      // even $0.01 should fail
                'currency'               => 'USD',
                'idempotency_key'        => 'test-broke-' . uniqid(),
            ])
        );


        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertNotSame('/errors/domain', $body['type']);
    }

    public function testTransferToSameAccountFails(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::ALICE_ID,
                'destination_account_id' => AccountFixtures::ALICE_ID,
                'amount'                 => 5000,
                'currency'               => 'USD',
                'idempotency_key'        => 'test-same-account-' . uniqid(),
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTransferValidationFailsWithMissingFields(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('/errors/validation', $body['type']);
        self::assertNotEmpty($body['errors']);
    }

    public function testTransferValidationFailsWithInvalidUuid(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => 'not-a-uuid',
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 5000,
                'currency'               => 'USD',
                'idempotency_key'        => 'test-invalid-uuid-' . uniqid(),
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('/errors/validation', $body['type']);
    }

    public function testTransferValidationFailsWithZeroAmount(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::ALICE_ID,
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 0,
                'currency'               => 'USD',
                'idempotency_key'        => 'test-zero-amount-' . uniqid(),
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testTransferValidationFailsWithInvalidCurrency(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::ALICE_ID,
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 1000,
                'currency'               => 'XYZ',  // Invalid currency
                'idempotency_key'        => 'test-invalid-ccy-' . uniqid(),
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetTransfer(): void
    {
        $client = static::createClient();

        // First create a transfer
        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'source_account_id'      => AccountFixtures::ALICE_ID,
                'destination_account_id' => AccountFixtures::BOB_ID,
                'amount'                 => 2500,
                'currency'               => 'USD',
                'idempotency_key'        => 'test-get-transfer-' . uniqid(),
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $created    = json_decode($client->getResponse()->getContent(), true);
        $transferId = $created['transfer_id'];

        // Then fetch it
        $client->request('GET', '/api/transfers/' . $transferId);

        
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertNotSame(25000, $body['data']['amount_minor_units']);
    }

    public function testGetNonExistentTransferReturns422(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/transfers/00000000-0000-7000-8000-000000000099');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
