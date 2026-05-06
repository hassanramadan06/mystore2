// Admin page — single-page CRUD for products, orders, categories.
// Auth: requires a logged-in user with role = "Admin"; otherwise redirects.

const STATUS_LABELS = ["Pending", "Paid", "Shipped", "Delivered", "Cancelled"];
const state = {
  products: [],
  categories: [],
  orders: []
};

(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();

  const user = Auth.getUser();
  if (!user) {
    location.replace("login.html?next=admin.html");
    return;
  }
  if (user.role !== "Admin") {
    document.querySelector("main").innerHTML = `
      <div class="empty-state">
        <h2>Admins only</h2>
        <p>You're signed in as <strong>${UI.escapeHtml(user.email)}</strong> (${UI.escapeHtml(user.role)}).</p>
        <p>Sign in with an admin account to manage products and orders.</p>
        <a class="btn btn-primary" href="login.html?next=admin.html">Sign in as admin</a>
      </div>`;
    return;
  }

  bindTabs();
  bindModals();
  bindForms();
  await Promise.all([loadCategories(), loadProducts(), loadOrders()]);
})();

/* ===================== Tabs ===================== */
function bindTabs() {
  document.querySelectorAll(".admin-tab").forEach((btn) => {
    btn.addEventListener("click", () => {
      const tab = btn.dataset.tab;
      document.querySelectorAll(".admin-tab").forEach((b) => b.classList.toggle("active", b === btn));
      document.querySelectorAll(".admin-tab-panel").forEach((p) => {
        p.classList.toggle("active", p.id === `tab-${tab}`);
      });
    });
  });
}

/* ===================== Products ===================== */
async function loadProducts() {
  const wrap = document.getElementById("products-table-wrap");
  wrap.innerHTML = `<div class="spinner"></div>`;
  try {
    const res = await Api.searchProducts({ pageSize: 100 });
    state.products = res.items || [];
    renderProductsTable();
  } catch (e) {
    wrap.innerHTML = errorBox("Could not load products", e.message);
  }
}

function renderProductsTable() {
  const wrap = document.getElementById("products-table-wrap");
  if (!state.products.length) {
    wrap.innerHTML = `<div class="empty-state"><p>No products yet. Click "Add product" to create one.</p></div>`;
    return;
  }
  wrap.innerHTML = `
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Featured</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${state.products.map(productRow).join("")}
        </tbody>
      </table>
    </div>`;
  wrap.querySelectorAll("[data-edit-product]").forEach((b) =>
    b.addEventListener("click", () => openProductModal(Number(b.dataset.editProduct)))
  );
  wrap.querySelectorAll("[data-delete-product]").forEach((b) =>
    b.addEventListener("click", () => deleteProduct(Number(b.dataset.deleteProduct)))
  );
}

function productRow(p) {
  return `
    <tr>
      <td>#${p.id}</td>
      <td><img class="thumb" src="${UI.escapeHtml(p.imageUrl || "")}" alt="" loading="lazy" /></td>
      <td>${UI.escapeHtml(p.name)}</td>
      <td>${UI.escapeHtml(p.categoryName || "")}</td>
      <td>${UI.formatPrice(p.price)}</td>
      <td>${p.stock}</td>
      <td>${p.isFeatured ? "★" : ""}</td>
      <td class="actions-cell">
        <button class="btn btn-link" data-edit-product="${p.id}">Edit</button>
        <button class="btn btn-link danger" data-delete-product="${p.id}">Delete</button>
      </td>
    </tr>`;
}

function openProductModal(id) {
  const form = document.getElementById("product-form");
  form.reset();
  document.getElementById("product-form-error").textContent = "";
  const sel = document.getElementById("product-category");
  sel.innerHTML = state.categories
    .map((c) => `<option value="${c.id}">${UI.escapeHtml(c.name)}</option>`)
    .join("");

  const title = document.getElementById("product-modal-title");
  if (id) {
    const p = state.products.find((x) => x.id === id);
    if (!p) return;
    title.textContent = `Edit product #${p.id}`;
    form.id.value = p.id;
    document.querySelector('input[name="name"]').value = p.name || "";
    form.description.value = p.description || "";
    form.price.value = p.price || 0;
    form.stock.value = p.stock || 0;
    document.getElementById("product-category").value = p.categoryId || "";
    form.brand.value = p.brand || "";
    form.isFeatured.checked = !!p.isFeatured;
  } else {
    title.textContent = "Add product";
    form.id.value = "";
  }
  showModal("product-modal");
}

