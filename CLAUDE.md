# Watchtower - WordPress Remote Management Suite

## Project Overview

Watchtower is a WordPress plugin suite for remote site monitoring and management. It consists of two plugins:

1. **Watchtower Manager** - Central dashboard for monitoring multiple WordPress sites
2. **Watchtower Agent** - Deployed on managed sites, provides REST API for remote management

## Local Development Environment

### WordPress Docker Setup

**Site URL**: `http://localhost:8082`
**Admin Panel**: `http://localhost:8082/wp-admin`
**phpMyAdmin**: `http://localhost:8081`

### Docker Containers
- **WordPress**: `wordpress_site` (port 8082)
- **Database**: `wordpress_db` (MySQL 8.0)
- **phpMyAdmin**: `wordpress_phpmyadmin` (port 8081)

### WordPress Credentials
- **Username**: `admin`
- **Application Password**: `DQ7w 6Xth 1DyA oLgZ uCIK k8n7`

### Database Credentials
- **Database Name**: `wordpress`
- **Database User**: `wordpress`
- **Database Password**: `wordpress`
- **Root Password**: `rootpassword`

### Docker Commands
```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# View logs
docker-compose logs -f wordpress

# Deploy plugins to container
docker cp build/watchtower-agent wordpress_site:/var/www/html/wp-content/plugins/
docker cp build/watchtower-manager wordpress_site:/var/www/html/wp-content/plugins/
docker exec wordpress_site chown -R www-data:www-data /var/www/html/wp-content/plugins/watchtower-agent
docker exec wordpress_site chown -R www-data:www-data /var/www/html/wp-content/plugins/watchtower-manager
```

## Build System

### Version Management

Versions are managed using **git tags** in the format `v{major}.{minor}.{maintenance}`.

**Version Format:**
- `0.0.1` - Clean release at tag `v0.0.1`
- `0.0.1-2` - 2 commits after tag
- `0.0.1-2-10271436` - 2 commits after tag + local changes (timestamp: Oct 27, 14:36)

### Build Scripts

**Unix/Linux/Mac:**
```bash
./build.sh
```

**Windows:**
```cmd
build.bat
```

### How Versioning Works

1. Reads latest git tag matching `v*.*.*` format
2. If commits exist after tag, appends commit count
3. If uncommitted local changes exist, appends timestamp `MMDDHHMM`
4. Generates `version.properties` during build process
5. Includes in both plugin packages

**Output:**
- `build/watchtower-agent-{version}.zip`
- `build/watchtower-manager-{version}.zip`

### Current Version
- **Latest Tag**: `v0.0.12`
- **Development Version**: `0.0.12-{timestamp}`

## Plugin Architecture

### Directory Structure

```
watchtower/
├── agent/                  # Agent plugin source
│   ├── agent.php           # Main plugin file
│   └── includes/
│       ├── class-watchtower-agent.php
│       ├── class-rest-api-controller.php
│       ├── class-admin-settings.php
│       ├── class-metrics-tracker.php
│       └── endpoints/
│           ├── class-user-management.php
│           ├── class-backup-management.php
│           ├── class-update-management.php
│           └── class-log-management.php
│
├── manager/                # Manager plugin source
│   ├── manager.php         # Main plugin file
│   ├── includes/
│   │   ├── class-watchtower-manager.php
│   │   ├── class-agent-storage.php
│   │   ├── class-health-storage.php
│   │   └── class-admin-dashboard.php
│   └── data/
│       └── sites/
│           └── {hostname-port-path}/
│               ├── info.json     # Static configuration
│               └── health.json   # Dynamic metrics
│
├── build.sh                # Unix build script
├── build.bat               # Windows build script
├── README.md               # User documentation
└── CLAUDE.md               # This file
```

## Data Storage Architecture

### Site Directory Naming

Sites are stored using this pattern:
- Standard ports (80/443): `hostname/`
- Non-standard ports: `hostname-port/`
- Multisite subdirectories: `hostname-port-path/`

**Examples:**
- `http://example.com` → `example.com/`
- `http://localhost:8082` → `localhost-8082/`
- `http://localhost:8082/site1` → `localhost-8082-site1/`

### info.json - Static Configuration Data

Contains information that doesn't change frequently:

