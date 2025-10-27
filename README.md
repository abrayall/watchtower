# Watchtower - WordPress Remote Management Suite

A comprehensive WordPress plugin suite for remote site monitoring and management.

## Components

### Watchtower Manager
Central management plugin that provides a dashboard to monitor and manage multiple WordPress sites.

**Features:**
- Multi-site dashboard with health status monitoring
- Real-time site health checks (CPU, Memory, Disk, PHP, WordPress versions)
- Site details page with comprehensive metrics
- Network-wide plugin management capabilities
- Multisite support with subdirectory installations

**Location:** `remote-manager/`

### Watchtower Agent
Agent plugin that runs on managed WordPress sites and provides REST API endpoints for remote management.

**Features:**
- REST API endpoints for site information and health data
- Automatic registration with manager plugin
- Application password authentication
- Comprehensive health monitoring (server, database, plugins, themes)
- User management endpoints
- Backup management integration (UpdraftPlus)
- Update management capabilities

**Location:** `remote-agent/`

## Installation

### Manager Plugin
1. Upload `remote-manager` to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the dashboard at **WP Admin → Sites**

### Agent Plugin
1. Upload `remote-agent` to `/wp-content/plugins/` on each site you want to manage
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the manager URL at **Settings → Remote Agent**
4. Click "Register with Manager" to connect to your central management site

## Architecture

### Manager Plugin Structure
```
remote-manager/
├── remote-manager.php                # Main plugin file
├── includes/
│   ├── class-watchtower-manager.php  # Core manager class
│   ├── class-agent-storage.php       # Agent registration and data storage
│   ├── class-health-storage.php      # Health monitoring and data retrieval
│   └── class-admin-dashboard.php     # Admin UI and dashboard
└── data/
    └── sites/
        └── {hostname-port-path}/     # Individual site directories
            ├── info.json             # Agent configuration
            └── health.json           # Latest health data
```

### Agent Plugin Structure
```
remote-agent/
├── remote-agent.php                    # Main plugin file
├── includes/
│   ├── class-watchtower-agent.php      # Core plugin class
│   ├── class-rest-api-controller.php   # REST API routes and health endpoint
│   ├── class-admin-settings.php        # Settings page
│   └── endpoints/
│       ├── class-user-management.php   # User CRUD operations
│       ├── class-backup-management.php # Backup operations
│       └── class-update-management.php # Plugin/theme updates
```

## REST API Endpoints

### Agent Endpoints

**Health Information:**
```
GET /wp-json/watchtower-agent/v1/health
```
Returns comprehensive health data including:
- PHP version and configuration
- Memory usage
- WordPress version and configuration
- Database information
- Server information
- Disk usage
- CPU load
- Active plugins and themes

**Site Information:**
```
GET /wp-json/watchtower-agent/v1/info
```

**Application Password (Registration):**
```
GET /wp-json/watchtower-agent/v1/app-password
```

## Directory Naming Convention

Sites are stored using the following naming pattern:
- Standard ports (80/443): `hostname/`
- Non-standard ports: `hostname-port/`
- Multisite subdirectories: `hostname-port-path/`

Examples:
- `http://example.com` → `example.com/`
- `http://localhost:8082` → `localhost-8082/`
- `http://localhost:8082/site1` → `localhost-8082-site1/`

## Requirements

- **WordPress:** 5.8 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- Application Passwords enabled (WordPress 5.6+)

## Multisite Support

Both plugins support WordPress multisite installations:
- **Manager:** Can manage multiple networks
- **Agent:** Each subsite can register independently
- Automatic detection of local vs. remote sites
- Proper handling of subdirectory and subdomain installations

## Development

### Testing Environment
The plugins were developed and tested with:
- WordPress 6.8.1
- PHP 8.2.28
- MySQL 8.0
- Docker-based local environment

### Health Check Polling
The manager plugin automatically polls agent sites every 5 minutes via WordPress cron to update health data.

## Security

- Uses WordPress Application Passwords for authentication
- REST API endpoints use WordPress capability checks
- Sensitive operations require `manage_options` capability
- SSL verification can be disabled for local development

## License

GPL v2 or later

## Author

Your Name
