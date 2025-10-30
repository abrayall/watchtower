# WP Remote Agent

A WordPress plugin that provides REST API endpoints for remote WordPress management including user management, backups (via UpdraftPlus), and WordPress core/plugins/themes updates.

## Features

- **User Management**: Create, read, update, and delete WordPress users
- **Backup Management**: Trigger and manage backups using UpdraftPlus
- **Update Management**: Update WordPress core, plugins, and themes remotely
- **System Health**: Comprehensive health monitoring (PHP, memory, CPU, disk, database)
- **Auto-Generated Credentials**: Automatically creates application password on activation
- **Secure API**: Uses WordPress authentication and permission checks

## Installation

1. Copy the `wp-remote-agent` directory to your WordPress plugins folder:
   ```bash
   cp -r wp-remote-agent /path/to/wordpress/wp-content/plugins/
   ```

2. Or in Docker environment:
   ```bash
   docker cp wp-remote-agent wordpress_site:/var/www/html/wp-content/plugins/
   ```

3. Activate the plugin via WordPress admin panel or WP-CLI:
   ```bash
   wp plugin activate wp-remote-agent
   ```

## Authentication

All endpoints require authentication. Use WordPress Application Passwords or Basic Authentication:

```bash
curl -u "admin:YOUR_APP_PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/status
```

For your local environment:
```bash
curl -u "admin:DQ7w 6Xth 1DyA oLgZ uCIK k8n7" http://localhost:8082/wp-json/wp-remote-agent/v1/status
```

## API Endpoints

### Status

#### Get Plugin Status
```
GET /wp-json/wp-remote-agent/v1/status
```

**Response:**
```json
{
  "success": true,
  "version": "1.0.0",
  "wordpress_version": "6.4.2",
  "php_version": "8.0.0",
  "site_url": "http://localhost:8082",
  "admin_email": "admin@example.com",
  "updraftplus_installed": true,
  "timestamp": "2025-10-25 12:00:00"
}
```

---

### Health

#### Get System Health
```
GET /wp-json/wp-remote-agent/v1/health
```

Get comprehensive system health information including PHP, WordPress, database, memory, CPU, disk usage, and more.

**Example:**
```bash
curl -u "admin:PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/health
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-26 16:25:50",
  "php": {
    "version": "8.2.28",
    "memory_limit": "256M",
    "max_execution_time": "30",
    "upload_max_filesize": "2M",
    "post_max_size": "8M",
    "extensions": ["Core", "date", "curl", "mysqli", ...]
  },
  "memory": {
    "current": "5.05 MB",
    "current_bytes": 5293936,
    "peak": "5.07 MB",
    "peak_bytes": 5321328,
    "limit": "256M",
    "wp_memory_limit": "40M",
    "wp_max_memory_limit": "256M",
    "usage_percentage": "1.97%"
  },
  "wordpress": {
    "version": "6.8.1",
    "site_url": "http://localhost:8082",
    "home_url": "http://localhost:8082",
    "admin_email": "test@test.com",
    "language": "en_US",
    "timezone": "UTC",
    "debug_mode": false,
    "multisite": false
  },
  "database": {
    "database_name": "wordpress",
    "database_host": "db:3306",
    "database_version": "8.0.43",
    "database_size": "2.31 MB",
    "database_size_bytes": 2424832
  },
  "server": {
    "software": "Apache/2.4.62 (Debian)",
    "protocol": "HTTP/1.1",
    "server_name": "localhost",
    "server_ip": "172.19.0.4",
    "https": false
  },
  "disk": {
    "free": "49.81 GB",
    "used": "8.56 GB",
    "total": "58.37 GB",
    "usage_percentage": "14.67%"
  },
  "cpu": {
    "load_1min": 1.53,
    "load_5min": 1.49,
    "load_15min": 1.51
  },
  "plugins": {
    "active_count": 4,
    "active_plugins": [
      {
        "name": "WP Remote Agent",
        "version": "1.0.0",
        "file": "wp-remote-agent/wp-remote-agent.php"
      }
    ]
  },
  "theme": {
    "name": "Twenty Twenty-Five",
    "version": "1.2"
  },
  "constants": {
    "WP_DEBUG": false,
    "WP_CACHE": false
  },
  "uptime": {
    "uptime": "16:25:50 up 4 days, 10:19, 0 user, load average: 1.53, 1.49, 1.51"
  }
}
```