```json
{
  "site_url": "http://localhost:8082",
  "site_name": "My WordPress Site",
  "admin_url": "http://localhost:8082/wp-admin/",
  "site_icon": "http://localhost:8082/wp-content/uploads/2025/10/icon.png",
  "username": "admin",
  "password": "DQ7w 6Xth 1DyA oLgZ uCIK k8n7",
  "registered_at": "2025-10-27 14:35:12",
  "updated_at": "2025-10-27 15:20:45",

  "php": {
    "version": "8.2.28",
    "memory_limit": "256M",
    "max_execution_time": "300",
    "upload_max_filesize": "64M",
    "post_max_size": "64M",
    "extensions": ["json", "mysqli", "curl", ...]
  },

  "wordpress": {
    "version": "6.8.1",
    "site_url": "http://localhost:8082",
    "home_url": "http://localhost:8082",
    "site_name": "My WordPress Site",
    "admin_url": "http://localhost:8082/wp-admin/",
    "admin_email": "admin@example.com",
    "language": "en_US",
    "timezone": "America/New_York",
    "debug_mode": false,
    "multisite": false
  },

  "database": {
    "database_name": "wordpress",
    "database_host": "db:3306",
    "database_version": "8.0.35",
    "database_size": "45.32 MB"
  },

  "server": {
    "software": "Apache/2.4.56 (Unix)",
    "server_name": "localhost",
    "server_ip": "172.18.0.3",
    "https": false
  },

  "plugins": {
    "active_count": 5,
    "active_plugins": [...]
  },

  "theme": {
    "name": "Twenty Twenty-Four",
    "version": "1.0.0"
  },

  "constants": {
    "WP_DEBUG": false,
    "WP_CACHE": false
  }
}
```

### health.json - Dynamic Monitoring Data

Contains real-time metrics (updated every 5 minutes):

```json
{
  "success": true,
  "timestamp": "2025-10-27 15:20:45",
  "checked_at": "2025-10-27 15:20:45",
  "site_url": "http://localhost:8082",

  "cpu": {
    "load_1min": 0.52,
    "load_5min": 0.48,
    "load_15min": 0.45
  },

  "memory": {
    "current": "45.23 MB",
    "current_bytes": 47423488,
    "peak": "52.78 MB",
    "peak_bytes": 55345152,
    "limit": "256M",
    "usage_percentage": "17.45%"
  },

  "disk": {
    "free": "125.45 GB",
    "free_bytes": 134682787840,
    "used": "24.55 GB",
    "used_bytes": 26348789760,
    "total": "150.00 GB",
    "total_bytes": 161031577600,
    "usage_percentage": "16.37%"
  },

  "uptime": {
    "uptime": "15:20:45 up 5 days, 3:00, 1 user, load average: 0.52, 0.48, 0.45"
  }
}
```

## REST API Endpoints

### Agent Endpoints

**Base URL**: `http://localhost:8082/wp-json/watchtower-agent/v1/`

**Health Information** (Public):
```
GET /health
```
Returns comprehensive health data including PHP, memory, WordPress, database, server, disk, CPU, plugins, and themes.

**Site Information** (Public):
```
GET /info
```
Returns basic plugin and site information.

**Application Password** (Public, transient-based):
```
GET /app-password
```
Retrieves application password (expires after 10 minutes).

### Manager Endpoints

The manager plugin provides a WordPress admin dashboard at **WP Admin → Sites**.

## Key Implementation Details

### Data Splitting Logic

The Manager's `class-health-storage.php` splits data when fetching from agent:

**Static Data (saved to info.json):**
- `php`, `wordpress`, `database`, `server`, `plugins`, `theme`, `constants`

**Dynamic Data (saved to health.json):**
- `cpu`, `memory`, `disk`, `uptime`

This happens in `fetch_and_save_health()` method (lines 133-239).

### Health Polling

- Manager automatically polls agents every 5 minutes via WordPress cron
- Uses `wp_remote_get()` with 10-second timeout
- For local agents (same host/port), uses internal address `http://127.0.0.1`
- SSL verification disabled for local development

### Version Reading

Both plugins read version from `version.properties`:

```php
$version_file = plugin_dir_path(__FILE__) . 'version.properties';
$version_data = parse_ini_file($version_file);
$major = $version_data['major'];
$minor = $version_data['minor'];
$maintenance = $version_data['maintenance'];
$version = "{$major}.{$minor}.{$maintenance}";
```

## Testing URLs

```bash
# Check WordPress is running
curl -s -o /dev/null -w "%{http_code}" http://localhost:8082
# Should return: 200

# Test REST API
curl -s http://localhost:8082/wp-json/

# Test agent health endpoint
curl -s http://localhost:8082/wp-json/watchtower-agent/v1/health | python3 -m json.tool

# Test with authentication
curl -s -u "admin:DQ7w 6Xth 1DyA oLgZ uCIK k8n7" http://localhost:8082/wp-json/wp/v2/users/me
```

## Deployment Workflow

