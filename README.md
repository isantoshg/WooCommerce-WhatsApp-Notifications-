# WooCommerce WhatsApp Notifications

**Plugin Name**: WooCommerce WhatsApp Notifications  
**Plugin URI**: [Insert Plugin URI]  
**Description**:  
The **WooCommerce WhatsApp Notifications** plugin allows you to send real-time WhatsApp notifications to your customers when they place an order, make a payment, or when their order is shipped. Enhance your customer experience with timely, personalized notifications directly on WhatsApp.

**Version**: 1.0.0  
**Author**: [Your Name or Company]  
**Author URI**: [Insert Author URI]  
**License**: GPL-2.0+  
**License URI**: https://opensource.org/licenses/GPL-2.0  

---

## Table of Contents

1. [Installation](#installation)
2. [Usage](#usage)
3. [Features](#features)
4. [Folder Structure](#folder-structure)
5. [Changelog](#changelog)
6. [Contribute](#contribute)
7. [Support](#support)

---

## Installation

1. Download the plugin ZIP file.
2. Go to your WordPress dashboard and navigate to **Plugins > Add New**.
3. Click **Upload Plugin** and choose the downloaded ZIP file.
4. Click **Install Now** and then **Activate** the plugin.

---

## Usage

Once activated, follow these steps to configure the plugin:

1. Go to **WooCommerce > Settings > WhatsApp Notifications**.
2. Enter your WhatsApp Business API credentials.
3. Configure your notification settings, such as what events trigger WhatsApp messages (order placed, order shipped, etc.).
4. Save your settings.

You are now ready to start sending WhatsApp notifications for your WooCommerce orders!

---

## Features

- Send real-time WhatsApp notifications to customers on key order events (new order, order processing, order completed, etc.).
- Customizable message templates for each event.
- Easy-to-use settings page for managing configurations.
- Integration with WooCommerce order hooks to send notifications at the right time.
- Cron job support for scheduled notifications.

---

## Folder Structure

```plaintext
wp-whatsapp-notify/
├── wp-whatsapp-notify.php        # Main plugin file, registers hooks and includes other classes
├── includes/                     # Contains all PHP classes for plugin functionality
│   ├── class-settings-page.php   # Admin settings page for configuring WhatsApp API details
│   ├── class-send-message.php    # Handles sending WhatsApp messages via Meta Cloud API
│   ├── class-order-hooks.php     # Hooks into WooCommerce order events to trigger messages
│   └── class-cron-handler.php    # Manages scheduled tasks like abandoned cart reminders
└── assets/                       # Static assets (CSS, JS, images)
    └── css/
        └── admin-style.css       # Stylesheet for the plugin's admin pages
