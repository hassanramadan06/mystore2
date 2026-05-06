// Checkout page: collects shipping details, calls /api/orders/checkout, shows confirmation.

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();

  if (!Auth.isLoggedIn()) {
    if (LocalCart.read().length) {
      sessionStorage.setItem("mystore_post_login", "checkout.html");
    }
    location.replace("login.html?next=checkout.html");
    return;
  }
  loadSummary();
  bindForm();
})();

async function loadSummary() {
  const summary = document.getElementById("summary");
  try {
    const cart = await Api.getCart();
    if (!cart.items.length) {
      document.getElementById("checkout-content").innerHTML =
        `<div class="empty-state"><h2>Your bag is empty</h2><a class="btn btn-primary" href="products.html">Continue shopping</a></div>`;
      return;
    }
    summary.innerHTML = `
      <h3 style="margin-top:0;">Order summary</h3>
      <ul>
        ${cart.items.map((i) => `<li><span>${UI.escapeHtml(i.productName)} × ${i.quantity}</span><span>${UI.formatPrice(i.lineTotal)}</span></li>`).join("")}
      </ul>
      <div class="grand-total"><span>Total</span><span>${UI.formatPrice(cart.subtotal)}</span></div>`;
  } catch (e) {
    summary.innerHTML = `<p>${UI.escapeHtml(e.message)}</p>`;
  }
}

function bindForm() {
  document.getElementById("checkout-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errEl = document.getElementById("form-error");
    errEl.textContent = "";
    const dto = {
      shippingAddress: document.getElementById("shippingAddress").value.trim(),
      city: document.getElementById("city").value.trim(),
      postalCode: document.getElementById("postalCode").value.trim(),
      country: document.getElementById("country").value.trim()
    };
    const submitBtn = document.getElementById("place-order");
    submitBtn.disabled = true;
    submitBtn.textContent = "Placing order…";
    try {
      const result = await Api.checkout(dto);
      // Stripe stub — render confirmation. With real keys you would confirm the
      // PaymentIntent here using result.clientSecret + result.publishableKey.
      document.getElementById("checkout-content").innerHTML = `
        <div class="empty-state">
          <h2>Order placed 🎉</h2>
          <p>Thanks for your purchase! Order #${result.order.id} for <strong>${UI.formatPrice(result.order.total)}</strong>.</p>
          <p style="color: var(--text-muted); font-size: 0.85rem;">PaymentIntent: ${UI.escapeHtml(result.order.paymentIntentId || "")}</p>
          <a class="btn btn-primary" href="orders.html" style="margin-top: 1rem;">View my orders</a>
        </div>`;
      await UI.renderNavbar();
    } catch (e) {
      errEl.textContent = e.message;
      submitBtn.disabled = false;
      submitBtn.textContent = "Place order";
    }
  });
}
