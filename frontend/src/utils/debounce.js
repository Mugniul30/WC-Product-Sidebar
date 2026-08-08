/**
 * debounce — delays invoking fn until after wait ms have elapsed
 * since the last invocation.
 *
 * @param {Function} fn   - Function to debounce
 * @param {number}   wait - Delay in milliseconds
 * @returns {Function}    - Debounced function with a .cancel() method
 */
export function debounce(fn, wait = 300) {
  let timer = null;

  function debounced(...args) {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
      fn.apply(this, args);
    }, wait);
  }

  debounced.cancel = function () {
    if (timer) {
      clearTimeout(timer);
      timer = null;
    }
  };

  debounced.flush = function (...args) {
    debounced.cancel();
    fn.apply(this, args);
  };

  return debounced;
}
