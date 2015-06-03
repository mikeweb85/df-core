<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Testing;

use DreamFactory\Library\Utility\Enums\Verbs;
use Illuminate\Support\Arr;
use Auth;
use Hash;

class UserResourceTestCase extends TestCase
{
    const RESOURCE = 'foo';

    protected $serviceId = 'system';

    protected $user1 = [
        'name'              => 'John Doe',
        'first_name'        => 'John',
        'last_name'         => 'Doe',
        'email'             => 'jdoe@dreamfactory.com',
        'password'          => 'test1234',
        'security_question' => 'Make of your first car?',
        'security_answer'   => 'mazda',
        'is_active'         => 1
    ];

    protected $user2 = [
        'name'                   => 'Jane Doe',
        'first_name'             => 'Jane',
        'last_name'              => 'Doe',
        'email'                  => 'jadoe@dreamfactory.com',
        'password'               => 'test1234',
        'is_active'              => 1,
        'user_lookup_by_user_id' => [
            [
                'name'    => 'test',
                'value'   => '1234',
                'private' => 0
            ],
            [
                'name'    => 'test2',
                'value'   => '5678',
                'private' => 1
            ]
        ]
    ];

    protected $user3 = [
        'name'                   => 'Dan Doe',
        'first_name'             => 'Dan',
        'last_name'              => 'Doe',
        'email'                  => 'ddoe@dreamfactory.com',
        'password'               => 'test1234',
        'is_active'              => 1,
        'user_lookup_by_user_id' => [
            [
                'name'    => 'test',
                'value'   => '1234',
                'private' => 0
            ],
            [
                'name'    => 'test2',
                'value'   => '5678',
                'private' => 1
            ],
            [
                'name'    => 'test3',
                'value'   => '56789',
                'private' => 1
            ]
        ]
    ];

    public function tearDown()
    {
        $this->deleteUser( 1 );
        $this->deleteUser( 2 );
        $this->deleteUser( 3 );

        parent::tearDown();
    }

    /************************************************
     * Testing POST
     ************************************************/
    public function testPOSTCreateAdmins()
    {
        $payload = json_encode( [ $this->user1, $this->user2 ], JSON_UNESCAPED_SLASHES );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );
        $content = $rs->getContent();
        $data = Arr::get( $content, 'record' );

