// Home page: hero + featured products + category tiles.

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  loadFeatured();
  loadCategories();
})();

async function loadFeatured() {
  const container = document.getElementById("featured-grid");
  container.innerHTML = `<div class="spinner"></div>`;
  try {
    const products = await Api.featuredProducts(8);
    if (!products.length) {
      container.innerHTML = `<div class="empty-state">No featured products yet.</div>`;
      return;
    }
    container.innerHTML = products.map(UI.productCardHtml).join("");
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><h2>Cannot reach the API</h2><p>${UI.escapeHtml(e.message)}</p></div>`;
  }
}

async function loadCategories() {
  const container = document.getElementById("category-tiles");
  if (!container) return;
  try {
    const cats = await Api.listCategories();
    container.innerHTML = cats.map((c) => `
      <a class="product-card" href="products.html?category=${c.slug}">
        <div class="body" style="padding: 2.5rem; text-align: center;">
          <h3 class="name" style="font-size: 1.5rem;">${UI.escapeHtml(c.name)}</h3>
          <p class="category">${c.productCount} products</p>
          <span class="price" style="margin-top: 1rem; color: var(--accent);">Shop ${UI.escapeHtml(c.name)} →</span>
        </div>
      </a>`).join("");
  } catch { /* silent */ }
}
