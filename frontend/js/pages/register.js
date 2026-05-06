(async function init() {
  await UI.renderNavbar();
  UI.renderFooter();
  bindForm();
})();

function bindForm() {
  document.getElementById("register-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const errEl = document.getElementById("form-error");
    errEl.textContent = "";
    const dto = {
      fullName: document.getElementById("fullName").value.trim(),
      email: document.getElementById("email").value.trim(),
      password: document.getElementById("password").value
    };
    try {
      const res = await Api.register(dto);
      Auth.setToken(res.token);
      Auth.setUser(res.user);
      await LocalCart.syncToServer();
      location.replace("index.html");
    } catch (e) {
      errEl.textContent = e.message;
    }
  });
}
