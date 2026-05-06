(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  bindForm();
})();

function bindForm() {
  document.getElementById("login-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errEl = document.getElementById("form-error");
    errEl.textContent = "";
    const dto = {
      email: document.getElementById("email").value.trim(),
      password: document.getElementById("password").value
    };
    try {
      const res = await Api.login(dto);
      Auth.setToken(res.token);
      Auth.setUser(res.user);
      // Merge any anonymous cart into the server cart.
      await LocalCart.syncToServer();
      const next = new URLSearchParams(location.search).get("next") || "index.html";
      location.replace(next);
    } catch (e) {
      errEl.textContent = e.message;
    }
  });
}