async function submitProduct(form) {
  const error = document.getElementById("product-form-error");
  error.textContent = "";

  const submitBtn = document.getElementById("product-submit");
  submitBtn.disabled = true;
  submitBtn.textContent = "Saving…";

  try {
    const formData = new FormData();

    formData.append("name", form.name.value.trim());
    formData.append("description", form.description.value.trim());
    formData.append("price", form.price.value);
    formData.append("stock", form.stock.value);
    formData.append("categoryId", document.getElementById("product-category").value);
    formData.append("brand", form.brand.value.trim());
    formData.append("isFeatured", form.isFeatured.checked ? 1 : 0);
    

    console.log([...formData.entries()]);

    const fileInput = document.querySelector('input[name="image"]');
    if (fileInput?.files?.length) {
      formData.append("image", fileInput.files[0]);
    }

    const id = Number(form.querySelector('input[name="id"]').value);

    console.log("NAME:", form.name?.value);
    console.log("CATEGORY:", document.getElementById("product-category").value);

    let res;
    

    if (id) {
      res = await fetch(`${API_BASE}/api/products/${id}`, {
        method: "PUT",
        headers: {
          Authorization: `Bearer ${Auth.getToken()}`
        },
        body: formData
      });
    } else {
      res = await fetch(`${API_BASE}/api/products`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${Auth.getToken()}`
        },
        body: formData
      });
    }

    if (!res.ok) {
      throw new Error(await res.text());
    }

    const data = await res.json();

    console.log(data);

    UI.showToast(id ? "Product updated" : "Product created");

    hideModal("product-modal");
    await loadProducts();

  } catch (e) {
    error.textContent = e.message;
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = "Save";
  }
}

async function deleteProduct(id) {
  const p = state.products.find((x) => x.id === id);
  if (!p) return;
  if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return;
  try {
    await Api.adminDeleteProduct(id);
    UI.showToast("Product deleted");
    await loadProducts();
  } catch (e) {
    UI.showToast(e.message, "error");
  }
}

/* ===================== Orders ===================== */
async function loadOrders() {
  const wrap = document.getElementById("orders-table-wrap");
  wrap.innerHTML = `<div class="spinner"></div>`;
  try {
    state.orders = await Api.adminListOrders();
    renderOrdersTable();
  } catch (e) {
    wrap.innerHTML = errorBox("Could not load orders", e.message);
  }
}

function renderOrdersTable() {
  const wrap = document.getElementById("orders-table-wrap");
  if (!state.orders.length) {
    wrap.innerHTML = `<div class="empty-state"><p>No orders yet.</p></div>`;
    return;
  }
  wrap.innerHTML = `
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Placed</th>
            <th>User</th>
            <th>Items</th>
            <th>Total</th>
            <th>Ships to</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          ${state.orders.map(orderRow).join("")}
        </tbody>
      </table>
    </div>`;
  wrap.querySelectorAll("select[data-order-status]").forEach((sel) =>
    sel.addEventListener("change", () => updateOrderStatus(Number(sel.dataset.orderStatus), Number(sel.value), sel))
  );
}

function orderRow(o) {
  const itemsLabel = `${o.items.length} item${o.items.length === 1 ? "" : "s"}`;
  const statusOptions = STATUS_LABELS
    .map((label, i) => `<option value="${i}" ${o.status === i ? "selected" : ""}>${label}</option>`)
    .join("");
  return `
    <tr>
      <td>#${o.id}</td>
      <td>${new Date(o.createdAt).toLocaleString()}</td>
      <td>#${o.userId}</td>
      <td>${itemsLabel}</td>
      <td>${UI.formatPrice(o.total)}</td>
      <td>${UI.escapeHtml(o.shippingAddress || "")}, ${UI.escapeHtml(o.city || "")}</td>
      <td>
        <select class="status-select" data-order-status="${o.id}">
          ${statusOptions}
        </select>
      </td>
    </tr>`;
}

async function updateOrderStatus(orderId, status, sel) {
  const original = state.orders.find((x) => x.id === orderId)?.status;
  sel.disabled = true;
  try {
    const updated = await Api.adminUpdateOrderStatus(orderId, status);
    state.orders = state.orders.map((o) => (o.id === orderId ? updated : o));
    UI.showToast(`Order #${orderId} → ${STATUS_LABELS[status]}`);
  } catch (e) {
    UI.showToast(e.message, "error");
    sel.value = original;
  } finally {
    sel.disabled = false;
  }
}

/* ===================== Categories ===================== */
async function loadCategories() {
  const wrap = document.getElementById("categories-table-wrap");
  wrap.innerHTML = `<div class="spinner"></div>`;
  try {
    state.categories = await Api.listCategories();
    renderCategoriesTable();
  } catch (e) {
    wrap.innerHTML = errorBox("Could not load categories", e.message);
  }
}

function renderCategoriesTable() {
  const wrap = document.getElementById("categories-table-wrap");
  if (!state.categories.length) {
    wrap.innerHTML = `<div class="empty-state"><p>No categories yet.</p></div>`;
    return;
  }
  wrap.innerHTML = `
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Description</th>
            <th>Products</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          ${state.categories.map(categoryRow).join("")}
        </tbody>
      </table>
    </div>`;
  wrap.querySelectorAll("[data-edit-category]").forEach((b) =>
    b.addEventListener("click", () => openCategoryModal(Number(b.dataset.editCategory)))
  );
  wrap.querySelectorAll("[data-delete-category]").forEach((b) =>
    b.addEventListener("click", () => deleteCategory(Number(b.dataset.deleteCategory)))
  );
}

function categoryRow(c) {
  return `
    <tr>
      <td>#${c.id}</td>
      <td>${UI.escapeHtml(c.name)}</td>
      <td><code>${UI.escapeHtml(c.slug)}</code></td>
      <td>${UI.escapeHtml(c.description || "")}</td>
      <td>${c.productCount}</td>
      <td class="actions-cell">
        <button class="btn btn-link" data-edit-category="${c.id}">Edit</button>
        <button class="btn btn-link danger" data-delete-category="${c.id}">Delete</button>
      </td>
    </tr>`;
}

function openCategoryModal(id) {
  const form = document.getElementById("category-form");
  form.reset();
  document.getElementById("category-form-error").textContent = "";
  const title = document.getElementById("category-modal-title");
  if (id) {
    const c = state.categories.find((x) => x.id === id);
    if (!c) return;
    title.textContent = `Edit category #${c.id}`;
    form.id.value = c.id;
    form.name.value = c.name || "";
    form.slug.value = c.slug || "";
    form.description.value = c.description || "";
  } else {
    title.textContent = "Add category";
    form.id.value = "";
  }
  showModal("category-modal");
}

async function submitCategory(form) {
  const error = document.getElementById("category-form-error");
  error.textContent = "";
  const submitBtn = document.getElementById("category-submit");
  submitBtn.disabled = true;
  submitBtn.textContent = "Saving…";
  try {
    const dto = {
      name: form.name.value.trim(),
      slug: form.slug.value.trim(),
      description: form.description.value.trim() || null
    };
    const id = form.id.value;
    
    if (id) {
      
      await Api.adminUpdateCategory(Number(id), dto);
      UI.showToast("Category updated");
    } else {
      await Api.adminCreateCategory(dto);
      UI.showToast("Category created");
    }
    hideModal("category-modal");
    await Promise.all([loadCategories(), loadProducts()]);
  } catch (e) {
    error.textContent = e.message;
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = "Save";
  }
}

async function deleteCategory(id) {
  const c = state.categories.find((x) => x.id === id);
  if (!c) return;
  if (!confirm(`Delete "${c.name}"? This cannot be undone.`)) return;
  try {
    await Api.adminDeleteCategory(id);
    UI.showToast("Category deleted");
    await loadCategories();
  } catch (e) {
    UI.showToast(e.message, "error");
  }
}

/* ===================== Modals ===================== */
function bindModals() {
  document.getElementById("btn-add-product").addEventListener("click", () => openProductModal(0));
  document.getElementById("btn-add-category").addEventListener("click", () => openCategoryModal(0));
  document.querySelectorAll("[data-close-modal]").forEach((b) =>
    b.addEventListener("click", () => {
      document.querySelectorAll(".modal-backdrop").forEach((m) => (m.hidden = true));
    })
  );
  document.querySelectorAll(".modal-backdrop").forEach((m) =>
    m.addEventListener("click", (e) => { if (e.target === m) m.hidden = true; })
  );
}

function bindForms() {
  document.getElementById("product-form").addEventListener("submit", (e) => {
    e.preventDefault();
    submitProduct(e.target);
  });
  document.getElementById("category-form").addEventListener("submit", (e) => {
    e.preventDefault();
    submitCategory(e.target);
  });
}

function showModal(id) { document.getElementById(id).hidden = false; }
function hideModal(id) { document.getElementById(id).hidden = true; }

function errorBox(title, msg) {
  return `<div class="empty-state"><h2>${UI.escapeHtml(title)}</h2><p>${UI.escapeHtml(msg)}</p></div>`;
}
