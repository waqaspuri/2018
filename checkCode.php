<?php




			function checkCodeEna($mobile_numbers) {
				
				
				$countryCode = substr($mobile_numbers,0,2);
				
				$mobile_numbers = (array)$countryCode;
				
				$countryCodes = array("Pakistan"=>92,"Turkey"=>90,"China"=>86);
			
				if(in_array($mobile_numbers[0],$countryCodes)) { return TRUE; } else { return FALSE; }; 
				
				
				
			
				
				
				
				
				
						
				
				
			}


$mobile_num = 863008207530; 
checkCodeEna($mobile_num);
$myvars = checkCodeEna($mobile_num);
var_dump($myvars); 

if($myvars == TRUE) { echo 'my var is true'; } else { echo 'NOT TRUE ...'; } 

?>