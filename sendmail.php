<?php
$to = "max.cheah@ybsinternational.com";
$pr_no = "YBS25060085";

//$txt = "This is a test email.";
$address = "noreply@ybsinternational.com";


$rejected = false;
if($rejected){	
	$subject = "RE : IPR $pr_no is REJECTED";
	}else{
	$subject = "RE : IPR $pr_no is APPROVED";
	}


// File URL to be included
$fileUrl = "http://orientalfastech.com/erp/login.php";

// txt with clickable link (in HTML)
$txt = "
<html>
<head>
  <title>View </title>
</head>
<body>
  <p>You have new IPR to view.PR/MPR #: $pr_no</p>
  <p>Please click the link below to Login:</p>
  <p><a href=\"$fileUrl\">ePR</a></p>
</body>
</html>
";

// To send HTML email, set content-type header
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";

// Additional headers
$headers .= 'From:' .$address. "\r\n";

// Send the email
$mailSent = mail($to, $subject, $txt, $headers);

if ($mailSent) {
    echo "Email sent successfully with clickable link.";
} else {
    echo "Failed to send email.";
}
?>