// Products listing page: filters, search, sort, grid.

const params = new URLSearchParams(location.search);
const state = {
  search: params.get("search") || "",
  category: params.get("category") || "",
  minPrice: params.get("minPrice") || "",
  maxPrice: params.get("maxPrice") || "",
  sort: params.get("sort") || ""
};

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  await loadCategoryOptions();
  bindForm();
  loadProducts();
})();

async function loadCategoryOptions() {
  try {
    const cats = await Api.listCategories();
    const sel = document.getElementById("f-category");
    sel.innerHTML = `<option value="">All categories</option>` +
      cats.map((c) => `<option value="${c.slug}" ${c.slug === state.category ? "selected" : ""}>${UI.escapeHtml(c.name)}</option>`).join("");
  } catch { /* keep default option */ }
}

function bindForm() {
  document.getElementById("f-search").value = state.search;
  document.getElementById("f-min").value = state.minPrice;
  document.getElementById("f-max").value = state.maxPrice;
  document.getElementById("f-sort").value = state.sort;

  document.getElementById("filters-form").addEventListener("submit", (e) => {
    e.preventDefault();
    state.search = document.getElementById("f-search").value.trim();
    state.category = document.getElementById("f-category").value;
    state.minPrice = document.getElementById("f-min").value;
    state.maxPrice = document.getElementById("f-max").value;
    state.sort = document.getElementById("f-sort").value;
    const u = new URL(location.href);
    Object.entries(state).forEach(([k, v]) => v ? u.searchParams.set(k, v) : u.searchParams.delete(k));
    history.replaceState({}, "", u.toString());
    loadProducts();
  });
}

async function loadProducts() {
  const container = document.getElementById("products-grid");
  const heading = document.getElementById("products-heading");
  container.innerHTML = `<div class="spinner"></div>`;
  try {
    const { items, total } = await Api.searchProducts({
      search: state.search,
      category: state.category,
      minPrice: state.minPrice || undefined,
      maxPrice: state.maxPrice || undefined,
      sort: state.sort,
      page: 1,
      pageSize: 60
    });
    heading.textContent = state.category
      ? `${capitalize(state.category)} (${total})`
      : `All Products (${total})`;
    if (!items.length) {
      container.innerHTML = `<div class="empty-state"><h2>No products match your filters</h2></div>`;
      return;
    }
    container.innerHTML = items.map(p => UI.productCardHtml(p)).join("");
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><h2>Could not load products</h2><p>${UI.escapeHtml(e.message)}</p></div>`;
  }
}

function capitalize(s) { return s ? s[0].toUpperCase() + s.slice(1) : ""; }
