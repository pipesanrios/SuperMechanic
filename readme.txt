=== Super Mechanic ===
Contributors: mardisomdevs
Tags: workshop, mechanic, vehicle management, service tracking, maintenance, dealership, vehicle history
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Super Mechanic is an advanced vehicle management system for mechanic workshops and dealerships, developed by Mardisom Devs.

== Description ==

Super Mechanic is a professional management system designed for:

* mechanic workshops
* automotive service centers
* motorcycle workshops
* vehicle dealerships
* fleet management operations

The plugin provides a structured workflow for managing vehicles, processes, maintenance operations, administrative paperwork, quotations, invoices and customer interactions.

It is built with a modular architecture designed for scalability and long-term maintenance.

Developed by **Mardisom Devs**
https://mardisom.com

== Core Features ==

### Vehicle and Client Management

* Client database
* Vehicle registry
* Client–vehicle relationships
* Ownership transfer support
* Vehicle history tracking

### Process Tracking

Track the complete lifecycle of a vehicle service:

* inspection
* diagnostics
* repair processes
* maintenance operations
* administrative paperwork
* delivery preparation

### Maintenance Management

* technical diagnostics
* maintenance notes
* parts tracking
* labor tracking
* mechanic assignments

### Quotes and Approvals

* service quotations
* itemized quotes
* client approval workflow

### Invoices and Payments

* invoice generation
* invoice items
* payment tracking
* printable invoice documents`r`n* reusable invoice PDF download base (requires a compatible PDF engine)

### Document Management

* file attachments
* process documents
* internal vs client-visible files
* timeline integration

### Client Portal

Shortcodes allow customers to:

* view their vehicles
* track service progress
* view invoices and quotes
* access shared documents

### Admin Dashboards

Separate dashboards for:

* administrators
* mechanics
* clients

== Architecture ==

Super Mechanic follows a modular architecture:

Repository
Service
Controller
REST Controller (when needed)
Admin UI
Frontend Shortcodes

Principles:

* SQL queries isolated in repositories
* business logic in services
* UI handled by controllers
* client features via shortcodes
* secure data access and ownership validation

== Security ==

The plugin follows WordPress security best practices:

* capability checks
* nonces
* prepared SQL statements
* input sanitization
* output escaping
* ownership validation for client data

== Installation ==

1. Upload the plugin folder to:

   /wp-content/plugins/super-mechanic

2. Activate the plugin from the WordPress admin panel.

3. The plugin will automatically create required database tables during activation.

== Requirements ==

WordPress 6.0 or newer
PHP 7.4 or newer
MySQL 5.7+ or MariaDB equivalent

== Frequently Asked Questions ==

= Is this plugin suitable for large workshops? =

Yes. The architecture is designed to support large volumes of vehicles, processes, and documents.

= Can it manage both cars and motorcycles? =

Yes. Vehicles are stored generically and can represent cars, motorcycles, or other vehicle types.

= Does it support customer portals? =

Yes. The plugin includes frontend shortcodes that allow clients to view vehicles, documents, invoices and service progress.

= Is the plugin extendable? =

Yes. The modular architecture allows developers to extend features through services, repositories and controllers.

== Screenshots ==

1. Admin dashboard
2. Client management panel
3. Vehicle tracking
4. Maintenance workflow
5. Quotes and invoices
6. Client portal interface

== Changelog ==

= 0.1.0 =
Initial development release.

Core modules implemented:

* clients
* vehicles
* processes
* maintenance
* paperwork
* quotes
* invoices
* payments
* attachments
* dashboards
* client portal

== Upgrade Notice ==

= 0.1.0 =
Initial release of Super Mechanic.

== Author ==

Mardisom Devs
https://mardisom.com

