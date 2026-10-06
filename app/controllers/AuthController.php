<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    public function register()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $username = trim($data['username'] ?? '');
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $this->api->respond_error('Username, email and password are required.', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('Invalid email address.', 422);
        }
        if (strlen($password) < 6) {
            $this->api->respond_error('Password must be at least 6 characters.', 422);
        }

        $exists = $this->db->table('users')
            ->where('email', $email)
            ->get();
        if ($exists) {
            $this->api->respond_error('Email is already registered.', 409);
        }

        $id = $this->db->table('users')->insert([
            'username' => $username,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => 'user',
        ]);

        $this->api->respond([
            'message' => 'User registered successfully.',
            'user'    => ['id' => $id, 'username' => $username, 'email' => $email],
        ], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->api->respond_error('Email and password are required.', 422);
        }

        $user = $this->db->table('users')
            ->where('email', $email)
            ->get();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid email or password.', 401);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->api->respond_error('Account is disabled.', 403);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => $user['id'],
            'role'   => $user['role'],
            'scopes' => ['read'],
        ]);

        $this->api->respond([
            'message' => 'Login successful.',
            'user'    => [
                'id'       => $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
            ],
            'tokens'  => $tokens,
        ]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $refresh_token = $data['refresh_token'] ?? '';
        if ($refresh_token === '') {
            $this->api->respond_error('refresh_token is required.', 422);
        }

        $this->api->refresh_access_token($refresh_token);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $data = $this->api->body();

        $refresh_token = $data['refresh_token'] ?? '';
        if ($refresh_token !== '') {
            $this->api->revoke_refresh_token($refresh_token);
        }

        $this->api->respond(['message' => 'Logged out successfully.']);
    }
}