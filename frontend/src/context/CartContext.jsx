/**
 * Cart Context — global add-to-cart state and toast notifications
 */

import { createContext, useContext, useState, useCallback, useRef, useEffect } from 'react';
import { addToCart as apiAddToCart, fetchCartCount } from '../utils/api';

const CartContext = createContext(null);

const TOAST_VISIBLE_MS = 4000;
const TOAST_EXIT_MS = 250;

// Common theme mini-cart count selectors — last-resort fallback when the
// count endpoint is unreachable but the theme already renders a count.
const DOM_COUNT_SELECTORS = [
  '.site-header-cart .count',
  '.cart-contents .count',
  '.mini-cart-count',
  '.header-cart-count',
  '.wc-block-mini-cart__quantity',
  '[data-cart-count]',
];

function readDomCartCount() {
  if (typeof document === 'undefined') return 0;
  for (const selector of DOM_COUNT_SELECTORS) {
    const el = document.querySelector(selector);
    if (!el) continue;
    const raw = (el.getAttribute('data-cart-count') || el.textContent || '').replace(/[^\d]/g, '');
    const n = parseInt(raw, 10);
    if (Number.isFinite(n) && n > 0) return n;
  }
  return 0;
}

export function CartProvider({ children, config }) {
  // Seed from the server-rendered count so the badge is correct on first
  // paint — before any fetch resolves.
  const [cartCount, setCartCount] = useState(() => Number(config?.initialCartCount) || 0);
  const [toast, setToast] = useState(null);
  const [toastLeaving, setToastLeaving] = useState(false);
  const hideTimer = useRef(null);
  const removeTimer = useRef(null);
  const configRef = useRef(null);
  if (configRef.current == null && config) {
    configRef.current = { nonce: config.nonce, wpNonce: config.wpNonce };
  }

  const dismissToast = useCallback(() => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
    if (removeTimer.current) clearTimeout(removeTimer.current);
    setToastLeaving(true);
    removeTimer.current = setTimeout(() => {
      setToast(null);
      setToastLeaving(false);
    }, TOAST_EXIT_MS);
  }, []);

  const showToast = useCallback((message, type = 'success', extra = {}) => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
    if (removeTimer.current) clearTimeout(removeTimer.current);
    setToastLeaving(false);
    setToast({ message, type, ...extra });
    hideTimer.current = setTimeout(dismissToast, TOAST_VISIBLE_MS);
  }, [dismissToast]);

  const addToCart = useCallback(async (productId, quantity = 1) => {
    if (!config?.cartUrl) {
      const message = 'Cart is not configured. Please reload the page.';
      showToast(message, 'error');
      throw new Error(message);
    }
    try {
      const result = await apiAddToCart(
        config.cartUrl,
        configRef.current?.nonce ?? config.nonce,
        configRef.current?.wpNonce ?? config.wpNonce,
        productId,
        quantity,
        {
          nonceUrl: config.nonceUrl,
          onNoncesRefreshed: (fresh) => {
            configRef.current = { ...(configRef.current || {}), ...fresh };
            if (window.wcpsConfig) {
              window.wcpsConfig.nonce = fresh.nonce;
              window.wcpsConfig.wpNonce = fresh.wpNonce;
            }
          },
        }
      );

      if (result.success) {
        setCartCount(result.cart_count || 0);
        // Always route shoppers to checkout — never the cart page.
        const checkoutUrl = result.checkout_url || result.cart_url || config?.cartPageUrl;
        showToast(
          result.message || config?.i18n?.added || 'Added to cart!',
          'success',
          {
            cartUrl: checkoutUrl,
            checkoutUrl,
            viewCartLabel: config?.i18n?.viewCart,
            productName: result.product_name || '',
          }
        );

        // Update WooCommerce cart fragment (trigger without null parameters)
        if (typeof window.jQuery !== 'undefined') {
          window.jQuery(window.document.body).trigger('wc_fragment_refresh');
        }
      } else {
        showToast(result.message || 'Failed to add to cart', 'error');
      }

      return result;
    } catch (err) {
      const isAuthFailure = err?.status === 403 || err?.status === 401;
      const message = isAuthFailure
        ? (config?.i18n?.sessionExpired || 'Security session expired. Please reload the page and try again.')
        : (err?.message || 'Failed to add to cart');
      showToast(message, 'error');
      throw err instanceof Error ? err : new Error(message);
    }
  }, [config, showToast]);

  useEffect(() => () => {
    if (hideTimer.current) clearTimeout(hideTimer.current);
    if (removeTimer.current) clearTimeout(removeTimer.current);
  }, []);

  // Sync the ribbon badge with the real cart on mount: items added on a
  // previous page (or before a reload) must show immediately. Strategy:
  //   1. Localized initialCartCount (server-rendered, instant, always right).
  //   2. DOM mini-cart count fallback (theme-rendered, instant).
  //   3. REST /cart/count (authoritative, async).
  // Refreshes again whenever WooCommerce fires its cart events so the
  // badge never goes stale.
  useEffect(() => {
    let cancelled = false;

    const syncCount = async (allowZero = false) => {
      const { cart_count } = await fetchCartCount(config?.cartCountUrl);
      if (cancelled) return;
      if (cart_count > 0 || allowZero) setCartCount(cart_count);
    };

    // Instant fallbacks first — faster than the network, better than zero.
    const domCount = readDomCartCount();
    if (domCount > 0) setCartCount(domCount);

    syncCount(true);
    const retryTimer = setTimeout(() => syncCount(true), 2500);

    const onCartChanged = () => syncCount(true);
    const onExternalCount = (e) => {
      const n = Number(e?.detail?.cartCount);
      if (!cancelled && Number.isFinite(n) && n > 0) setCartCount(n);
    };
    window.addEventListener('wcps:cart-count', onExternalCount);
    window.addEventListener('wc_fragments_refreshed', onCartChanged);
    window.addEventListener('wc_fragments_loaded', onCartChanged);
    window.addEventListener('added_to_cart', onCartChanged);
    window.addEventListener('removed_from_cart', onCartChanged);
    window.addEventListener('updated_cart_totals', onCartChanged);
    if (typeof window.jQuery !== 'undefined') {
      window.jQuery(window.document.body).on('wc_fragments_refreshed.wcps added_to_cart.wcps removed_from_cart.wcps updated_cart_totals.wcps updated_wc_div.wcps', onCartChanged);
    }

    return () => {
      cancelled = true;
      clearTimeout(retryTimer);
      window.removeEventListener('wcps:cart-count', onExternalCount);
      window.removeEventListener('wc_fragments_refreshed', onCartChanged);
      window.removeEventListener('wc_fragments_loaded', onCartChanged);
      window.removeEventListener('added_to_cart', onCartChanged);
      window.removeEventListener('removed_from_cart', onCartChanged);
      window.removeEventListener('updated_cart_totals', onCartChanged);
      if (typeof window.jQuery !== 'undefined') {
        window.jQuery(window.document.body).off('.wcps');
      }
    };
  }, [config?.cartCountUrl]);

  // Read the live position (the settings bridge can toggle it after mount).
  const livePosition = (typeof window !== 'undefined' && window.wcpsConfig?.settings?.ribbonPosition)
    || config?.settings?.ribbonPosition
    || 'left';
  const toastSideClass = livePosition === 'left' ? 'wcps-toast--sidebar-left' : 'wcps-toast--sidebar-right';

  return (
    <CartContext.Provider value={{ cartCount, addToCart, toast, showToast }}>
      {children}
      {toast && (
        <div
          className={`wcps-toast wcps-toast--${toast.type} ${toastSideClass} ${toastLeaving ? 'wcps-toast--leaving' : ''}`}
          role="alert"
        >
          <span className="wcps-toast-icon" aria-hidden="true">
            {toast.type === 'success' ? (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            ) : (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            )}
          </span>

          <div className="wcps-toast-body">
            <span className="wcps-toast-title">
              {toast.type === 'success'
                ? (config?.i18n?.addedTitle || 'Added to cart')
                : (config?.i18n?.errorTitle || 'Something went wrong')}
            </span>
            <span className="wcps-toast-message">{toast.message}</span>
          </div>

          {toast.type === 'success' && toast.cartUrl && (
            <div className="wcps-toast-actions">
              <a href={toast.cartUrl} className="wcps-toast-link">
                {toast.viewCartLabel || 'Checkout'}
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <line x1="5" y1="12" x2="19" y2="12" />
                  <polyline points="12 5 19 12 12 19" />
                </svg>
              </a>
            </div>
          )}

          <button
            type="button"
            className="wcps-toast-close"
            aria-label="Dismiss"
            onClick={dismissToast}
          >
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
      )}
    </CartContext.Provider>
  );
}

export function useCartContext() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error('useCartContext must be used within CartProvider');
  return ctx;
}
