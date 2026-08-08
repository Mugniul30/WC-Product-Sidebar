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
  const url = new URL(apiUrl);

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

  return response.json();
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

export async function addToCart(cartUrl, nonce, wpNonce, productId, quantity = 1) {
  const run = () => _doAddToCart(cartUrl, nonce, wpNonce, productId, quantity);
  // Chain onto the existing queue; always resolve so the queue keeps moving.
  const queued = _cartQueue.then(run, run);
  _cartQueue = queued.then(() => { }, () => { });
  return queued;
}

async function _doAddToCart(cartUrl, nonce, wpNonce, productId, quantity) {
  // WooCommerce's wc-ajax handler reads $_POST, not php://input,
  // so the body must be form-encoded — NOT JSON.
  const body = new URLSearchParams({
    product_id: String(productId),
    quantity: String(quantity),
    nonce: nonce || '',
  });

  const response = await fetch(cartUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Accept': 'application/json',
      'X-WP-Nonce': wpNonce || '',
    },
    credentials: 'same-origin',
    body: body.toString(),
  });

  const data = await response.json();

  if (!response.ok) {
    throw new Error(data?.message || `HTTP error ${response.status}`);
  }

  return {
    success: true,
    cart_count: data?.cart_count || 0,
    cart_total: data?.cart_total || '£0.00',
    cart_url: data?.cart_url || window.wcpsConfig?.cartPageUrl,
    message: data?.message || 'Product added to cart',
  };
}