/**
 * API utilities — REST calls to the WC Product Sidebar endpoints
 */

/**
 * Fetch product list from the custom REST endpoint.
 *
 * @param {string} apiUrl    - Full REST URL for /wc-ps/v1/products
 * @param {string} wpNonce   - WordPress REST nonce (X-WP-Nonce)
 * @param {string} search    - Optional search query
 * @param {AbortSignal} signal - AbortController signal
 * @returns {Promise<Array>}
 */
export async function fetchProducts(apiUrl, wpNonce, search = '', signal = null) {
  if (!apiUrl) {
    throw new Error('Product API URL is not configured.');
  }

  let url;
  try {
    url = new URL(apiUrl, window.location.origin);
  } catch {
    throw new Error('Product API URL is invalid.');
  }

  if (search) {
    url.searchParams.set('search', search);
  }

  const options = {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
    },
    credentials: 'same-origin',
  };

  if (signal) options.signal = signal;

  const response = await fetch(url.toString(), options);

  if (!response.ok) {
    let message = `HTTP error ${response.status}`;
    try {
      const data = await response.json();
      message = data?.message || message;
    } catch {
      // ignore parse error
    }
    throw new Error(message);
  }

  try {
    const data = await response.json();
    return Array.isArray(data) ? data : [];
  } catch {
    throw new Error('Invalid product response from server.');
  }
}

/**
 * Fetch a fresh nonce pair from the uncached /wc-ps/v1/nonce endpoint.
 *
 * Page/CDN caches can serve HTML with expired localized nonces to some
 * devices; calling this lets the app self-heal instead of hard-failing.
 *
 * @param {string} nonceUrl - Full REST URL for /wc-ps/v1/nonce
 * @returns {Promise<{nonce: string, wpNonce: string}|null>}
 */
export async function fetchFreshNonces(nonceUrl) {
  if (!nonceUrl) return null;

  let url;
  try {
    url = new URL(nonceUrl, window.location.origin);
  } catch {
    return null;
  }

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!response.ok) return null;
    const data = await response.json();
    if (!data || typeof data.nonce !== 'string') return null;
    return { nonce: data.nonce, wpNonce: data.wpNonce || '' };
  } catch {
    return null;
  }
}

/**
 * Fetch the live cart item count so the ribbon badge is correct on first
 * paint — even when items were added before this page load.
 *
 * @param {string} countUrl - Full REST URL for /wc-ps/v1/cart/count
 * @returns {Promise<{cart_count: number, checkout_url: string}>}
 */
export async function fetchCartCount(countUrl) {
  if (!countUrl) return { cart_count: 0, checkout_url: '' };

  let url;
  try {
    url = new URL(countUrl, window.location.origin);
  } catch {
    return { cart_count: 0, checkout_url: '' };
  }

  try {
    const response = await fetch(url.toString(), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!response.ok) return { cart_count: 0, checkout_url: '' };
    const data = await response.json();
    return {
      cart_count: Number(data?.cart_count) || 0,
      checkout_url: data?.checkout_url || '',
    };
  } catch {
    return { cart_count: 0, checkout_url: '' };
  }
}

/**
 * Add a product to the WooCommerce cart via the wc-ajax endpoint.
 *
 * Sends as application/x-www-form-urlencoded (FormData) which is what
 * WooCommerce's ?wc-ajax=add_to_cart handler expects. JSON bodies are
 * silently ignored by that handler, causing only the first queued item
 * to appear in the cart.
 *
 * Requests are serialised through a promise queue so that rapid
 * successive clicks (products 1, 2, 3 …) each wait for the previous
 * response before firing — preventing nonce/session race conditions.
 *
 * @param {string} cartUrl    - Full URL for the add-to-cart endpoint
 * @param {string} nonce      - Plugin nonce for permission check
 * @param {string} wpNonce    - WordPress REST nonce (X-WP-Nonce header)
 * @param {number} productId  - Product ID or Variation ID
 * @param {number} quantity   - Quantity to add
 * @returns {Promise<Object>}
 */

// Serialise concurrent add-to-cart calls so WC session/nonce state
// is consistent between requests.
let _cartQueue = Promise.resolve();

export async function addToCart(cartUrl, nonce, wpNonce, productId, quantity = 1, options = {}) {
  const run = () => _doAddToCart(cartUrl, nonce, wpNonce, productId, quantity, options);
  // Chain onto the existing queue; always resolve so the queue keeps moving.
  const queued = _cartQueue.then(run, run);
  _cartQueue = queued.then(() => { }, () => { });
  return queued;
}

async function _doAddToCart(cartUrl, nonce, wpNonce, productId, quantity, options = {}) {
  if (!cartUrl) {
    throw new Error('Cart API URL is not configured.');
  }

  const attempt = async (activeNonce, activeWpNonce) => {
    // WooCommerce's wc-ajax handler reads $_POST, not php://input,
    // so the body must be form-encoded — NOT JSON.
    const body = new URLSearchParams({
      product_id: String(productId),
      quantity: String(quantity),
      nonce: activeNonce || '',
    });

    let response;
    try {
      response = await fetch(cartUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'X-WP-Nonce': activeWpNonce || '',
        },
        credentials: 'same-origin',
        body: body.toString(),
      });
    } catch {
      throw new Error('Network error. Please check your connection and try again.');
    }

    let data = null;
    try {
      data = await response.json();
    } catch {
      data = null;
    }

    if (!response.ok) {
      const err = new Error(data?.message || `HTTP error ${response.status}`);
      err.status = response.status;
      err.code = data?.code;
      throw err;
    }

    return data;
  };

  let data;
  try {
    data = await attempt(nonce, wpNonce);
  } catch (err) {
    // Nonce expired (typical on devices served a cached page): fetch a fresh
    // pair once and retry before surfacing an error to the shopper.
    const isNonceFailure = err?.status === 403 || err?.status === 401;
    if (!isNonceFailure || options.nonceUrl == null) throw err;

    const fresh = await fetchFreshNonces(options.nonceUrl);
    if (!fresh) throw err;

    if (typeof options.onNoncesRefreshed === 'function') {
      try { options.onNoncesRefreshed(fresh); } catch { /* ignore */ }
    }

    data = await attempt(fresh.nonce, fresh.wpNonce);
  }

  return {
    success: true,
    cart_count: data?.cart_count || 0,
    cart_total: data?.cart_total || '£0.00',
    cart_url: data?.checkout_url || data?.cart_url || window.wcpsConfig?.cartPageUrl,
    checkout_url: data?.checkout_url || data?.cart_url || window.wcpsConfig?.cartPageUrl,
    product_name: data?.product_name || '',
    message: data?.message || 'Product added to cart',
  };
}