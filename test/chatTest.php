<?php

use PHPUnit\Framework\TestCase;

class ChatTest extends TestCase
{
    // Include the code to test
    require_once 'api/chat.php';

    // Mock the database connection (You may need to adjust this based on your database setup)
    protected function setUp(): void
    {
        $this->db = new SQLite3(':memory:');
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT UNIQUE, password TEXT)');
        $this->db->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY, sender_id INTEGER, receiver_id INTEGER, message TEXT)');
    }

    // Tests for login function
    public function testLoginSuccess()
    {
        // Prepare a request with valid credentials for login
        $request = new Request([], [], [], [], [], [], json_encode(['username' => 'testuser', 'password' => 'testpassword']));
        $response = new Response();

        // Call the login function
        $result = login($request, $response, []);

        // Verify that the response status code is 200 (Success) and contains a token in the response body
        $this->assertSame(200, $result->getStatusCode());
        $this->assertArrayHasKey('token', json_decode($result->getBody(), true));
    }

    public function testLoginFailure()
    {
        // Prepare a request with invalid credentials for login
        $request = new Request([], [], [], [], [], [], json_encode(['username' => 'testuser', 'password' => 'wrongpassword']));
        $response = new Response();

        // Call the login function
        $result = login($request, $response, []);

        // Verify that the response status code is 401 (Unauthorized) and contains an error message in the response body
        $this->assertSame(401, $result->getStatusCode());
        $this->assertArrayHasKey('error', json_decode($result->getBody(), true));
    }

    // Tests for getMessages function
    public function testGetMessagesSuccess()
    {
        // Insert test data into the database (assuming user_id 1 has some messages)
        $this->db->exec("INSERT INTO users (username, password) VALUES ('testuser', 'testpassword')");
        $this->db->exec("INSERT INTO messages (sender_id, receiver_id, message) VALUES (1, 2, 'Test message')");

        // Prepare a request with a valid JWT token (replace 'your_jwt_token_here' with an actual token)
        $request = new Request([], [], [], [], [], ['Authorization' => 'your_jwt_token_here'], []);
        $response = new Response();

        // Call the getMessages function for user with ID 1
        $result = getMessages($request, $response, ['user_id' => 1]);

        // Verify that the response status code is 200 (Success) and the response body is an array
        $this->assertSame(200, $result->getStatusCode());
        $this->assertIsArray(json_decode($result->getBody(), true));
    }


    // Tests for sendMessage function
    public function testSendMessageSuccess()
    {
        // Insert test data into the database (assuming user_id 1 exists)
        $this->db->exec("INSERT INTO users (username, password) VALUES ('testuser', 'testpassword')");

        // Prepare a request with a valid JWT token (replace 'your_jwt_token_here' with an actual token)
        $request = new Request([], [], [], [], [], ['Authorization' => 'your_jwt_token_here'], json_encode(['message' => 'Test message']));
        $response = new Response();

        // Call the sendMessage function for user with ID 1
        $result = sendMessage($request, $response, ['user_id' => 1]);

        // Verify that the response status code is 201 (Created) and the response body contains a success message
        $this->assertSame(201, $result->getStatusCode());
        $this->assertArrayHasKey('message', json_decode($result->getBody(), true));
    }

}
