/**
 * useCart — thin hook that re-exports CartContext actions
 * kept for backward compatibility and as a standalone hook option.
 */

import { useCartContext } from '../context/CartContext';

export function useCart() {
  return useCartContext();
}
