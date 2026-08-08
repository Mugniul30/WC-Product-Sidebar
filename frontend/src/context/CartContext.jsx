/**
 * Cart Context — global add-to-cart state and toast notifications
 */

import { createContext, useContext, useState, useCallback, useRef } from 'react';
import { addToCart as apiAddToCart } from '../utils/api';

const CartContext = createContext(null);

const TOAST_VISIBLE_MS = 3000;
const TOAST_EXIT_MS = 250;

export function CartProvider({ children, config }) {
  const [cartCount, setCartCount] = useState(0);
  const [toast, setToast] = useState(null);
  const [toastLeaving, setToastLeaving] = useState(false);
  const hideTimer = useRef(null);
  const removeTimer = useRef(null);

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
    try {
      const result = await apiAddToCart(
        config.cartUrl,
        config.nonce,
        config.wpNonce,
        productId,
        quantity
      );

      if (result.success) {
        setCartCount(result.cart_count || 0);
        showToast(
          result.message || config?.i18n?.added || 'Added to cart!',
          'success',
          { cartUrl: result.cart_url || config?.cartPageUrl, viewCartLabel: config?.i18n?.viewCart }
        );

        // Update WooCommerce cart fragment (trigger without null parameters)
        if (typeof jQuery !== 'undefined') {
          jQuery(document.body).trigger('wc_fragment_refresh');
        }
      } else {
        showToast(result.message || 'Failed to add to cart', 'error');
      }

      return result;
    } catch (err) {
      const message = err.message || 'Failed to add to cart';
      console.error('Add to cart error:', message, err);
      showToast(message, 'error');
      throw err;
    }
  }, [config, showToast]);

  return (
    <CartContext.Provider value={{ cartCount, addToCart, toast, showToast }}>
      {children}
      {toast && (
        <div
          className={`wcps-toast wcps-toast--${toast.type} ${toastLeaving ? 'wcps-toast--leaving' : ''}`}
          role="alert"
        >
          <span className="wcps-toast-icon" aria-hidden="true">
            {toast.type === 'success' ? (
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
            ) : (
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            )}
          </span>

          <span className="wcps-toast-message">{toast.message}</span>

          {toast.type === 'success' && toast.cartUrl && (
            <a href={toast.cartUrl} className="wcps-toast-link">
              {toast.viewCartLabel || 'View cart'}
            </a>
          )}

          <button
            type="button"
            className="wcps-toast-close"
            aria-label="Dismiss"
            onClick={dismissToast}
          >
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
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
