/**
 * QuantityControl — +/- stepper for product quantities
 */

import { memo, useCallback } from 'react';

const QuantityControl = memo(({ value, min = 1, max = 999, onChange }) => {
  const decrement = useCallback(() => {
    if (value > min) onChange(value - 1);
  }, [value, min, onChange]);

  const increment = useCallback(() => {
    if (value < max) onChange(value + 1);
  }, [value, max, onChange]);

  const handleInput = useCallback((e) => {
    const parsed = parseInt(e.target.value, 10);
    if (!isNaN(parsed)) {
      onChange(Math.min(max, Math.max(min, parsed)));
    }
  }, [min, max, onChange]);

  return (
    <div className="wcps-qty-control" role="group" aria-label="Quantity">
      <button
        className="wcps-qty-btn wcps-qty-btn--dec"
        onClick={decrement}
        disabled={value <= min}
        aria-label="Decrease quantity"
        type="button"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </button>
      <input
        className="wcps-qty-input"
        type="number"
        value={value}
        min={min}
        max={max}
        onChange={handleInput}
        aria-label="Quantity"
      />
      <button
        className="wcps-qty-btn wcps-qty-btn--inc"
        onClick={increment}
        disabled={value >= max}
        aria-label="Increase quantity"
        type="button"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
          <line x1="12" y1="5" x2="12" y2="19"/>
          <line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
      </button>
    </div>
  );
});

QuantityControl.displayName = 'QuantityControl';
export default QuantityControl;
