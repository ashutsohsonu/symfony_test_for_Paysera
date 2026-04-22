<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http\Controller;

use App\DataFixtures\AccountFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for POST /api/accounts and GET /api/accounts/{id}.
 */
final class AccountControllerTest extends WebTestCase
{
    public function testCreateAccount(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/accounts',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'owner_name'      => 'Charlie Brown',
                'currency'        => 'EUR',
                'initial_balance' => 50000, // €500.00
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('account_id', $body);
        self::assertNotEmpty($body['account_id']);
    }

    public function testCreateAccountValidationFailsWithEmptyName(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/accounts',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'owner_name' => '',
                'currency'   => 'USD',
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('/errors/validation', $body['type']);
    }

    public function testCreateAccountValidationFailsWithInvalidCurrency(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/accounts',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'owner_name' => 'Test User',
                'currency'   => 'DOGE',
            ])
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetAccount(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/accounts/' . AccountFixtures::ALICE_ID);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(AccountFixtures::ALICE_ID, $body['data']['id']);
        self::assertSame('Ashutosh', $body['data']['owner_name']);
        self::assertSame('INR', $body['data']['currency']);
        self::assertSame(25000, $body['data']['balance_minor_units']);
        self::assertTrue($body['data']['active']);
    }

    public function testGetNonExistentAccount(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/accounts/00000000-0000-7000-8000-000000009999');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
