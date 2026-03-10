<!DOCTYPE html>
<html>
<head>
    <title>Test Lead Form</title>
</head>
<body>

<h2>Test Lead Form</h2>

<form id="leadForm">
    <input type="text" id="name" placeholder="Name" required><br><br>
    <input type="text" id="phone" placeholder="Phone" required><br><br>
    <input type="email" id="email" placeholder="Email"><br><br>
    <input type="text" id="course" placeholder="Course"><br><br>

    <button type="submit">Submit</button>
</form>

<script>
document.getElementById("leadForm").addEventListener("submit", function(e) {

    e.preventDefault();

    fetch("http://127.0.0.1:8000/crm-store-lead", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-API-KEY": "college_secure_2025_key"
        },
        body: JSON.stringify({
            name: document.getElementById("name").value,
            phone: document.getElementById("phone").value,
            email: document.getElementById("email").value,
            course: document.getElementById("course").value,
            source: "test_localhost"
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(JSON.stringify(data));
        console.log(data);
    })
    .catch(error => {
        console.error("Error:", error);
    });

});
</script>

</body>
</html>
