// Edit this file to point the frontend at your backend deployment.
//
// • Production on cPanel (frontend + PHP API on the same domain):
//     apiBase: ""          ← leave empty, requests go to /api/...
//
// • Local development with the PHP backend (php -S 127.0.0.1:8081):
//     apiBase: "http://127.0.0.1:8081"
//
// • Local development with the legacy .NET backend (dotnet run):
//     apiBase: "http://localhost:5080"
window.MYSTORE_CONFIG = {
  apiBase: "http://localhost/mystore/backend-php"
};
