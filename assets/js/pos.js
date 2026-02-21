/* ═══════════════════════════════════════════════════════════
   Giftz POS — Cart State Machine
   ═══════════════════════════════════════════════════════════ */

const POS = (() => {

  let cart     = [];           // [{ id, sku, name, price, stock, image, qty }]
  let discount = 0;
  let discType = 'flat';       // 'flat' | 'percent'
  let payMethod = 'cash';
  let customerId = 0;

  // ─── DOM refs (set after DOMContentLoaded) ───────────────
  let cartItemsEl, cartCountEl, cartEmptyEl;
  let subtotalEl, discountEl, taxEl, totalEl;
  let discountInput, discountTypeFlat, discountTypePercent;
  let checkoutBtn;

  const TAX_RATE = 0; // set to e.g. 0.12 for 12% VAT

  // ─── Cart Operations ─────────────────────────────────────
  function findItem(productId) {
    return cart.find(i => i.id === productId);
  }

  function addItem(product) {
    const existing = findItem(product.id);
    if (existing) {
      if (existing.qty < product.stock) {
        existing.qty++;
        render();
      } else {
        showToast('Insufficient stock', 'warning');
      }
    } else {
      if (product.stock < 1) {
        showToast('Out of stock', 'error');
        return;
      }
      cart.push({ ...product, qty: 1 });
      render();
    }
  }

  function removeItem(productId) {
    cart = cart.filter(i => i.id !== productId);
    render();
  }

  function setQty(productId, qty) {
    const item = findItem(productId);
    if (!item) return;
    qty = Math.max(1, Math.min(parseInt(qty) || 1, item.stock));
    item.qty = qty;
    render();
  }

  function clearCart() {
    cart = [];
    discount = 0;
    if (discountInput) discountInput.value = '';
    render();
  }

  // ─── Calculations ─────────────────────────────────────────
  function calcSubtotal() {
    return cart.reduce((sum, i) => sum + i.price * i.qty, 0);
  }

  function calcDiscount(sub) {
    if (discType === 'percent') return sub * (discount / 100);
    return Math.min(discount, sub);
  }

  function calcTotal() {
    const sub  = calcSubtotal();
    const disc = calcDiscount(sub);
    const taxable = sub - disc;
    const tax  = taxable * TAX_RATE;
    const total = taxable + tax;
    return { sub, disc, tax, total };
  }

  // ─── Render ───────────────────────────────────────────────
  function render() {
    if (!cartItemsEl) return;

    const { sub, disc, tax, total } = calcTotal();

    // Cart count badge
    const itemCount = cart.reduce((s, i) => s + i.qty, 0);
    if (cartCountEl) cartCountEl.textContent = itemCount;

    // Empty state
    if (cartEmptyEl) cartEmptyEl.style.display = cart.length === 0 ? 'block' : 'none';

    // Items
    let html = '';
    cart.forEach(item => {
      const imgHtml = item.image
        ? `<img src="${item.image}" alt="" class="cart-item-thumb">`
        : `<div class="cart-item-thumb" style="display:grid;place-items:center;font-size:.9rem;color:#ccc;">🎁</div>`;

      html += `
        <div class="cart-item" data-id="${item.id}">
          ${imgHtml}
          <div class="cart-item-info">
            <div class="cart-item-name">${escHtml(item.name)}</div>
            <div class="cart-item-price">${formatCurrency(item.price)} each</div>
            <div class="cart-item-qty">
              <button class="qty-btn" onclick="POS.changeQty(${item.id}, -1)">−</button>
              <input class="qty-input" type="number" value="${item.qty}" min="1" max="${item.stock}"
                onchange="POS.setQty(${item.id}, this.value)"
                onfocus="this.select()">
              <button class="qty-btn" onclick="POS.changeQty(${item.id}, 1)">+</button>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.5rem">
            <button class="cart-remove-btn" onclick="POS.removeItem(${item.id})" title="Remove">✕</button>
            <div class="cart-item-subtotal">${formatCurrency(item.price * item.qty)}</div>
          </div>
        </div>`;
    });
    cartItemsEl.innerHTML = html || '';

    // Totals
    if (subtotalEl) subtotalEl.textContent = formatCurrency(sub);
    if (discountEl) discountEl.textContent = '−' + formatCurrency(disc);
    if (taxEl && TAX_RATE > 0) {
      taxEl.textContent = formatCurrency(tax);
      taxEl.parentElement.style.display = 'flex';
    }
    if (totalEl) totalEl.textContent = formatCurrency(total);

    // Checkout button
    if (checkoutBtn) {
      checkoutBtn.disabled = cart.length === 0;
    }

    // Update hidden form fields
    updateHiddenForm(sub, disc, tax, total);
  }

  function updateHiddenForm(sub, disc, tax, total) {
    setHidden('form_subtotal',    sub.toFixed(2));
    setHidden('form_discount',    disc.toFixed(2));
    setHidden('form_tax',         tax.toFixed(2));
    setHidden('form_total',       total.toFixed(2));
    setHidden('form_cart',        JSON.stringify(cart));
    setHidden('form_payment',     payMethod);
    setHidden('form_customer_id', customerId);
    setHidden('form_disc_type',   discType);
  }

  function setHidden(name, value) {
    const el = document.getElementById(name);
    if (el) el.value = value;
  }

  // ─── Public API ───────────────────────────────────────────
  function changeQty(productId, delta) {
    const item = findItem(productId);
    if (item) setQty(productId, item.qty + delta);
  }

  function setDiscountType(type) {
    discType = type;
    document.querySelectorAll('.discount-type-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.type === type);
    });
    render();
  }

  function setPaymentMethod(method) {
    payMethod = method;
    document.querySelectorAll('.pm-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.method === method);
    });
    setHidden('form_payment', payMethod);
  }

  function setCustomer(id) {
    customerId = parseInt(id) || 0;
    setHidden('form_customer_id', customerId);
  }

  // ─── Init ─────────────────────────────────────────────────
  function init() {
    cartItemsEl    = document.getElementById('cartItems');
    cartCountEl    = document.getElementById('cartCount');
    cartEmptyEl    = document.getElementById('cartEmpty');
    subtotalEl     = document.getElementById('subtotalVal');
    discountEl     = document.getElementById('discountVal');
    taxEl          = document.getElementById('taxVal');
    totalEl        = document.getElementById('totalVal');
    discountInput  = document.getElementById('discountInput');
    checkoutBtn    = document.getElementById('checkoutBtn');

    discountInput?.addEventListener('input', () => {
      discount = parseFloat(discountInput.value) || 0;
      render();
    });

    document.querySelectorAll('.discount-type-btn').forEach(btn => {
      btn.addEventListener('click', () => setDiscountType(btn.dataset.type));
    });

    document.querySelectorAll('.pm-btn').forEach(btn => {
      btn.addEventListener('click', () => setPaymentMethod(btn.dataset.method));
    });

    document.getElementById('customerSelect')?.addEventListener('change', function() {
      setCustomer(this.value);
    });

    document.getElementById('clearCartBtn')?.addEventListener('click', () => {
      if (cart.length && confirm('Clear all items from cart?')) clearCart();
    });

    // Search / filter product grid
    const searchInput = document.getElementById('posSearch');
    searchInput?.addEventListener('input', filterProducts);

    document.querySelectorAll('.cat-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        filterProducts();
      });
    });

    // Mobile cart expand
    document.getElementById('cartToggleMobile')?.addEventListener('click', () => {
      document.querySelector('.pos-cart-panel')?.classList.toggle('cart-expanded');
    });

    initBarcodeScanner();
    render();
  }

  function filterProducts() {
    const query  = (document.getElementById('posSearch')?.value || '').toLowerCase().trim();
    const catId  = document.querySelector('.cat-tab.active')?.dataset.catId || '0';

    document.querySelectorAll('.pos-product-card').forEach(card => {
      const name    = (card.dataset.name || '').toLowerCase();
      const sku     = (card.dataset.sku  || '').toLowerCase();
      const cardCat = card.dataset.catId || '0';

      const matchQuery = !query || name.includes(query) || sku.includes(query);
      const matchCat   = catId === '0' || cardCat === catId;

      card.style.display = matchQuery && matchCat ? '' : 'none';
    });
  }

  // ─── Barcode Scanner ──────────────────────────────────────
  function processBarcode(code) {
    code = code.trim().toUpperCase();
    if (!code) return;

    const card = [...document.querySelectorAll('.pos-product-card')]
      .find(c => (c.dataset.sku || '').toUpperCase() === code);

    if (!card) {
      showToast('Barcode not found: ' + code, 'error');
      flashBarcodeInput('error');
      return;
    }

    if (card.classList.contains('out-of-stock')) {
      showToast((card.dataset.name || 'Product') + ' is out of stock', 'warning');
      flashBarcodeInput('warning');
      return;
    }

    card.click();
    flashBarcodeInput('success');
  }

  function flashBarcodeInput(type) {
    const input = document.getElementById('barcodeInput');
    if (!input) return;
    const border = { success: 'var(--success)', error: 'var(--danger)', warning: '#f59e0b' };
    const bg     = { success: '#f0fdf4',        error: '#fef2f2',       warning: '#fffbeb' };
    input.style.borderColor = border[type] || '';
    input.style.background  = bg[type]     || '';
    setTimeout(() => { input.style.borderColor = ''; input.style.background = ''; }, 600);
  }

  function initBarcodeScanner() {
    const barcodeInput = document.getElementById('barcodeInput');
    if (!barcodeInput) return;

    // Dedicated input: Enter key triggers scan
    barcodeInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const code = barcodeInput.value.trim();
        barcodeInput.value = '';
        processBarcode(code);
      }
    });

    // Global keydown: F2 focuses the barcode field;
    // accumulator captures scanner keystrokes when no input is focused
    let accumulated = '';
    let accTimer    = null;

    document.addEventListener('keydown', e => {
      if (e.key === 'F2') {
        e.preventDefault();
        barcodeInput.focus();
        barcodeInput.select();
        return;
      }

      // Only accumulate when focus is not on any form element
      const tag = document.activeElement?.tagName?.toLowerCase();
      if (['input', 'textarea', 'select'].includes(tag)) return;

      if (e.key === 'Enter') {
        if (accumulated.length > 2) processBarcode(accumulated);
        accumulated = '';
        clearTimeout(accTimer);
        return;
      }

      if (e.key.length === 1) {          // printable character
        accumulated += e.key;
        clearTimeout(accTimer);
        accTimer = setTimeout(() => { accumulated = ''; }, 80); // reset if typing stalls
      }
    });
  }

  // ─── Helpers ──────────────────────────────────────────────
  function escHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  function formatCurrency(amount) {
    const sym = window.CURRENCY_SYMBOL || '₱';
    return sym + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function showToast(msg, type = 'info') {
    const cont = document.querySelector('.flash-container') || (() => {
      const c = document.createElement('div');
      c.className = 'flash-container';
      document.body.appendChild(c);
      return c;
    })();

    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    const el = document.createElement('div');
    el.className = `flash flash-${type}`;
    el.innerHTML = `<span class="flash-icon">${icons[type] || 'ℹ'}</span>
      <span class="flash-msg">${msg}</span>
      <button class="flash-close" onclick="this.parentElement.remove()">×</button>`;
    cont.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  document.addEventListener('DOMContentLoaded', init);

  return { addItem, removeItem, setQty, changeQty, clearCart, setDiscountType, setPaymentMethod, setCustomer };

})();

// Global alias for inline onclick handlers
window.POS = POS;
window.CURRENCY_SYMBOL = window.CURRENCY_SYMBOL || '₱';
