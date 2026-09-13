/**
 * WC Product Sidebar — React App Entry Point
 */

import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import App from './App';

// Mount when DOM is ready
const mount = () => {
  let container = document.getElementById('wcps-root');
  if (!container) {
    // Inject mount point
    container = document.createElement('div');
    container.id = 'wcps-root';
    document.body.appendChild(container);
  }
  if (container.__wcpsMounted) return;
  container.__wcpsMounted = true;
  createRoot(container).render(
    <StrictMode>
      <App config={window.wcpsConfig || {}} />
    </StrictMode>
  );
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
