<?php
require_once("../dompdf_config.inc.php");
$demoTesting =' <table border="0" style="width: 650px;font-family:Arial;">
  <tr>
    <td><span style="float:left;font-size:20px;color:#F79646;font-weight:bold;width:100%;">E-Coupon</span><br />
      <span style="float:left;font-family:12px;"> FunStay Coupon ID ­ 123456 <br />
      Generation Date ­ abv here1 </span></td>
    <td align="right"><img  src="images/logoPDF.png" /></td>
  </tr>
  <tr>
    <td colspan="2" align="center"><img src="images/border.png" width="650" height="6" /></td>
  </tr>
  <tr>
    <td colspan="2"><span style="float:left;font-size:20px;color:#F79646;font-weight:bold;">Offer Details</span> </td>
  </tr>
  <tr>
    <td colspan="2"><div style="line-height:24px;float:left;width:100%;font-family:12px;">Hello <b>JEewan Auluck</b>, <br >
        Thank you for showing interest in the Homestay Name, <br >
        Below are the details:<br >
        <b>Name of Property:</b> Tweet Tweet Homestay <br >
        <b>Address:</b> Address Address Address Address  Address Address Address Address  v <br >
        <b>Offer Period:</b> 10-10-2014 to 15-10-2014<br >
        <b>Offer Details:</b> Description of Offer?<br >
        <b>Number of Guest:</b> Count<br >
        <b>Host Name:</b> Name<br >
        <b>Contact detail of Host:</b> Mobile Number of Host<br >
        <br >
        Home stay is not just a holiday destinations, its portal to a timeless journey into the culture and heritage. Wish you a very happy and fun stay at Homestay Name.<br >
        <b>Regards,</b><br >
        FunStay Team<br >
        Email: info@funstay.in<br >
        Ph: +91-(124) 4058639<br >
        Web: www.funstay.in<br >
        <br >
        <span style="float:left;font-size:20px;color:#F79646;font-weight:bold;">Important Information</span><br >
        A printed copy of this e-coupon must be presented at the time of check in in order to avail offer.<br >
        Please carry Government recognized photo identification (ID) along with your E­Coupon?This can include: Driving License, Passport, PAN Card, Voter ID Card or any other ID issued by the Government of India.<br >
        Call to <b>0124­4058639</b> or email at <b>info@funstay.in</b> if you face any issues while availing offer.</div></td>
  </tr>
  <tr>
    <td align="center" colspan="2"><span style=""><img src="images/border.png" width="650" height="6" /></span><br />
      <a href="http://google.com" ><img src="images/a.jpg" width="200" height="140" /></a> &nbsp; &nbsp; 
	  <a href="http://google.com"><img src="images/b.jpg" width="200" height="140" /></a>  &nbsp; &nbsp; 
	  <a href="http://google.com"><img src="images/c.jpg" width="200" height="140" /></a></td>
  </tr>
</table>';
$dompdf = new DOMPDF();
$dompdf->load_html($demoTesting);
$dompdf->set_paper('letter','portrait');
$dompdf->render();
$dompdf->stream("dompdf_out.pdf", array("Attachment" => true));
?>