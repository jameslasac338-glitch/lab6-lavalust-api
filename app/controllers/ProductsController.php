<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
        $this->call->library('api');
    }

    private function validate_product($data, $partial = false)
    {
        $errors = [];

        if (!$partial || array_key_exists('product_name', $data)) {
            $name = trim($data['product_name'] ?? '');
            if ($name === '' || strlen($name) > 100) {
                $errors[] = 'product_name is required (max 100 characters).';
            }
        }
        if (!$partial || array_key_exists('price', $data)) {
            if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
                $errors[] = 'price must be a number that is 0 or higher.';
            }
        }
        if (!$partial || array_key_exists('quantity', $data)) {
            if (!isset($data['quantity']) || filter_var($data['quantity'], FILTER_VALIDATE_INT) === false || $data['quantity'] < 0) {
                $errors[] = 'quantity must be a whole number that is 0 or higher.';
            }
        }

        if (!empty($errors)) {
            $this->api->respond(['error' => 'Validation failed.', 'details' => $errors, 'status' => 422], 422);
        }
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $products = $this->db->table('products')
            ->order_by('id', 'DESC')
            ->get_all();

        $this->api->respond(['data' => $products]);
    }

    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $product = $this->db->table('products')->where('id', (int) $id)->get();
        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['data' => $product]);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->api->body();
        $this->validate_product($data);

        $id = $this->db->table('products')->insert([
            'product_name' => trim($data['product_name']),
            'description'  => $data['description'] ?? null,
            'price'        => (float) $data['price'],
            'quantity'     => (int) $data['quantity'],
        ]);

        $product = $this->db->table('products')->where('id', (int) $id)->get();
        $this->api->respond(['message' => 'Product created.', 'data' => $product], 201);
    }

    public function update($id)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method !== 'PUT' && $method !== 'PATCH') {
            $this->api->respond_error('Method not allowed.', 405);
        }
        $this->api->require_jwt();

        $id = (int) $id;
        $existing = $this->db->table('products')->where('id', $id)->get();
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $data = $this->api->body();
        $this->validate_product($data, $method === 'PATCH');

        $fields = [];
        if (array_key_exists('product_name', $data)) $fields['product_name'] = trim($data['product_name']);
        if (array_key_exists('description', $data))  $fields['description']  = $data['description'];
        if (array_key_exists('price', $data))        $fields['price']        = (float) $data['price'];
        if (array_key_exists('quantity', $data))     $fields['quantity']     = (int) $data['quantity'];

        if (empty($fields)) {
            $this->api->respond_error('No fields to update.', 422);
        }

        $this->db->table('products')->where('id', $id)->update($fields);

        $product = $this->db->table('products')->where('id', $id)->get();
        $this->api->respond(['message' => 'Product updated.', 'data' => $product]);
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        $id = (int) $id;
        $existing = $this->db->table('products')->where('id', $id)->get();
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->db->table('products')->where('id', $id)->delete();
        $this->api->respond(['message' => 'Product deleted.']);
    }
}