(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  if (!Auth.isLoggedIn()) {
    location.replace("login.html?next=orders.html");
    return;
  }
  loadOrders();
})();

async function loadOrders() {
  const container = document.getElementById("orders-container");
  container.innerHTML = `<div class="spinner"></div>`;
  try {
    const orders = await Api.myOrders();
    if (!orders.length) {
      container.innerHTML = `<div class="empty-state"><h2>No orders yet</h2><a class="btn btn-primary" href="products.html">Start shopping</a></div>`;
      return;
    }
    container.innerHTML = orders.map((o) => `
      <div class="cart-item" style="grid-template-columns: 1fr auto;">
        <div>
          <div class="name">Order #${o.id}</div>
          <div class="meta">Placed ${new Date(o.createdAt).toLocaleString()} — Status: <strong>${statusLabel(o.status)}</strong></div>
          <div class="meta">${o.items.length} item${o.items.length === 1 ? "" : "s"} · Ships to ${UI.escapeHtml(o.shippingAddress)}, ${UI.escapeHtml(o.city)}</div>
        </div>
        <div style="text-align:right;">
          <div class="line-total" style="font-weight:600; font-size:1.1rem;">${UI.formatPrice(o.total)}</div>
        </div>
      </div>`).join("");
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><h2>Could not load orders</h2><p>${UI.escapeHtml(e.message)}</p></div>`;
  }
}

function statusLabel(s) {
  return ["Pending", "Paid", "Shipped", "Delivered", "Cancelled"][s] || `#${s}`;
}
