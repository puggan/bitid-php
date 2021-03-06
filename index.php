<?php
/*
Copyright 2014 Daniel Esteban

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
*/

// BitID is required for login (do not modify)
// DAO could be replace by your CMS/FRAMEWORK database classes
require_once dirname(__FILE__) . "/config.php";
require_once dirname(__FILE__) . "/BitID.php";
require_once dirname(__FILE__) . "/DAO.php";
$bitid = new BitID();
// generate a nonce
$nonce = $bitid->generateNonce();
// build uri with nonce, nonce is optional, but we pre-calculate it to avoid extracting it later
$bitid_uri = $bitid->buildURI(SERVER_URL . 'callback.php', $nonce);

// Insert nonce + IP in the database to avoid an attacker go and try several nonces
// This will only allow one nonce per IP, but it could be easily modified to allow severals per IP
// (this is deleted after an user successfully log in the system, so only will collide if two or more users try to log in at the same time)
$dao = new DAO();
$result = $dao->insert($nonce, @$_SERVER['REMOTE_ADDR']);
if(!$result)
{
	echo "<pre>";
	echo "Database failer\n";
	var_dump($dao);
	die();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>BitID Open Protocol - Demonstration site</title>
</head>
<body>

<div class="container">
    <div class="tab-content">
        <div class="tab-pane">
            <div class="spacer40"></div>
            <h3>Scan this QRcode with your BitID enabled mobile wallet.</h3>
            <p>You can also click on the QRcode if you have a BitID enabled desktop wallet.
            <div class="spacer20"></div>
            <a href="<?php echo $bitid_uri; ?>"><img align="center" alt="Click on QRcode to activate compatible desktop wallet" border="0" src="<?php echo $bitid->qrCode($bitid_uri); ?>" /></a>
            <div class="spacer40"></div>


            <div class="spacer50"></div>
            <h3>Manual signing</h3>
            <p>The user experience is quite combersome, but it has the advangage of being compatible with all wallets
                including Bitcoin Core.</p>
            <p>Please sign the challenge in the box below using the private key of this Bitcoin address you want to
                identify yourself with. Copy the text, open your wallet, choose your Bitcoin address, select the sign message
                function, paste the text into the message input and sign. After it is done, copy and paste the signature
                into the field below.</p>
            <p>Cumbersome. Yep. Much better with a simple scan or click using a compatible wallet :)</p>
            <pre><?php echo $bitid_uri; ?></pre>
            <form method="post" action="callback.php" >
                <input type="hidden" name="uri" value="<?php echo $bitid_uri; ?>" />
                <div class="form-group">
                    <label>Bitcoin address</label>
                    <input type="text" name="address" id="address" class="form-control" placeholder="Enter your public Bitcoin address" />
                </div>
                <div class="form-group">
                    <label>Signature</label>
                    <input type="text" name="signature" id="signature" class="form-control" placeholder="Enter the signature" />
                </div>
                <button type="submit" id="check" class="btn btn-success" data-loading-text="Verifying signature">Sign in !</button>
            </form>
            <div class="spacer40"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    setInterval(function() {
        var r = new XMLHttpRequest();
        r.open("POST", "<?php echo SERVER_URL; ?>ajax.php", true);
        r.onreadystatechange = function () {
            if (r.readyState != 4 || r.status != 200) return;
            if(r.responseText!='false') {
                window.location = '<?php echo SERVER_URL; ?>user.php';
            }
        };
        r.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        r.send("nonce=<?php echo $nonce; ?>");
    }, 3000);
</script>

</body>
</html>
