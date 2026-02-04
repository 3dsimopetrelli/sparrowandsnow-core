# Architettura Plugin Sparrow Core

## Panoramica

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            SPARROW CORE PLUGIN                               │
│                              (sas-plugin.php)                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐                   │
│  │   CLASSES    │    │  FRAMEWORK   │    │   WIDGETS    │                   │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘                   │
│         │                   │                   │                            │
│         ▼                   ▼                   ▼                            │
│  ┌────────────┐      ┌────────────┐      ┌────────────┐                     │
│  │ post-type  │      │  helper    │      │  header/   │                     │
│  │ utils      │      │  query_    │      │  content/  │                     │
│  │ wc-radio   │      │  helper    │      │  assets/   │                     │
│  └────────────┘      └────────────┘      └────────────┘                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Struttura Directory

```
sparrowandsnow-core/
│
├── sas-plugin.php              # Entry point principale
├── sas-el-widgets.php          # Registrazione widget Elementor
├── index.php                   # Security (silence is golden)
│
├── classes/                    # Classi PHP core
│   ├── post-type.php          # Custom Post Types
│   ├── classes.utils.php      # Utility functions
│   └── wc-variations-radio-buttons.php  # WooCommerce integration
│
├── framework/                  # Helper functions
│   ├── helper.php             # Generic helpers
│   └── query_helper.php       # Query & AJAX handlers
│
├── widgets/                    # Elementor Widgets
│   ├── header/                # Header widgets
│   │   ├── user.php          # User login/account
│   │   ├── cart.php          # WooCommerce cart
│   │   ├── product-search.php # Search form
│   │   └── mobile-menu.php   # Mobile menu
│   │
│   ├── content/               # Content widgets
│   │   ├── products.php      # Products grid
│   │   ├── products-slider.php
│   │   ├── blog-grid.php     # Blog grid
│   │   ├── box-category.php
│   │   ├── text-big-title.php
│   │   └── ...
│   │
│   └── assets/                # Widget assets
│       ├── css/              # Stylesheets
│       ├── js/               # JavaScript
│       ├── fonts/            # Custom fonts
│       └── img/              # Images
│
├── templates/                  # WooCommerce overrides
│   └── single-product/
│       └── add-to-cart/
│           └── variable.php
│
└── assets/                     # Global assets
    └── js/
        ├── isotope.js
        └── frontend/
            └── add-to-cart-variation.js
```

## Flusso di Inizializzazione

```
┌─────────────────────────────────────────────────────────────────┐
│                     WordPress Boot                               │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              plugins_loaded hook                                 │
│              └── SAS_Plugin::getInstance()                       │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              init() method                                       │
│              ├── Check Elementor dependency                      │
│              ├── Load classes (post-type, utils, wpapi)         │
│              ├── Register WooCommerce hooks                      │
│              └── Enqueue slick slider                           │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              init hook                                           │
│              └── sas_create_builders()                          │
│                  ├── load_plugin_textdomain('sas')              │
│                  └── Register CPTs (showcase, services, etc.)   │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│              SAS_Elementor_Widgets                               │
│              ├── elementor/widgets/register                      │
│              │   └── include_widgets() - Load all widgets       │
│              ├── elementor/elements/categories_registered        │
│              │   └── Add sas-header, sas-content, sas-footer    │
│              └── wp hook                                         │
│                  └── register_scripts_frontend()                │
└─────────────────────────────────────────────────────────────────┘
```

## Custom Post Types

```
┌─────────────────────────────────────────────────────────────────┐
│                    CUSTOM POST TYPES                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
│  │  showcase   │  │  services   │  │    help     │              │
│  │  (testim.)  │  │  (servizi)  │  │   (docs)    │              │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
│         │                │                │                      │
│         ▼                ▼                ▼                      │
│  showcase_cat      services_cat      help_cat                    │
│                                                                  │
│  ┌─────────────┐                                                 │
│  │ sas-portfolio│                                                 │
│  │ (progetti)  │                                                 │
│  └──────┬──────┘                                                 │
│         │                                                        │
│         ▼                                                        │
│  sas-portfolio-cat                                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Widget Elementor

```
┌─────────────────────────────────────────────────────────────────┐
│                    ELEMENTOR WIDGETS                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  HEADER (sas-header)                                            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │   User   │ │   Cart   │ │  Search  │ │  Mobile  │           │
│  │  Login   │ │   Icon   │ │   Form   │ │   Menu   │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
│                                                                  │
│  CONTENT (sas-content)                                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │ Products │ │ Products │ │   Blog   │ │   Box    │           │
│  │   Grid   │ │  Slider  │ │   Grid   │ │ Category │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │   Big    │ │  Sales   │ │  Fixed   │ │ Product  │           │
│  │  Title   │ │  Table   │ │Price Bar │ │   Meta   │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
│  ┌──────────┐ ┌──────────┐                                      │
│  │ Product  │ │ Rotellina│                                      │
│  │  Review  │ │          │                                      │
│  └──────────┘ └──────────┘                                      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Integrazioni Esterne

