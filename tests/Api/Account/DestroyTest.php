<?php

namespace tests\Api\Account;

use AbuseIO\Models\Account;
use AbuseIO\Models\User;
use tests\Api\DestroyTestHelper;
use tests\TestCase;

class DestroyTest extends TestCase
{
    use DestroyTestHelper;

    const URL = '/api/d41d8cd98f00b204e8000998ecf8427e/v1/accounts';

    public function initWithValidResponse()
    {
        $user = User::find(1);

        $account = factory(Account::class)->create();

        $response = $this->actingAs($user)->call('DELETE', self::getURLWithId($account->id));

        $this->statusCode = $response->getStatusCode();
        $this->content = $response->getContent();
    }

    private static function getURLWithId($id)
    {
        return sprintf('%s/%s', self::URL, $id);
    }

    public function testWithUndeleteable()
    {
        $user = User::find(1);

        $response = $this->actingAs($user)->call('DELETE', self::getURLWithId(1));

        $this->assertEquals(403, $response->getStatusCode());

        $this->assertJson($response->getContent());
    }

    /**
     * @return void
     */
    public function testStatusCodeInvalidRequest()
    {
        $this->initWithInvalidResponse();
        $this->assertEquals(404, $this->statusCode);
    }

    public function initWithInvalidResponse()
    {
        $user = User::find(1);

        $response = $this->actingAs($user)->call('DELETE', self::URL.'/200');

        $this->statusCode = $response->getStatusCode();
        $this->content = $response->getContent();
    }

    /**
     * @return void
     */
    public function testResponseInvalidRequest()
    {
        $this->initWithInvalidResponse();

        //todo: fix laravel 404 message

        //$obj = json_decode($this->content);

        //dd($this->content);

        //$this->assertTrue(
        //    property_exists($obj, 'message')
        //);

        //$this->assertFalse($obj->message->success);
    }
}