        $this->assertEquals( Arr::get( $this->user1, 'email' ), Arr::get( $data, '0.email' ) );
        $this->assertEquals( Arr::get( $this->user2, 'email' ), Arr::get( $data, '1.email' ) );
        $this->assertEquals( 0, count( Arr::get( $data, '0.user_lookup_by_user_id' ) ) );
        $this->assertEquals( 2, count( Arr::get( $data, '1.user_lookup_by_user_id' ) ) );
        $this->assertTrue( $this->adminCheck( $data ) );
    }

    public function testPOSTCreateAdmin()
    {
        $payload = json_encode( [ $this->user3 ], JSON_UNESCAPED_SLASHES );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );
        $data = $rs->getContent();

        $this->assertEquals( Arr::get( $this->user3, 'email' ), Arr::get( $data, 'email' ) );
        $this->assertEquals( 3, count( Arr::get( $data, 'user_lookup_by_user_id' ) ) );
        $this->assertEquals( '**********', Arr::get( $data, 'user_lookup_by_user_id.1.value' ) );
        $this->assertEquals( '**********', Arr::get( $data, 'user_lookup_by_user_id.2.value' ) );
        $this->assertTrue( $this->adminCheck( [ $data ] ) );
    }

    /************************************************
     * Testing PATCH
     ************************************************/

    public function testPATCHById()
    {
        $user = $this->createUser( 1 );

        $data = [
            'name'                   => 'Julie Doe',
            'first_name'             => 'Julie',
            'user_lookup_by_user_id' => [
                [
                    'name'  => 'param1',
                    'value' => '1234'
                ]
            ]
        ];

        $payload = json_encode( $data, JSON_UNESCAPED_SLASHES );

        $rs = $this->makeRequest( Verbs::PATCH, static::RESOURCE . '/' . $user['id'], [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );
        $content = $rs->getContent();

        $this->assertEquals( 'Julie Doe', $content['name'] );
        $this->assertEquals( 'Julie', $content['first_name'] );
        $this->assertEquals( 'param1', Arr::get( $content, 'user_lookup_by_user_id.0.name' ) );
        $this->assertEquals( '1234', Arr::get( $content, 'user_lookup_by_user_id.0.value' ) );

        Arr::set( $content, 'user_lookup_by_user_id.0.name', 'my_param' );
        Arr::set( $content, 'user_lookup_by_user_id.1', [ 'name' => 'param2', 'value' => 'secret', 'private' => 1 ] );

        $rs = $this->makeRequest(
            Verbs::PATCH,
            static::RESOURCE . '/' . $user['id'],
            [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ],
            json_encode( $content, JSON_UNESCAPED_SLASHES )
        );

        $content = $rs->getContent();

        $this->assertEquals( 'my_param', Arr::get( $content, 'user_lookup_by_user_id.0.name' ) );
        $this->assertEquals( '**********', Arr::get( $content, 'user_lookup_by_user_id.1.value' ) );
        $this->assertTrue( $this->adminCheck( [ $content ] ) );
    }

    public function testPATCHByIds()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $payload = json_encode(
            [
                [
                    'is_active'              => 0,
                    'user_lookup_by_user_id' => [
                        [
                            'name'  => 'common',
                            'value' => 'common name'
                        ]
                    ]
                ]
            ],
            JSON_UNESCAPED_SLASHES
        );

        $ids = implode( ',', array_column( [ $user1, $user2, $user3 ], 'id' ) );
        $rs = $this->makeRequest( Verbs::PATCH, static::RESOURCE, [ 'ids' => $ids, 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );
        $content = $rs->getContent();
        $data = $content['record'];

        foreach ( $data as $user )
        {
            $this->assertEquals( 0, $user['is_active'] );
        }

        $this->assertEquals( 'common name', Arr::get( $data, '0.user_lookup_by_user_id.0.value' ) );
        $this->assertEquals( 'common name', Arr::get( $data, '1.user_lookup_by_user_id.2.value' ) );
        $this->assertEquals( 'common name', Arr::get( $data, '2.user_lookup_by_user_id.3.value' ) );
        $this->assertTrue( $this->adminCheck( $data ) );
    }

    public function testPATCHByRecords()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        Arr::set( $user1, 'first_name', 'Kevin' );
        Arr::set( $user2, 'first_name', 'Lloyed' );
        Arr::set( $user3, 'first_name', 'Jack' );

        $payload = json_encode( [ $user1, $user2, $user3 ], JSON_UNESCAPED_SLASHES );

        $rs = $this->makeRequest( Verbs::PATCH, static::RESOURCE, [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );
        $content = $rs->getContent();

        $this->assertEquals( $user1['first_name'], Arr::get( $content, 'record.0.first_name' ) );
        $this->assertEquals( $user2['first_name'], Arr::get( $content, 'record.1.first_name' ) );
        $this->assertEquals( $user3['first_name'], Arr::get( $content, 'record.2.first_name' ) );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    public function testPATCHPassword()
    {
        $user = $this->createUser( 1 );

        Arr::set( $user, 'password', '1234' );

        $payload = json_encode( $user, JSON_UNESCAPED_SLASHES );
        $rs = $this->makeRequest( Verbs::PATCH, static::RESOURCE . '/' . $user['id'], [ ], $payload );
        $content = $rs->getContent();

        $this->assertTrue( Auth::attempt( [ 'email' => $user['email'], 'password' => '1234' ] ) );
        $this->assertTrue( $this->adminCheck( [ $content ] ) );
    }

    public function testPATCHSecurityAnswer()
    {
        $user = $this->createUser( 1 );

        Arr::set( $user, 'security_answer', 'mazda' );

        $payload = json_encode( $user, JSON_UNESCAPED_SLASHES );
        $rs = $this->makeRequest( Verbs::PATCH, static::RESOURCE . '/' . $user['id'], [ 'fields' => 'id,security_answer' ], $payload );
        $content = $rs->getContent();

        $this->assertTrue( $this->adminCheck( [ $content ] ) );
        $this->assertTrue( Hash::check( 'mazda', $content['security_answer'] ) );
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGET()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE );
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals( 4, count( $content['record'] ) );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );

        $ids = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( implode( ',', array_column( [ [ 'id' => 1 ], $user1, $user2, $user3 ], 'id' ) ), $ids );
    }

    public function testGETById()
    {
        $user = $this->createUser( 2 );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE . '/' . $user['id'], [ 'related' => 'user_lookup_by_user_id' ] );
        $data = $rs->getContent();

        $this->assertEquals( $user['name'], $data['name'] );
        $this->assertTrue( $this->adminCheck( [ $data ] ) );

        $this->assertTrue( $this->adminCheck( [ $data ] ) );
        $this->assertEquals( count( $user['user_lookup_by_user_id'] ), count( $data['user_lookup_by_user_id'] ) );
    }

    public function testGETByIds()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $ids = implode( ',', array_column( [ $user1, $user2, $user3 ], 'id' ) );
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'ids' => $ids ] );
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals( 3, count( $content['record'] ) );

        $idsOut = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( $ids, $idsOut );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    public function testGETByRecord()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $payload = json_encode( [ $user1, $user2, $user3 ], JSON_UNESCAPED_SLASHES );

        $ids = implode( ',', array_column( [ $user1, $user2, $user3 ], 'id' ) );
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ ], $payload );
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals( 3, count( $content['record'] ) );

        $idsOut = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( $ids, $idsOut );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    public function testGETByFilterFirstNameLastName()
    {
        $this->createUser( 1 );
        $this->createUser( 2 );
        $this->createUser( 3 );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'filter' => "first_name='Dan'" ] );
        $content = $rs->getContent();
        $data = $content['record'];
        $firstNames = array_column( $data, 'first_name' );

        $this->assertTrue( in_array( 'Dan', $firstNames ) );
        $this->assertEquals( 1, count( $data ) );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'filter' => "last_name='doe'" ] );
        $content = $rs->getContent();
        $data = $content['record'];
        $firstNames = array_column( $data, 'first_name' );
        $lastNames = array_column( $data, 'last_name' );

        $this->assertTrue( in_array( 'Dan', $firstNames ) );
        $this->assertTrue( in_array( 'Doe', $lastNames ) );
        $this->assertEquals( 3, count( $data ) );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    public function testGETWithLimitOffset()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'limit' => 3 ] );
        $content = $rs->getContent();

        $this->assertEquals( 3, count( $content['record'] ) );

        $idsOut = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( implode( ',', array_column( [ [ 'id' => 1 ], $user1, $user2 ], 'id' ) ), $idsOut );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'limit' => 3, 'offset' => 1 ] );
        $content = $rs->getContent();

        $this->assertEquals( 3, count( $content['record'] ) );

        $idsOut = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( implode( ',', array_column( [ $user1, $user2, $user3 ], 'id' ) ), $idsOut );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'limit' => 2, 'offset' => 2 ] );
        $content = $rs->getContent();

        $this->assertEquals( 2, count( $content['record'] ) );

        $idsOut = implode( ',', array_column( $content['record'], 'id' ) );
        $this->assertEquals( implode( ',', array_column( [ $user2, $user3 ], 'id' ) ), $idsOut );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    public function testGETWithOrder()
    {
        $user1 = $this->createUser( 1 );
        $user2 = $this->createUser( 2 );
        $user3 = $this->createUser( 3 );

        $ids = implode( ',', array_column( [ $user1, $user2, $user3 ], 'id' ) );
        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'ids' => $ids, 'order' => 'first_name' ] );
        $content = $rs->getContent();
        $data = $content['record'];
        $firstNames = implode( ',', array_column( $data, 'first_name' ) );

        $this->assertEquals( 'Dan,Jane,John', $firstNames );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'ids' => $ids, 'order' => 'first_name DESC' ] );
        $content = $rs->getContent();
        $data = $content['record'];
        $firstNames = implode( ',', array_column( $data, 'first_name' ) );

        $this->assertEquals( 'John,Jane,Dan', $firstNames );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE, [ 'ids' => $ids, 'order' => 'last_name,first_name DESC' ] );
        $content = $rs->getContent();
        $data = $content['record'];
        $firstNames = implode( ',', array_column( $data, 'first_name' ) );

        $this->assertEquals( 'John,Jane,Dan', $firstNames );
        $this->assertTrue( $this->adminCheck( $content['record'] ) );
    }

    /************************************************
     * Helper methods
     ************************************************/

    protected function createUser( $num )
    {
        $user = $this->{'user' . $num};
        $payload = json_encode( [ $user ], JSON_UNESCAPED_SLASHES );
        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE, [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], $payload );

        return $rs->getContent();
    }

    protected function deleteUser( $num )
    {
        $user = $this->{'user' . $num};
        $email = Arr::get( $user, 'email' );
        \DreamFactory\Rave\Models\User::whereEmail( $email )->delete();
    }

    protected function adminCheck( $records )
    {
        return false;
    }
}