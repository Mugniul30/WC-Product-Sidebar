/**
 * SearchBar — debounced product search input
 */

import { useState, useEffect, useRef, memo, useCallback } from 'react';
import { debounce } from '../utils/debounce';

const SearchBar = memo(({ value, onChange, placeholder }) => {
  const [localValue, setLocalValue] = useState(value);
  const inputRef = useRef(null);

  // Sync external value resets (e.g. clearing search)
  useEffect(() => {
    if (value === '') setLocalValue('');
  }, [value]);

  // Debounced upstream change — 300ms
  const debouncedOnChange = useRef(
    debounce((val) => onChange(val), 300)
  ).current;

  const handleChange = useCallback((e) => {
    const val = e.target.value;
    setLocalValue(val);
    debouncedOnChange(val);
  }, [debouncedOnChange]);

  const handleClear = useCallback(() => {
    setLocalValue('');
    onChange('');
    inputRef.current?.focus();
  }, [onChange]);

  return (
    <div className="wcps-search-bar">
      <span className="wcps-search-icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
      </span>
      <input
        ref={inputRef}
        type="search"
        className="wcps-search-input"
        value={localValue}
        onChange={handleChange}
        placeholder={placeholder}
        autoComplete="off"
        spellCheck="false"
        aria-label={placeholder}
      />
      {localValue && (
        <button
          className="wcps-search-clear"
          onClick={handleClear}
          aria-label="Clear search"
          type="button"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      )}
    </div>
  );
});

SearchBar.displayName = 'SearchBar';
export default SearchBar;
