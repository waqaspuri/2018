<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if(isset($_GET['act']) && $_GET['act']=="prod"){
    $productID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $sql = mysqli_query($conn,"SELECT prodQty,prodOrigin,prodPackaging FROM products WHERE id='".$productID."'");
    $row = mysqli_fetch_assoc($sql);
    echo ($row ? $row['prodQty'] : '').'|||'.($row ? $row['prodOrigin'] : '').'|||'.($row ? $row['prodPackaging'] : '');
}elseif(isset($_GET['act']) && $_GET['act']=="vendorProducts"){
    $vendorID = isset($_GET['vendorID']) ? (int)$_GET['vendorID'] : 0;
    echo '<option value="">- Select -</option>';
    if($vendorID > 0){
        $sql = mysqli_query($conn,"SELECT id,prodName FROM products WHERE assignToVendor='".$vendorID."' ORDER BY prodName ASC");
        while($row = mysqli_fetch_assoc($sql)){
            echo '<option value="'.(int)$row['id'].'">'.htmlspecialchars($row['prodName'], ENT_QUOTES, 'UTF-8').'</option>';
        }
    }
}else{
header('location: ../');
}
?>
