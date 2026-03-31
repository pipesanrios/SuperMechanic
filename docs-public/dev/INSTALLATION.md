# INSTALLATION.md

## Purpose

This document explains how to install and set up Super Mechanic in a development or production environment.

---

## Requirements

### Minimum Requirements

- WordPress: 6.0+
- PHP: 8.0+
- MySQL / MariaDB
- Web server (Apache, Nginx, etc.)

### Recommended

- Local environment (XAMPP, LocalWP, Docker)
- WP_DEBUG enabled (development only)

---

## Installation Steps

### 1. Copy Plugin

Place the plugin in:

/wp-content/plugins/super-mechanic

---

### 2. Activate Plugin

- Go to WordPress Admin → Plugins
- Activate **Super Mechanic**

---

### 3. Automatic Setup

On activation, the plugin will:

- create custom database tables
- register roles and capabilities
- initialize core system components

No manual DB setup is required.

---

## Development Setup

### Enable Debugging

In `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);

```