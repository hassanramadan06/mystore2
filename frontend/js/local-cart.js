// Local (anonymous) cart used when the user is not logged in.
// On login we attempt to merge it into the server cart.

const LOCAL_KEY = "mystore_local_cart";

const LocalCart = {
  read() { return JSON.parse(localStorage.getItem(LOCAL_KEY) || "[]"); },
  write(items) { localStorage.setItem(LOCAL_KEY, JSON.stringify(items)); },
  add(productId, quantity = 1) {
    const items = LocalCart.read();
    const existing = items.find((i) => i.productId === productId);
    if (existing) existing.quantity += quantity;
    else items.push({ productId, quantity });
    LocalCart.write(items);
  },
  set(productId, quantity) {
    const items = LocalCart.read().filter((i) => i.productId !== productId);
    if (quantity > 0) items.push({ productId, quantity });
    LocalCart.write(items);
  },
  remove(productId) {
    LocalCart.write(LocalCart.read().filter((i) => i.productId !== productId));
  },
  clear() { localStorage.removeItem(LOCAL_KEY); },
  async resolve() {
    const items = LocalCart.read();
    if (!items.length) return [];
    const products = await Promise.all(
      items.map((i) => Api.getProduct(i.productId).catch(() => null))
    );
    return items
      .map((i, idx) => products[idx] && ({
        productId: i.productId,
        productName: products[idx].name,
        imageUrl: products[idx].imageUrl,
        unitPrice: products[idx].price,
        quantity: i.quantity,
        stock: products[idx].stock,
        lineTotal: products[idx].price * i.quantity
      }))
      .filter(Boolean);
  },
  async syncToServer() {
    const items = LocalCart.read();
    for (const i of items) {
      try { await Api.addToCart(i.productId, i.quantity); } catch (e) { /* ignore individual failures */ }
    }
    LocalCart.clear();
  }
};

window.LocalCart = LocalCart;
