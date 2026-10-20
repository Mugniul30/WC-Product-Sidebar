/**
 * ProductItem — single product row, all controls on one line
 */

import { memo, useState, useCallback, useRef, useEffect } from 'react';
import QuantityControl from './QuantityControl';
import { useCartContext } from '../context/CartContext';

const ProductItem = memo(({ product, config }) => {
  const [quantity, setQuantity] = useState(1);
  const [adding, setAdding] = useState(false);
  const [justAdded, setJustAdded] = useState(false);
  const { addToCart } = useCartContext();
  const timerRef = useRef(null);
  const i18n = config?.i18n || {};

  const isOutOfStock = !product.in_stock;
  const isVariable = product.type === 'variable';
  const isVariation = product.type === 'variation';
  const maxQty = product.stock_qty ?? 999;

  const handleAddToCart = useCallback(async () => {
    if (adding || isOutOfStock) return;
    if (isVariable) {
      if (product.permalink) window.open(product.permalink, '_blank', 'noopener,noreferrer');
      return;
    }
    setAdding(true);
    try {
      await addToCart(product.id, quantity);
      setJustAdded(true);
      timerRef.current = setTimeout(() => setJustAdded(false), 2000);
    } catch {
      // Toast is shown by cart context; no further action needed here.
    } finally { setAdding(false); }
  }, [adding, isOutOfStock, isVariable, product, quantity, addToCart]);

  useEffect(() => () => {
    if (timerRef.current) clearTimeout(timerRef.current);
  }, []);

  return (
    <article className={`wcps-product-item ${isOutOfStock ? 'wcps-product-item--oos' : ''}`}>

      {/* Thumbnail */}
      <div className="wcps-product-thumb">
        {product.thumbnail
          ? <img src={product.thumbnail} alt="" width="48" height="48" loading="lazy" decoding="async" />
          : <span className="wcps-product-thumb-placeholder" aria-hidden="true">📦</span>}
        {product.featured && (
          <span className="wcps-featured-badge" aria-label={i18n.featured || 'Featured'}>★</span>
        )}
      </div>

      {/* Name + price — flex grows to fill */}
      <div className="wcps-product-info">
        <a href={product.permalink} className="wcps-product-name"
          target="_blank" rel="noopener noreferrer">
          {product.name}
        </a>
        {isVariable && (
          <span className="wcps-variation-hint">Select options</span>
        )}
        <div className="wcps-product-price"
          dangerouslySetInnerHTML={{ __html: product.price_html }} />
        {isOutOfStock && (
          <span className="wcps-oos-label">{i18n.outOfStock || 'Out of Stock'}</span>
        )}
      </div>

      {/* Quantity stepper — for simple products and variations, not parent variable products */}
      {!isOutOfStock && !isVariable && (
        <QuantityControl value={quantity} min={1} max={maxQty} onChange={setQuantity} />
      )}

      {/* Add to cart — always present, inline */}
      <button
        className={[
          'wcps-atc-btn',
          justAdded ? 'wcps-atc-btn--added' : '',
          isOutOfStock ? 'wcps-atc-btn--disabled' : '',
        ].join(' ').trim()}
        onClick={handleAddToCart}
        disabled={adding || isOutOfStock}
        aria-label={`${i18n.addToCart || 'Add to cart'}: ${product.name}`}
        type="button"
        title={isVariable ? 'View options' : i18n.addToCart || 'Add to cart'}
      >
        {adding ? (
          <span className="wcps-btn-spinner" aria-hidden="true" />
        ) : justAdded ? (
          /* checkmark */
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
        ) : isVariable ? (
          /* external link */
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
            <polyline points="15 3 21 3 21 9" />
            <line x1="10" y1="14" x2="21" y2="3" />
          </svg>
        ) : (
          /* cart icon */
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
          </svg>
        )}
      </button>

    </article>
  );
});

ProductItem.displayName = 'ProductItem';
export default ProductItem;
