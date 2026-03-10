<?php

if (!defined('ABSPATH')) {
    exit;
}

class Watchtower_Plugin_Catalog {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'watchtower_plugin_catalog';
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id VARCHAR(36) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            properties LONGTEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_by_slug($slug) {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE slug = %s", $slug),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['properties'] = json_decode($row['properties'], true) ?: array();
        return $row;
    }

    public function get_all() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY name ASC", ARRAY_A);

        foreach ($rows as &$row) {
            $row['properties'] = json_decode($row['properties'], true) ?: array();
        }

        return $rows;
    }

    public function upsert($slug, $name, $properties = array()) {
        global $wpdb;
        $existing = $this->get_by_slug($slug);

        if ($existing) {
            $merged = $existing['properties'];

            if (isset($properties['agent'])) {
                $merged['agent'] = $properties['agent'];
            }
            if (isset($properties['wp_org'])) {
                $merged['wp_org'] = $properties['wp_org'];
            }

            $wpdb->update(
                $this->table_name,
                array(
                    'name' => $name,
                    'properties' => json_encode($merged),
                    'updated_at' => current_time('mysql'),
                ),
                array('slug' => $slug),
                array('%s', '%s', '%s'),
                array('%s')
            );

            return $this->get_by_slug($slug);
        }

        $id = wp_generate_uuid4();
        $now = current_time('mysql');

        $wpdb->insert(
            $this->table_name,
            array(
                'id' => $id,
                'slug' => $slug,
                'name' => $name,
                'properties' => json_encode($properties),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        return $this->get_by_slug($slug);
    }

    public function update_properties($slug, $properties) {
        global $wpdb;
        $existing = $this->get_by_slug($slug);

        if (!$existing) {
            return null;
        }

        $merged = $existing['properties'];
        $user_fields = array('vendor', 'tags', 'license', 'cost', 'category', 'notes', 'licenses');

        foreach ($user_fields as $field) {
            if (array_key_exists($field, $properties)) {
                $merged[$field] = $properties[$field];
            }
        }

        $wpdb->update(
            $this->table_name,
            array(
                'properties' => json_encode($merged),
                'updated_at' => current_time('mysql'),
            ),
            array('slug' => $slug),
            array('%s', '%s'),
            array('%s')
        );

        return $this->get_by_slug($slug);
    }

    public function enrich_from_wp_org($slug) {
        $existing = $this->get_by_slug($slug);

        if ($existing) {
            $props = $existing['properties'];
            if (isset($props['wp_org']['fetched_at'])) {
                $fetched = strtotime($props['wp_org']['fetched_at']);
                if ($fetched && (time() - $fetched) < 7 * 86400) {
                    return $existing;
                }
            }
            if (isset($props['wp_org']['not_found']) && $props['wp_org']['not_found']) {
                $fetched = isset($props['wp_org']['fetched_at']) ? strtotime($props['wp_org']['fetched_at']) : 0;
                if ($fetched && (time() - $fetched) < 7 * 86400) {
                    return $existing;
                }
            }
        }

        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . urlencode($slug);
        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            return $existing;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            $wp_org = array(
                'not_found' => true,
                'fetched_at' => current_time('mysql'),
            );
        } else {
            $wp_org = array(
                'fetched_at' => current_time('mysql'),
                'rating' => isset($data['rating']) ? $data['rating'] : null,
                'num_ratings' => isset($data['num_ratings']) ? $data['num_ratings'] : null,
                'active_installs' => isset($data['active_installs']) ? $data['active_installs'] : null,
                'downloaded' => isset($data['downloaded']) ? $data['downloaded'] : null,
                'last_updated' => isset($data['last_updated']) ? $data['last_updated'] : null,
                'tested' => isset($data['tested']) ? $data['tested'] : null,
                'short_description' => isset($data['short_description']) ? $data['short_description'] : null,
                'tags' => isset($data['tags']) ? $data['tags'] : null,
                'banners' => isset($data['banners']) ? $data['banners'] : null,
                'icons' => isset($data['icons']) ? $data['icons'] : null,
                'homepage' => isset($data['homepage']) ? $data['homepage'] : null,
            );
        }

        if ($existing) {
            $merged = $existing['properties'];
            $merged['wp_org'] = $wp_org;

            global $wpdb;
            $wpdb->update(
                $this->table_name,
                array(
                    'properties' => json_encode($merged),
                    'updated_at' => current_time('mysql'),
                ),
                array('slug' => $slug),
                array('%s', '%s'),
                array('%s')
            );

            return $this->get_by_slug($slug);
        }

        return null;
    }

    public function populate_from_agent_data($plugin) {
        $slug = isset($plugin['slug']) ? $plugin['slug'] : null;
        if (!$slug) {
            if (isset($plugin['file'])) {
                $parts = explode('/', $plugin['file']);
                $slug = $parts[0];
            } else {
                return null;
            }
        }

        $name = isset($plugin['name']) ? $plugin['name'] : $slug;

        $agent_data = array();
        $fields = array('author', 'author_uri', 'description', 'plugin_uri', 'requires_wp', 'requires_php');
        foreach ($fields as $field) {
            if (isset($plugin[$field])) {
                $agent_data[$field] = $plugin[$field];
            }
        }

        $properties = array();
        if (!empty($agent_data)) {
            $properties['agent'] = $agent_data;
        }

        $result = $this->upsert($slug, $name, $properties);
        $this->enrich_from_wp_org($slug);

        return $result;
    }

    public function get_display_data($slug) {
        $entry = $this->get_by_slug($slug);
        if (!$entry) {
            return null;
        }

        $props = $entry['properties'];
        $display = array(
            'slug' => $entry['slug'],
            'name' => $entry['name'],
        );

        $user_fields = array('vendor', 'tags', 'license', 'cost', 'category', 'notes');
        foreach ($user_fields as $field) {
            $display[$field] = isset($props[$field]) ? $props[$field] : '';
        }

        $display['licenses'] = isset($props['licenses']) ? $props['licenses'] : array();
        $display['agent'] = isset($props['agent']) ? $props['agent'] : array();
        $display['wp_org'] = isset($props['wp_org']) ? $props['wp_org'] : array();
        $display['versions'] = isset($props['versions']) ? $props['versions'] : array();

        return $display;
    }
}
