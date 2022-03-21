<?php
	require_once("../passport.php");
	session_start();
	try {
		Passport::doLanding("/example/index.php");
	} catch(PassportException $e) {
		exit($e->getMessage());
	}
?>