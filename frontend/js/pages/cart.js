// Cart page: displays server cart for logged-in users, local cart otherwise.

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  loadCart();
})();

async function loadCart() {
  const container = document.getElementById("cart-container");
  container.innerHTML = `<div class="spinner"></div>`;

  let items, subtotal, totalQuantity;
  try {
    if (Auth.isLoggedIn()) {
      const cart = await Api.getCart();
      items = cart.items;
      subtotal = cart.subtotal;
      totalQuantity = cart.totalQuantity;
    } else {
      items = await LocalCart.resolve();
      subtotal = items.reduce((s, i) => s + i.lineTotal, 0);
      totalQuantity = items.reduce((s, i) => s + i.quantity, 0);
    }
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><h2>Could not load your bag</h2><p>${UI.escapeHtml(e.message)}</p></div>`;
    return;
  }

  if (!items.length) {
    container.innerHTML = `
      <div class="empty-state">
        <h2>Your bag is empty</h2>
        <p>Browse our store and find something you'll love.</p>
        <a class="btn btn-primary" href="products.html" style="margin-top: 1rem;">Continue shopping</a>
      </div>`;
    return;
  }

  container.innerHTML = `
    <div class="cart-list">
      ${items.map((i) => `
        <div class="cart-item" data-product="${i.productId}">
          <img src="${i.imageUrl}" alt="${UI.escapeHtml(i.productName)}" />
          <div>
            <a class="name" href="product.html?id=${i.productId}">${UI.escapeHtml(i.productName)}</a>
            <div class="meta">${UI.formatPrice(i.unitPrice)} each</div>
            <div class="qty-control" style="margin-top: 0.5rem;">
              <button class="qty-btn" data-action="dec">−</button>
              <span data-qty>${i.quantity}</span>
              <button class="qty-btn" data-action="inc">+</button>
              <button class="btn btn-ghost" data-action="remove" style="padding: 0.3rem 0.8rem;">Remove</button>
            </div>
          </div>
          <div class="line-total" style="font-weight:600;">${UI.formatPrice(i.lineTotal)}</div>
        </div>`).join("")}
    </div>
    <div class="cart-summary">
      <div>
        <div>${totalQuantity} item${totalQuantity === 1 ? "" : "s"}</div>
        <div class="total">Subtotal: ${UI.formatPrice(subtotal)}</div>
      </div>
      <a href="checkout.html" class="btn btn-primary">Checkout</a>
    </div>`;

  container.querySelectorAll(".cart-item").forEach((el) => {
    const productId = parseInt(el.dataset.product, 10);
    el.querySelector('[data-action="inc"]').onclick = () => changeQty(productId, +1);
    el.querySelector('[data-action="dec"]').onclick = () => changeQty(productId, -1);
    el.querySelector('[data-action="remove"]').onclick = () => removeItem(productId);
  });
}

async function changeQty(productId, delta) {
  try {
    if (Auth.isLoggedIn()) {
      const cart = await Api.getCart();
      const item = cart.items.find((i) => i.productId === productId);
      if (!item) return;
      const newQty = Math.max(0, item.quantity + delta);
      await Api.updateCartItem(productId, newQty);
    } else {
      const items = LocalCart.read();
      const item = items.find((i) => i.productId === productId);
      if (!item) return;
      LocalCart.set(productId, Math.max(0, item.quantity + delta));
    }
    await UI.renderNavbar();
    loadCart();
  } catch (e) { UI.showToast(e.message, "error"); }
}

async function removeItem(productId) {
  try {
    if (Auth.isLoggedIn()) await Api.removeCartItem(productId);
    else LocalCart.remove(productId);
    await UI.renderNavbar();
    loadCart();
  } catch (e) { UI.showToast(e.message, "error"); }
}
