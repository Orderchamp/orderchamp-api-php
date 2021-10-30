# Orderchamp API PHP Client
This is a php client for the API of orderchamp.com. More documentation of the API can be found at:
https://developers.orderchamp.com

# Installation
Install the composer package
```
composer require Orderchamp/orderchamp-api-php
```

# Usage
The first steps are only necessary when you are building a public app which needs Oauth. If you are building a private connection, you will receive an access token from Orderchamp which you can use straight away.

```php
use Orderchamp\Api\OrderchampApiClient;

$client = new OrderchampApiClient([
    'client_id'     => 'your_client_id',
    'client_secret' => 'your_client_secret',
]);

// Redirect the user to this url for authorization
$authorizationUrl = $client->authorizationUrl($this->config['scopes'], 'redirect_url_goes_here');

// We redirect the user back to your redirect url
// Call the method with the $_GET parameters and we'll fetch you a token
$token = $client->requestToken($request->all());

// Persist this token and use it moving forward
$client->setAccessToken($token);
$response = $client->graphql('{ account { id name } }');

dd($response);
```
