# Passport PHP

This is a PHP library to use Passport authentication on the backend. It provides a simple and easy-to-use API.

## Installation

Add the `passport.php` file to your PHP project and include it like any other file.

### Dependencies
- cURL 7.0.0 or greater (tested on 7.68.0)

## Usage

Handling your landing page:
```php
Passport::doLanding("/example/index.php");
```

Authenticating a user:
```php
$passport = new Passport();
$passport->load();
```

Logging a user out:
```php
$passport = new Passport();
$passport->logout();
```

Creating, reading, updating, and deleting user data:
```php
$passport = new Passport();
$passport->add("foo", "bar"); // Creates some user data under the name "foo"
echo($passport->get("foo")); // bar
$passport->set("foo", "baz"); // foo is now "baz"
echo($passport->get("foo")); // baz
$passport->remove("foo");
```

All functions throw exceptions on failure.

## Support

You can contact me directly at [support@kurtisknodel.com](mailto:support@kurtisknodel.com).

## Contributing

Submit a pull request with your changes, as well as a detailed explanation of the changes and why you made them. Attach an issue if applicable.

## License

See [LICENSE](LICENSE).
