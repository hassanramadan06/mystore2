// Centralised API client for the MyStore frontend.
// Reads the API base URL from `window.MYSTORE_CONFIG.apiBase` (defined in config.js).

const API_BASE = (window.MYSTORE_CONFIG && window.MYSTORE_CONFIG.apiBase) || "http://localhost:5080";
const TOKEN_KEY = "mystore_token";
const USER_KEY = "mystore_user";

const Auth = {
  getToken() { return localStorage.getItem(TOKEN_KEY); },
  setToken(token) { localStorage.setItem(TOKEN_KEY, token); },
  clear() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },
  getUser() {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  },
  setUser(user) { localStorage.setItem(USER_KEY, JSON.stringify(user)); },
  isLoggedIn() { return !!localStorage.getItem(TOKEN_KEY); }
};

async function request(path, { method = "GET", body, auth = false, query } = {}) {
  const url = new URL(API_BASE + path);
  if (query) {
    Object.entries(query).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== "") url.searchParams.append(k, v);
    });
  }
  const headers = {};
  let finalBody = body;

  if (!(body instanceof FormData)) {
  headers["Content-Type"] = "application/json";
  finalBody = body ? JSON.stringify(body) : undefined;
}
  if (auth) {
    const token = Auth.getToken();
    if (token) headers["Authorization"] = `Bearer ${token}`;
  }
  const res = await fetch(url.toString(), {
    method,
    headers,
    body: finalBody
  });

  if (res.status === 401 && auth) {
    Auth.clear();
  }

  let data = null;
  const text = await res.text();
  if (text) {
    try { data = JSON.parse(text); } catch { data = text; }
  }

  if (!res.ok) {
    const msg = (data && data.error) || (typeof data === "string" ? data : `HTTP ${res.status}`);
    throw new Error(msg);
  }
  return data;
}

const Api = {
  // Auth
  register: (dto) => request("/api/auth/register", { method: "POST", body: dto }),
  login: (dto) => request("/api/auth/login", { method: "POST", body: dto }),
  me: () => request("/api/users/me", { auth: true }),

  // Products
  searchProducts: (query) => request("/api/products", { query }),
  getProduct: (id) => request(`/api/products/${id}`),
  featuredProducts: (take = 8) => request("/api/products/featured", { query: { take } }),

  // Categories
  listCategories: () => request("/api/categories"),

  // Cart
  getCart: () => request("/api/cart", { auth: true }),
  addToCart: (productId, quantity = 1) =>
    request("/api/cart/items", { method: "POST", auth: true, body: { productId, quantity } }),
  updateCartItem: (productId, quantity) =>
    request(`/api/cart/items/${productId}`, { method: "PUT", auth: true, body: { quantity } }),
  removeCartItem: (productId) =>
    request(`/api/cart/items/${productId}`, { method: "DELETE", auth: true }),
  clearCart: () => request("/api/cart", { method: "DELETE", auth: true }),

  // Orders
  checkout: (dto) => request("/api/orders/checkout", { method: "POST", auth: true, body: dto }),
  myOrders: () => request("/api/orders", { auth: true }),
  getOrder: (id) => request(`/api/orders/${id}`, { auth: true }),

  // Admin — products
  adminCreateProduct: (dto) => request("/api/products", { method: "POST", auth: true, body: dto }),
  adminUpdateProduct: (id, dto) => request(`/api/products/${id}`, { method: "PUT", auth: true, body: dto }),
  adminDeleteProduct: (id) => request(`/api/products/${id}`, { method: "DELETE", auth: true }),

  // Admin — categories
  adminCreateCategory: (dto) => request("/api/categories", { method: "POST", auth: true, body: dto }),
  adminUpdateCategory: (id, dto) => request(`/api/categories/${id}`, { method: "PUT", auth: true, body: dto }),
  adminDeleteCategory: (id) => request(`/api/categories/${id}`, { method: "DELETE", auth: true }),

  // Admin — orders
  adminListOrders: () => request("/api/orders/all", { auth: true }),
  adminUpdateOrderStatus: (id, status) =>
    request(`/api/orders/${id}/status`, { method: "PUT", auth: true, body: { status } })
};

window.Api = Api;
window.Auth = Auth;
