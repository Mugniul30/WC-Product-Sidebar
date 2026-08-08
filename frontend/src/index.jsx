/**
 * WC Product Sidebar — React App Entry Point
 */

import { createRoot } from 'react-dom/client';
import { StrictMode } from 'react';
import App from './App';

// Mount when DOM is ready
const mount = () => {
  const container = document.getElementById('wcps-root');
  if (!container) {
    // Inject mount point
    const div = document.createElement('div');
    div.id = 'wcps-root';
    document.body.appendChild(div);
    createRoot(div).render(
      <StrictMode>
        <App config={window.wcpsConfig || {}} />
      </StrictMode>
    );
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
