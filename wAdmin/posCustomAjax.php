<?php
include "include/sessionAdmin.php";  
include "include/configure.php";
if($_GET['act']=="cc"){
echo '<table class="table table-bordered table-striped table-condensed mb-none">
<thead>
<tr>
<th width="5%">SNo</th>
<th width="35%">Title</th>
<th width="30%">Amt</th>
<th width="30%">Value</th>
</tr>
</thead>
<tbody >';
$sqlTax = mysqli_query($conn,"SELECT * FROM pos_custom_charges WHERE pos_categoryID='".$_GET['id']."' ORDER BY id ASC");
$numRowsTax = mysqli_num_rows($sqlTax);
if($numRowsTax>0){
$sno=0;
while($rowTx=mysqli_fetch_assoc($sqlTax)){
$sno++;
echo '<tr>
<td>&nbsp; '.$sno.'</td>
<td>'.$rowTx['title'].'</td>
<td><input type="number" step="any" class="form-control" name="customValuePKR[]" value="" id="inputDefault" >
<input type="hidden" name="customValueID[]" value="'.$rowTx['id'].'" ></td>
<td>--</td>
</tr>';
}
}else{
echo "<tr>
<td colspan='8' align='center'>No Data Found.</td>
</tr>";
}
echo '</tbody>
</table>';
}else{
	header('location: ../');
}
?>