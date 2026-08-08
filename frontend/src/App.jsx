/**
 * WC Product Sidebar — Root App Component
 */

import { useState, useCallback, memo } from 'react';
import Sidebar from './components/Sidebar';
import RibbonButton from './components/RibbonButton';
import { CartProvider } from './context/CartContext';
import './styles/main.scss';

const App = memo(({ config }) => {
  const [isOpen, setIsOpen] = useState(false);

  const openSidebar = useCallback(() => setIsOpen(true), []);
  const closeSidebar = useCallback(() => setIsOpen(false), []);

  return (
    <CartProvider config={config}>
      <RibbonButton
        onClick={openSidebar}
        label={config?.settings?.buttonLabel || 'Products'}
        isOpen={isOpen}
        ribbonPosition={config?.settings?.ribbonPosition}
      />
      <Sidebar
        isOpen={isOpen}
        onClose={closeSidebar}
        config={config}
        ribbonPosition={config?.settings?.ribbonPosition}
      />
    </CartProvider>
  );
});

App.displayName = 'App';
export default App;
