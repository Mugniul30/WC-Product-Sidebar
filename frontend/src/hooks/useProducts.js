/**
 * useProducts — fetch, cache, filter, and group products
 */

import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { fetchProducts } from '../utils/api';
import { debounce } from '../utils/debounce';

const CACHE_TTL = 5 * 60 * 1000; // 5 minutes in ms
const memoryCache = { data: null, ts: 0 };

export function useProducts(config) {
  const [allProducts, setAllProducts] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [collapsedCategories, setCollapsedCategories] = useState(new Set());
  const abortRef = useRef(null);

  // Debounce search → triggers API call for search terms
  const debouncedSetSearch = useRef(
    debounce((val) => setDebouncedSearch(val), 300)
  ).current;

  const handleSearchChange = useCallback((val) => {
    setSearch(val);
    debouncedSetSearch(val);
  }, [debouncedSetSearch]);

  // Fetch products
  useEffect(() => {
    if (!config?.apiUrl) return;

    const isSearch = debouncedSearch.trim().length > 0;

    // Use memory cache for initial full load
    if (!isSearch) {
      const now = Date.now();
      if (memoryCache.data && (now - memoryCache.ts) < CACHE_TTL) {
        setAllProducts(memoryCache.data);
        setLoading(false);
        return;
      }
    }

    // Cancel previous request
    if (abortRef.current) abortRef.current.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setError(null);

    fetchProducts(config.apiUrl, config.wpNonce, debouncedSearch, controller.signal)
      .then((data) => {
        if (!isSearch) {
          memoryCache.data = data;
          memoryCache.ts = Date.now();
        }
        setAllProducts(data);
        setLoading(false);
      })
      .catch((err) => {
        if (err.name === 'AbortError') return;
        setError(err.message || 'Failed to load products.');
        setLoading(false);
      });

    return () => controller.abort();
  }, [config?.apiUrl, config?.wpNonce, debouncedSearch]);

  // Client-side search filter (for fast UX while debounce waits)
  const products = useMemo(() => {
    if (!search) return allProducts;
    const q = search.toLowerCase();
    return allProducts.filter(
      (p) =>
        (p.name || '').toLowerCase().includes(q) ||
        p.categories?.some((c) => (c.name || '').toLowerCase().includes(q))
    );
  }, [allProducts, search]);

  // Derive unique categories from visible products
  const categories = useMemo(() => {
    const catMap = new Map();
    products.forEach((product) => {
      product.categories?.forEach((cat) => {
        if (!catMap.has(cat.id)) {
          catMap.set(cat.id, cat);
        }
      });
    });
    return Array.from(catMap.values());
  }, [products]);

  // Group products by first category
  const groupedProducts = useMemo(() => {
    const groups = {};
    products.forEach((product) => {
      const catId = product.categories?.[0]?.id ?? 0;
      if (!groups[catId]) groups[catId] = [];
      groups[catId].push(product);
    });
    return groups;
  }, [products]);

  const toggleCategory = useCallback((catId) => {
    setCollapsedCategories((prev) => {
      const next = new Set(prev);
      if (next.has(catId)) {
        next.delete(catId);
      } else {
        next.add(catId);
      }
      return next;
    });
  }, []);

  return {
    products,
    groupedProducts,
    categories,
    loading,
    error,
    search,
    setSearch: handleSearchChange,
    collapsedCategories,
    toggleCategory,
  };
}
