# StoreFleet

**StoreFleet** is a multi-branch marketplace and merchant operations platform for businesses that need to manage products, inventory, staff, POS, payments, delivery, and multiple store locations from one system.

The platform combines **WordPress + WooCommerce + Dokan** for commerce and marketplace operations, **Next.js** for the customer-facing storefront, and **Python** for scraping, automation, and background workers.

> **Status:** Active development

## Core Goals

StoreFleet is being built to support:

- Multi-vendor marketplace operations
- Multi-branch / multi-outlet merchants
- Branch-level inventory
- Merchant staff and role management
- Shared marketplace and POS inventory
- Customer accounts and checkout
- Online payments and merchant payouts
- Delivery quotations and booking
- Product and merchant acquisition through web and Facebook scraping
- Merchant onboarding and verification
- Central StoreFleet pricing and margin controls

## Tech Stack

### Commerce Backend
- WordPress
- WooCommerce
- Dokan
- wePOS
- Custom StoreFleet WordPress plugin

### Frontend
- Next.js
- TypeScript
- Tailwind CSS

### Automation / Scraping
- Python
- n8n
- Web scraping
- Facebook scraping

### Payments
- Xendit
- XenPlatform

### Delivery
- Lalamove API

### Local Development
- Podman
- Podman Compose
- MariaDB

## Repository Structure

```text
storefleet/
├── compose.yml
├── storefleet-marketplace/
│   ├── storefleet-marketplace.php
│   ├── includes/
│   │   ├── admin/
│   │   ├── api/
│   │   ├── branches/
│   │   ├── compatibility/
│   │   ├── inventory/
│   │   ├── merchants/
│   │   ├── payments/
│   │   ├── delivery/
│   │   ├── pos/
│   │   ├── pricing/
│   │   ├── staff/
│   │   └── automation/
│   └── assets/
└── storefleet-web/
    ├── app/
    ├── components/
    ├── data/
    └── public/
```

## Platform Architecture

```text
Customers
    |
    v
Next.js StoreFleet Frontend
    |
    v
StoreFleet Server APIs
    |
    v
WordPress + WooCommerce + Dokan
    |
    +---- StoreFleet Marketplace Plugin
    +---- Xendit / XenPlatform
    +---- Lalamove
    +---- n8n
    +---- Python Scrapers / Workers
```

WordPress and WooCommerce are the **commerce source of truth**. Next.js is the **public StoreFleet storefront**. Python and n8n handle scraping, acquisition workflows, and automation.

## Multi-Branch Model

A single merchant can operate multiple StoreFleet branches or outlets.

Each branch can have:

- Branch name
- Address
- GPS coordinates
- Pickup contact
- Active / inactive status
- Assigned staff
- Branch-level inventory
- POS operations
- Delivery pickup location

A merchant can manage several branches under one Dokan vendor account. StoreFleet supports staff assignment across one or multiple branches.

## Merchant Staff Roles

Current planned roles include:

- Manager
- Branch Manager
- Cashier
- Inventory Staff
- Order Staff
- Delivery Staff

## Pricing Model

StoreFleet separates the merchant's protected base price from the customer-facing marketplace price.

Pricing priority:

```text
Product override
    ↓
Merchant override
    ↓
Deepest product subcategory markup
    ↓
Parent category markup
    ↓
Global default markup
```

StoreFleet can also enforce minimum margin, campaign discount caps, campaign eligibility, and category-based markup rules.

## Merchant Verification

Merchant account approval and merchant verification are separate.

### Merchant Status

```text
pending_approval
active
suspended
```

### Verification Status

```text
unverified
pending_review
verified
rejected
```

A merchant may register without a business permit. Uploading a permit does not automatically verify the merchant; StoreFleet administrators review submitted documents before granting verified status.

## Customer Accounts

StoreFleet currently supports backend customer APIs for:

- Registration
- Login
- Current customer session
- Logout

Customer sessions are designed to be proxied through Next.js and stored using secure **HttpOnly cookies** so internal WordPress API credentials and raw customer session tokens are not exposed to browser JavaScript.

