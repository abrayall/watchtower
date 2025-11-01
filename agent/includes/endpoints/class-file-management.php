<?php
class Watchtower_Agent_File_Management {
    private $wordpress_root;

    public function __construct() {
        $this->wordpress_root = ABSPATH;
    }

    public function register_routes() {
        register_rest_route('watchtower-agent/v1', '/files/list', array(
            'methods' => 'POST',
            'callback' => array($this, 'list_files'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('watchtower-agent/v1', '/files/content', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_file_content'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('watchtower-agent/v1', '/files/save', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_file'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('watchtower-agent/v1', '/files/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_file'),
            'permission_callback' => array($this, 'check_permission')
        ));

        register_rest_route('watchtower-agent/v1', '/files/create-directory', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_directory'),
            'permission_callback' => array($this, 'check_permission')
        ));
    }

    public function check_permission($request) {
        return current_user_can('manage_options');
    }

    private function sanitize_path($path) {
        $path = trim($path);
        if (empty($path) || $path === '/') {
            return $this->wordpress_root;
        }

        $path = ltrim($path, '/');
        $full_path = $this->wordpress_root . $path;
        $real_path = realpath($full_path);

        if ($real_path === false) {
            $real_path = $full_path;
        }

        $normalized_root = rtrim(realpath($this->wordpress_root), DIRECTORY_SEPARATOR);
        $normalized_path = rtrim($real_path, DIRECTORY_SEPARATOR);

        if (strpos($normalized_path, $normalized_root) !== 0) {
            return null;
        }

        return $real_path;
    }

    public function list_files($request) {
        $path = $request->get_param('path');
        $full_path = $this->sanitize_path($path);

        if ($full_path === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid path - access denied'
            ), 403);
        }

        if (!file_exists($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path does not exist'
            ), 404);
        }

        if (!is_dir($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path is not a directory'
            ), 400);
        }

        $items = array();
        $entries = scandir($full_path);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $item_path = $full_path . DIRECTORY_SEPARATOR . $entry;
            $relative_path = str_replace($this->wordpress_root, '', $item_path);
            $relative_path = '/' . ltrim($relative_path, '/');

            $items[] = array(
                'name' => $entry,
                'path' => $relative_path,
                'type' => is_dir($item_path) ? 'directory' : 'file',
                'size' => is_file($item_path) ? filesize($item_path) : null,
                'modified' => filemtime($item_path),
                'permissions' => substr(sprintf('%o', fileperms($item_path)), -4),
                'readable' => is_readable($item_path),
                'writable' => is_writable($item_path)
            );
        }

        usort($items, function($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return new WP_REST_Response(array(
            'success' => true,
            'path' => str_replace($this->wordpress_root, '/', $full_path),
            'items' => $items
        ), 200);
    }

    public function get_file_content($request) {
        $path = $request->get_param('path');
        $full_path = $this->sanitize_path($path);

        if ($full_path === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid path - access denied'
            ), 403);
        }

        if (!file_exists($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'File does not exist'
            ), 404);
        }

        if (!is_file($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path is not a file'
            ), 400);
        }

        if (!is_readable($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'File is not readable'
            ), 403);
        }

        $content = file_get_contents($full_path);

        if ($content === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to read file'
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'path' => str_replace($this->wordpress_root, '/', $full_path),
            'content' => $content,
            'size' => filesize($full_path),
            'modified' => filemtime($full_path)
        ), 200);
    }

    public function save_file($request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');

        if (empty($path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path parameter is required'
            ), 400);
        }

        if ($content === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Content parameter is required'
            ), 400);
        }

        $full_path = $this->sanitize_path($path);

        if ($full_path === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid path - access denied'
            ), 403);
        }

        $dir = dirname($full_path);
        if (!is_dir($dir)) {
            if (!wp_mkdir_p($dir)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Failed to create directory'
                ), 500);
            }
        }

        $file_exists = file_exists($full_path);

        $result = file_put_contents($full_path, $content);

        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to write file'
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => $file_exists ? 'File updated successfully' : 'File created successfully',
            'path' => str_replace($this->wordpress_root, '/', $full_path),
            'size' => filesize($full_path)
        ), 200);
    }

    public function delete_file($request) {
        $path = $request->get_param('path');

        if (empty($path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path parameter is required'
            ), 400);
        }

        $full_path = $this->sanitize_path($path);

        if ($full_path === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid path - access denied'
            ), 403);
        }

        if (!file_exists($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'File does not exist'
            ), 404);
        }

        if (!is_file($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path is not a file'
            ), 400);
        }

        if (!is_writable($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'File is not writable'
            ), 403);
        }

        if (!unlink($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to delete file'
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'File deleted successfully',
            'path' => str_replace($this->wordpress_root, '/', $full_path)
        ), 200);
    }

    public function create_directory($request) {
        $path = $request->get_param('path');

        if (empty($path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Path parameter is required'
            ), 400);
        }

        $full_path = $this->sanitize_path($path);

        if ($full_path === null) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid path - access denied'
            ), 403);
        }

        if (file_exists($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Directory or file already exists'
            ), 400);
        }

        if (!wp_mkdir_p($full_path)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to create directory'
            ), 500);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Directory created successfully',
            'path' => str_replace($this->wordpress_root, '/', $full_path)
        ), 200);
    }
}
