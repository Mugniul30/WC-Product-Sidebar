/**
 * Sidebar — slide-in product panel
 */

import { useEffect, useRef, memo } from 'react';
import SearchBar from './SearchBar';
import CategorySection from './CategorySection';
import ProductItem from './ProductItem';
import { useProducts } from '../hooks/useProducts';

const Sidebar = memo(({ isOpen, onClose, config, ribbonPosition }) => {
  const sidebarRef = useRef(null);
  const i18n = config?.i18n || {};
  const settings = config?.settings || {};

  const {
    products,
    groupedProducts,
    categories,
    loading,
    error,
    search,
    setSearch,
    collapsedCategories,
    toggleCategory,
  } = useProducts(config);

  // Trap focus + ESC key when open
  useEffect(() => {
    if (!isOpen) return;

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') onClose();
    };

    document.addEventListener('keydown', handleKeyDown);

    // Prevent body scroll on mobile
    document.body.style.overflow = 'hidden';

    // Focus first interactive element
    const firstFocusable = sidebarRef.current?.querySelector(
      'button, input, [tabindex]:not([tabindex="-1"])'
    );
    firstFocusable?.focus();

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isOpen, onClose]);

  const showCategories = settings.showCategoryHeadings && !search;

  return (
    <>
      {/* Backdrop overlay */}
      <div
        className={`wcps-overlay ${isOpen ? 'wcps-overlay--visible' : ''}`}
        onClick={onClose}
        aria-hidden="true"
      />

      {/* Sidebar panel */}
      <aside
        id="wcps-sidebar"
        ref={sidebarRef}
        className={`wcps-sidebar ${ribbonPosition === 'left' ? 'wcps-sidebar--left' : ''} ${isOpen ? 'wcps-sidebar--open' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-label={settings.buttonLabel || 'Products'}
      >
        {/* Header */}
        <div className="wcps-sidebar-header">
          <div className="wcps-sidebar-header-left">
            <span className="wcps-sidebar-icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 0 1-8 0" />
              </svg>
            </span>
            <h2 className="wcps-sidebar-title">
              {settings.buttonLabel || 'Products'}
              <span className="wcps-sidebar-subtitle">
                {products.length > 0
                  ? `${products.length} product${products.length === 1 ? '' : 's'} available`
                  : (i18n.shopSubtitle || 'Shop our research collection')}
              </span>
            </h2>
          </div>
          <button
            className="wcps-close-btn"
            onClick={onClose}
            aria-label={i18n.close || 'Close'}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        {/* Search */}
        <div className="wcps-sidebar-search">
          <SearchBar
            value={search}
            onChange={setSearch}
            placeholder={i18n.searchPlaceholder || 'Search products…'}
          />
        </div>

        {/* Product list */}
        <div className="wcps-sidebar-body">
          {!loading && !error && search.trim() && products.length > 0 && (
            <p className="wcps-results-count" role="status">
              {products.length} result{products.length === 1 ? '' : 's'} for “{search.trim()}”
            </p>
          )}
          {loading && (
            <div className="wcps-loading">
              <div className="wcps-spinner" aria-hidden="true" />
              <span>{i18n.loading || 'Loading products…'}</span>
            </div>
          )}

          {error && (
            <div className="wcps-error" role="alert">
              <span>⚠ {error}</span>
            </div>
          )}

          {!loading && !error && products.length === 0 && (
            <div className="wcps-empty">
              <span className="wcps-empty-icon" aria-hidden="true">🔍</span>
              <p>{i18n.noProducts || 'No products found.'}</p>
            </div>
          )}

          {!loading && !error && showCategories && categories.length > 0 && (
            <div className="wcps-product-list">
              {categories.map((category) => (
                <CategorySection
                  key={category.id}
                  category={category}
                  products={groupedProducts[category.id] || []}
                  isCollapsed={collapsedCategories.has(category.id)}
                  onToggle={() => toggleCategory(category.id)}
                  config={config}
                />
              ))}
            </div>
          )}

          {!loading && !error && (!showCategories || categories.length === 0) && products.length > 0 && (
            <div className="wcps-product-list wcps-product-list--flat">
              {products.map((product) => (
                <ProductItem
                  key={product.id}
                  product={product}
                  config={config}
                />
              ))}
            </div>
          )}
        </div>

        {/* Footer */}
        {config?.cartPageUrl && (
          <div className="wcps-sidebar-footer">
            <a
              href={config.cartPageUrl}
              className="wcps-view-cart-btn"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
              </svg>
              {i18n.viewCart || 'View Cart'}
            </a>
            <p className="wcps-footer-note">{i18n.footerNote || 'Secure checkout · Research use only'}</p>
          </div>
        )}
      </aside>
    </>
  );
});

Sidebar.displayName = 'Sidebar';
export default Sidebar;
