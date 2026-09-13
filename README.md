# WC Product Sidebar

A production-ready WooCommerce WordPress plugin that adds a React-based slide-in product sidebar to any page of your store. Customers can browse, search, and add products to cart without leaving the current page.

## Features

- ⚡ **Floating ribbon button** — fixed-position, customizable label and colors
- 🛍 **Slide-in sidebar** — smooth animation, right side on desktop, fullscreen on mobile
- 🔍 **Debounced live search** — instant client-side filtering + debounced API fetch
- 🗂 **Category grouping** — collapsible category sections, toggleable in admin
- ⭐ **Featured products first** — optional, toggled in admin
- ➕ **AJAX add to cart** — no page reload, quantity selector, WooCommerce cart fragments updated
- 🎨 **Admin customization** — background, icon, ribbon, font color/size/family, Google Fonts
- 🔒 **Secure** — nonce verification, input sanitization, escaped output
- 🚀 **Performant** — PHP transient cache, React memoization, AbortController, lazy images

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 8.0+
- Node.js 18+ (build only)

## Installation

### Option A — Upload ZIP

1. Download or clone this repository
2. Install dependencies and build the React frontend (see below)
3. From the plugin root, run `npm run zip` — this builds a clean
   `wc-product-sidebar.zip` excluding `node_modules/`, sources and tooling
4. Upload via **WP Admin → Plugins → Add New → Upload Plugin**
5. Activate

### Option B — Manual

1. Upload `wc-product-sidebar/` to `/wp-content/plugins/`
2. Build the React frontend (see below)
3. Activate via **WP Admin → Plugins**

### Build the React Frontend

```bash
cd wc-product-sidebar/frontend
npm install
npm run build
```

This compiles `frontend/src/` → `frontend/build/bundle.js` + `frontend/build/main.css`.

## Admin Settings

Navigate to **WooCommerce → Product Sidebar** to configure:

| Setting                | Description                                      |
|------------------------|--------------------------------------------------|
| Sidebar Background     | Panel background color                           |
| Ribbon Button Color    | Background of the floating trigger button        |
| Icon / Accent Color    | Icons, prices, category labels, buttons          |
| Font Color             | All body text                                    |
| Font Size              | Base font size (px / rem / em)                   |
| Font Family            | Select a Google Font or use theme default        |
| Custom Google Font     | Enter any Google Font name (overrides dropdown)  |
| Ribbon Button Label    | Text displayed on the trigger button             |
| Show Category Headings | Toggle collapsible category groups on/off        |
| Featured Products First| Move featured products to the top                |
| Hide Categories        | Check categories to exclude from the sidebar     |

## REST API Endpoints

### GET `/wp-json/wc-ps/v1/products`

Returns paginated product list (cached 5 min).

**Query params:**
- `search` — filter by name/category (bypasses cache)
- `bust` — `1` to skip cache

**Response:**
```json
[
  {
    "id": 42,
    "name": "Sample Product",
    "price": 19.99,
    "price_html": "<span class=\"woocommerce-Price-amount\">...</span>",
    "featured": false,
    "categories": [{"id": 15, "name": "Clothing"}],
    "thumbnail": "https://example.com/wp-content/uploads/...",
    "in_stock": true,
    "stock_qty": 100,
    "type": "simple",
    "permalink": "https://example.com/product/sample/"
  }
]
```

### POST `/wp-json/wc-ps/v1/cart/add`

Adds a product to the WooCommerce cart.

**Body (JSON):**
```json
{
  "product_id": 42,
  "quantity": 2,
  "nonce": "<wcps_nonce>"
}
```

**Response:**
```json
{
  "success": true,
  "cart_count": 3,
  "cart_total": "<span>$59.97</span>",
  "cart_url": "https://example.com/cart/",
  "message": "\"Sample Product\" was added to your cart."
}
```

## File Structure

```
wc-product-sidebar/
├── wc-product-sidebar.php       # Bootstrap, constants, activation/deactivation
├── uninstall.php                # Cleanup on plugin deletion
├── includes/
│   ├── class-init.php           # Bootstraps all modules
│   ├── class-assets.php         # Enqueue scripts/styles, pass config to React
│   ├── class-rest.php           # REST endpoints + transient caching
│   ├── class-settings.php       # Option management + CSS variable generation
│   └── class-security.php       # Nonce, sanitization, permission helpers
├── admin/
│   ├── settings-page.php        # WooCommerce submenu settings UI
│   └── fields/
│       ├── color-field.php      # Color picker partial
│       ├── font-field.php       # Font size/family partial
│       └── toggle-field.php     # Toggle switch partial
├── frontend/
│   ├── build/
│   │   ├── bundle.js            # ← Compiled React bundle (run npm run build)
│   │   └── main.css             # ← Compiled styles
│   ├── src/
│   │   ├── index.jsx
│   │   ├── App.jsx
│   │   ├── context/CartContext.jsx
│   │   ├── components/
│   │   │   ├── RibbonButton.jsx
│   │   │   ├── Sidebar.jsx
│   │   │   ├── SearchBar.jsx
│   │   │   ├── CategorySection.jsx
│   │   │   ├── ProductItem.jsx
│   │   │   └── QuantityControl.jsx
│   │   ├── hooks/
│   │   │   ├── useProducts.js
│   │   │   └── useCart.js
│   │   ├── utils/
│   │   │   ├── api.js
│   │   │   └── debounce.js
│   │   └── styles/main.scss
│   ├── package.json
│   ├── webpack.config.js
│   └── README.md
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── languages/                   # .pot file for translations
```

## Caching

| Layer          | Method              | TTL        | Busted on          |
|----------------|---------------------|------------|--------------------|
| PHP backend    | WP Transient        | 5 minutes  | Product save/delete, admin save |
| React frontend | In-memory object    | 5 minutes  | Page reload        |

## Security

- All inputs sanitized via `sanitize_text_field()`, `absint()`, `sanitize_hex_color()`
- All outputs escaped via `esc_html()`, `esc_attr()`, `esc_url()`
- Nonce verified for all write operations (add to cart)
- REST endpoints use `permission_callback` (public read, nonce-guarded write)
- `declare(strict_types=1)` throughout PHP

## Hooks & Filters

**Actions:**
- `wcps/before_output` — fires before sidebar HTML is rendered
- `wcps/after_output` — fires after sidebar HTML is rendered

**Filters:**
- `wcps/product_data` — filter individual product array before REST response
- `wcps/products_query_args` — modify `wc_get_products()` arguments
- `wcps/cache_ttl` — change cache duration in seconds

## License

GPL v2 or later — see https://www.gnu.org/licenses/gpl-2.0.html
