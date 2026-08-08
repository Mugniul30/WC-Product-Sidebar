/**
 * CategorySection — collapsible category group
 */

import { memo } from 'react';
import ProductItem from './ProductItem';

const CategorySection = memo(({ category, products, isCollapsed, onToggle, config }) => {
  if (!products || products.length === 0) return null;

  return (
    <div className={`wcps-category-section ${isCollapsed ? 'wcps-category-section--collapsed' : ''}`}>
      <button
        className="wcps-category-header"
        onClick={onToggle}
        aria-expanded={!isCollapsed}
        aria-controls={`wcps-cat-${category.id}`}
        type="button"
      >
        <span className="wcps-category-name">{category.name}</span>
        <span className="wcps-category-meta">
          <span className="wcps-category-count">{products.length}</span>
          <span className="wcps-category-chevron" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </span>
        </span>
      </button>

      <div
        id={`wcps-cat-${category.id}`}
        className="wcps-category-products"
        role="region"
        aria-label={category.name}
      >
        {!isCollapsed && products.map((product) => (
          <ProductItem
            key={product.id}
            product={product}
            config={config}
          />
        ))}
      </div>
    </div>
  );
});

CategorySection.displayName = 'CategorySection';
export default CategorySection;