```
┌─────────────────────────────────────────────────────────────────┐
│                    INTEGRAZIONI                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐                                            │
│  │   ELEMENTOR     │◄─── Dipendenza obbligatoria                │
│  │   (min 2.0.0)   │     Widget registration, controls, render  │
│  └─────────────────┘                                            │
│                                                                  │
│  ┌─────────────────┐                                            │
│  │  WOOCOMMERCE    │◄─── Dipendenza opzionale                   │
│  │                 │     Cart, Products, Variations, Checkout   │
│  └─────────────────┘                                            │
│                                                                  │
│  ┌─────────────────┐                                            │
│  │     AJAX        │◄─── wordpress_post_ajax_load              │
│  │   WordPress     │     Load more blog posts                   │
│  └─────────────────┘                                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Flusso Dati Widget Blog

```
┌──────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
│  Client  │────▶│   AJAX   │────▶│  Query   │────▶│ Template │
│  (JS)    │     │ Handler  │     │  Helper  │     │  Render  │
└──────────┘     └──────────┘     └──────────┘     └──────────┘
     │                │                │                │
     │                │                │                │
     ▼                ▼                ▼                ▼
blog-grid.js    query_helper.php  WP_Query      blog-templates/
                                                  ├── grid.php
                                                  ├── classic.php
                                                  └── list.php
```

## Assets e Dipendenze JS

```
┌─────────────────────────────────────────────────────────────────┐
│                    JAVASCRIPT DEPENDENCIES                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  jQuery (WordPress)                                              │
│     │                                                            │
│     ├── sas-slickslider (slick.js)                              │
│     │      └── Products Slider, Blog Slider                     │
│     │                                                            │
│     ├── sas-header-user (user.js)                               │
│     │      └── User widget interactions                         │
│     │                                                            │
│     ├── sas-blog-grid (blog-grid.js)                            │
│     │      └── Load more, filtering                             │
│     │                                                            │
│     ├── sas-products (products.js)                              │
│     │      └── Product grid interactions                        │
│     │                                                            │
│     ├── sas-mobile-menu-js (mobile-menu.js)                     │
│     │      └── Mobile menu toggle                               │
│     │                                                            │
│     └── sas-fixed-price-bar (fixed-price-bar.js)                │
│            └── Sticky price bar                                 │
│                                                                  │
│  Standalone                                                      │
│     ├── isotope (isotope.js) - Grid layouts                     │
│     └── smartmenu (smartmenu.js) - Desktop menu                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Pattern di Sicurezza (Target)

```
┌─────────────────────────────────────────────────────────────────┐
│              SECURITY PATTERN (Da implementare)                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  INPUT                                                           │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  $_POST/$_GET/$_REQUEST                                   │   │
│  │         │                                                 │   │
│  │         ▼                                                 │   │
│  │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐    │   │
│  │  │   Nonce     │──▶│  Sanitize   │──▶│  Validate   │    │   │
│  │  │   Check     │   │   Input     │   │  Whitelist  │    │   │
│  │  └─────────────┘   └─────────────┘   └─────────────┘    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  OUTPUT                                                          │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Database/Variable Data                                   │   │
│  │         │                                                 │   │
│  │         ▼                                                 │   │
│  │  ┌─────────────┐   ┌─────────────┐                       │   │
│  │  │   Escape    │──▶│   Render    │                       │   │
│  │  │  esc_html   │   │    HTML     │                       │   │
│  │  │  esc_attr   │   │             │                       │   │
│  │  │  esc_url    │   │             │                       │   │
│  │  └─────────────┘   └─────────────┘                       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  REST API                                                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  Request                                                  │   │
│  │         │                                                 │   │
│  │         ▼                                                 │   │
│  │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐    │   │
│  │  │ Permission  │──▶│  API Key    │──▶│  Process    │    │   │
│  │  │  Callback   │   │  Validate   │   │  Request    │    │   │
│  │  └─────────────┘   └─────────────┘   └─────────────┘    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Legenda

| Simbolo | Significato |
|---------|-------------|
| `───▶`  | Flusso dati/chiamata |
| `◄───`  | Dipendenza |
| `┌─┐`   | Componente/Modulo |
| `├──`   | Sotto-elemento |
| `└──`   | Ultimo elemento |

---

*Documentazione generata il 2026-02-04*
