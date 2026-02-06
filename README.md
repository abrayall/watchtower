# Watchtower - WordPress Remote Management Suite

A comprehensive WordPress plugin suite for remote site monitoring and management.

## Components

### Watchtower Manager
Central management plugin that provides a dashboard to monitor and manage multiple WordPress sites.

**Features:**
- Multi-site dashboard with health status monitoring
- Real-time site health checks (CPU, Memory, Disk, PHP, WordPress versions)
- Site details page with comprehensive metrics
- **Global Plugins page** - View all plugins across all managed sites
  - Filterable cards (All Plugins, Active, Updates Available)
  - Clickable rows to view plugin details
  - Details dialog showing all sites/versions with update indicators
- **Maintenance mode toggle** - Enable/disable maintenance mode on remote sites
- **Backup management** - View, create, and restore backups with background processing
- **User management** - View and manage administrator users across sites
- **Agent auto-update** - Automatically update agents during health scans
- Configurable health polling interval (default: 15 minutes)
- Multisite support with subdirectory installations

**Location:** `manager/`

### Watchtower Agent
Agent plugin that runs on managed WordPress sites and provides REST API endpoints for remote management.

**Features:**
- REST API endpoints for site information and health data
- Automatic registration with manager plugin
- **Agent URL setting** - Configure external URL for Docker/proxy/NAT scenarios
- Application password authentication (auto-creates on activation)
- Comprehensive health monitoring (server, database, plugins, themes)
- **Detailed plugin inventory** - Collects slug, version, update status, WP.org data, icons, requirements
- User management endpoints
- Backup management integration (UpdraftPlus)
  - Create full or partial backups
  - List and restore backups with progress tracking
  - Automatic weekly backups with retention (keeps last 3)
- Maintenance mode management (Intermission plugin integration)
- Update management capabilities
- **Wordfence compatibility** - Automatically enables Application Passwords if blocked
- Periodic re-registration with credential rotation (twice daily)

**Location:** `agent/`

## Installation

### Manager Plugin
1. Upload `watchtower-manager` to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Access the dashboard at **WP Admin → Sites**

### Agent Plugin
1. Upload `watchtower-agent` to `/wp-content/plugins/` on each site you want to manage
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the manager URL at **Settings → Remote Agent**
4. Click "Register with Manager" to connect to your central management site

## Architecture

### Manager Plugin Structure
```
watchtower-manager/
├── manager.php                       # Main plugin file
├── includes/
│   ├── class-watchtower-manager.php  # Core manager class
│   ├── class-agent-storage.php       # Agent registration and data storage
│   ├── class-health-storage.php      # Health monitoring and data retrieval
│   ├── class-auto-updater.php        # Auto-update functionality
│   └── class-admin-dashboard.php     # Admin UI and dashboard
└── assets/
    └── watchtower-agent-{version}.zip # Bundled agent plugin

Data Directory (outside plugin, persists across updates):
wp-content/watchtower-manager/
└── sites/
    └── {hostname-port-path}/         # Individual site directories
        ├── info.json                 # Agent configuration and static data
        ├── health.json               # Latest health metrics (CPU, memory, disk)
        ├── plugins.json              # Plugin inventory
        ├── backups.json              # Backup list
        └── users.json                # Administrator users
```

### Agent Plugin Structure
```
watchtower-agent/
├── agent.php                           # Main plugin file
├── includes/
│   ├── class-watchtower-agent.php      # Core plugin class
│   ├── class-rest-api-controller.php   # REST API routes and health endpoint
│   ├── class-admin-settings.php        # Settings page
│   ├── class-audit-logger.php          # Action logging
│   └── endpoints/
│       ├── class-user-management.php       # User CRUD operations
│       ├── class-backup-management.php     # Backup operations (UpdraftPlus)
│       ├── class-update-management.php     # Plugin/theme updates
│       ├── class-maintenance-management.php # Maintenance mode toggle
│       ├── class-file-management.php       # File operations
│       ├── class-log-management.php        # Log retrieval
│       └── class-audit-endpoint.php        # Audit log access
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

## Building

Build both plugins using [wordsmith](https://github.com/abrayall/wordsmith):

```bash
./build.sh
```

This builds both plugins and bundles the agent ZIP inside the manager:
- `agent/build/watchtower-agent-{version}.zip`
- `manager/build/watchtower-{version}.zip`

You can also build each plugin individually:

```bash
cd agent && wordsmith build
cd manager && wordsmith build
```

These ZIP files can be uploaded directly to WordPress via **Plugins → Add New → Upload Plugin**.

### Version Management

Version information is managed using **git tags**:

**To release a new version:**
```bash
git tag v0.0.2
git push origin v0.0.2
./build.sh
```

**Version Format:**
- **Exact tag**: `v0.0.1` → version `0.0.1`
- **After tag**: `v0.0.1-5` → version `0.0.1-5`
- **Uncommitted changes**: Appends timestamp

## Development

### Testing Environment
The plugins were developed and tested with:
- WordPress 6.8.1
- PHP 8.2.28
- MySQL 8.0
- Docker-based local environment

### Health Check Polling
The manager plugin automatically polls agent sites every 15 minutes via WordPress cron to update health data. During each poll:
- Fetches site info and health metrics
- Collects plugin inventory data
- Retrieves backup status
- Fetches administrator user list
- Optionally auto-updates outdated agents

## Security

- Uses WordPress Application Passwords for authentication
- REST API endpoints use WordPress capability checks
- Sensitive operations require `manage_options` capability
- SSL verification can be disabled for local development

## Future Enhancements

The following features are planned for future releases:

- **Batch Health Polling** - Process agents in parallel batches for improved scalability with large numbers of sites
- **Adaptive Polling** - Poll healthy sites less frequently, unhealthy sites more frequently
- **Traffic Stats Integration** - Support for popular analytics plugins (MonsterInsights, Google Analytics 4) to display traffic metrics in the dashboard
- **Security Integration** - Integration with security plugins (Wordfence, etc.) to monitor security events, firewall status, and threats across managed sites
- **Bulk Plugin Updates** - Update plugins across multiple sites from the global Plugins page

## License

GPL v2 or later

## Author

Brayall, LLC
