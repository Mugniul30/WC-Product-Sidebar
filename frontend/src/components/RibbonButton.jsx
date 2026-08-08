/**
 * RibbonButton — the floating trigger button
 */

import { memo } from 'react';
import { useCartContext } from '../context/CartContext';

const RibbonButton = memo(({ onClick, label, isOpen, ribbonPosition }) => {
  const { cartCount } = useCartContext();

  return (
    <button
      className={`wcps-ribbon-btn ${ribbonPosition === 'left' ? 'wcps-ribbon-btn--left' : ''} ${isOpen ? 'wcps-ribbon-btn--open' : ''}`}
      onClick={onClick}
      aria-label={`${label} — open product sidebar`}
      aria-expanded={isOpen}
      aria-controls="wcps-sidebar"
    >
      <span className="wcps-ribbon-icon" aria-hidden="true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
          <line x1="3" y1="6" x2="21" y2="6" />
          <path d="M16 10a4 4 0 0 1-8 0" />
        </svg>
      </span>
      <span className="wcps-ribbon-label">{label}</span>
      {cartCount > 0 && (
        <span className="wcps-ribbon-badge" aria-label={`${cartCount} items in cart`}>
          {cartCount > 99 ? '99+' : cartCount}
        </span>
      )}
    </button>
  );
});

RibbonButton.displayName = 'RibbonButton';
export default RibbonButton;
