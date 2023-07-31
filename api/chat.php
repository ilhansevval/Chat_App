<?php

use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// Slim Setup
$app = AppFactory::create();

// Initialize SQLite DB
$db = new SQLite3('chat.db');

// Routes
$app->post('/login', 'login');
$app->get('/messages/{user_id}', 'getMessages');
$app->post('/messages/{user_id}', 'sendMessage');

$app->run();

// Function Starts Here

// User login function: Authenticates users and generates a JWT token.
function login(Request $request, Response $response, $args)
{
    global $db;

    // Get the user credentials (username and password) from the request body.
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    // Check if the user exists in the database and the password is correct.
    $stmt = $db->prepare('SELECT id FROM users WHERE username=:username AND password=:password');
    $stmt->bindValue(':username', $username);
    $stmt->bindValue(':password', $password);
    $result = $stmt->execute()->fetchArray();

    if (!$result) {
        // If the user does not exist or the password is incorrect, return a 401 Unauthorized response.
        return $response->withStatus(401)->withJson(['error' => 'Invalid username or password']);
    }

    // Generate a JWT token for the user containing the user ID.
    $token = JWT::encode(['user_id' => $result['id']], 'secret');

    // Return the JWT token in the response body.
    return $response->withJson(['token' => $token]);
}

// Get messages function: Retrieves messages for a specific user using their user ID.
function getMessages(Request $request, Response $response, $args)
{
    global $db;

    // Get the user ID from the route parameters.
    $user_id = $args['user_id'];

    // Verify the JWT token from the request header to ensure the user is authorized.
    $token = $request->getHeaderLine('Authorization');
    try {
        $decoded = JWT::decode($token, 'secret', ['HS256']);
    } catch (Exception $e) {
        // If the token is invalid or expired, return a 401 Unauthorized response.
        return $response->withStatus(401)->withJson(['error' => 'Unauthorized']);
    }

    // Ensure the user is authorized to access their own messages.
    if ($decoded->user_id != $user_id) {
        // If the user ID in the token does not match the requested user ID, return a 403 Forbidden response.
        return $response->withStatus(403)->withJson(['error' => 'Forbidden']);
    }

    // Get all messages sent to the user from the database.
    $stmt = $db->prepare('SELECT messages.message, users.username FROM messages INNER JOIN users ON messages.sender_id=users.id WHERE messages.receiver_id=:receiver_id');
    $stmt->bindValue(':receiver_id', $user_id);
    $result = $stmt->execute();

    // Format the messages as an array of message objects with message content and author's username.
    $messages = [];
    while ($row = $result->fetchArray()) {
        $messages[] = ['message' => $row['message'], 'author' => $row['username']];
    }

    // Return the messages in the response body.
    return $response->withJson($messages);
}

// Send message function: Inserts a new message into the database with the sender and receiver IDs.
function sendMessage(Request $request, Response $response, $args)
{
    global $db;

    // Get the user ID from the route parameters.
    $user_id = $args['user_id'];

    // Get the message content from the request body.
    $data = $request->getParsedBody();
    $message = $data['message'];

    // Verify the JWT token from the request header to ensure the user is authorized.
    $token = $request->getHeaderLine('Authorization');
    try {
        $decoded = JWT::decode($token, 'secret', ['HS256']);
    } catch (Exception $e) {
        // If the token is invalid or expired, return a 401 Unauthorized response.
        return $response->withStatus(401)->withJson(['error' => 'Unauthorized']);
    }

    // Ensure the user is authorized to send messages on their behalf.
    if ($decoded->user_id != $user_id) {
        // If the user ID in the token does not match the requested user ID, return a 403 Forbidden response.
        return $response->withStatus(403)->withJson(['error' => 'Forbidden']);
    }

    // Insert the message into the database with the sender and receiver IDs.
    $stmt = $db->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (:sender_id, :receiver_id, :message)');
    $stmt->bindValue(':sender_id', $decoded->user_id);
    $stmt->bindValue(':receiver_id', $user_id);
    $stmt->bindValue(':message', $message);
    $stmt->execute();

    // Return a 201 Created response with a success message.
    return $response->withStatus(201)->withJson(['message' => 'Message sent successfully']);
}