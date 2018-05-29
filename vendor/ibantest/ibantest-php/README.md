# IBANTEST API PHP client


This library can be used to access IBANTEST API endpoints.

## Installing

Get last version with [Composer](http://getcomposer.org "Composer").

```bash
$ composer require ibantest/ibantest-php
```

## Register

You need an API token to use this API.

Please register your account at https://www.ibantest.com and receive 100 credits for free.

## Basic Usage

```php
<?php

include "vendor/autoload.php";

use Ibantest\Ibantest;

$api = new Ibantest();
$api->setToken('###your_api_token###');

# get count of remaining credits
$res = $api->getRemainingCredits();
print_r($res);

# validate IBAN
$res = $api->validateIban('DE02600501010002034304');
print_r($res);

# calculate IBAN out of country code, bank code and account number
$res = $api->calculateIban('AT', '12000', '703447144');
print_r($res);

# calculate IBAN out of country code, bank code and account number
$res = $api->calculateIban('BE', '510', '0075470', '61');
print_r($res);

# calculate IBAN out of country code, bank code and account number
$res = $api->calculateIban('DE', '10090000', '0657845795');
print_r($res);

# validate BIC
$res = $api->validateBic('BFSWDE33BER');
print_r($res);

# find Bank by country code and bank code
$res = $api->findBank('CH', '100');
print_r($res);

```

## Documentation
Please have a look at the full documentation
https://api.ibantest.com/

## License
ibantest-php is licensed under the MIT License - see the LICENSE file for details