1. Make code changes
2. Test locally
3. Build plugins: `./build.sh`
4. Deploy to Docker:
   ```bash
   cd build
   unzip -q -o watchtower-agent-*.zip
   unzip -q -o watchtower-manager-*.zip
   docker cp watchtower-agent wordpress_site:/var/www/html/wp-content/plugins/
   docker cp watchtower-manager wordpress_site:/var/www/html/wp-content/plugins/
   docker exec wordpress_site chown -R www-data:www-data /var/www/html/wp-content/plugins/watchtower-agent
   docker exec wordpress_site chown -R www-data:www-data /var/www/html/wp-content/plugins/watchtower-manager
   ```
5. Verify deployment: `curl -s http://localhost:8082/wp-json/watchtower-agent/v1/health`

## Release Workflow

1. Commit all changes
2. Create version tag: `git tag v0.0.2`
3. Push tag: `git push origin v0.0.2`
4. Build release: `./build.sh`
5. Upload ZIP files to WordPress via admin interface

## Important Files

### Build System
- `build.sh` - Unix/Linux/Mac build script
- `build.bat` - Windows build script
- `version.properties` - Generated during build from git tags

### Agent Plugin
- `agent/agent.php` - Main plugin file, version: WATCHTOWER_AGENT_VERSION
- `agent/includes/class-rest-api-controller.php` - All REST endpoints including `/health`

### Manager Plugin
- `manager/manager.php` - Main plugin file, version: WATCHTOWER_MANAGER_VERSION
- `manager/includes/class-agent-storage.php` - Manages info.json
- `manager/includes/class-health-storage.php` - Manages health.json, data splitting logic
- `manager/includes/class-admin-dashboard.php` - WordPress admin UI

## Git Information

- **Repository**: `/Users/abrayall/Workspace/watchtower`
- **Branch**: `main`
- **Latest Tag**: `v0.0.1`
- **Remote**: `origin`

## Development Guidelines

### Git Commit Messages
- **Do NOT commit automatically** - always ask first
- **Keep commits to one-line summaries** - no multi-line bullet lists
- **Do NOT include "Claude" or "Claude Code" in commit messages**
- Examples of good commit messages:
  - `Add manual agent update button to site details page`
  - `Update plugin author to abrayall`
  - `Add custom admin URL support and configuration notice`

### Code Standards
- **DO NOT add comments to code** - no inline comments, no CSS comments, no explanatory comments
- The `build/` directory is in `.gitignore`
- Root `version.properties` was removed - now generated during build
- Manager plugin data directory (`manager/data/sites/`) is in `.gitignore`
- Both plugins support WordPress multisite installations
- Application Passwords must be enabled (WordPress 5.6+)
- Minimum requirements: WordPress 5.8+, PHP 7.4+, MySQL 5.6+

## Recent Changes

### 2025-10-28 - v0.0.12

1. **UI Enhancements**
   - Added compact mobile layout for stats filter tiles (3-column horizontal)
   - Added responsive stacking for table action buttons (< 1400px width)
   - Added manager version display in dashboard header (right-aligned)
   - Improved dashboard styling with dynamic color coding for health stats
   - Added clickable filtering for site health status with toggle behavior
   - Added Token display with copy-to-clipboard icon on site details page

2. **New Features**
   - Added log management endpoints for agent plugin (`class-log-management.php`)
   - Added metrics tracker for real-time request monitoring (`class-metrics-tracker.php`)
   - Added Future Enhancements section to README (Users, Backup, Traffic Stats, Security)
   - Added auto-update agents during health scans (respects auto-update setting)

3. **Wordfence Compatibility**
   - Fixed Application Password authentication blocked by Wordfence Security
   - Agent plugin automatically detects and disables `loginSec_disableApplicationPasswords`
   - Works regardless of installation order (agent before/after Wordfence)
   - Automatically recovers if Wordfence setting is re-enabled
   - Runs on Application Password filters - zero performance impact when not using auth
   - Prevents 401 authentication errors on sites with Wordfence installed
   - See `watchtower_agent_check_wordfence_app_password_block()` function (agent/agent.php:337-377)

4. **Plugin Update System Improvements**
   - Changed from delete-and-move to copy-over strategy for plugin updates
   - Prevents "Could not remove old plugin directory" errors
   - More reliable for updating active plugins in production

5. **Metadata Updates**
   - Updated plugin author to "Brayall, LLC" in both plugins
   - Renamed manager plugin to "Watchtower" in WordPress plugin screen
   - Updated README author to "Brayall, LLC"

### 2025-10-27

1. **Created git tag-based versioning system**
   - Reads from `git describe --tags`
   - Appends commit count after tag
   - Appends timestamp for uncommitted changes
   - Removed root `version.properties` file

2. **Reorganized data storage**
   - Split static configuration (info.json) from dynamic metrics (health.json)
   - Static: php, wordpress, database, server, plugins, theme, constants
   - Dynamic: cpu, memory, disk, uptime
   - Updated `class-health-storage.php` with data splitting logic

3. **Deployed version 0.0.1-2-{timestamp}** to local Docker container