## Marketplace Frontend

Current and planned Next.js routes include:

```text
/
├── shop
│   └── [slug]
├── stores
│   └── [slug]
├── cart
├── checkout
├── merchant
│   ├── register
│   └── login
└── account
    ├── register
    ├── login
    ├── orders
    └── addresses
```

Some account and checkout routes are still under development.

## Cart

The StoreFleet frontend currently supports:

- Add to cart
- Buy now
- Quantity updates
- Remove item
- Stock limits
- Persistent cart using local storage
- Grouping cart items by merchant
- Merchant subtotals
- Marketplace subtotal

Checkout integration is under development.

## Marketplace Orders

Target checkout flow:

```text
Customer Cart
    ↓
StoreFleet Checkout
    ↓
Online Payment
    ↓
WooCommerce Order
    ↓
Merchant Suborders
    ↓
Merchant Fulfillment
    ↓
Lalamove Delivery
```

A cart containing products from multiple merchants can generate separate merchant suborders and separate delivery jobs when pickup locations differ.

## Payments

StoreFleet uses **Xendit XenPlatform** for:

- Customer online payments
- Merchant onboarding
- Merchant payout destinations
- Marketplace payment flows
- Future merchant settlement logic

Cash and COD are not part of the initial marketplace checkout flow.

## Delivery

StoreFleet uses the **Lalamove API** for delivery quotations, delivery booking, pickup and destination details, and multi-merchant delivery handling.

Customers are expected to prepay delivery charges during checkout.

## Scraping and Merchant Acquisition

StoreFleet is designed to acquire merchant and product data through:

- Website scraping
- Facebook scraping
- Python workers
- n8n workflows

Target workflow:

```text
Scraper
    ↓
StoreFleet Lead
    ↓
Merchant Registration
    ↓
Merchant Verification / Approval
    ↓
Payment Onboarding
    ↓
Product Review / Confirmation
    ↓
Marketplace Listing
```

## Current Development Progress

Completed or working foundations include:

- WordPress / WooCommerce / Dokan environment
- StoreFleet custom WordPress plugin
- Merchant registration API
- Merchant approval and verification states
- Customer registration and login API
- Customer session management
- WooCommerce customer synchronization
- Dokan / WooCommerce customer analytics compatibility
- Multi-branch data model
- Staff data model
- Branch inventory foundation
- Marketplace pricing foundation
- Next.js shop
- Product pages
- Store pages
- Shopping cart
- Merchant login
- Merchant registration

Next major development stage:

```text
Next.js Customer Authentication
    ↓
Protected Checkout
    ↓
Customer Addresses
    ↓
WooCommerce Order Creation
    ↓
Xendit Payment
    ↓
Merchant Suborders
    ↓
Lalamove Delivery
```

## Local Development

### Requirements

Install:

- Podman
- Podman Compose
- Node.js
- npm
- Git

### Start WordPress

From the repository root:

```bash
podman compose up -d
```

WordPress development URL:

```text
http://localhost:8080
```

Next.js development URL:

```text
http://localhost:3000
```

### Start Next.js

```bash
cd storefleet-web
npm install
npm run dev
```

## Environment Variables

Environment files are intentionally excluded from Git.

Never commit:

```text
.env
.env.local
API keys
database passwords
payment credentials
webhook secrets
```

## Development Principles

- WordPress / WooCommerce remains the commerce source of truth.
- Next.js is the public customer experience.
- Dokan remains the merchant marketplace layer.
- StoreFleet custom logic lives in the StoreFleet plugin rather than modifying vendor plugin files.
- External integrations are wrapped through StoreFleet-controlled backend APIs.
- Secrets must never be exposed to browser-side JavaScript.
- Branch-level inventory must remain compatible with shared WooCommerce inventory.
- Merchant protected base prices must remain separate from StoreFleet customer pricing.

## License

Private project. All rights reserved.

---

**StoreFleet — Multi-branch commerce, marketplace, POS, payments, delivery, and merchant operations in one platform.**
