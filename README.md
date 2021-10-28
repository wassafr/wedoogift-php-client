# Wassa WeDooGift PHP Client

## Installation

`composer require wassa/wedoogift-client`

## Supported features

* Add a user
* List deposits
* Create a distribution to a user (give 💰💰💰)

## Usage

```php
$reasonId = 1234;
$userId = 5678;
$valueToDistribute = 1;

$wdg = new \Wassa\WeDooGift\WeDooGiftClient('<APIKEY>');
$wdg->init();

$wdg->addUser('John', 'Doe', 'john.doe@yourhosting.com'); // returns the user id
$wdg->distribute($reasonId, 'Cool message', $userId, $valueToDistribute);
```

All methods throw a `WeDooGiftException` if something wrong happens.

## TODO

* Add support for production environment

## License

You are free to use this, although it probably won't be very useful to you. You can submit PR but we will most probably not work on it. 
