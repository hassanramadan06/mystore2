// Shared UI helpers: navbar, footer, toast, formatting.

const formatPrice = (n) =>
  new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 }).format(n);

function showToast(message, type = "info") {
  let toast = document.querySelector(".toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.toggle("error", type === "error");
  toast.classList.add("show");
  clearTimeout(toast._t);
  toast._t = setTimeout(() => toast.classList.remove("show"), 2400);
}

async function renderNavbar() {
  const slot = document.getElementById("navbar-slot");
  if (!slot) return;
  const user = Auth.getUser();
  const cartCount = await getCartCount();
  slot.innerHTML = `
    <nav class="navbar">
      <div class="navbar-inner">
        <a class="brand" href="index.html">MyStore</a>
        <ul class="nav-links" id="nav-links">
          <li><a href="products.html">Store</a></li>
          <li><a href="products.html?category=iphone">iPhone</a></li>
          <li><a href="products.html?category=macbook">Mac</a></li>
          <li><a href="products.html?category=ipad">iPad</a></li>
          <li><a href="products.html?category=accessories">Accessories</a></li>
        </ul>
        <div class="nav-actions">
          <a href="cart.html" aria-label="Cart">Cart<span class="cart-badge" id="cart-badge">${cartCount}</span></a>
          ${user
            ? `${user.role === "Admin" ? `<a href="admin.html">Admin</a>` : ""}<a href="orders.html">Orders</a><a href="#" id="logout-link">Logout</a>`
            : `<a href="login.html">Sign in</a>`}
          <button class="menu-toggle" id="menu-toggle" aria-label="Menu">≡</button>
        </div>
      </div>
    </nav>`;
  document.getElementById("menu-toggle")?.addEventListener("click", () => {
    document.getElementById("nav-links").classList.toggle("open");
  });
  document.getElementById("logout-link")?.addEventListener("click", (e) => {
    e.preventDefault();
    Auth.clear();
    showToast("Logged out");
    setTimeout(() => location.href = "index.html", 400);
  });
}

function renderFooter() {
  const slot = document.getElementById("footer-slot");
  if (!slot) return;
  slot.innerHTML = `
    <footer class="footer">
      <div class="footer-inner">
        <div>
          <h4>Shop</h4>
          <ul>
            <li><a href="products.html?category=iphone">iPhone</a></li>
            <li><a href="products.html?category=macbook">Mac</a></li>
            <li><a href="products.html?category=ipad">iPad</a></li>
            <li><a href="products.html?category=accessories">Accessories</a></li>
          </ul>
        </div>
        <div>
          <h4>Account</h4>
          <ul>
            <li><a href="login.html">Sign in</a></li>
            <li><a href="register.html">Create account</a></li>
            <li><a href="orders.html">Orders</a></li>
          </ul>
        </div>
        <div>
          <h4>About</h4>
          <ul>
            <li><a href="#">Our Story</a></li>
            <li><a href="#">Careers</a></li>
            <li><a href="#">Press</a></li>
          </ul>
        </div>
        <div>
          <h4>Help</h4>
          <ul>
            <li><a href="#">Shipping</a></li>
            <li><a href="#">Returns</a></li>
            <li><a href="#">Contact</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        © ${new Date().getFullYear()} MyStore. Apple-inspired demo storefront.
      </div>
    </footer>`;
}

async function getCartCount() {
  if (!Auth.isLoggedIn()) {
    const local = JSON.parse(localStorage.getItem("mystore_local_cart") || "[]");
    return local.reduce((s, i) => s + i.quantity, 0);
  }
  try {
    const cart = await Api.getCart();
    return cart.totalQuantity || 0;
  } catch { return 0; }
}

function productCardHtml(p) {
  return `
    <a class="product-card" href="product.html?id=${p.id}" aria-label="${p.name}">
      <div class="image-wrap">
        <img src="${p.imageUrl}" alt="${p.name}" loading="lazy" />
      </div>
      <div class="body">
        <span class="category">${p.categoryName || ""}</span>
        <h3 class="name">${p.name}</h3>
        <span class="price">${formatPrice(p.price)}</span>
      </div>
    </a>`;
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  })[c]);
}

window.UI = { renderNavbar, renderFooter, showToast, formatPrice, productCardHtml, escapeHtml, getCartCount };
