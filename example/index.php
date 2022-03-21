<?php
	session_start();
	require_once("../passport.php");
	$passport = new Passport();
	try {
		$passport->load();
	} catch(PassportException $e) {
		header("Location: /example/login.html", true, 302);
		exit();
	}

	$msg = null;
	if($_SERVER["REQUEST_METHOD"] == "POST") {
		$foo = $_POST["value"];
		try {
			$passport->set("foo", $foo); // No sanitization needed; Passport handles this
		} catch(PassportException $e) {
			$msg = $e->getMessage();
		}
	}
?>

<!DOCTYPE html>
<hmtl>
	<head>
		<title>Passport PHP Test</title>
	</head>
	<body>
		<h1>Index - Account Info</h1>
		<p>UID: <?php echo($passport->getUid()); ?></p>

		<p>
			<?php
				try {
					$foo = $passport->get("foo");
					echo("Foo is $foo");
				} catch(PassportException $e) {
					// If foo does not exist, create it
					$passport->add("foo", "bar");
					echo("Foo is bar");
				}

				echo("<p style='color: red;'>$msg</p>");
			?>
		</p>

		<form method="POST">
			<label for="value-input">Foo: </label>
			<input type="text" name="value" id="value-input" />
			<button type="submit">Set Foo</button>
		</form>
	</body>
</hmtl>