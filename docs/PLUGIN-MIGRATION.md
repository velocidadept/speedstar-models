# Speedstar Models plugin migration audit

Evidence date: 2026-09-28.

This inventory is based on public frontend asset paths and WordPress REST namespaces from the current production site. A REST namespace proves that code registered that API route, but does not by itself prove that every feature is actively used. Business-critical plugins must be checked in wp-admin before removal.

| Current component evidenced | Evidence | 1.0 direction |
|---|---|---|
| WooCommerce | frontend assets + wc REST namespaces | KEEP. Commerce source of truth. |
| LiteSpeed Cache | litespeed/v1 and v3 namespaces | KEEP. Hosting runs LiteSpeed; configure conservatively. |
| Yoast SEO | yoast/v1 namespace | KEEP for migration. Preserve metadata/canonicals/sitemaps first. |
| Elementor | frontend assets + Elementor REST namespaces | REMOVE after confirming no surviving page content depends on it. New theme replaces layout/header/footer/product UI. |
| Jetpack | frontend assets + multiple Jetpack namespaces | REVIEW modules. Remove if no unique business function remains. |
| Jetpack Boost | jetpack-boost/v1 namespace | REMOVE if LiteSpeed handles the chosen performance features; avoid overlapping optimization layers. |
| WooPayments | frontend asset / payments namespaces | KEEP only if currently used for live payments. Never remove during migration without gateway/order audit. |
| WooCommerce PayPal Payments | frontend assets + paypal namespaces | KEEP if PayPal is offered/used. |
| Google Listings & Ads | frontend assets + wc/gla | KEEP if Merchant Center/product feed is active. |
| Google Site Kit | frontend assets + google-site-kit/v1 | OPTIONAL. Keep if used for Search Console/Analytics management. |
| Pinterest for WooCommerce | frontend assets + pinterest/v1 | OPTIONAL. Keep only if catalogue/tracking is used. |
| YITH WooCommerce Wishlist | frontend assets + yith/wishlist/v1 | REVIEW. Remove if wishlist is not a product requirement. |
| YITH WooCommerce Affiliates Premium | frontend assets + yith-wcaf namespace | REVIEW CAREFULLY. Preserve if an affiliate programme or historical commission data is in use. |
| YITH WooCommerce Quick View | frontend assets | REMOVE. New product/catalogue UX does not require it. |
| WooCommerce Notification | frontend assets | REVIEW/REMOVE unless sales notifications are deliberately required. |
| Pojo Accessibility | frontend assets | REMOVE only after the custom theme accessibility pass confirms equivalent/better keyboard, contrast and semantic behaviour. |
| Akismet | akismet/v1 namespace | OPTIONAL. Needed mainly if comments/forms exposed to spam are retained. |
| Health Check | health-check/v1 namespace | DEV/ADMIN only. Not required for storefront rendering. |
| FireBox / framework | firebox/fpframework namespaces | IDENTIFY dependent feature before removal. |
| Ifthenpay recommendation namespace | nakedcat-recommend-ifthenpay/v1 | INVESTIGATE. Namespace alone does not prove an active payment gateway. |

## Public architecture observed

Production currently exposes canonical paths such as:
- /shop
- /cart
- /checkout
- /about
- /product-category/bodykits
- /product-category/wheels
- /product-category/engines
- /product-category/t-shirts
- product URLs under /products/<slug>

The custom theme should preserve these where practical. Version 0.6 replaces the temporary ?ss= category navigation with native WooCommerce category archives.

## Target lean stack

Core target: WooCommerce + Speedstar custom theme + active payment gateway(s) + Yoast SEO + LiteSpeed Cache.

Add only business integrations that are actually used: Google Listings & Ads, Site Kit, Pinterest, newsletter/consent, affiliates, or wishlist. Do not duplicate caching, image optimization, sliders, galleries, quick view, header/footer builders, custom CSS/JS or page-builder functionality already owned by the theme.