---

### User Management

#### List Users
```
GET /wp-json/wp-remote-agent/v1/users
```

**Parameters:**
- `role` (optional): Filter by role (e.g., administrator, editor, subscriber)
- `search` (optional): Search term for username or email

**Example:**
```bash
curl -u "admin:PASSWORD" "http://localhost:8082/wp-json/wp-remote-agent/v1/users?role=administrator"
```

**Response:**
```json
{
  "success": true,
  "users": [
    {
      "id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "display_name": "Admin",
      "roles": ["administrator"],
      "registered": "2025-01-01 00:00:00"
    }
  ],
  "count": 1
}
```

#### Create User
```
POST /wp-json/wp-remote-agent/v1/users
```

**Parameters:**
- `username` (required): Username
- `email` (required): Email address
- `password` (required): Password
- `role` (optional): User role (default: subscriber)
- `first_name` (optional): First name
- `last_name` (optional): Last name

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "newuser",
    "email": "newuser@example.com",
    "password": "SecurePass123!",
    "role": "editor",
    "first_name": "John",
    "last_name": "Doe"
  }' \
  http://localhost:8082/wp-json/wp-remote-agent/v1/users
```

**Response:**
```json
{
  "success": true,
  "message": "User created successfully",
  "user_id": 2,
  "username": "newuser",
  "email": "newuser@example.com",
  "role": "editor"
}
```

#### Get User
```
GET /wp-json/wp-remote-agent/v1/users/{id}
```

**Example:**
```bash
curl -u "admin:PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/users/2
```

#### Update User
```
PUT /wp-json/wp-remote-agent/v1/users/{id}
```

**Parameters:**
- `email` (optional): New email
- `password` (optional): New password
- `role` (optional): New role
- `first_name` (optional): First name
- `last_name` (optional): Last name

**Example:**
```bash
curl -X PUT -u "admin:PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "updated@example.com",
    "role": "author"
  }' \
  http://localhost:8082/wp-json/wp-remote-agent/v1/users/2
```

#### Delete User
```
DELETE /wp-json/wp-remote-agent/v1/users/{id}
```

**Parameters:**
- `reassign` (optional): User ID to reassign posts to

**Example:**
```bash
curl -X DELETE -u "admin:PASSWORD" \
  "http://localhost:8082/wp-json/wp-remote-agent/v1/users/2?reassign=1"
```

---

### Backup Management (UpdraftPlus)

#### Get Backup Status
```
GET /wp-json/wp-remote-agent/v1/backup/status
```

**Example:**
```bash
curl -u "admin:PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/backup/status
```

**Response:**
```json
{
  "success": true,
  "updraftplus_installed": true,
  "updraftplus_version": "1.23.0",
  "last_backup": {
    "timestamp": 1729857600,
    "date": "2025-10-25 12:00:00"
  },
  "backup_running": false
}
```

#### Run Backup
```
POST /wp-json/wp-remote-agent/v1/backup/run
```

**Parameters:**
- `type` (optional): Backup type - `full`, `database`, or `files` (default: full)

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  -H "Content-Type: application/json" \
  -d '{"type": "full"}' \
  http://localhost:8082/wp-json/wp-remote-agent/v1/backup/run
```

**Response:**
```json
{
  "success": true,
  "message": "Backup started successfully",
  "type": "full",
  "backup_files": true,
  "backup_database": true
}
```

#### List Backups
```
GET /wp-json/wp-remote-agent/v1/backup/list
```

**Example:**
```bash
curl -u "admin:PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/backup/list
```

**Response:**
```json
{
  "success": true,
  "backups": [
    {
      "timestamp": 1729857600,
      "date": "2025-10-25 12:00:00",
      "nonce": "abc123",
      "files": ["plugins", "themes", "uploads"],
      "complete": true
    }
  ],
  "count": 1
}
```

