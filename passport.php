<?php

class Passport {
	const COOKIE_LIFETIME_SECONDS = 60 * 60 * 24 * 30; // 30 days

	private array $data;

	public function __construct() {
		$this->data = [];
	}

	public function add(string $name, mixed $value) : mixed {
		if(isset($this->data[$name])) throw new PassportException("Key already exists", "ERR_ADD");
		
		$this->data[$name] = $value;
		$ser = serialize($value);
		$hex = "\\x" . bin2hex($ser);
		return $this->addRaw($name, $hex);
	}

	public function addRaw(string $name, string $value) : mixed {
		$key = $this->getKey();
		$res = self::doRequest("account/data", [
			"method" => "POST",
			"body" => [
				"key" => $key,
				"name" => $name,
				"value" => $value,
			],
		]);

		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
		return $res;
	}

	public function get(string $name) : mixed {
		if(isset($this->data[$name])) return $this->data[$name];

		$raw = $this->getRaw($name);
		$value = hex2bin(substr($raw, 2));
		$value = unserialize($value);
		$this->data[$name] = $value;
		return $value;
	}

	public function getRaw(string $name) : string {
		$key = $this->getKey();
		$res = self::doRequest("account/data", [
			"method" => "GET",
			"query" => [
				"key" => $key,
				"name" => $name
			],
		]);

		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
		return $res->value;
	}

	public function set(string $name, mixed $value) : mixed {
		$this->data[$name] = $value;
		$v = serialize($value);
		$raw = "\\x" . bin2hex($v);
		return $this->setRaw($name, $raw);
	}

	public function setRaw(string $name, string $value) : mixed {
		$key = $this->getKey();
		$res = self::doRequest("account/data", [
			"method" => "PUT",
			"body" => [
				"key" => $key,
				"name" => $name,
				"value" => $value
			],
		]);

		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
		return $res;
	}

	public function remove(string $name) : bool {
		unset($this->data[$name]);
		$key = $this->getKey();
		$res = self::doRequest("account/data", [
			"method" => "DELETE",
			"query" => [
				"key" => $key,
				"name" => $name
			],
		]);

		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
		return $res->success;
	}

	public function load() : void {
		if($this->getUid() == null) $this->loadUid();
	}

	public function getUid() : ?string {
		if(isset($_SESSION["passport-uid"])) return $_SESSION["passport-uid"];
		else return null;
	}

	public function logout() : void {
		$key = $this->getKey();
		$res = self::doRequest("authentication", [
			"method" => "DELETE",
			"query" => [ "key" => $key ],
		]);

		unset($_SESSION["passport-uid"]);
		setcookie("passport-key", "", 1);
		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
	}

	public static function doLanding(?string $destination = null) : void {
		$key = $_GET["key"];
		if(!$key) throw new PassportException("Missing key", "ERR_LANDING_NO_KEY");

		setcookie("passport-key", $key, time() + self::COOKIE_LIFETIME_SECONDS, "/", "", true, true);
		if($destination) header("Location: $destination", true, 302);
	}

	private function loadUid() {
		$key = $this->getKey();
		$res = self::doRequest("account/uid", [
			"method" => "GET",
			"query" => [
				"key" => $key
			],
		]);

		if(isset($res->error)) throw new PassportException($res->error, "ERR_RESPONSE");
		$_SESSION["passport-uid"] = $res->uid;
	}

	private function getKey() {
		if(!isset($_COOKIE["passport-key"])) throw new PassportException("Not logged in", "ERR_LOGIN");
		return $_COOKIE["passport-key"];
	}

	private static function doRequest(string $endpoint, array $options) {
		if(!isset($options["method"])) throw new PassportException("Missing request method", "ERR_REQUEST_METHOD");
		if(!isset($options["body"]) && !isset($options["query"])) throw new PassportException("Missing body and query", "ERR_REQUEST_CONTENT");

		$body = null;
		$query = [];
		$method = $options["method"];

		if(isset($options["body"])) $body = json_encode($options["body"]);
		if(isset($options["query"])) $query = $options["query"];

		$q = [];
		foreach($query as $key => $value) {
			$q[] = urlencode($key) . "=" . urlencode($value);
		}

		$url = "https://passport.kurtisknodel.com/api/$endpoint";
		if(count($q)) $url .= "?" . implode("&", $q);

		$curl = curl_init($url);
		if(!$curl) throw new PassportException("Error initializing cURL", "ERR_CURL_INIT");

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return output as string
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Don't verify SSL cert. Should be fine since we're going to a known domain
		switch($method) {
			case "POST":
				curl_setopt($curl, CURLOPT_POST, true);
				break;
			case "GET":
				// cURL uses GET by default
				break;
			case "PUT":
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
				break;
			case "DELETE":
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
				break;
			default:
				throw new PassportException("Method not supported", "ERR_REQUEST_METHOD");
		}

		if($body) {
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
			curl_setopt($curl, CURLOPT_HTTPHEADER, [
				"Content-Type: application/json"
			]);
		}

		$res = curl_exec($curl);
		if($res === false) throw new PassportException("Failed to make request", "ERR_REQUEST", new \Exception(curl_error($curl), curl_errno($curl)));

		$sta = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($sta != 200) throw new PassportException("Server returned code $sta", "ERR_RESPONSE");
		curl_close($curl);

		return json_decode($res);
	}
}

class PassportException extends \Exception {
	public function __construct(string $message, public string $scode, \Throwable $previous = null) {
		$icode = crc32($scode);
		parent::__construct($message, $icode, $previous);
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->scode}]: {$this->message}\n";
	}
}