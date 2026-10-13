<?php require_once "include/sessionAdmin.php"; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add/Remove JSON Data</title>
</head>
<body>
    <h2>Add Data</h2>
    <input type="text" id="addData" placeholder="Enter data to add">
    <form id="addForm">
        <button type="button" id="addButton">Add Data</button>
    </form>

    <h2>Remove Data</h2>
    <input type="text" id="removeData" placeholder="Enter data to remove">
    <form id="removeForm">
        <button type="button" id="removeButton">Remove Data</button>
    </form>

    <div id="result"></div>

    <script>
        // Function to add data via AJAX
        document.getElementById("addButton").addEventListener("click", function () {
            const dataToAdd = document.getElementById("addData").value;
            if (dataToAdd) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "ajax_handler.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById("result").innerHTML = xhr.responseText;
                        retrieveData();
                    }
                };
                xhr.send("action=add&data=" + dataToAdd);
            }
        });

        // Function to remove data via AJAX
        document.getElementById("removeButton").addEventListener("click", function () {
            const dataToRemove = document.getElementById("removeData").value;
            if (dataToRemove) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "ajax_handler.php", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById("result").innerHTML = xhr.responseText;
                        retrieveData();
                    }
                };
                xhr.send("action=remove&data=" + dataToRemove);
            }
        });
        
// Function to retrieve and display data via AJAX
        function retrieveData() {
            var dataToGet = <?php echo json_encode($dataToGet); ?>;
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "ajax_handler.phpid=" + dataToGet, true); // Use GET request
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText); // Parse JSON response
                    document.getElementById("result").innerHTML = data.join("<br>"); // Display data
                }
            };
            xhr.send();
        }

        // Call the retrieveData function when the page loads
        retrieveData();
 

 
        </script>


  
</body>
</html>
