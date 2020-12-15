<<<<<<< HEAD
# psalm-moodle-plugin
A [Psalm](https://github.com/vimeo/psalm) plugin to detect calling private or protected method via proxy

Installation:

```console
$ composer require --dev klebann/psalm-moodle-plugin
$ vendor/bin/psalm-plugin enable klebann/psalm-moodle-plugin
```

Usage:

Run your usual Psalm command:
```console
$ vendor/bin/psalm --config=psalm-plugin.xml --no-diff
```

Explanation:

Visibilities in PHP are not strongly enforced. According to [php.net](https://www.php.net/manual/en/language.oop5.visibility.php):
> Objects of the same type will have access to each others private and protected members even though they are not the same instances. This is because the implementation specific details are already known when inside those objects.

This means that a private method is not actually private when called from another instance of the same object.
This sort of behavior is possible:
```php
class PrivateTests{
    private string $secret;

    private function privateMethod(): void {echo $this->secret;}

    public function __construct(string $secret){
        $this->secret = $secret;
    }

    public function proxyByParam(PrivateTests $a): void {
        $a->privateMethod(); //This is a call to a private method from outside the instance
    }
}

$first_secret_key = new PrivateTests('first_secret_key');
$second_secret_key = new PrivateTests('second_secret_key');

$first_secret_key->proxyByParam($second_secret_key);
```
This call to $first_secret_key instance will actually call a private method on $second_secret_key and display the value of the private attribute of $second_secret_key

This plugins intends to fill those holes in PHP visibility checks
=======
# psalm-moodle-plugin
Psalm plugin for checking Moodle Plugins
>>>>>>> a6dd2b118edee6e44b97773c7cf48b9be6684964
