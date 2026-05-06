// Product detail page.

const id = parseInt(new URLSearchParams(location.search).get("id"), 10);

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  if (!id) {
    document.getElementById("product-detail").innerHTML = `<div class="empty-state"><h2>Product not found</h2></div>`;
    return;
  }
  loadProduct();
})();

async function loadProduct() {
  const container = document.getElementById("product-detail");
  container.innerHTML = `<div class="spinner"></div>`;
  try {
    const p = await Api.getProduct(id);
    document.title = `${p.name} — MyStore`;
    container.innerHTML = `
      <div class="gallery"><img src="${p.imageUrl}" alt="${UI.escapeHtml(p.name)}" /></div>
      <div class="info">
        <p class="brand">${UI.escapeHtml(p.brand || "Apple")} · ${UI.escapeHtml(p.categoryName)}</p>
        <h1>${UI.escapeHtml(p.name)}</h1>
        <p class="price">${UI.formatPrice(p.price)}</p>
        <p class="stock ${p.stock > 0 ? "" : "out"}">${p.stock > 0 ? `In stock (${p.stock} available)` : "Out of stock"}</p>
        <p class="description">${UI.escapeHtml(p.description)}</p>
        <div class="actions">
          <div class="qty-control">
            <button class="qty-btn" id="qty-minus" aria-label="Decrease">−</button>
            <span id="qty-value">1</span>
            <button class="qty-btn" id="qty-plus" aria-label="Increase">+</button>
          </div>
          <button class="btn btn-primary" id="add-cart" ${p.stock > 0 ? "" : "disabled"}>Add to Bag</button>
          <a class="btn btn-outline" href="cart.html">View Bag</a>
        </div>
      </div>`;
    let qty = 1;
    const qval = document.getElementById("qty-value");
    document.getElementById("qty-minus").onclick = () => { if (qty > 1) { qty--; qval.textContent = qty; } };
    document.getElementById("qty-plus").onclick = () => { if (qty < p.stock) { qty++; qval.textContent = qty; } };
    document.getElementById("add-cart").onclick = async () => {
      try {
        if (Auth.isLoggedIn()) {
          await Api.addToCart(p.id, qty);
        } else {
          LocalCart.add(p.id, qty);
        }
        UI.showToast(`Added ${qty} × ${p.name} to bag`);
        await UI.renderNavbar();
      } catch (e) {
        UI.showToast(e.message, "error");
      }
    };
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><h2>Product not found</h2><p>${UI.escapeHtml(e.message)}</p></div>`;
  }
}