#### Delete Backup
```
DELETE /wp-json/wp-remote-agent/v1/backup/{timestamp}
```

**Example:**
```bash
curl -X DELETE -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/backup/1729857600
```

---

### Update Management

#### Check for Updates
```
GET /wp-json/wp-remote-agent/v1/updates/check
```

**Example:**
```bash
curl -u "admin:PASSWORD" http://localhost:8082/wp-json/wp-remote-agent/v1/updates/check
```

**Response:**
```json
{
  "success": true,
  "wordpress": {
    "current_version": "6.4.2",
    "update_available": true,
    "new_version": "6.4.3"
  },
  "plugins": {
    "updates_available": 2,
    "plugins": [
      {
        "plugin": "akismet/akismet.php",
        "name": "Akismet",
        "current_version": "5.0",
        "new_version": "5.1"
      }
    ]
  },
  "themes": {
    "updates_available": 1,
    "themes": [
      {
        "theme": "twentytwentyfour",
        "name": "Twenty Twenty-Four",
        "current_version": "1.0",
        "new_version": "1.1"
      }
    ]
  }
}
```

#### Update WordPress Core
```
POST /wp-json/wp-remote-agent/v1/updates/core
```

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/core
```

**Response:**
```json
{
  "success": true,
  "message": "WordPress core updated successfully",
  "version": "6.4.3"
}
```

#### Update All Plugins
```
POST /wp-json/wp-remote-agent/v1/updates/plugins
```

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/plugins
```

**Response:**
```json
{
  "success": true,
  "message": "Plugin updates completed",
  "updated": [
    {
      "plugin": "akismet/akismet.php",
      "status": "updated"
    }
  ],
  "errors": [],
  "total_updated": 1,
  "total_errors": 0
}
```

#### Update Specific Plugin
```
POST /wp-json/wp-remote-agent/v1/updates/plugin/{plugin-slug}
```

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/plugin/akismet
```

#### Update All Themes
```
POST /wp-json/wp-remote-agent/v1/updates/themes
```

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/themes
```

#### Update Specific Theme
```
POST /wp-json/wp-remote-agent/v1/updates/theme/{theme-slug}
```

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/theme/twentytwentyfour
```

#### Update Everything
```
POST /wp-json/wp-remote-agent/v1/updates/all
```

Updates WordPress core, all plugins, and all themes in one request.

**Example:**
```bash
curl -X POST -u "admin:PASSWORD" \
  http://localhost:8082/wp-json/wp-remote-agent/v1/updates/all
```

---

## Security Considerations

1. **Use HTTPS**: Always use HTTPS in production to protect credentials
2. **Application Passwords**: Use WordPress Application Passwords instead of regular passwords
3. **IP Restrictions**: Consider restricting API access to specific IP addresses
4. **Rate Limiting**: Implement rate limiting to prevent abuse
5. **Logging**: Monitor API usage and failed authentication attempts
6. **Backup Before Updates**: Always run a backup before updating core/plugins/themes

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- UpdraftPlus plugin (for backup functionality)
- Administrator access for all endpoints

## Permissions

All endpoints require the `manage_options` capability (administrator role). Update endpoints additionally require `update_core`, `update_plugins`, and `update_themes` capabilities.

## Error Responses

All endpoints return standardized error responses:

```json
{
  "success": false,
  "error": "Error message description"
}
```

Common HTTP status codes:
- `200`: Success
- `201`: Created (for POST requests)
- `400`: Bad request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not found
- `500`: Server error

## Development

To install the plugin in your Docker environment:

```bash
# Copy plugin to WordPress container
docker cp wp-remote-agent wordpress_site:/var/www/html/wp-content/plugins/

# Set correct permissions
docker exec wordpress_site chown -R www-data:www-data /var/www/html/wp-content/plugins/wp-remote-agent

# Activate plugin
docker exec wordpress_site wp plugin activate wp-remote-agent --allow-root
```

## License

GPL v2 or later

## Support

For issues and questions, please open an issue on the GitHub repository.
