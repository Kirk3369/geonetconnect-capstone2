const togglePassword = document.getElementById("togglePassword");
const password = document.getElementById("password");

if (togglePassword && password) {
    togglePassword.addEventListener("click", function () {
        const type = password.getAttribute("type");
        if (type === "password") {
            password.setAttribute("type", "text");
            this.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
        } else {
            password.setAttribute("type", "password");
            this.innerHTML = '<i class="fa-regular fa-eye"></i>';
        }
    });
}
