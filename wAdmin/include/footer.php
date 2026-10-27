<div class="panel-body">
<form id="quickNoteForm" method="post" class="white-popup-block mfp-hide form-horizontal">
<div class="row">
<div class="col-sm-12">
<h3 align="center">Add New Quick Note</h3><hr />
</div>
</div>
<div class="form-group mt-lg">
<label class="col-sm-3 control-label">Title</label>
<div class="col-sm-9">
<input type="text" name="title" class="form-control" placeholder="Type your Title..." required/>
</div>
</div>
<div class="form-group">
<label class="col-sm-3 control-label">Description</label>
<div class="col-sm-9">
<textarea rows="5" class="form-control" name="description" placeholder="Type your description..." required></textarea>
</div>
</div>

<div class="row mb-lg">
<div class="col-sm-9 col-sm-offset-3">
<button class="btn btn-primary" name="action" value="quickNote">Submit</button>
</div>
</div>

</form>
</div>
</section>

<!-- Refresh button -->



<script>
    function showFlash(message) {
        var flash = $("#bottomFlash");
        if (!flash.length) {
            flash = $('<div id="bottomFlash"></div>').css({position: "fixed", left: "50%", bottom: "20px", transform: "translateX(-50%)", color: "#fff", padding: "10px 18px", borderRadius: "4px", zIndex: 99999, boxShadow: "0 3px 12px rgba(0,0,0,.25)", display: "none"}).appendTo("body");
        }
        flash.css("background", message.toLowerCase().indexOf("error") >= 0 ? "#ffffff" : "#2e7d32");
        flash.stop(true, true).text(message).fadeIn(150).delay(2500).fadeOut(400);
    }

    // Your JavaScript code
    $(document).ready(function() {
        var refreshButton = $("#refreshButton");
        if (!refreshButton.length) {
            return;
        }

        refreshButton.click(function(event) {
            event.preventDefault();

            // Sanitize values before including them in the URL
            var sanitizedBillNo = encodeURIComponent("<?php echo isset($billNo) ? $billNo : ''; ?>");
            var sanitizedId = encodeURIComponent("<?php echo isset($id) ? $id : ''; ?>");

            // Make an AJAX request to check file existence and update the ETA
            $.ajax({
                type: "GET",
                url: "ajax_eta_tracking.php?billNo=" + sanitizedBillNo + "&id=" + sanitizedId,
	                success: function(response) {
	                    console.log(response); // Log the response from the server
	
	                    // Display the response in the container
	                    $("#responseContainer").html(response);
	                    showFlash(response);

	                    if ($.trim(response) === "Updated Successfully!") {
	                        setTimeout(function() {
	                            window.location.reload();
	                        }, 1000);
	                    }
	                },
                error: function(xhr, status, error) {
                    console.error("Error:", error);

                    // Display an error message in the container
                    $("#responseContainer").html("Error: " + error);
                    showFlash("Error: " + error);
                }
            });
        });
    });
</script>



<script>
//track button footer
function showLoader() {
            document.getElementById('loading').style.display = 'block';
        }

        function hideLoader() {
            document.getElementById('loading').style.display = 'none';
        }

</script>




<script>
var trackButton = document.getElementById('trackButton');
if (trackButton) {
	trackButton.addEventListener('click', function(event) {
	    // Prevent the default behavior of the button
	    event.preventDefault();
	    showLoader();
	    // Get the value of $billNo
	    var billNo = '<?php echo isset($billNo) ? $billNo : ''; ?>';

	    // Open a new window with some styling
	    var newWindow = window.open('', '_blank', 'width=950,height=700,resizable=yes,scrollbars=yes');

	    // Make an AJAX request with $billNo as a parameter
	    var xhr = new XMLHttpRequest();
	    xhr.open('GET', 'ajax_container_tracking.php?billNo=' + billNo, true);

	    xhr.onreadystatechange = function() {
	        if (xhr.readyState == 4 && xhr.status == 200) {
	            hideLoader();
	            // On successful response, display the result in the new window
	            var popupContent = newWindow.document.createElement('div');
	            popupContent.id = 'popupContent';
	            popupContent.innerHTML = xhr.responseText;
	            newWindow.document.body.appendChild(popupContent);
	        }
	    };

	    xhr.send();
	});
}
</script>



    <script>
       // Function to add data via AJAX
var addButton = document.getElementById("addButton");
if (addButton) {
addButton.addEventListener("click", function () {
    var dataToGet = <?php echo json_encode(isset($_GET['id']) ? $_GET['id'] : ''); ?>;
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
        xhr.send("action=add&data=" + dataToAdd + "&id=" + dataToGet);
    }
});
}

// Function to remove data via AJAX
var removeButton = document.getElementById("removeButton");
if (removeButton) {
removeButton.addEventListener("click", function () {
    var dataToGet = <?php echo json_encode(isset($_GET['id']) ? $_GET['id'] : ''); ?>;
    const dataToRemove = document.getElementById("addData").value; // Use the same input for removal
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
        xhr.send("action=remove&data=" + dataToRemove + "&id=" + dataToGet);
    }
});
}


        // Function to retrieve and display data via AJAX
        function retrieveData() {
            var resultContainer = document.getElementById("result");
            if (!resultContainer) {
                return;
            }
            var dataToGet = <?php echo json_encode(isset($_GET['id']) ? $_GET['id'] : ''); ?>;
            const xhr = new XMLHttpRequest();
            xhr.open("GET", "ajax_handler.php?id=" + dataToGet, true); // Use GET request
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText); // Parse JSON response
                    resultContainer = document.getElementById("result");
                     data.reverse();
                    // Clear existing data
                    resultContainer.innerHTML = "";

                   // Loop through the data and create styled div elements with serialized numbers
            data.forEach((item, index) => {
                const dataDiv = document.createElement("div");
                dataDiv.className = "data-item";
                dataDiv.textContent = `${item}`; // Serialize the number
                resultContainer.appendChild(dataDiv);
            });
        }
    };
    xhr.send();
        }



        // Call the retrieveData function when the page loads
        retrieveData();
    </script>

<script src="<?=$siteUrl;?>assets/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-cookie/jquery.cookie.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/bootstrap/js/bootstrap.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/nanoscroller/nanoscroller.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/magnific-popup/magnific-popup.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-placeholder/jquery.placeholder.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-ui/js/jquery-ui-1.10.4.custom.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-ui-touch-punch/jquery.ui.touch-punch.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/jquery-appear/jquery.appear.js"></script>
<script src="<?=$siteUrl;?>assets/vendor/bootstrap-multiselect/bootstrap-multiselect.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.custom.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/theme.init.js"></script>
<script src="<?=$siteUrl;?>assets/javascripts/ui-elements/examples.lightbox.js"></script>
</body>
</html>
